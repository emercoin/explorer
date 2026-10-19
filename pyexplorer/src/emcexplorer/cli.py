"""emcexplorer initdb | index | api"""

from __future__ import annotations

import argparse
import asyncio
import logging
import os
import signal

from .config import Settings


def _database_url() -> str:
    url = os.environ.get("DATABASE_URL")
    if not url:
        raise SystemExit("environment variable DATABASE_URL is required")
    return url


async def _initdb(database_url: str) -> None:
    import asyncpg

    from .db import create_database, init_schema

    if await create_database(database_url):
        logging.getLogger(__name__).info("created the database")
    conn = await asyncpg.connect(database_url)
    try:
        await init_schema(conn)
    finally:
        await conn.close()


async def _index(settings: Settings) -> None:
    from .db import connect_pool
    from .indexer.sync import Indexer
    from .rpc import RpcClient

    # In a container the indexer is PID 1, and the kernel ignores signals PID 1 has
    # no handler for: without this, `docker stop` would wait out its grace period
    # and kill it, and a release would keep the site down meanwhile. Each block is
    # written in one transaction, so stopping mid-block just rolls that block back.
    loop = asyncio.get_running_loop()
    main = asyncio.current_task()
    for sig in (signal.SIGTERM, signal.SIGINT):
        loop.add_signal_handler(sig, main.cancel)

    pool = await connect_pool(settings.database_url, min_size=1, max_size=2)
    try:
        async with RpcClient(settings.rpc_url, timeout=settings.rpc_timeout) as rpc:
            await Indexer(pool, rpc, settings).run_forever()
    except asyncio.CancelledError:
        logging.getLogger(__name__).info("stopping")
    finally:
        await pool.close()


def main() -> None:
    parser = argparse.ArgumentParser(prog="emcexplorer")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("initdb", help="create the database and tables (idempotent)")
    sub.add_parser("index", help="run the indexer")
    api = sub.add_parser("api", help="run the HTTP API")
    api.add_argument("--host", default="127.0.0.1")
    api.add_argument("--port", type=int, default=8000)
    args = parser.parse_args()

    logging.basicConfig(
        level=os.environ.get("LOG_LEVEL", "INFO"),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    # httpx logs every RPC request at INFO; keep only its warnings.
    logging.getLogger("httpx").setLevel(logging.WARNING)

    if args.command == "initdb":
        asyncio.run(_initdb(_database_url()))
    elif args.command == "index":
        asyncio.run(_index(Settings.from_env()))
    elif args.command == "api":
        import uvicorn

        from .api.app import create_app

        app = create_app(dsn=_database_url(), rpc_url=os.environ.get("EMC_RPC_URL"))
        uvicorn.run(app, host=args.host, port=args.port)


if __name__ == "__main__":
    main()
