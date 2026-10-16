"""Names listing order and status, invisible characters, block ages, spent/unspent colours."""

import datetime as dt
import re

import httpx
import pytest

from emcexplorer.api.app import create_app
from emcexplorer.web.pages import _age, _visible
from fakenode import ChainBuilder, FakeNode, Out, TxSpec, name_script
from test_indexer import A_, B_, NAME_TX, coinbase, sync

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
    # input: the name output it spends; output: where the name now lives
    assert re.search(r'name: <a class="neg" href="/name/dns%3Afoo">', page)
    assert re.search(r'name: <a class="pos" href="/name/dns%3Afoo">', page)
    assert '<span class="small pos">unspent</span>' in page
    tx = (await api.get(f"/api/v1/txs/{later}")).json()
    assert [o.get("name_state") for o in tx["vout"]] == [None if o["name"] is None else "active"
                                                          for o in tx["vout"]]

    blocks = (await api.get("/blocks")).text
    assert ">Age</th>" in blocks
    assert re.search(r'class="nowrap mono" title="[^"]+ UTC">\d+ d \d\d:\d\d<', blocks)


def test_visible_and_age():
    assert _visible("a\x01\x7f\u202eb\n\t", keep="\n") == "a\u2401\u2421\ufffdb\n\u2409"
    now = int(dt.datetime.now(dt.UTC).timestamp())
    assert _age(now - 65) in ("01:05", "01:06")
    assert _age(now - 3 * 3600 - 7) in ("3:00:07", "3:00:08")
    assert _age(now - 2 * 86400 - 3600) == "2 d 01:00"
