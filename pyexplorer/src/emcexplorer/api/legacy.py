"""The /api/... routes of the PHP explorer (html/api.php, in the git
history up to v0.1.0), served from the new index.

Kept for outside users of the old API. Paths and field names are the same,
values follow the PHP version: everything that came from MySQL is a string,
amounts are plain decimals without trailing zeros, errors come back as
{"error": ...} with status 200, and every response is pretty-printed.

Fields the new index does not keep (coin-days destroyed, address counters per
block, entropy bit, modifier checksum, script asm, required signatures, input
scripts) are null. /api/address/ismine (a wallet call) is not served.

The chart routes (/api/stats/{known_addresses,addresses_in_use,empty_addresses,
chain_size}/{30days,60days}) read daily_stats. Addresses with a balance are
only known for days the indexer sampled, so the last two start short.
"""

from __future__ import annotations

import hashlib
import json
import re
from decimal import Decimal
from typing import Any

import asyncpg
from fastapi import APIRouter, HTTPException, Request
from fastapi.responses import PlainTextResponse, Response

from ..amounts import COIN
from .app import daily_rows

router = APIRouter(include_in_schema=False)

HEX64 = re.compile(r"^[0-9a-fA-F]{64}$")
ADDRESS = re.compile(r"^[0-9A-Za-z]{20,100}$")
# Newest transactions listed by /api/address/balance/<address>/full. The PHP
# version listed all of them, which for a pool address is hundreds of thousands.
ADDRESS_TX_LIMIT = 1000

HELP = {
    "version": "1.2.3",
    "date": "2018-13-12",
    "commands": {
        "/api/block/hash/<hash>/less": "details about a given block",
        "/api/block/hash/<hash>": "details about a given block including it's transactions",
        "/api/block/hash/<hash>/full": "details about a given block including it's transactions"
        " together with all transaction in- and outputs",
        "/api/block/height/<height>/less": "details about a given block (block height as reference)",
        "/api/block/height/<height>": "details about a given block including it's transactions"
        " (block height as reference)",
        "/api/block/height/<height>/full": "details about a given block including it's transactions"
        " together with all transaction in- and outputs (block height as reference)",
        "/api/block/latest/less": "details about the last block",
        "/api/block/latest": "details about the last block including it's transactions",
        "/api/block/latest/full": "details about the last block including it's transactions"
        " together with all transaction in- and outputs",
        "/api/tx/hash/<hash>/less": "details about a given transaction",
        "/api/tx/hash/<hash>": "details about a given transaction including it's in- and outputs",
        "/api/tx/hash/<hash>/full": "more details information about a given transaction"
        " including it's in- and outputs",
        "/api/address/balance/<address>/full": "details about a given address"
        " including an transaction overview",
        "/api/address/balance/<address>": "details about a given address",
        "/api/address/balance/<address>/less": "shows the balance only",
        "/api/address/isvalid/<address>": "returns 1 if the address is valid",
        "/api/stats/block_height": "returns the current block height",
        "/api/stats/coin_supply": "returns the current coin supply",
    },
}

UNKNOWN = {"error": "Unknown API call"}
NOT_FOUND = {"error": "Could not decode hash"}


class _Pretty(Response):
    media_type = "application/json"

    def render(self, content: Any) -> bytes:
        return json.dumps(content, indent=4, ensure_ascii=False).encode()


def _s(value: Any) -> str | None:
    return None if value is None else str(value)


def _amount(units: int | None) -> str | None:
    """Units as MySQL printed a DOUBLE: "12.5", "100", "0"."""
    if units is None:
        return None
    d = Decimal(units) / COIN
    return format(d.normalize(), "f") if d else "0"


def _hex(data: bytes | None) -> str | None:
    return data.hex() if data is not None else None


# --- address validation (base58check and bech32, mainnet prefixes) ----------

B58 = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"
BASE58_VERSIONS = {33, 92}  # 'E...' pubkey hash, 'e...' script hash
BECH32_HRP = "em"
BECH32 = "qpzry9x8gf2tvdw0s3jn54khce6mua7l"


def _base58_ok(addr: str) -> bool:
    n = 0
    for c in addr:
        i = B58.find(c)
        if i < 0:
            return False
        n = n * 58 + i
    raw = n.to_bytes((n.bit_length() + 7) // 8, "big")
    raw = b"\0" * (len(addr) - len(addr.lstrip("1"))) + raw
    if len(raw) != 25:
        return False
    body, check = raw[:-4], raw[-4:]
    return hashlib.sha256(hashlib.sha256(body).digest()).digest()[:4] == check and body[0] in BASE58_VERSIONS


def _bech32_polymod(values: list[int]) -> int:
    gen = (0x3B6A57B2, 0x26508E6D, 0x1EA119FA, 0x3D4233DD, 0x2A1462B3)
    chk = 1
    for v in values:
        top = chk >> 25
        chk = (chk & 0x1FFFFFF) << 5 ^ v
        for i in range(5):
            chk ^= gen[i] if (top >> i) & 1 else 0
    return chk


def _convertbits(data: list[int], frm: int, to: int) -> list[int] | None:
    acc = bits = 0
    out = []
    for v in data:
        acc = (acc << frm) | v
        bits += frm
        while bits >= to:
            bits -= to
            out.append((acc >> bits) & ((1 << to) - 1))
    if bits >= frm or (acc << (to - bits)) & ((1 << to) - 1):
        return None
    return out


def _bech32_ok(addr: str) -> bool:
    if addr.lower() != addr and addr.upper() != addr:
        return False
    addr = addr.lower()
    hrp, _, data = addr.rpartition("1")
    if hrp != BECH32_HRP or len(data) < 6 or any(c not in BECH32 for c in data):
        return False
    values = [BECH32.index(c) for c in data]
    const = _bech32_polymod([ord(c) >> 5 for c in hrp] + [0] + [ord(c) & 31 for c in hrp] + values)
    version = values[0]
    if const != (1 if version == 0 else 0x2BC830A3):  # BIP173 for v0, BIP350 for v1+
        return False
    prog = _convertbits(values[1:-6], 5, 8)
    if prog is None or version > 16 or not 2 <= len(prog) <= 40:
        return False
    return version != 0 or len(prog) in (20, 32)


def is_valid_address(addr: str) -> bool:
    return bool(ADDRESS.match(addr)) and (_base58_ok(addr) or _bech32_ok(addr))


# --- blocks and transactions ---------------------------------------------------


def _tx_less(tx: asyncpg.Record, numvin: int, numvout: int, block_time: int) -> dict[str, Any]:
    return {
        "tx_hash": tx["txid"].hex(),
        "time": _s(tx["time"] if tx["time"] is not None else block_time),
        "numvin": _s(numvin),
        "numvout": _s(numvout),
        "valuein": _amount(tx["value_in"]),
        "valueout": _amount(tx["value_out"]),
        "fee": _amount(tx["fee"]),
        "coindaysdestroyed": None,
        "avgcoindaysdestroyed": None,
    }


def _vin(i: asyncpg.Record, full: bool) -> dict[str, Any]:
    if not full:
        return {
            "address": i["address"],
            "value": _amount(i["value"]),
            "coindaysdestroyed": None,
            "avgcoindaysdestroyed": None,
        }
    return {
        "output_txid": _hex(i["prev_txid"]),
        "coinbase": _hex(i["coinbase"]),
        "vout": _s(i["prev_n"]),
        "asm": None,
        "hex": None,
        "sequence": _s(i["sequence"]),
        "address": i["address"],
        "value": _amount(i["value"]),
        "coindaysdestroyed": None,
        "avgcoindaysdestroyed": None,
    }


def _vout(o: asyncpg.Record, full: bool) -> dict[str, Any]:
    if not full:
        return {"value": _amount(o["value"]), "address": o["address"]}
    return {
        "value": _amount(o["value"]),
        "n": _s(o["n"]),
        "asm": None,
        "hex": o["script"].hex(),
        "reqsigs": None,
        "type": o["script_type"],
        "address": o["address"],
    }


async def _txs(conn: asyncpg.Connection, txids: list[bytes], details: str, block_time: dict[int, int]):
    """Transactions in the given order, shaped for `details` ('less', 'reduce' or 'full')."""
    txs = await conn.fetch("SELECT * FROM transactions WHERE txid = ANY($1)", txids)
    by_id = {t["txid"]: t for t in txs}
    ins = await conn.fetch("SELECT * FROM inputs WHERE txid = ANY($1) ORDER BY txid, n", txids)
    outs = await conn.fetch("SELECT * FROM outputs WHERE txid = ANY($1) ORDER BY txid, n", txids)
    vin: dict[bytes, list] = {t: [] for t in txids}
    vout: dict[bytes, list] = {t: [] for t in txids}
    for i in ins:
        vin[i["txid"]].append(i)
    for o in outs:
        vout[o["txid"]].append(o)
    result = []
    for txid in txids:
        tx = by_id[txid]
        item = _tx_less(tx, len(vin[txid]), len(vout[txid]), block_time[tx["height"]])
        if details != "less":
            full = details == "full"
            item["vin"] = [_vin(i, full) for i in vin[txid]]
            item["vout"] = [_vout(o, full) for o in vout[txid]]
        result.append(item)
    return result


async def _block(conn: asyncpg.Connection, subtype: str, pattern: str, details: str):
    if subtype == "latest":
        row = await conn.fetchrow("SELECT * FROM blocks ORDER BY height DESC LIMIT 1")
    elif subtype == "hash" and HEX64.match(pattern):
        row = await conn.fetchrow("SELECT * FROM blocks WHERE hash = $1", bytes.fromhex(pattern))
    elif subtype == "height" and pattern.isdigit() and int(pattern) < 2**31:
        row = await conn.fetchrow("SELECT * FROM blocks WHERE height = $1", int(pattern))
    elif subtype in ("hash", "height"):
        row = None
    else:
        return UNKNOWN
    if row is None:
        return NOT_FOUND
    height = row["height"]
    txids = [r["txid"] for r in await conn.fetch(
        "SELECT txid FROM transactions WHERE height = $1 ORDER BY idx", height
    )]
    counts = await conn.fetchrow(
        """
        SELECT (SELECT count(*) FROM inputs WHERE txid = ANY($1)) AS numvin,
               (SELECT count(*) FROM outputs WHERE txid = ANY($1)) AS numvout,
               (SELECT coalesce(sum(value_in), 0) FROM transactions WHERE height = $2) AS valuein
        """,
        txids,
        height,
    )
    nxt = await conn.fetchval("SELECT hash FROM blocks WHERE height = $1", height + 1)
    block = {
        "blockhash": row["hash"].hex(),
        "size": _s(row["size"]),
        "height": _s(height),
        "version": _s(row["version"]),
        "merkleroot": row["merkleroot"].hex(),
        "time": _s(row["time"]),
        "nonce": _s(row["nonce"]),
        "bits": row["bits"],
        "difficulty": _s(row["difficulty"]),
        "mint": _amount(row["mint"]),
        "previousblockhash": _hex(row["prev_hash"]),
        "nextblockhash": nxt.hex() if nxt else "",
        "flags": row["flags"],
        "proofhash": _hex(row["proofhash"]),
        "entropybit": None,
        "modifier": row["modifier"],
        "modifierchecksum": None,
        "numtx": _s(row["tx_count"]),
        "numvin": _s(counts["numvin"]),
        "numvout": _s(counts["numvout"]),
        "valuein": _amount(counts["valuein"]),
        "valueout": _amount(row["value_out"]),
        "fee": _amount(row["fees"]),
        "total_coins": _amount(row["supply"]),
        "coindaysdestroyed": None,
        "avgcoindaysdestroyed": None,
        "total_coindays": None,
        "total_avgcoindays": None,
        "total_addresses_used": None,
        "total_addresses_empty": None,
    }
    if details != "less":
        # 'reduce' lists the transactions without their inputs and outputs
        block["transactions"] = await _txs(
            conn, txids, "full" if details == "full" else "less", {height: row["time"]}
        )
    return block


async def _tx(conn: asyncpg.Connection, subtype: str, pattern: str, details: str):
    if subtype != "hash":
        return UNKNOWN
    if not HEX64.match(pattern):
        return NOT_FOUND
    txid = bytes.fromhex(pattern)
    row = await conn.fetchrow(
        "SELECT t.height, b.time FROM transactions t JOIN blocks b USING (height) WHERE t.txid = $1", txid
    )
    if row is None:
        return NOT_FOUND
    return (await _txs(conn, [txid], details, {row["height"]: row["time"]}))[0]


async def _address(conn: asyncpg.Connection, pattern: str, details: str):
    if not ADDRESS.match(pattern):
        return NOT_FOUND
    row = await conn.fetchrow("SELECT * FROM address_stats WHERE address = $1", pattern)
    if row is None:
        return NOT_FOUND
    result: dict[str, Any] = {"address": row["address"], "balance": _amount(row["balance"])}
    if details == "less":
        return result
    agg = await conn.fetchrow(
        """
        SELECT count(*) FILTER (WHERE sent > 0) AS count_sent,
               count(*) FILTER (WHERE received > 0) AS count_received,
               (SELECT time FROM blocks WHERE height = max(a.height) FILTER (WHERE sent > 0)) AS last_sent,
               (SELECT time FROM blocks WHERE height = max(a.height) FILTER (WHERE received > 0))
                   AS last_received
        FROM address_txs a WHERE address = $1
        """,
        pattern,
    )
    result.update(
        last_sent=_s(agg["last_sent"]),
        last_received=_s(agg["last_received"]),
        count_sent=_s(agg["count_sent"]),
        count_received=_s(agg["count_received"]),
        total_sent=_amount(row["sent"]),
        total_received=_amount(row["received"]),
    )
    if details == "full":
        rows = await conn.fetch(
            """
            SELECT a.txid, a.sent, a.received, coalesce(t.time, b.time) AS time
            FROM address_txs a
            JOIN transactions t ON t.txid = a.txid
            JOIN blocks b ON b.height = a.height
            WHERE a.address = $1
            ORDER BY a.height DESC, a.idx DESC
            LIMIT $2
            """,
            pattern,
            ADDRESS_TX_LIMIT,
        )
        result["transactions"] = [
            {
                "tx_hash": r["txid"].hex(),
                "time": _s(r["time"]),
                "sent": _amount(r["sent"]) if r["sent"] else "",
                "received": _amount(r["received"]) if r["received"] else "",
            }
            for r in rows
        ]
    return result


CHARTS = ("known_addresses", "addresses_in_use", "empty_addresses", "chain_size")


def _php_date(day) -> str:
    return f"{day.year}-{day.month}-{day.day}"  # MySQL CONCAT(YEAR, '-', MONTH, '-', DAY)


def _pct(new: float, old: float) -> float:
    return round((new - old) / new * 100, 2) if new else 0


async def _chart(conn: asyncpg.Connection, subtype: str, period: str, details: str):
    days = 30 if period == "30days" else 60
    rows = await daily_rows(conn, days + 1)  # one more day to compare the oldest with
    if subtype == "chain_size":
        points = [(r["day"], r["chain_size"]) for r in rows]
    elif subtype == "known_addresses":
        points = [(r["day"], r["known_addresses"]) for r in rows]
    else:
        sampled = [r for r in rows if r["funded_addresses"] is not None]
        if subtype == "addresses_in_use":
            points = [(r["day"], r["funded_addresses"]) for r in sampled]
        else:
            points = [(r["day"], r["known_addresses"] - r["funded_addresses"]) for r in sampled]

    pairs = list(zip(points, points[1:], strict=False))
    if subtype != "chain_size":
        return [
            {subtype: _s(cur), "change": cur - old, "percent_change": _pct(cur, old), "date": _php_date(day)}
            for (day, cur), (_, old) in pairs
        ]
    if details == "sum":
        if not pairs:
            return []
        return [{
            "current_chain_size": round(points[0][1] / 1024, 2),
            "old_chain_size": round(points[-1][1] / 1024, 2),
            "change": round((points[0][1] - points[-1][1]) / 1024, 2),
            "percent_change": round(sum(_pct(cur, old) for (_, cur), (_, old) in pairs), 2),
            "current_date": _php_date(points[0][0]),
            "old_date": _php_date(points[-1][0]),
        }]
    return [
        {
            "chain_size": round(cur / 1024, 2),
            "change": round((cur - old) / 1024, 2),
            "percent_change": _pct(cur, old),
            "date": _php_date(day),
        }
        for (day, cur), (_, old) in pairs
    ]


@router.get("/api/{path:path}")
async def legacy_api(request: Request, path: str) -> Response:
    # Unknown /api/v1/... paths keep their 404 instead of the legacy error.
    if path == "v1" or path.startswith("v1/"):
        raise HTTPException(404, "Not Found")
    # The PHP version split REQUEST_URI on "/": type/subtype/pattern/details.
    parts = path.split("/")
    parts += [""] * (4 - len(parts))
    type_, subtype, pattern, details = parts[0], parts[1], parts[2], parts[3] or "reduce"

    if type_ == "help":
        return _Pretty(HELP)

    pool: asyncpg.Pool = request.app.state.pool
    if type_ == "block":
        if subtype == "latest":
            # /api/block/latest/<details>: the details sit where the pattern would
            details = pattern or "reduce"
        if details not in ("less", "reduce", "full") or (subtype != "latest" and not pattern):
            return _Pretty(UNKNOWN)
        async with pool.acquire() as conn:
            return _Pretty(await _block(conn, subtype, pattern, details))

    if type_ == "tx":
        if details not in ("less", "reduce", "full") or not pattern:
            return _Pretty(UNKNOWN)
        async with pool.acquire() as conn:
            return _Pretty(await _tx(conn, subtype, pattern, details))

    if type_ == "address":
        if subtype == "isvalid" and pattern and details == "reduce":
            # PHP echoed the boolean: "1" or nothing
            return PlainTextResponse("1" if is_valid_address(pattern) else "", media_type="text/html")
        if subtype == "balance" and pattern and details in ("less", "reduce", "full"):
            async with pool.acquire() as conn:
                return _Pretty(await _address(conn, pattern, details))
        return _Pretty(UNKNOWN)

    if type_ == "stats":
        if subtype == "block_height":
            height = await pool.fetchval("SELECT max(height) FROM blocks")
            return _Pretty({"block_height": _s(height)} if height is not None else NOT_FOUND)
        if subtype == "coin_supply":
            supply = await pool.fetchval("SELECT supply FROM blocks ORDER BY height DESC LIMIT 1")
            if supply is None:
                return _Pretty(NOT_FOUND)
            if pattern == "txt":
                return PlainTextResponse(_amount(supply), media_type="text/html")
            return _Pretty({"coin_supply": float(Decimal(supply) / COIN)})
        if subtype in CHARTS and pattern in ("30days", "60days"):
            async with pool.acquire() as conn:
                return _Pretty(await _chart(conn, subtype, pattern, parts[3]))

    return _Pretty(UNKNOWN)
