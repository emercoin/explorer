"""A second network (testnet) next to mainnet: its database, the header switch, the status field."""

from urllib.parse import urlsplit, urlunsplit

import asyncpg
import httpx
import pytest

from conftest import TEST_DSN
from emcexplorer.api.app import create_app
from emcexplorer.config import network_links, network_name
from emcexplorer.db import create_database
from emcexplorer.web.pages import templates


async def test_create_database():
    if not TEST_DSN:
        pytest.skip("TEST_DATABASE_URL is not set")
    parts = urlsplit(TEST_DSN)
    name = parts.path.lstrip("/") + "_testnet"
    dsn = urlunsplit(parts._replace(path="/" + name))
    admin = await asyncpg.connect(TEST_DSN)
    try:
        await admin.execute(f'DROP DATABASE IF EXISTS "{name}"')
        assert await create_database(dsn) is True
        assert await create_database(dsn) is False  # already there
        assert await create_database(TEST_DSN) is False
        conn = await asyncpg.connect(dsn)
        await conn.close()
    finally:
        await admin.execute(f'DROP DATABASE IF EXISTS "{name}"')
        await admin.close()


def test_network_settings(monkeypatch):
    monkeypatch.delenv("EXPLORER_NETWORK", raising=False)
    monkeypatch.delenv("EXPLORER_NETWORKS", raising=False)
    assert network_name() == "mainnet" and network_links() == []
    monkeypatch.setenv("EXPLORER_NETWORK", "testnet")
    monkeypatch.setenv("EXPLORER_NETWORKS", " mainnet=https://m.example, testnet=https://t.example ,junk")
    assert network_name() == "testnet"
    assert network_links() == [("mainnet", "https://m.example"), ("testnet", "https://t.example")]


async def test_network_switch(pool, monkeypatch):
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as api:
        page = (await api.get("/stats")).text
        assert 'class="networks"' not in page and "Testnet" not in page  # mainnet, no switch set
        assert (await api.get("/api/v1/status")).json()["network"] == "mainnet"

        links = [("mainnet", "https://explorer3.emercoin.com"), ("testnet", "https://testnet3.emercoin.com")]
        monkeypatch.setitem(templates.env.globals, "network", {"name": "testnet", "links": links})
        header = (await api.get("/stats")).text.split("</header>")[0]
        assert '<span class="tag pow">Testnet</span>' in header
        assert '<a href="https://explorer3.emercoin.com">Mainnet</a> | <strong>Testnet</strong>' in header

        monkeypatch.setenv("EXPLORER_NETWORK", "testnet")
        assert (await api.get("/api/v1/status")).json()["network"] == "testnet"
