"""Indexer + API against a real PostgreSQL (TEST_DATABASE_URL) and a fake node."""

import httpx
import pytest

from emcexplorer.api.app import create_app
from emcexplorer.config import Settings
from emcexplorer.db import init_schema
from emcexplorer.indexer import store
from emcexplorer.indexer.parse import RANDPAY_TXID
from emcexplorer.indexer.sync import Indexer
from emcexplorer.rpc import RpcClient
from fakenode import ChainBuilder, FakeNode, Out, TxSpec, name_script

NAME_TX = 0x0666
A_, B_, C_, D_, M_, N_, Z_ = (f"E{x * 33}" for x in "ABCDMNZ")
NAME = b"dns:example.emc"
EMPTY = Out("0", None, script=b"", type="nonstandard")


def coinbase(addr: str, value: str) -> TxSpec:
    return TxSpec(vin=[], vout=[Out(value, addr)], coinbase=True)


def main_chain() -> ChainBuilder:
    c = ChainBuilder("a")
    c.add_block([coinbase(A_, "100")], mint="100")
    c.add_block([coinbase(B_, "100")], mint="100")
    b0, b1 = c.blocks[0]["tx"][0]["txid"], c.blocks[1]["tx"][0]["txid"]
    t1 = TxSpec(vin=[(b0, 0)], vout=[Out("60", C_), Out("39.99", A_)])
    t2 = TxSpec(
        vin=[(b1, 0)],
        vout=[
            Out("0.01", C_, script=name_script(1, NAME, b"A=1.2.3.4", 365, C_), type="name_pubkeyhash"),
            Out("99.98", B_),
        ],
        version=NAME_TX,
    )
    c.add_block([coinbase(M_, "10"), t1, t2], mint="10")
    t1id, t2id = c.blocks[2]["tx"][1]["txid"], c.blocks[2]["tx"][2]["txid"]
    # PoS block: empty coinbase, coinstake (first output empty), a randpay spend,
    # and a name_update that moves the name output along.
    stake = TxSpec(vin=[(t1id, 0)], vout=[EMPTY, Out("61", C_)])
    randpay = TxSpec(vin=[(RANDPAY_TXID.hex(), 0), (t1id, 1)], vout=[Out("39.98", A_)])
    update = TxSpec(
        vin=[(t2id, 0)],
        vout=[Out("0.01", C_, script=name_script(2, NAME, b"A=5.6.7.8", 10, C_), type="name_pubkeyhash")],
        version=NAME_TX,
    )
    c.add_block(
        [TxSpec(vin=[], vout=[EMPTY], coinbase=True), stake, randpay, update], pos=True, mint="1"
    )
    return c


def fork_chain(base: ChainBuilder) -> ChainBuilder:
    """Replaces blocks 2.. with a longer branch that spends A's coins differently."""
    f = base.fork("b", keep=2)
    b0 = f.blocks[0]["tx"][0]["txid"]
    other = TxSpec(
        vin=[(b0, 0)],
        vout=[Out("0.01", D_, script=name_script(1, b"dns:other.emc", b"x", 30, D_), type="name_pubkeyhash"),
              Out("99.99", D_)],
        version=NAME_TX,
    )
    f.add_block([coinbase(M_, "10"), other], mint="10")
    f.add_block([coinbase(M_, "10")], mint="10")
    f.add_block([coinbase(N_, "10")], mint="10")
    return f


SETTINGS = Settings(rpc_url="http://u:p@node/", database_url="", prefetch=4, max_reorg_depth=50)


async def sync(pool, node: FakeNode, **kw) -> None:
    async with RpcClient(SETTINGS.rpc_url, transport=node.transport) as rpc:
        indexer = Indexer(pool, rpc, SETTINGS)
        for _ in range(10):
            try:
                if await indexer.sync_once(**kw):
                    return
            except store.ChainChanged:
                continue
        raise AssertionError("indexer did not settle")


TABLES = {
    "blocks": "height",
    "transactions": "txid",
    "outputs": "txid, n",
    "inputs": "txid, n",
    "address_txs": "address, height, idx",
    "address_stats": "address",
    "name_ops": "txid, n",
    "names": "name",
    "names_dirty": "name",
}


async def snapshot(pool) -> dict:
    async with pool.acquire() as conn:
        return {t: [tuple(r) for r in await conn.fetch(f"SELECT * FROM {t} ORDER BY {o}")]
                for t, o in TABLES.items()}


async def reset(pool) -> None:
    async with pool.acquire() as conn:
        await conn.execute("DROP SCHEMA public CASCADE; CREATE SCHEMA public;")
        await init_schema(conn)


async def test_index_main_chain(pool):
    await sync(pool, FakeNode(main_chain()))
    async with pool.acquire() as conn:
        tip = await store.get_tip(conn)
        assert tip.height == 3
        supplies = [r["supply"] for r in await conn.fetch("SELECT supply FROM blocks ORDER BY height")]
        # fees are burned: 0.01 (t1) + 0.01 (name_new) in block 2, 0.01 (randpay) in block 3
        assert supplies == [100_000000, 200_000000, 209_980000, 210_970000]
        fees = [r["fees"] for r in await conn.fetch("SELECT fees FROM blocks ORDER BY height")]
        assert fees == [0, 0, 20000, 10000]

        balances = {r["address"]: r["balance"] for r in await conn.fetch("SELECT * FROM address_stats")}
        assert balances == {A_: 39_980000, B_: 99_980000, C_: 61_010000, M_: 10_000000}
        utxo_total = await conn.fetchval("SELECT sum(value) FROM outputs WHERE spent_txid IS NULL")
        assert utxo_total == tip.supply

        stake = await conn.fetchrow("SELECT * FROM transactions WHERE height = 3 AND idx = 1")
        assert stake["is_coinstake"] and stake["fee"] == 0
        rp = await conn.fetchrow("SELECT * FROM inputs WHERE is_randpay")
        assert rp["value"] == 0 and rp["prev_txid"] == RANDPAY_TXID

        ops = await conn.fetch("SELECT op, days FROM name_ops ORDER BY height")
        assert [(r["op"], r["days"]) for r in ops] == [(1, 365), (2, 10)]
        name = await conn.fetchrow("SELECT * FROM names WHERE name = $1", NAME)
        assert name["value"] == b"A=5.6.7.8"
        assert name["expires_at"] == 2 + 175 * 375
        assert name["namespace"] == "dns" and not name["deleted"]
        assert await conn.fetchval("SELECT count(*) FROM names_dirty") == 0


async def test_reorg_equals_fresh_index(pool):
    base = main_chain()
    node = FakeNode(base)
    await sync(pool, node)
    node.chain = fork_chain(base)
    await sync(pool, node)
    after_reorg = await snapshot(pool)

    await reset(pool)
    await sync(pool, FakeNode(fork_chain(main_chain())))
    fresh = await snapshot(pool)

    assert after_reorg == fresh
    names = {r[0] for r in fresh["names"]}
    assert names == {b"dns:other.emc"}  # dns:example.emc only existed on the abandoned branch


@pytest.mark.parametrize(
    "method, calls, exc, tip_after_error",
    [
        # block 2 of the old branch is written; block 3 comes from the new
        # branch and does not extend it
        ("getblock", 1, store.PrevHashMismatch, 2),
        # the hash of block 2 is read from the old branch (call 1 is the tip
        # check), then the node drops that block
        ("getblockhash", 2, store.ChainChanged, 1),
    ],
)
async def test_chain_switch_during_sync(pool, method, calls, exc, tip_after_error):
    base = main_chain()
    node = FakeNode(base)
    settings = Settings(rpc_url=SETTINGS.rpc_url, database_url="", prefetch=1)
    await sync(pool, node, stop_height=1)
    node.switch_after(method, calls, fork_chain(base))
    async with RpcClient(SETTINGS.rpc_url, transport=node.transport) as rpc:
        indexer = Indexer(pool, rpc, settings)
        with pytest.raises(store.ChainChanged) as info:
            await indexer.sync_once()
        assert type(info.value) is exc
        async with pool.acquire() as conn:
            assert (await store.get_tip(conn)).hash == bytes.fromhex(base.blocks[tip_after_error]["hash"])
        while not await indexer.sync_once():
            pass
    switched = await snapshot(pool)

    await reset(pool)
    await sync(pool, FakeNode(fork_chain(main_chain())))
    assert switched == await snapshot(pool)


async def test_reorg_depth_limit(pool):
    base = main_chain()
    node = FakeNode(base)
    await sync(pool, node)
    node.chain = ChainBuilder("z")
    for _ in range(5):
        node.chain.add_block([coinbase(Z_, "1")])
    shallow = Settings(rpc_url=SETTINGS.rpc_url, database_url="", max_reorg_depth=2)
    async with RpcClient(SETTINGS.rpc_url, transport=node.transport) as rpc:
        with pytest.raises(Exception, match="no common block"):
            await Indexer(pool, rpc, shallow).sync_once()


async def test_unusual_names(pool):
    """Names seen on mainnet that once broke the names table.

    b"dns:test\\x00": valid UTF-8, but PostgreSQL TEXT cannot hold NUL, so it is
    stored without name_text. "dns:☼.coin": the node only finds it when the
    request carries raw UTF-8 (see RpcClient._post); an escaped "\\u263c" is read
    as "<" and returns the state of "dns:<.coin" instead.
    """
    c = ChainBuilder("n")
    c.add_block([coinbase(A_, "100")], mint="100")
    nul, sun, lt = b"dns:test\x00", "dns:☼.coin".encode(), b"dns:<.coin"
    t = TxSpec(
        vin=[(c.blocks[0]["tx"][0]["txid"], 0)],
        vout=[Out("0.01", C_, script=name_script(1, nul, b"v", 30, C_), type="name_pubkeyhash"),
              Out("0.01", C_, script=name_script(1, sun, b"sun", 30, C_), type="name_pubkeyhash"),
              Out("0.01", D_, script=name_script(1, lt, b"lt", 30, D_), type="name_pubkeyhash"),
              Out("99.96", A_)],
        version=NAME_TX,
    )
    c.add_block([coinbase(M_, "10"), t], mint="10")
    await sync(pool, FakeNode(c))
    async with pool.acquire() as conn:
        row = await conn.fetchrow("SELECT * FROM names WHERE name = $1", nul)
        assert row["name_text"] is None and row["namespace"] == "dns" and row["value"] == b"v"
        row = await conn.fetchrow("SELECT * FROM names WHERE name = $1", sun)
        assert row["name_text"] == "dns:☼.coin" and row["value"] == b"sun"
        assert row["address"] == C_
        assert await conn.fetchval("SELECT count(*) FROM names_dirty") == 0


@pytest.fixture
async def api(pool):
    await sync(pool, FakeNode(main_chain()))
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        yield c


async def test_api_block_and_tx(api):
    status = (await api.get("/api/v1/status")).json()
    assert status["indexed_height"] == 3 and status["node_height"] == 3

    b = (await api.get("/api/v1/blocks/2")).json()
    assert b["tx_count"] == 3 and b["fees"] == "0.020000" and b["proof"] == "pow"
    assert (await api.get(f"/api/v1/blocks/{b['hash']}")).json()["height"] == 2
    assert [x["height"] for x in (await api.get("/api/v1/blocks?limit=2")).json()] == [3, 2]

    name_tx = b["tx"][2]["txid"]
    tx = (await api.get(f"/api/v1/txs/{name_tx}")).json()
    assert tx["fee"] == "0.010000" and tx["confirmations"] == 2
    assert tx["vin"][0]["address"] == B_ and tx["vin"][0]["value"] == "100.000000"
    assert tx["vout"][0]["spent_by"]["height"] == 3
    assert tx["name_ops"] == [{
        "vout": 0, "op": "name_new", "name": {"text": "dns:example.emc"},
        "value": {"text": "A=1.2.3.4"}, "days": 365, "address": C_,
    }]


async def test_api_address(api):
    a = (await api.get(f"/api/v1/addresses/{C_}")).json()
    assert a["balance"] == "61.010000" and a["tx_count"] == 4
    page1 = (await api.get(f"/api/v1/addresses/{C_}/txs?limit=3")).json()
    assert len(page1["items"]) == 3 and page1["next"]
    page2 = (await api.get(f"/api/v1/addresses/{C_}/txs?limit=3&before={page1['next']}")).json()
    assert len(page2["items"]) == 1 and page2["next"] is None
    assert sum(float(i["delta"]) for i in page1["items"] + page2["items"]) == pytest.approx(61.01)
    utxo = (await api.get(f"/api/v1/addresses/{C_}/utxo")).json()
    assert sorted(u["value"] for u in utxo) == ["0.010000", "61.000000"]
    rich = (await api.get("/api/v1/richlist?limit=1")).json()
    assert rich == [{"address": B_, "balance": "99.980000"}]


async def test_api_names(api):
    n = (await api.get("/api/v1/names/dns:example.emc")).json()
    assert n["value"] == {"text": "A=5.6.7.8"} and n["active"] and n["expires_in"] == 2 + 175 * 375 - 3
    hist = (await api.get("/api/v1/names/dns:example.emc/history")).json()
    assert [h["op"] for h in hist] == ["name_update", "name_new"]
    listing = (await api.get("/api/v1/names?prefix=dns:")).json()
    assert [i["name"]["text"] for i in listing["items"]] == ["dns:example.emc"]
    assert (await api.get("/api/v1/names?prefix=dns%25")).json()["items"] == []  # % is literal
    assert (await api.get("/api/v1/names/dns:missing")).status_code == 404


async def test_api_search_and_validation(api):
    b = (await api.get("/api/v1/blocks/1")).json()
    assert (await api.get("/api/v1/search?q=1")).json() == {"type": "block", "id": "1"}
    assert (await api.get(f"/api/v1/search?q={b['hash']}")).json()["type"] == "block"
    assert (await api.get("/api/v1/search?q=dns:example.emc")).json()["type"] == "name"
    assert (await api.get("/api/v1/search?q=nothing-here")).status_code == 404
    assert (await api.get("/api/v1/txs/' OR 1=1 --")).status_code == 400
    assert (await api.get(f"/api/v1/addresses/{A_}'--")).status_code == 400
    assert (await api.get(f"/api/v1/addresses/{C_}/txs?before=x")).status_code == 400
