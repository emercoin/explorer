"""Names listing order and status, invisible characters, block ages, spent/unspent colours."""

import datetime as dt
import re

import httpx
import pytest

from emcexplorer.api.app import create_app
from emcexplorer.web.pages import _age, _since, _visible
from fakenode import ChainBuilder, FakeNode, Out, TxSpec, name_script
from test_indexer import A_, B_, C_, NAME_TX, RANDPAY_TXID, coinbase, sync

FOO, BAR, OLD, BELL = b"dns:foo", b"dns:bar", b"dns:old", b"dns:\x01bell"


def name_out(op: int, name: bytes, days: int, addr: str = A_) -> Out:
    return Out("0.01", addr, script=name_script(op, name, b"v", days, addr), type="name_pubkeyhash")


def chain() -> ChainBuilder:
    c = ChainBuilder("nu")
    c.add_block([coinbase(A_, "100")], mint="100")
    a0 = c.blocks[0]["tx"][0]["txid"]
    made = TxSpec(vin=[(a0, 0)], version=NAME_TX, vout=[
        name_out(1, FOO, 30), name_out(1, BAR, 30), name_out(1, OLD, 1), Out("99.96", A_)])
    c.add_block([coinbase(B_, "10"), made], mint="10")
    t1 = c.blocks[1]["tx"][1]["txid"]
    later = TxSpec(vin=[(t1, 0), (t1, 3)], version=NAME_TX, vout=[
        name_out(2, FOO, 30, B_), name_out(1, BELL, 30), Out("99.94", A_)])
    c.add_block([coinbase(B_, "10"), later], mint="10")
    c.add_block([coinbase(B_, "10")], mint="10")
    return c


@pytest.fixture
async def api(pool):
    await sync(pool, FakeNode(chain()))
    async with pool.acquire() as conn:  # a test chain is too short to outlive a name
        await conn.execute("UPDATE names SET expires_at = 1 WHERE name = $1", OLD)
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        yield c


def texts(res: dict) -> list[str]:
    return [n["name"]["text"] for n in res["items"]]


async def test_names_newest_first(api):
    page1 = (await api.get("/api/v1/names?include_inactive=true&limit=2")).json()  # newest first by default
    assert texts(page1) == ["dns:foo", "dns:\x01bell"]  # changed in block 2
    page2 = (await api.get("/api/v1/names", params={
        "sort": "new", "include_inactive": "true", "limit": 2, "after": page1["next"]})).json()
    assert texts(page2) == ["dns:old", "dns:bar"]  # block 1
    # A to Z, in byte order
    assert texts((await api.get("/api/v1/names?sort=name&include_inactive=true")).json()) == [
        "dns:\x01bell", "dns:bar", "dns:foo", "dns:old"]
    assert (await api.get("/api/v1/names?sort=new&after=nonsense")).status_code == 400

    old = (await api.get("/api/v1/names/dns:old")).json()
    block1 = chain().blocks[1]["time"]
    assert not old["active"] and old["expired_time"] == block1
    assert (await api.get("/api/v1/names/dns:foo")).json()["expired_time"] is None  # still active


async def test_names_page(api):
    page = (await api.get("/names?inactive=true")).text  # newest first by default
    assert page.index("dns:foo") < page.index("dns:bar")
    assert "dns:\u2401bell" in page and "dns:\x01bell" not in page
    days = round((2 + 175 * 30 - 3) / 175)
    assert f'<td class="small pos">expires in {2 + 175 * 30 - 3:,} blocks (~{days} days)</td>' in page
    expired = dt.datetime.fromtimestamp(chain().blocks[1]["time"], dt.UTC).strftime("%Y-%m-%d")
    assert f'<td class="small neg">expired {expired}</td>' in page
    assert 'href="/names?prefix=&amp;inactive=true&amp;sort=name"' in page

    one = (await api.get("/name/dns%3A%01bell")).text
    assert "<h1 class=\"wrap\">dns:\u2401bell</h1>" in one
    assert '<dd class="neg">expired' in (await api.get("/name/dns:old")).text


async def test_tx_colours_and_block_ages(api):
    later = chain().blocks[2]["tx"][1]["txid"]
    page = (await api.get(f"/tx/{later}")).text
    # input: an earlier output of a name still active; output: where the name now lives
    assert re.search(r'title="dns:foo \(earlier output, the name is active\)">name: <a class="past" '
                     r'href="/name/dns%3Afoo">', page)
    assert re.search(r'name: <a class="pos" href="/name/dns%3Afoo">', page)
    assert '<span class="small pos">unspent</span>' in page
    tx = (await api.get(f"/api/v1/txs/{later}")).json()
    assert [o.get("name_state") for o in tx["vout"]] == [None if o["name"] is None else "active"
                                                          for o in tx["vout"]]
    assert tx["vin"][0]["name_state"] == "past" and tx["kinds"] == ["name"]
    made = chain().blocks[1]["tx"][1]["txid"]
    assert [o["name_state"] for o in (await api.get(f"/api/v1/txs/{made}")).json()["vout"]] == [
        "past", "active", "expired", None]

    blocks = (await api.get("/blocks")).text
    assert ">Age</th>" in blocks
    assert re.search(r'class="nowrap mono" title="[^"]+ UTC">\d+ d \d\d:\d\d<', blocks)


def test_visible_and_age():
    assert _visible("a\x01\x7f\u202eb\r\n\t") == "a\u2401\u2421\ufffdb\u240d\u240a\u2409"
    now = int(dt.datetime.now(dt.UTC).timestamp())
    assert _age(now - 65) in ("01:05", "01:06")
    assert _age(now - 3 * 3600 - 7) in ("3:00:07", "3:00:08")
    assert _age(now - 2 * 86400 - 3600) == "2 d 01:00"
    day = 86400
    assert _since(now - (6 * 365 + 335) * day - 7200) == "6 years 335 days"
    assert _since(now - 365 * day - 100) == "1 year"
    assert _since(now - 12 * day - 5 * 3600 - 59) == "12 days 5 hours"
    assert _since(now - day - 1800) == "1 day"
    assert _since(now - 3 * 3600 - 600) == "3 hours"
    assert _since(now - 125) in ("2 min", "3 min")


async def test_long_names_are_cut_in_lists(pool):
    long_name = b"dns:" + b"x" * 500  # the node allows up to 512 bytes
    c = ChainBuilder("ln")
    c.add_block([coinbase(A_, "100")], mint="100")
    a0 = c.blocks[0]["tx"][0]["txid"]
    c.add_block([coinbase(B_, "10"), TxSpec(vin=[(a0, 0)], version=NAME_TX, vout=[
        name_out(1, long_name, 30), Out("99.98", A_)])], mint="10")
    await sync(pool, FakeNode(c))
    app = create_app(pool=pool)
    full, cut = long_name.decode(), long_name.decode()[:79] + "…"
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as client:
        listing = (await client.get("/names")).text
        assert f">{cut}</a>" in listing and f'title="{full}"' in listing
        tx = (await client.get(f"/tx/{c.blocks[1]['tx'][1]['txid']}")).text
        assert f">{cut}</a>" in tx and f">{full}<" not in tx
        page = (await client.get(f"/name/{full}")).text
        assert f'<h1 class="wrap">{full[:119]}…</h1>' in page
        assert f'<pre class="value">{full}</pre>' in page and f"<title>{full[:59]}… · " in page


async def test_tx_kinds(pool):
    c = ChainBuilder("tk")
    c.add_block([coinbase(A_, "100")], mint="100")
    a0 = c.blocks[0]["tx"][0]["txid"]
    c.add_block([coinbase(B_, "10"), TxSpec(vin=[(a0, 0)], version=NAME_TX, vout=[
        name_out(1, FOO, 30), Out("0", None, script=b"\x6a\x04note", type="nulldata"), Out("99.98", A_)])],
        mint="10")
    t1 = c.blocks[1]["tx"][1]["txid"]
    c.add_block([coinbase(B_, "10"),
                 TxSpec(vin=[(RANDPAY_TXID.hex(), 0), (t1, 2)], vout=[Out("50", C_), Out("49.97", A_)]),
                 TxSpec(vin=[(c.blocks[1]["tx"][0]["txid"], 0)], vout=[Out("9.99", C_)])], mint="10")
    await sync(pool, FakeNode(c))
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as client:
        b1 = (await client.get("/api/v1/blocks/1")).json()
        assert [t["kinds"] for t in b1["tx"]] == [[], ["name", "return"]]
        b2 = (await client.get("/api/v1/blocks/2")).json()
        assert [t["kinds"] for t in b2["tx"]] == [[], ["randpay"], []]
        assert re.findall(r"</a></td>\s*<td>([a-z]+)</td>", (await client.get("/block/2")).text) == [
            "coinbase", "randpay", "payment"]
        assert "<td>name</td>" in (await client.get("/block/1")).text  # the first kind only
        page = (await client.get(f"/tx/{t1}")).text
        assert "<dt>Type</dt><dd>name, return</dd>" in page
        assert (await client.get(f"/api/v1/txs/{t1}")).json()["kinds"] == ["name", "return"]
        payment = b2["tx"][2]["txid"]
        assert "<dt>Type</dt><dd>payment</dd>" in (await client.get(f"/tx/{payment}")).text

        rich = (await client.get("/richlist")).text
        assert ">Last transaction</th>" in rich and 'href="/richlist?sort=recent"' in rich
        recent = (await client.get("/richlist?sort=recent")).text
        # C_ got coins in block 2, A_ (the richest) last moved in block 2 too, B_ in block 1
        assert recent.index(f">{B_}<") > recent.index(f">{C_}<")
        assert (await client.get("/richlist?sort=nonsense")).status_code == 422
