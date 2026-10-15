"""Bare multisig outputs: every key is kept, the value stays with the first one."""

import httpx
import pytest

from emcexplorer.api.app import create_app
from fakenode import ChainBuilder, FakeNode, Out, TxSpec
from test_indexer import A_, B_, D_, M_, N_, coinbase, reset, snapshot, sync


def chain() -> ChainBuilder:
    c = ChainBuilder("m")
    c.add_block([coinbase(A_, "100")], mint="100")
    a0 = c.blocks[0]["tx"][0]["txid"]
    lock = TxSpec(vin=[(a0, 0)], vout=[Out.multisig("60", [B_, M_, N_], 2), Out("39.99", A_)])
    c.add_block([coinbase(D_, "10"), lock], mint="10")
    locked = c.blocks[1]["tx"][1]["txid"]
    c.add_block([coinbase(D_, "10"), TxSpec(vin=[(locked, 0)], vout=[Out("59.99", D_)])], mint="10")
    return c


@pytest.fixture
async def api(pool):
    await sync(pool, FakeNode(chain()))
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        yield c


async def test_multisig_output(api):
    locked = chain().blocks[1]["tx"][1]["txid"]
    tx = (await api.get(f"/api/v1/txs/{locked}")).json()
    out, change = tx["vout"]
    assert out["type"] == "multisig" and out["address"] == B_
    assert out["addresses"] == [B_, M_, N_] and out["required"] == 2
    assert "addresses" not in change  # only multisig outputs carry the list

    # the value is credited to the first key only, as before
    assert (await api.get(f"/api/v1/addresses/{B_}")).json()["received"] == "60.000000"
    assert (await api.get(f"/api/v1/addresses/{M_}")).status_code == 404
    listed = (await api.get(f"/api/v1/addresses/{M_}/multisig")).json()
    assert [(m["txid"], m["vout"], m["value"], m["required"]) for m in listed] == [(locked, 0, "60.000000", 2)]
    assert listed[0]["spent_by"]["height"] == 2
    assert (await api.get(f"/api/v1/addresses/{D_}/multisig")).json() == []

    page = await api.get(f"/tx/{locked}")
    assert "multisig, 2 of 3" in page.text and f'href="/address/{N_}"' in page.text
    other = await api.get(f"/address/{M_}")  # known only as a co-signer
    assert other.status_code == 200 and "Multisig outputs" in other.text and "2 of 3" in other.text
    assert (await api.get(f"/address/{D_}")).text.count("Multisig outputs") == 0
    assert (await api.get(f"/address/E{'Q' * 33}")).status_code == 404


async def test_backfill_older_index(pool):
    node = FakeNode(chain())
    await sync(pool, node)
    fresh = await snapshot(pool)
    async with pool.acquire() as conn:
        # an index written before the columns existed
        await conn.execute("UPDATE outputs SET addresses = NULL, required = NULL")
        await conn.execute("DELETE FROM meta WHERE key = 'multisig_backfill'")
    await sync(pool, node)
    assert await snapshot(pool) == fresh
    async with pool.acquire() as conn:
        assert await conn.fetchval("SELECT value->>'outputs' FROM meta WHERE key = 'multisig_backfill'") == "1"

    await reset(pool)
    await sync(pool, node)
    assert await snapshot(pool) == fresh
