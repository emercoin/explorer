"""Unconfirmed transactions, read from the node and cached for a few seconds.

The API asks the node for its memory pool (getrawmempool true) and decodes the
newest transactions (getrawtransaction, one batch). Inputs get their value and
address from the index, or from another pooled transaction when they spend
one; an input spending something neither knows has value null.
"""

from __future__ import annotations

import asyncio
import base64
import logging
import time
from decimal import Decimal
from typing import Any

import asyncpg
import httpx

from ..amounts import format_units, to_units
from ..indexer.parse import Tx, parse_tx
from ..nvs import OP_NAMES, as_text
from ..rpc import RpcClient, RpcError

log = logging.getLogger(__name__)

TTL = 10.0  # seconds a snapshot is served before the node is asked again
MAX_DECODED = 100  # newest transactions decoded per snapshot


class NodeUnavailable(Exception):
    pass


def _field(data: bytes) -> dict[str, Any]:
    text = as_text(data)
    return {"text": text} if text is not None else {"text": None, "base64": base64.b64encode(data).decode()}


def multisig_fields(addresses: list[str] | None, required: int | None) -> dict[str, Any]:
    """Extra vout fields of a bare multisig output ("address" stays the first key's).

    Shared with app.py, which imports this module.
    """
    if addresses is None:
        return {}
    return {"addresses": addresses, "required": required}


def _fee(entry: dict[str, Any]) -> int:
    fee = entry.get("fee")
    if fee is None:
        fee = entry.get("fees", {}).get("base", 0)
    return to_units(Decimal(str(fee)))


class Mempool:
    def __init__(self, rpc: RpcClient | None):
        self.rpc = rpc
        self._lock = asyncio.Lock()
        self._at = 0.0
        self._snapshot: dict[str, Any] | None = None

    async def snapshot(self, pool: asyncpg.Pool) -> dict[str, Any]:
        """{"count", "bytes", "txs": [decoded, newest first]}, at most TTL seconds old."""
        if self.rpc is None:
            raise NodeUnavailable("the API has no node RPC configured (EMC_RPC_URL)")
        async with self._lock:
            if self._snapshot is None or time.monotonic() - self._at > TTL:
                self._snapshot = await self._fetch(pool)
                self._at = time.monotonic()
            return self._snapshot

    async def transaction(self, pool: asyncpg.Pool, txid: str) -> dict[str, Any] | None:
        """One pooled transaction, decoded; None if the node does not have it."""
        snap = await self.snapshot(pool)
        for tx in snap["txs"]:
            if tx["txid"] == txid:
                return tx
        entry = snap["entries"].get(txid)
        if entry is None:
            return None
        decoded = await self._decode(pool, {txid: entry}, snap["entries"])
        return decoded[0] if decoded else None

    async def _fetch(self, pool: asyncpg.Pool) -> dict[str, Any]:
        try:
            entries: dict[str, Any] = await self.rpc.call("getrawmempool", True)
        except (httpx.HTTPError, OSError, RpcError) as e:
            raise NodeUnavailable(f"node did not answer: {e}") from e
        newest = dict(sorted(entries.items(), key=lambda kv: (-kv[1].get("time", 0), kv[0]))[:MAX_DECODED])
        return {
            "count": len(entries),
            "bytes": sum(e.get("vsize") or e.get("size") or 0 for e in entries.values()),
            "entries": entries,
            "txs": await self._decode(pool, newest, entries),
        }

    async def _decode(
        self, pool: asyncpg.Pool, wanted: dict[str, Any], entries: dict[str, Any]
    ) -> list[dict[str, Any]]:
        try:
            raws = await self.rpc.batch(
                [("getrawtransaction", [txid, 1]) for txid in wanted], return_exceptions=True
            )
        except (httpx.HTTPError, OSError) as e:
            raise NodeUnavailable(f"node did not answer: {e}") from e
        txs: list[tuple[Tx, dict[str, Any]]] = []
        for entry, raw in zip(wanted.values(), raws, strict=True):
            if isinstance(raw, RpcError):
                continue  # mined or evicted since getrawmempool
            txs.append((parse_tx(raw, 0), entry))

        # values and addresses of the outputs these transactions spend
        keys = [(i.prev_txid, i.prev_n) for tx, _ in txs for i in tx.inputs if i.spends_output]
        prev: dict[tuple[bytes, int], tuple[int, str | None]] = {}
        if keys:
            rows = await pool.fetch(
                """
                SELECT o.txid, o.n, o.value, o.address
                FROM outputs o JOIN unnest($1::bytea[], $2::int[]) AS k(txid, n)
                  ON o.txid = k.txid AND o.n = k.n
                """,
                [k[0] for k in keys],
                [k[1] for k in keys],
            )
            prev = {(r["txid"], r["n"]): (r["value"], r["address"]) for r in rows}
        for tx, _ in txs:  # outputs of other pooled transactions
            for o in tx.outputs:
                prev.setdefault((tx.txid, o.n), (o.value, o.address))

        result = []
        for tx, entry in txs:
            vin, value_in = [], 0
            for i in tx.inputs:
                if i.is_randpay:
                    value, address = 0, None
                else:
                    value, address = prev.get((i.prev_txid, i.prev_n), (None, None))
                if value is None:
                    value_in = None
                elif value_in is not None:
                    value_in += value
                vin.append({
                    "n": i.n,
                    "coinbase": None,
                    "txid": i.prev_txid.hex() if i.prev_txid else None,
                    "vout": i.prev_n,
                    "randpay": i.is_randpay,
                    "value": format_units(value) if value is not None else None,
                    "address": address,
                })
            value_out = sum(o.value for o in tx.outputs)
            result.append({
                "txid": tx.txid.hex(),
                "height": None,
                "time": entry.get("time"),
                "coinbase": False,
                "coinstake": False,
                "value_in": format_units(value_in) if value_in is not None else None,
                "value_out": format_units(value_out),
                "fee": format_units(_fee(entry)),
                "size": entry.get("vsize") or entry.get("size") or tx.size,
                "version": tx.version,
                "locktime": tx.locktime,
                "depends": entry.get("depends", []),
                "vin": vin,
                "vout": [
                    {"n": o.n, "value": format_units(o.value), "type": o.script_type,
                     "address": o.address, **multisig_fields(o.addresses, o.required),
                     "script": o.script.hex(), "spent_by": None}
                    for o in tx.outputs
                ],
                "name_ops": [
                    {"vout": n.n, "op": OP_NAMES[n.op.op], "name": _field(n.op.name),
                     "value": _field(n.op.value), "days": n.op.days if n.op.days >= 0 else None,
                     "address": n.address}
                    for n in tx.name_ops
                ],
            })
        return result
