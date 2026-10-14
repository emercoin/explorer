import os
import sys
from pathlib import Path

import asyncpg
import pytest

sys.path.insert(0, str(Path(__file__).parent))

from emcexplorer.db import init_schema  # noqa: E402

TEST_DSN = os.environ.get("TEST_DATABASE_URL")


@pytest.fixture
async def pool():
    """Fresh schema per test. Needs TEST_DATABASE_URL (the database is wiped)."""
    if not TEST_DSN:
        pytest.skip("TEST_DATABASE_URL is not set")
    pool = await asyncpg.create_pool(TEST_DSN, min_size=1, max_size=4)
    async with pool.acquire() as conn:
        await conn.execute("DROP SCHEMA public CASCADE; CREATE SCHEMA public;")
        await init_schema(conn)
    yield pool
    await pool.close()
