"""emcexplorer initdb | index | api"""

from __future__ import annotations

import argparse
import asyncio
import logging
import os

from .config import Settings


def _database_url() -> str:
    url = os.environ.get("DATABASE_URL")
    if not url:
        raise SystemExit("environment variable DATABASE_URL is required")
    return url


async def _initdb(database_url: str) -> None:
    import asyncpg

    from .db import init_schema

    conn = await asyncpg.connect(database_url)
    try:
        await init_schema(conn)
    finally:
        await conn.close()


async def _index(settings: Settings) -> None:
    from .db import connect_pool
    from .indexer.sync import Indexer
    from .rpc import RpcClient

    pool = await connect_pool(settings.database_url, min_size=1, max_size=2)
    async with RpcClient(settings.rpc_url, timeout=settings.rpc_timeout) as rpc:
        try:
            await Indexer(pool, rpc, settings).run_forever()
        finally:
            await pool.close()


def main() -> None:
    parser = argparse.ArgumentParser(prog="emcexplorer")
    sub = parser.add_subparsers(dest="command", required=True)
    sub.add_parser("initdb", help="create tables (idempotent)")
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

        uvicorn.run(create_app(dsn=_database_url()), host=args.host, port=args.port)


if __name__ == "__main__":
    main()
