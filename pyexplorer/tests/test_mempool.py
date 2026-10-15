"""Unconfirmed transactions: read from the node through the API, cached briefly."""

import httpx
import pytest

from emcexplorer.api import mempool as mempool_mod
from emcexplorer.api.app import create_app
from emcexplorer.rpc import RpcClient
from fakenode import FakeNode, Out, TxSpec
from test_indexer import B_, C_, D_, SETTINGS, main_chain, sync

NOW = 1_700_000_000


@pytest.fixture
async def node(pool):
    chain = main_chain()
    node = FakeNode(chain)
    await sync(pool, node)
    name_tx = chain.blocks[2]["tx"][2]["txid"]  # its output 1 (99.98 to B) is unspent
    first = node.add_to_mempool(TxSpec(vin=[(name_tx, 1)], vout=[Out("50", D_), Out("49.97", B_)]),
                                fee="0.01", time=NOW)
    node.add_to_mempool(TxSpec(vin=[(first, 0)], vout=[Out("49.99", C_)]), fee="0.01", time=NOW + 5)
    node.add_to_mempool(TxSpec(vin=[("ff" * 32, 0)], vout=[Out("1", C_)]), fee="0.001", time=NOW - 5)
    return node


@pytest.fixture
async def api(pool, node):
    async with RpcClient(SETTINGS.rpc_url, transport=node.transport) as rpc:
        app = create_app(pool=pool, rpc=rpc)
        async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
            yield c


async def test_mempool_listing(api, node):
    pool = (await api.get("/api/v1/mempool")).json()
    assert pool["count"] == 3 and pool["bytes"] == 600
    first, second = list(node.mempool)[:2]
    assert [t["txid"] for t in pool["txs"]][:2] == [second, first]  # newest first
    assert pool["txs"][0]["depends"] == [first]
    t1 = pool["txs"][1]
    assert t1["value_in"] == "99.980000" and t1["value_out"] == "99.970000" and t1["fee"] == "0.010000"
    assert pool["txs"][2]["value_in"] is None  # spends an output nobody knows
    assert len((await api.get("/api/v1/mempool?limit=1")).json()["txs"]) == 1


async def test_mempool_tx_and_page(api, node):
    first, second = list(node.mempool)[:2]
    tx = (await api.get(f"/api/v1/mempool/{second}")).json()
    # the input comes from another pooled transaction
    assert tx["vin"][0] == {"n": 0, "coinbase": None, "txid": first, "vout": 0, "randpay": False,
                            "value": "50.000000", "address": D_}
    assert tx["vout"][0]["address"] == C_ and tx["vout"][0]["spent_by"] is None
    assert (await api.get("/api/v1/mempool/" + "0" * 64)).status_code == 404
    assert (await api.get("/api/v1/mempool/xyz")).status_code == 400

    page = await api.get(f"/tx/{first}")
    assert page.status_code == 200 and "unconfirmed" in page.text and "99.980000" in page.text
    listing = await api.get("/mempool")
    assert listing.status_code == 200 and "3 unconfirmed transactions" in listing.text
    assert (await api.get("/tx/" + "0" * 64)).status_code == 404


async def test_mempool_is_cached(api, node, monkeypatch):
    calls = 0
    original = node._call

    def counting(method, params):
        nonlocal calls
        calls += method == "getrawmempool"
        return original(method, params)

    monkeypatch.setattr(node, "_call", counting)
    await api.get("/api/v1/mempool")
    await api.get("/api/v1/mempool")
    assert calls == 1
    monkeypatch.setattr(mempool_mod, "TTL", -1.0)
    await api.get("/api/v1/mempool")
    assert calls == 2


async def test_mempool_without_node(pool):
    await sync(pool, FakeNode(main_chain()))
    app = create_app(pool=pool)  # no RPC configured
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        assert (await c.get("/api/v1/mempool")).status_code == 503
        assert (await c.get("/mempool")).status_code == 503
        assert (await c.get("/tx/" + "0" * 64)).status_code == 404  # plain "not found"
