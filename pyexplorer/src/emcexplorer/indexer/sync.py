"""Main indexer loop: follow the node's best chain, roll back on reorgs."""

from __future__ import annotations

import asyncio
import logging
import time
from collections import deque

import asyncpg
import httpx

from ..config import Settings
from ..rpc import RpcClient, RpcError
from . import stats, store
from .parse import Block, parse_block

log = logging.getLogger(__name__)

# getblockhash/getblock errors that mean "the chain moved under us"
RPC_INVALID_ADDRESS_OR_KEY = -5  # Block not found
RPC_INVALID_PARAMETER = -8  # Block height out of range


class ReorgTooDeep(Exception):
    pass


class Indexer:
    def __init__(self, pool: asyncpg.Pool, rpc: RpcClient, settings: Settings):
        self.pool = pool
        self.rpc = rpc
        self.settings = settings

    async def run_forever(self) -> None:
        while True:
            try:
                idle = await self.sync_once()
            except store.ChainChanged as e:
                log.info("chain changed while syncing (%s); re-checking tip", e)
                continue
            except (httpx.TransportError, OSError, asyncpg.PostgresConnectionError) as e:
                # node or database restarting: wait and resume from the last written block
                log.warning("connection problem: %s; retrying in %.0fs", e, self.settings.poll_interval)
                await asyncio.sleep(self.settings.poll_interval)
                continue
            if idle:
                await asyncio.sleep(self.settings.poll_interval)

    async def sync_once(self, stop_height: int | None = None) -> bool:
        """Index up to the node's tip (or stop_height). Returns True if there was nothing to do."""
        node_height: int = await self.rpc.call("getblockcount")
        target = node_height if stop_height is None else min(node_height, stop_height)

        async with self.pool.acquire() as conn:
            tip = await store.get_tip(conn)
            if tip is not None:
                fork = await self._find_fork(conn, tip.height, node_height)
                if fork < tip.height:
                    log.warning("reorg: rolling back from %d to %d", tip.height, fork)
                    await store.rollback_to(conn, fork)
                start = fork + 1
            else:
                start = 0

            if start <= target:
                await self._index_range(conn, start, target)

            caught_up = node_height - target <= self.settings.prefetch
            await store.set_meta(
                conn, "status", {"node_height": node_height, "updated_at": int(time.time())}
            )
            if caught_up:
                # Name state is read from the node; do it once near the tip
                # instead of after every block of the initial sync.
                await store.refresh_dirty_names(conn, self.rpc)
                await store.backfill_multisig(conn, self.rpc)
                await stats.refresh_daily_stats(conn)
            return start > target

    async def _find_fork(self, conn: asyncpg.Connection, tip_height: int, node_height: int) -> int:
        """Highest height at which our block hash equals the node's."""
        height = min(tip_height, node_height)
        while height >= 0:
            if tip_height - height > self.settings.max_reorg_depth:
                raise ReorgTooDeep(
                    f"no common block within {self.settings.max_reorg_depth} blocks of {tip_height}"
                )
            ours = await store.get_block_hash(conn, height)
            theirs = bytes.fromhex(await self.rpc.call("getblockhash", height))
            if ours == theirs:
                return height
            height -= 1
        return -1

    async def _fetch(self, height: int) -> Block:
        try:
            block_hash = await self.rpc.call("getblockhash", height)
            return parse_block(await self.rpc.call("getblock", block_hash, 2))
        except RpcError as e:
            if e.code in (RPC_INVALID_ADDRESS_OR_KEY, RPC_INVALID_PARAMETER):
                raise store.ChainChanged(f"block {height}: {e}") from e
            raise

    async def _index_range(self, conn: asyncpg.Connection, start: int, end: int) -> None:
        """Fetch blocks `prefetch` ahead while writing them strictly in order."""
        pending: deque[asyncio.Task[Block]] = deque()
        next_height = start
        started = time.monotonic()
        try:
            while next_height <= end or pending:
                while next_height <= end and len(pending) < self.settings.prefetch:
                    pending.append(asyncio.create_task(self._fetch(next_height)))
                    next_height += 1
                block = await pending.popleft()
                await store.apply_block(conn, block)
                if block.height % 1000 == 0 or block.height == end:
                    rate = (block.height - start + 1) / max(time.monotonic() - started, 1e-9)
                    log.info("indexed block %d / %d (%.1f blocks/s)", block.height, end, rate)
                    await store.set_meta(
                        conn, "status", {"node_height": end, "updated_at": int(time.time())}
                    )
        finally:
            for task in pending:
                task.cancel()
            await asyncio.gather(*pending, return_exceptions=True)
