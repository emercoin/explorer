"""daily_stats: rebuilt from the index near the tip, kept right across reorgs."""

import datetime as dt

import httpx
import pytest

from emcexplorer.api.app import create_app
from emcexplorer.indexer.stats import refresh_daily_stats
from fakenode import ChainBuilder, FakeNode, Out, TxSpec
from test_indexer import A_, B_, C_, D_, coinbase, snapshot, sync, reset

DAY1 = int(dt.datetime(2024, 3, 1, tzinfo=dt.UTC).timestamp())
DAY2, DAY3 = DAY1 + 86400, DAY1 + 2 * 86400


def chain() -> ChainBuilder:
    c = ChainBuilder("s")
    c.add_block([coinbase(A_, "100")], mint="100", time=DAY1 + 100)
    c.add_block([coinbase(B_, "100")], mint="100", time=DAY1 + 700)
    b0 = c.blocks[0]["tx"][0]["txid"]
    pay = TxSpec(vin=[(b0, 0)], vout=[Out("70", C_), Out("29.99", A_)])
    c.add_block([coinbase(A_, "10"), pay], mint="10", time=DAY2 + 50)
    # a later block stamped slightly earlier than its predecessor
    c.add_block([coinbase(B_, "10")], mint="10", time=DAY2 + 40)
    c.add_block([coinbase(D_, "5")], mint="5", time=DAY3 + 10)
    return c


@pytest.fixture
async def api(pool):
    await sync(pool, FakeNode(chain()))
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        yield c


async def test_daily_stats(api, pool):
    days = (await api.get("/api/v1/stats/daily")).json()
    assert [d["date"] for d in days] == ["2024-03-03", "2024-03-02", "2024-03-01"]
    d3, d2, d1 = days
    assert (d1["blocks"], d1["txs"], d1["payments"], d1["new_addresses"]) == (2, 2, 0, 2)
    assert (d2["blocks"], d2["txs"], d2["payments"]) == (2, 3, 1)
    assert d2["volume"] == "99.990000" and d2["fees"] == "0.010000" and d2["mint"] == "20.000000"
    assert d2["new_addresses"] == 1 and d2["active_addresses"] == 3  # C new; A, B, C active
    assert (d2["first_height"], d2["last_height"]) == (2, 3)
    assert d3["supply"] == "224.990000" and d3["known_addresses"] == 4
    assert d3["chain_size"] == 5000 and d1["chain_size"] == 2000
    assert d3["funded_addresses"] == 4 and d2["funded_addresses"] is None  # sampled on the latest day
    assert (await api.get("/api/v1/stats/daily?days=1")).json() == [d3]

    summary = (await api.get("/api/v1/stats/summary")).json()
    assert summary["height"] == 4 and summary["txs"] == 6 and summary["payments"] == 1
    assert summary["known_addresses"] == 4 and summary["funded_addresses"] == 4

    async with pool.acquire() as conn:
        assert await refresh_daily_stats(conn) is False  # nothing new since the last refresh


async def test_legacy_charts(api):
    known = (await api.get("/api/stats/known_addresses/30days")).json()
    assert known == [
        {"known_addresses": "4", "change": 1, "percent_change": 25.0, "date": "2024-3-3"},
        {"known_addresses": "3", "change": 1, "percent_change": 33.33, "date": "2024-3-2"},
    ]
    size = (await api.get("/api/stats/chain_size/60days")).json()
    assert size[0] == {"chain_size": 4.88, "change": 0.98, "percent_change": 20.0, "date": "2024-3-3"}
    total = (await api.get("/api/stats/chain_size/60days/sum")).json()
    assert total[0]["old_chain_size"] == 1.95 and total[0]["old_date"] == "2024-3-1"
    # only the latest day has a sampled balance count, so there is nothing to compare yet
    assert (await api.get("/api/stats/addresses_in_use/30days")).json() == []
    assert (await api.get("/api/stats/empty_addresses/30days")).json() == []
    assert (await api.get("/api/stats/chain_size/7days")).json() == {"error": "Unknown API call"}


async def test_reorg_rebuilds_days(pool):
    base = chain()
    await sync(pool, FakeNode(base))
    # replace day 3 and the last day-2 block with a longer branch that stays in day 2
    fork = base.fork("t", keep=3)
    fork.add_block([coinbase(C_, "10")], mint="10", time=DAY2 + 900)
    fork.add_block([coinbase(C_, "10")], mint="10", time=DAY2 + 1500)
    fork.add_block([coinbase(C_, "10")], mint="10", time=DAY2 + 2100)
    await sync(pool, FakeNode(fork))
    after_reorg = await snapshot(pool)
    assert [r[0].isoformat() for r in after_reorg["daily_stats"]] == ["2024-03-01", "2024-03-02"]

    await reset(pool)
    await sync(pool, FakeNode(fork))
    fresh = await snapshot(pool)
    assert after_reorg["daily_stats"] == fresh["daily_stats"]


async def test_stats_page(api):
    page = await api.get("/stats")
    assert page.status_code == 200
    assert page.text.count('<svg class="chart"') == 4
    assert "2024-03-03: 1 addresses" in page.text  # bar tooltip
    assert '<td class="num">99.990000</td>' in page.text  # day 2 volume in the table


async def test_stats_page_before_first_refresh(pool):
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        assert (await c.get("/stats")).status_code == 404  # empty index
        assert (await c.get("/api/v1/stats/daily")).json() == []


async def test_static_urls_are_versioned(api):
    page = (await api.get("/stats")).text
    assert '/static/style.css?v=' in page and '/static/htmx.min.js?v=' in page
    # without the stylesheet the hit areas must stay transparent, not black
    assert '<rect class="hit" fill="transparent"' in page
    assert (await api.get("/static/style.css?v=whatever")).status_code == 200


async def test_cache_headers(api):
    v = (await api.get("/stats")).text.split("style.css?v=")[1].split('"')[0]
    css = await api.get(f"/static/style.css?v={v}")
    assert css.headers["cache-control"] == "public, max-age=31536000, immutable"
    assert (await api.get("/static/style.css")).headers["cache-control"] == "public, max-age=300"
    assert (await api.get("/api/v1/status")).headers["cache-control"] == "public, max-age=10"
    assert (await api.get("/api/stats/block_height")).headers["cache-control"] == "public, max-age=10"
    assert "cache-control" not in (await api.get("/stats")).headers  # pages: browser default
    assert (await api.get("/api/v1/mempool")).headers["cache-control"] == "no-store"  # 503, no node here
