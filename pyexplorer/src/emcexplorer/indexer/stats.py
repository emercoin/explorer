"""Per-day totals in daily_stats, rebuilt from the indexed tables.

The indexer calls refresh_daily_stats() when it is near the node's tip. It
recomputes every UTC day from the one holding the last block it had covered
(meta key 'daily_stats') up to the tip, so new blocks extend the current day
and a reorg, which lowers that height in store.rollback_to(), has its days
rebuilt from the surviving blocks.
"""

from __future__ import annotations

import logging
import time
from datetime import UTC, datetime, timedelta

import asyncpg

log = logging.getLogger(__name__)

META_KEY = "daily_stats"
DAY = 86400

# $1: unix time of the first day to rebuild (always a UTC midnight).
_REBUILD = """
WITH b AS (
    SELECT (to_timestamp(time) AT TIME ZONE 'UTC')::date AS day, height, time, size, mint, fees,
           difficulty, is_pos
    FROM blocks WHERE time >= $1
), bd AS (
    SELECT day, min(height) AS first_height, max(height) AS last_height, count(*) AS blocks,
           count(*) FILTER (WHERE is_pos) AS pos_blocks, sum(size) AS size,
           sum(mint) AS mint, sum(fees) AS fees,
           avg(difficulty) FILTER (WHERE NOT is_pos) AS difficulty_pow,
           avg(difficulty) FILTER (WHERE is_pos) AS difficulty_pos
    FROM b GROUP BY day
), td AS (
    SELECT b.day, count(*) AS txs,
           count(*) FILTER (WHERE NOT t.is_coinbase AND NOT t.is_coinstake) AS payments,
           coalesce(sum(t.value_out) FILTER (WHERE NOT t.is_coinbase AND NOT t.is_coinstake), 0)
               AS volume
    FROM b JOIN transactions t ON t.height = b.height GROUP BY b.day
), nd AS (
    SELECT b.day, count(*) AS n FROM b JOIN address_stats a ON a.first_height = b.height GROUP BY b.day
), ad AS (
    SELECT b.day, count(DISTINCT x.address) AS n
    FROM b JOIN address_txs x ON x.height = b.height GROUP BY b.day
)
INSERT INTO daily_stats (day, first_height, last_height, blocks, pos_blocks, size, txs, payments,
                         volume, fees, mint, supply, difficulty_pow, difficulty_pos,
                         new_addresses, active_addresses)
SELECT bd.day, bd.first_height, bd.last_height, bd.blocks, bd.pos_blocks, bd.size,
       coalesce(td.txs, 0), coalesce(td.payments, 0), coalesce(td.volume, 0), bd.fees, bd.mint,
       (SELECT supply FROM blocks WHERE height = bd.last_height),
       bd.difficulty_pow, bd.difficulty_pos, coalesce(nd.n, 0), coalesce(ad.n, 0)
FROM bd LEFT JOIN td USING (day) LEFT JOIN nd USING (day) LEFT JOIN ad USING (day)
ON CONFLICT (day) DO UPDATE SET
    first_height = EXCLUDED.first_height, last_height = EXCLUDED.last_height,
    blocks = EXCLUDED.blocks, pos_blocks = EXCLUDED.pos_blocks, size = EXCLUDED.size,
    txs = EXCLUDED.txs, payments = EXCLUDED.payments, volume = EXCLUDED.volume,
    fees = EXCLUDED.fees, mint = EXCLUDED.mint, supply = EXCLUDED.supply,
    difficulty_pow = EXCLUDED.difficulty_pow, difficulty_pos = EXCLUDED.difficulty_pos,
    new_addresses = EXCLUDED.new_addresses, active_addresses = EXCLUDED.active_addresses
"""


async def refresh_daily_stats(conn: asyncpg.Connection) -> bool:
    """Bring daily_stats up to the indexed tip. Returns False if it already was."""
    tip = await conn.fetchval("SELECT max(height) FROM blocks")
    done = await conn.fetchval("SELECT (value->>'height')::int FROM meta WHERE key = $1", META_KEY)
    if tip is None or done == tip:
        return False
    started = time.monotonic()
    if done is None:
        start = 0
    else:
        # Block times are not monotonic: a later block may carry an earlier
        # time, so rebuild from the earliest day any of these blocks touches.
        earliest = await conn.fetchval("SELECT min(time) FROM blocks WHERE height >= $1", done)
        if earliest is None:  # `done` is above the tip (should not happen); rebuild the last days
            earliest = await conn.fetchval("SELECT time FROM blocks WHERE height = $1", tip)
        day = datetime.fromtimestamp(earliest, UTC).date() - timedelta(days=1)
        start = int(datetime(day.year, day.month, day.day, tzinfo=UTC).timestamp())
    start_day = datetime.fromtimestamp(start, UTC).date()
    async with conn.transaction():
        await conn.execute(_REBUILD, start)
        # days whose every block was rolled back
        await conn.execute(
            """
            DELETE FROM daily_stats d WHERE d.day >= $1 AND NOT EXISTS (
                SELECT 1 FROM blocks
                WHERE time >= extract(epoch FROM d.day)::bigint
                  AND time < extract(epoch FROM d.day)::bigint + $2)
            """,
            start_day,
            DAY,
        )
        await conn.execute(
            """
            UPDATE daily_stats SET funded_addresses = (SELECT count(*) FROM address_stats WHERE balance > 0)
            WHERE day = (SELECT max(day) FROM daily_stats)
            """
        )
        await conn.execute(
            "INSERT INTO meta (key, value) VALUES ($1, jsonb_build_object('height', $2::int)) "
            "ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value",
            META_KEY,
            tip,
        )
    elapsed = time.monotonic() - started
    if elapsed > 5:
        log.info("daily stats rebuilt from %s in %.0fs", start_day, elapsed)
    return True
