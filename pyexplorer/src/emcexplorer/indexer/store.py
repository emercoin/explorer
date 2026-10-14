"""Database writes of the indexer. Each block is applied in one transaction."""

from __future__ import annotations

import base64
import json
import logging
from collections import defaultdict
from dataclasses import dataclass

import asyncpg

from ..nvs import as_text, namespace_of
from ..rpc import RpcClient, RpcError
from .parse import Block

log = logging.getLogger(__name__)

# RPC error codes (bitcoind protocol.h)
RPC_WALLET_ERROR = -4  # name_show: "failed to read from name DB" = no such name
RPC_CLIENT_IN_INITIAL_DOWNLOAD = -10


class IndexCorrupted(Exception):
    """The index and the chain disagree in a way that a rollback will not fix."""


class ChainChanged(Exception):
    """The node switched to another branch while we were reading it; re-check the tip."""


class PrevHashMismatch(ChainChanged):
    """The next block does not build on our tip."""


@dataclass(frozen=True)
class Tip:
    height: int
    hash: bytes
    supply: int


async def get_tip(conn: asyncpg.Connection) -> Tip | None:
    row = await conn.fetchrow(
        "SELECT height, hash, supply FROM blocks ORDER BY height DESC LIMIT 1"
    )
    return Tip(row["height"], row["hash"], row["supply"]) if row else None


async def get_block_hash(conn: asyncpg.Connection, height: int) -> bytes | None:
    return await conn.fetchval("SELECT hash FROM blocks WHERE height = $1", height)


async def set_meta(conn: asyncpg.Connection, key: str, value: object) -> None:
    await conn.execute(
        "INSERT INTO meta (key, value) VALUES ($1, $2::jsonb) "
        "ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value",
        key,
        json.dumps(value),
    )


async def _resolve_prevouts(
    conn: asyncpg.Connection, block: Block
) -> dict[tuple[bytes, int], tuple[int, str | None]]:
    """Value and address of every output spent in the block."""
    created = {
        (tx.txid, out.n): (out.value, out.address) for tx in block.txs for out in tx.outputs
    }
    wanted = [
        (i.prev_txid, i.prev_n)
        for tx in block.txs
        for i in tx.inputs
        if i.spends_output and (i.prev_txid, i.prev_n) not in created
    ]
    found: dict[tuple[bytes, int], tuple[int, str | None]] = {}
    if wanted:
        rows = await conn.fetch(
            """
            SELECT o.txid, o.n, o.value, o.address, o.spent_txid
            FROM unnest($1::bytea[], $2::int[]) AS u(txid, n)
            JOIN outputs o USING (txid, n)
            """,
            [w[0] for w in wanted],
            [w[1] for w in wanted],
        )
        for r in rows:
            if r["spent_txid"] is not None:
                raise IndexCorrupted(
                    f"block {block.height}: output {r['txid'].hex()}:{r['n']} is already spent"
                )
            found[(r["txid"], r["n"])] = (r["value"], r["address"])
        missing = [w for w in wanted if w not in found]
        if missing:
            txid, n = missing[0]
            raise IndexCorrupted(
                f"block {block.height}: spent output {txid.hex()}:{n} is not in the index "
                f"({len(missing)} missing)"
            )
    return created | found


async def apply_block(conn: asyncpg.Connection, block: Block) -> None:
    async with conn.transaction():
        tip = await get_tip(conn)
        if tip is None:
            if block.height != 0:
                raise IndexCorrupted(f"empty index must start at genesis, got height {block.height}")
            prev_supply = 0
        else:
            if block.height != tip.height + 1 or block.prev_hash != tip.hash:
                raise PrevHashMismatch(
                    f"block {block.height} ({block.hash.hex()}) does not extend tip {tip.height}"
                )
            prev_supply = tip.supply

        prevouts = await _resolve_prevouts(conn, block)

        tx_rows, out_rows, in_rows, name_rows = [], [], [], []
        spent_keys: list[tuple[bytes, int, bytes, int]] = []
        addr_tx_rows = []
        # address -> [received, sent, tx_count]
        addr_totals: dict[str, list[int]] = defaultdict(lambda: [0, 0, 0])
        block_in = block_out = block_fees = 0

        for tx in block.txs:
            value_in = 0
            per_addr: dict[str, list[int]] = defaultdict(lambda: [0, 0])
            for i in tx.inputs:
                value, address = 0, None
                if i.spends_output:
                    value, address = prevouts[(i.prev_txid, i.prev_n)]
                    spent_keys.append((i.prev_txid, i.prev_n, tx.txid, i.n))
                value_in += value
                if address:
                    per_addr[address][1] += value
                in_rows.append(
                    (tx.txid, i.n, i.prev_txid, i.prev_n, i.coinbase, i.is_randpay,
                     i.sequence, value, address)
                )
            value_out = 0
            for o in tx.outputs:
                value_out += o.value
                if o.address:
                    per_addr[o.address][0] += o.value
                out_rows.append(
                    (tx.txid, o.n, block.height, o.value, o.script_type, o.address, o.script)
                )
            special = tx.is_coinbase or tx.is_coinstake
            fee = 0 if special else value_in - value_out
            block_in += value_in
            block_out += value_out
            block_fees += fee
            tx_rows.append(
                (tx.txid, block.height, tx.idx, tx.version, tx.time, tx.locktime, tx.size,
                 tx.is_coinbase, tx.is_coinstake, value_in, value_out, fee)
            )
            for address, (received, sent) in per_addr.items():
                addr_tx_rows.append((address, block.height, tx.idx, tx.txid, received, sent))
                totals = addr_totals[address]
                totals[0] += received
                totals[1] += sent
                totals[2] += 1
            for no in tx.name_ops:
                name_rows.append(
                    (tx.txid, no.n, block.height, tx.idx, no.op.op, no.op.name, no.op.value,
                     no.op.days, no.address)
                )

        # Coins are created by coinbase/coinstake and destroyed by fees (PoS
        # chains burn them), so supply moves by outputs minus inputs.
        supply = prev_supply + block_out - block_in

        await conn.execute(
            """
            INSERT INTO blocks (height, hash, prev_hash, time, mediantime, version, merkleroot,
                bits, nonce, difficulty, size, is_pos, flags, proofhash, modifier, is_auxpow,
                mint, tx_count, value_out, fees, supply)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19,$20,$21)
            """,
            block.height, block.hash, block.prev_hash, block.time, block.mediantime,
            block.version, block.merkleroot, block.bits, block.nonce, block.difficulty,
            block.size, block.is_pos, block.flags, block.proofhash, block.modifier,
            block.is_auxpow, block.mint, len(block.txs), block_out, block_fees, supply,
        )
        await conn.copy_records_to_table(
            "transactions",
            records=tx_rows,
            columns=["txid", "height", "idx", "version", "time", "locktime", "size",
                     "is_coinbase", "is_coinstake", "value_in", "value_out", "fee"],
        )
        await conn.copy_records_to_table(
            "outputs",
            records=out_rows,
            columns=["txid", "n", "height", "value", "script_type", "address", "script"],
        )
        await conn.copy_records_to_table(
            "inputs",
            records=in_rows,
            columns=["txid", "n", "prev_txid", "prev_n", "coinbase", "is_randpay",
                     "sequence", "value", "address"],
        )
        if spent_keys:
            await conn.execute(
                """
                UPDATE outputs o
                SET spent_txid = u.stxid, spent_n = u.sn, spent_height = $5
                FROM unnest($1::bytea[], $2::int[], $3::bytea[], $4::int[]) AS u(txid, n, stxid, sn)
                WHERE o.txid = u.txid AND o.n = u.n
                """,
                [k[0] for k in spent_keys],
                [k[1] for k in spent_keys],
                [k[2] for k in spent_keys],
                [k[3] for k in spent_keys],
                block.height,
            )
        if addr_tx_rows:
            await conn.copy_records_to_table(
                "address_txs",
                records=addr_tx_rows,
                columns=["address", "height", "idx", "txid", "received", "sent"],
            )
            addrs = list(addr_totals)
            await conn.execute(
                """
                INSERT INTO address_stats AS s
                    (address, balance, received, sent, tx_count, first_height, last_height)
                SELECT a, r - s, r, s, c, $5, $5
                FROM unnest($1::text[], $2::bigint[], $3::bigint[], $4::int[]) AS u(a, r, s, c)
                ON CONFLICT (address) DO UPDATE SET
                    balance = s.balance + EXCLUDED.balance,
                    received = s.received + EXCLUDED.received,
                    sent = s.sent + EXCLUDED.sent,
                    tx_count = s.tx_count + EXCLUDED.tx_count,
                    last_height = EXCLUDED.last_height
                """,
                addrs,
                [addr_totals[a][0] for a in addrs],
                [addr_totals[a][1] for a in addrs],
                [addr_totals[a][2] for a in addrs],
                block.height,
            )
        if name_rows:
            await conn.copy_records_to_table(
                "name_ops",
                records=name_rows,
                columns=["txid", "n", "height", "idx", "op", "name", "value", "days", "address"],
            )
            await conn.execute(
                "INSERT INTO names_dirty (name) SELECT DISTINCT unnest($1::bytea[]) "
                "ON CONFLICT DO NOTHING",
                [r[5] for r in name_rows],
            )


async def rollback_to(conn: asyncpg.Connection, height: int) -> None:
    """Remove every block above `height` and undo its effects."""
    async with conn.transaction():
        addresses = [
            r["address"]
            for r in await conn.fetch(
                "SELECT DISTINCT address FROM address_txs WHERE height > $1", height
            )
        ]
        await conn.execute(
            "INSERT INTO names_dirty (name) SELECT DISTINCT name FROM name_ops WHERE height > $1 "
            "ON CONFLICT DO NOTHING",
            height,
        )
        await conn.execute(
            "UPDATE outputs SET spent_txid = NULL, spent_n = NULL, spent_height = NULL "
            "WHERE spent_height > $1",
            height,
        )
        # Cascades to transactions, outputs, inputs, name_ops and address_txs.
        await conn.execute("DELETE FROM blocks WHERE height > $1", height)
        if addresses:
            await conn.execute("DELETE FROM address_stats WHERE address = ANY($1::text[])", addresses)
            await conn.execute(
                """
                INSERT INTO address_stats
                    (address, balance, received, sent, tx_count, first_height, last_height)
                SELECT address, sum(received) - sum(sent), sum(received), sum(sent), count(*),
                       min(height), max(height)
                FROM address_txs
                WHERE address = ANY($1::text[])
                GROUP BY address
                """,
                addresses,
            )


async def refresh_dirty_names(
    conn: asyncpg.Connection, rpc: RpcClient, batch_size: int = 200
) -> int:
    """Re-read the state of touched names from the node. Returns how many were processed."""
    done = 0
    while True:
        names = [
            r["name"]
            for r in await conn.fetch("SELECT name FROM names_dirty LIMIT $1", batch_size)
        ]
        if not names:
            return done
        queryable = [n for n in names if as_text(n) is not None]
        results = await rpc.batch(
            [("name_show", [n.decode(), "base64"]) for n in queryable], return_exceptions=True
        )
        async with conn.transaction():
            for name, res in zip(queryable, results, strict=True):
                if isinstance(res, RpcError):
                    if res.code == RPC_CLIENT_IN_INITIAL_DOWNLOAD:
                        log.info("node is in initial block download; names refresh postponed")
                        return done
                    if res.code != RPC_WALLET_ERROR:
                        raise res
                    await conn.execute("DELETE FROM names WHERE name = $1", name)
                    continue
                await conn.execute(
                    """
                    INSERT INTO names (name, name_text, namespace, value, address, txid, vout,
                                       expires_at, deleted, time)
                    VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)
                    ON CONFLICT (name) DO UPDATE SET
                        value = EXCLUDED.value, address = EXCLUDED.address,
                        txid = EXCLUDED.txid, vout = EXCLUDED.vout,
                        expires_at = EXCLUDED.expires_at, deleted = EXCLUDED.deleted,
                        time = EXCLUDED.time
                    """,
                    name, name.decode(), namespace_of(name),
                    base64.b64decode(res.get("value", "")),
                    res.get("address") or None, bytes.fromhex(res["txid"]), res["vout"],
                    res["expires_at"], bool(res.get("deleted", False)), res.get("time"),
                )
            unqueryable = set(names) - set(queryable)
            if unqueryable:
                # name_show takes the name as a JSON string, so non-UTF-8 names
                # cannot be looked up; their history is still in name_ops.
                log.warning("%d non-UTF-8 names skipped", len(unqueryable))
            await conn.execute("DELETE FROM names_dirty WHERE name = ANY($1::bytea[])", names)
        done += len(names)
