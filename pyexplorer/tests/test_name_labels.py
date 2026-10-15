"""Since 0.8 a name lives on an output, and one transaction can move several names."""

import httpx
import pytest

from emcexplorer.api.app import create_app
from emcexplorer.rpc import RpcClient
from fakenode import ChainBuilder, FakeNode, Out, TxSpec, name_script
from test_indexer import A_, B_, C_, NAME_TX, SETTINGS, coinbase, sync

FOO, BAR = b"dns:foo.emc", b"dns:bar.emc"


def name_out(op: int, name: bytes, value: bytes, addr: str) -> Out:
    return Out("0.01", addr, script=name_script(op, name, value, 30, addr), type="name_pubkeyhash")


def chain() -> ChainBuilder:
    c = ChainBuilder("nl")
    c.add_block([coinbase(A_, "100")], mint="100")
    a0 = c.blocks[0]["tx"][0]["txid"]
    both = TxSpec(vin=[(a0, 0)], vout=[name_out(1, FOO, b"1", A_), name_out(1, BAR, b"2", A_),
                                       Out("99.97", A_)], version=NAME_TX)
    c.add_block([coinbase(B_, "10"), both], mint="10")
    made = c.blocks[1]["tx"][1]["txid"]
    # one transaction updates both names and hands them to different owners
    move = TxSpec(vin=[(made, 2), (made, 1), (made, 0)],
                  vout=[Out("99.96", A_), name_out(2, FOO, b"3", B_), name_out(2, BAR, b"4", C_)],
                  version=NAME_TX)
    c.add_block([coinbase(B_, "10"), move], mint="10")
    return c


@pytest.fixture
async def node(pool):
    node = FakeNode(chain())
    await sync(pool, node)
    return node


async def test_names_on_inputs_and_outputs(pool, node):
    move = node.chain.blocks[2]["tx"][1]["txid"]
    async with RpcClient(SETTINGS.rpc_url, transport=node.transport) as rpc:
        app = create_app(pool=pool, rpc=rpc)
        async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
            tx = (await c.get(f"/api/v1/txs/{move}")).json()
            assert [i["name"] for i in tx["vin"]] == [None, {"text": "dns:bar.emc"}, {"text": "dns:foo.emc"}]
            assert [o["name"] for o in tx["vout"]] == [None, {"text": "dns:foo.emc"}, {"text": "dns:bar.emc"}]

            page = (await c.get(f"/tx/{move}")).text
            assert page.count('href="/name/dns%3Afoo.emc"') == 3  # input, output, name operations
            assert page.count('href="/name/dns%3Abar.emc"') == 3

            # the same in the memory pool: the input names come from the index
            update = TxSpec(vin=[(move, 1)], vout=[name_out(2, FOO, b"5", B_)], version=NAME_TX)
            pooled = node.add_to_mempool(update, fee="0.01", time=1_700_000_000)
            ptx = (await c.get(f"/api/v1/mempool/{pooled}")).json()
            assert ptx["vin"][0]["name"] == {"text": "dns:foo.emc"}
            assert ptx["vout"][0]["name"] == {"text": "dns:foo.emc"}
