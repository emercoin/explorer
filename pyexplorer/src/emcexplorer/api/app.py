"""Read-only HTTP API over the index. All queries are parameterized."""

from __future__ import annotations

import base64
import json
import re
from contextlib import asynccontextmanager
from typing import Any

import asyncpg
from fastapi import APIRouter, FastAPI, HTTPException, Query, Request

from ..amounts import format_units
from ..nvs import OP_NAMES, as_text

HEX64 = re.compile(r"^[0-9a-fA-F]{64}$")
ADDRESS = re.compile(r"^[0-9A-Za-z]{20,100}$")
MAX_LIMIT = 500

router = APIRouter(prefix="/api/v1")


def _pool(request: Request) -> asyncpg.Pool:
    return request.app.state.pool


def _hash(value: str) -> bytes:
    if not HEX64.match(value):
        raise HTTPException(400, "expected 64 hex characters")
    return bytes.fromhex(value)


def _address(value: str) -> str:
    if not ADDRESS.match(value):
        raise HTTPException(400, "malformed address")
    return value


def _bytes_field(data: bytes) -> dict[str, Any]:
    text = as_text(data)
    if text is not None:
        return {"text": text}
    return {"text": None, "base64": base64.b64encode(data).decode()}


def _block(row: asyncpg.Record) -> dict[str, Any]:
    return {
        "height": row["height"],
        "hash": row["hash"].hex(),
        "previousblockhash": row["prev_hash"].hex() if row["prev_hash"] else None,
        "time": row["time"],
        "version": row["version"],
        "merkleroot": row["merkleroot"].hex(),
        "bits": row["bits"],
        "nonce": row["nonce"],
        "difficulty": str(row["difficulty"]),
        "size": row["size"],
        "proof": "pos" if row["is_pos"] else "pow",
        "flags": row["flags"],
        "auxpow": row["is_auxpow"],
        "mint": format_units(row["mint"]),
        "tx_count": row["tx_count"],
        "value_out": format_units(row["value_out"]),
        "fees": format_units(row["fees"]),
        "supply": format_units(row["supply"]),
    }


def _tx_summary(row: asyncpg.Record) -> dict[str, Any]:
    return {
        "txid": row["txid"].hex(),
        "height": row["height"],
        "index": row["idx"],
        "time": row["time"],
        "coinbase": row["is_coinbase"],
        "coinstake": row["is_coinstake"],
        "value_in": format_units(row["value_in"]),
        "value_out": format_units(row["value_out"]),
        "fee": format_units(row["fee"]),
    }


def _name(row: asyncpg.Record, tip: int) -> dict[str, Any]:
    return {
        "name": _bytes_field(row["name"]),
        "namespace": row["namespace"],
        "value": _bytes_field(row["value"]),
        "address": row["address"],
        "txid": row["txid"].hex(),
        "vout": row["vout"],
        "expires_at": row["expires_at"],
        "expires_in": row["expires_at"] - tip,
        "deleted": row["deleted"],
        "active": not row["deleted"] and row["expires_at"] > tip,
    }


async def _tip(conn: asyncpg.Connection) -> int:
    return await conn.fetchval("SELECT coalesce(max(height), -1) FROM blocks")


@router.get("/status")
async def status(request: Request) -> dict[str, Any]:
    async with _pool(request).acquire() as conn:
        tip = await conn.fetchrow("SELECT height, hash, time FROM blocks ORDER BY height DESC LIMIT 1")
        meta = await conn.fetchval("SELECT value FROM meta WHERE key = 'status'")
    meta = json.loads(meta) if meta else {}
    return {
        "indexed_height": tip["height"] if tip else None,
        "indexed_hash": tip["hash"].hex() if tip else None,
        "indexed_time": tip["time"] if tip else None,
        "node_height": meta.get("node_height"),
        "updated_at": meta.get("updated_at"),
    }


@router.get("/blocks")
async def recent_blocks(
    request: Request,
    before: int | None = Query(None, description="return blocks below this height"),
    limit: int = Query(20, ge=1, le=100),
) -> list[dict[str, Any]]:
    rows = await _pool(request).fetch(
        "SELECT * FROM blocks WHERE ($1::int IS NULL OR height < $1) ORDER BY height DESC LIMIT $2",
        before,
        limit,
    )
    return [_block(r) for r in rows]


@router.get("/blocks/{ref}")
async def block(
    request: Request,
    ref: str,
    offset: int = Query(0, ge=0),
    limit: int = Query(100, ge=1, le=MAX_LIMIT),
) -> dict[str, Any]:
    async with _pool(request).acquire() as conn:
        if ref.isdigit():
            row = await conn.fetchrow("SELECT * FROM blocks WHERE height = $1", int(ref))
        else:
            row = await conn.fetchrow("SELECT * FROM blocks WHERE hash = $1", _hash(ref))
        if row is None:
            raise HTTPException(404, "block not found")
        txs = await conn.fetch(
            "SELECT * FROM transactions WHERE height = $1 ORDER BY idx OFFSET $2 LIMIT $3",
            row["height"],
            offset,
            limit,
        )
        nxt = await conn.fetchval("SELECT hash FROM blocks WHERE height = $1", row["height"] + 1)
    result = _block(row)
    result["nextblockhash"] = nxt.hex() if nxt else None
    result["tx"] = [_tx_summary(t) for t in txs]
    return result


@router.get("/txs/{txid}")
async def transaction(request: Request, txid: str) -> dict[str, Any]:
    key = _hash(txid)
    async with _pool(request).acquire() as conn:
        tx = await conn.fetchrow("SELECT * FROM transactions WHERE txid = $1", key)
        if tx is None:
            raise HTTPException(404, "transaction not found")
        ins = await conn.fetch("SELECT * FROM inputs WHERE txid = $1 ORDER BY n", key)
        outs = await conn.fetch("SELECT * FROM outputs WHERE txid = $1 ORDER BY n", key)
        names = await conn.fetch("SELECT * FROM name_ops WHERE txid = $1 ORDER BY n", key)
        block_hash = await conn.fetchval("SELECT hash FROM blocks WHERE height = $1", tx["height"])
        tip = await _tip(conn)
    result = _tx_summary(tx)
    result.update(
        blockhash=block_hash.hex(),
        confirmations=tip - tx["height"] + 1,
        version=tx["version"],
        locktime=tx["locktime"],
        size=tx["size"],
        vin=[
            {
                "n": i["n"],
                "coinbase": i["coinbase"].hex() if i["coinbase"] is not None else None,
                "txid": i["prev_txid"].hex() if i["prev_txid"] else None,
                "vout": i["prev_n"],
                "randpay": i["is_randpay"],
                "value": format_units(i["value"]),
                "address": i["address"],
            }
            for i in ins
        ],
        vout=[
            {
                "n": o["n"],
                "value": format_units(o["value"]),
                "type": o["script_type"],
                "address": o["address"],
                "script": o["script"].hex(),
                "spent_by": (
                    {"txid": o["spent_txid"].hex(), "vin": o["spent_n"], "height": o["spent_height"]}
                    if o["spent_txid"]
                    else None
                ),
            }
            for o in outs
        ],
        name_ops=[
            {
                "vout": n["n"],
                "op": OP_NAMES[n["op"]],
                "name": _bytes_field(n["name"]),
                "value": _bytes_field(n["value"]),
                "days": n["days"] if n["days"] >= 0 else None,
                "address": n["address"],
            }
            for n in names
        ],
    )
    return result


@router.get("/addresses/{address}")
async def address(request: Request, address: str) -> dict[str, Any]:
    row = await _pool(request).fetchrow(
        "SELECT * FROM address_stats WHERE address = $1", _address(address)
    )
    if row is None:
        raise HTTPException(404, "address not seen on chain")
    return {
        "address": row["address"],
        "balance": format_units(row["balance"]),
        "received": format_units(row["received"]),
        "sent": format_units(row["sent"]),
        "tx_count": row["tx_count"],
        "first_height": row["first_height"],
        "last_height": row["last_height"],
    }


@router.get("/addresses/{address}/txs")
async def address_txs(
    request: Request,
    address: str,
    before: str | None = Query(None, description="cursor '<height>:<index>' from the previous page"),
    limit: int = Query(50, ge=1, le=MAX_LIMIT),
) -> dict[str, Any]:
    height, idx = 2**31 - 1, 0
    if before:
        try:
            h, i = before.split(":")
            height, idx = int(h), int(i)
        except ValueError:
            raise HTTPException(400, "cursor must look like '<height>:<index>'") from None
    rows = await _pool(request).fetch(
        """
        SELECT a.height, a.idx, a.txid, a.received, a.sent, t.time
        FROM address_txs a JOIN transactions t ON t.txid = a.txid
        WHERE a.address = $1 AND (a.height, a.idx) < ($2, $3)
        ORDER BY a.height DESC, a.idx DESC
        LIMIT $4
        """,
        _address(address),
        height,
        idx,
        limit,
    )
    items = [
        {
            "txid": r["txid"].hex(),
            "height": r["height"],
            "time": r["time"],
            "received": format_units(r["received"]),
            "sent": format_units(r["sent"]),
            "delta": format_units(r["received"] - r["sent"]),
        }
        for r in rows
    ]
    cursor = f"{rows[-1]['height']}:{rows[-1]['idx']}" if len(rows) == limit else None
    return {"items": items, "next": cursor}


@router.get("/addresses/{address}/utxo")
async def address_utxo(request: Request, address: str) -> list[dict[str, Any]]:
    rows = await _pool(request).fetch(
        """
        SELECT txid, n, value, height FROM outputs
        WHERE address = $1 AND spent_txid IS NULL
        ORDER BY height, txid, n
        LIMIT 5000
        """,
        _address(address),
    )
    return [
        {"txid": r["txid"].hex(), "vout": r["n"], "value": format_units(r["value"]), "height": r["height"]}
        for r in rows
    ]


@router.get("/richlist")
async def richlist(request: Request, limit: int = Query(100, ge=1, le=MAX_LIMIT)) -> list[dict[str, Any]]:
    rows = await _pool(request).fetch(
        "SELECT address, balance FROM address_stats ORDER BY balance DESC LIMIT $1", limit
    )
    return [{"address": r["address"], "balance": format_units(r["balance"])} for r in rows]


@router.get("/names")
async def names(
    request: Request,
    prefix: str = Query("", description="name prefix, e.g. 'dns:'"),
    namespace: str | None = None,
    after: str | None = Query(None, description="cursor: last name of the previous page"),
    include_inactive: bool = False,
    limit: int = Query(50, ge=1, le=MAX_LIMIT),
) -> dict[str, Any]:
    # LIKE prefix search needs its wildcards escaped
    pattern = prefix.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_") + "%"
    async with _pool(request).acquire() as conn:
        tip = await _tip(conn)
        rows = await conn.fetch(
            """
            SELECT * FROM names
            WHERE name_text COLLATE "C" LIKE $1
              AND ($2::text IS NULL OR namespace = $2)
              AND ($3::text IS NULL OR name_text COLLATE "C" > $3)
              AND ($4 OR (NOT deleted AND expires_at > $5))
            ORDER BY name_text COLLATE "C"
            LIMIT $6
            """,
            pattern,
            namespace,
            after,
            include_inactive,
            tip,
            limit,
        )
    items = [_name(r, tip) for r in rows]
    cursor = rows[-1]["name_text"] if len(rows) == limit else None
    return {"items": items, "next": cursor}


# Declared before /names/{name:path}: the path converter would swallow "/history".
@router.get("/names/{name:path}/history")
async def name_history(request: Request, name: str) -> list[dict[str, Any]]:
    rows = await _pool(request).fetch(
        """
        SELECT n.*, t.time FROM name_ops n JOIN transactions t ON t.txid = n.txid
        WHERE n.name = $1 ORDER BY n.height DESC, n.idx DESC, n.n DESC
        """,
        name.encode(),
    )
    if not rows:
        raise HTTPException(404, "name not found")
    return [
        {
            "txid": r["txid"].hex(),
            "vout": r["n"],
            "height": r["height"],
            "time": r["time"],
            "op": OP_NAMES[r["op"]],
            "value": _bytes_field(r["value"]),
            "days": r["days"] if r["days"] >= 0 else None,
            "address": r["address"],
        }
        for r in rows
    ]


@router.get("/names/{name:path}")
async def name(request: Request, name: str) -> dict[str, Any]:
    async with _pool(request).acquire() as conn:
        row = await conn.fetchrow("SELECT * FROM names WHERE name = $1", name.encode())
        if row is None:
            raise HTTPException(404, "name not found")
        tip = await _tip(conn)
    return _name(row, tip)


@router.get("/search")
async def search(request: Request, q: str = Query(..., min_length=1, max_length=600)) -> dict[str, Any]:
    q = q.strip()
    async with _pool(request).acquire() as conn:
        if q.isdigit():
            if await conn.fetchval("SELECT 1 FROM blocks WHERE height = $1", int(q)):
                return {"type": "block", "id": q}
        if HEX64.match(q):
            key = bytes.fromhex(q)
            if await conn.fetchval("SELECT 1 FROM blocks WHERE hash = $1", key):
                return {"type": "block", "id": q.lower()}
            if await conn.fetchval("SELECT 1 FROM transactions WHERE txid = $1", key):
                return {"type": "tx", "id": q.lower()}
        if ADDRESS.match(q) and await conn.fetchval(
            "SELECT 1 FROM address_stats WHERE address = $1", q
        ):
            return {"type": "address", "id": q}
        if await conn.fetchval("SELECT 1 FROM names WHERE name = $1", q.encode()):
            return {"type": "name", "id": q}
    raise HTTPException(404, "nothing found")


def create_app(dsn: str | None = None, pool: asyncpg.Pool | None = None) -> FastAPI:
    """Pass `dsn` to let the app own its pool, or an existing `pool` (tests)."""

    @asynccontextmanager
    async def lifespan(app: FastAPI):
        if pool is not None:
            app.state.pool = pool
            yield
            return
        app.state.pool = await asyncpg.create_pool(dsn, min_size=1, max_size=10)
        try:
            yield
        finally:
            await app.state.pool.close()

    app = FastAPI(title="Emercoin explorer API", version="1", lifespan=lifespan)
    if pool is not None:
        app.state.pool = pool
    app.include_router(router)
    return app
