from __future__ import annotations

from importlib.resources import files

import asyncpg


async def connect_pool(dsn: str, **kwargs) -> asyncpg.Pool:
    return await asyncpg.create_pool(dsn, **kwargs)


async def init_schema(conn: asyncpg.Connection) -> None:
    sql = files("emcexplorer").joinpath("schema.sql").read_text()
    await conn.execute(sql)
