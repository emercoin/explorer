from __future__ import annotations

from importlib.resources import files
from urllib.parse import unquote, urlsplit

import asyncpg


async def connect_pool(dsn: str, **kwargs) -> asyncpg.Pool:
    return await asyncpg.create_pool(dsn, **kwargs)


async def create_database(dsn: str) -> bool:
    """Create the database named in dsn unless it exists; True if it was created.

    The index of a second network (testnet) lives in its own database of the same
    PostgreSQL, which docker's postgres image does not create by itself.
    """
    try:
        conn = await asyncpg.connect(dsn)
    except asyncpg.InvalidCatalogNameError:
        pass
    else:
        await conn.close()
        return False
    name = unquote(urlsplit(dsn).path.lstrip("/"))
    admin = await asyncpg.connect(dsn, database="postgres")
    try:
        await admin.execute('CREATE DATABASE "{}"'.format(name.replace('"', '""')))
    finally:
        await admin.close()
    return True


async def init_schema(conn: asyncpg.Connection) -> None:
    sql = files("emcexplorer").joinpath("schema.sql").read_text()
    await conn.execute(sql)
