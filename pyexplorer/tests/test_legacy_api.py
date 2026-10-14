"""The PHP-compatible /api/... routes (emcexplorer.api.legacy)."""

import hashlib

import httpx
import pytest

from emcexplorer.api import legacy
from emcexplorer.api.app import create_app
from fakenode import FakeNode
from test_indexer import A_, B_, C_, main_chain, sync

B58 = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"


def b58check(version: int, payload: bytes) -> str:
    raw = bytes([version]) + payload
    raw += hashlib.sha256(hashlib.sha256(raw).digest()).digest()[:4]
    n, out = int.from_bytes(raw, "big"), ""
    while n:
        n, r = divmod(n, 58)
        out = B58[r] + out
    return "1" * (len(raw) - len(raw.lstrip(b"\0"))) + out


def bech32(hrp: str, version: int, prog: bytes) -> str:
    bits = "".join(f"{b:08b}" for b in prog)
    bits += "0" * (-len(bits) % 5)
    data = [version] + [int(bits[i:i + 5], 2) for i in range(0, len(bits), 5)]
    const = 1 if version == 0 else 0x2BC830A3
    values = [ord(c) >> 5 for c in hrp] + [0] + [ord(c) & 31 for c in hrp] + data
    mod = legacy._bech32_polymod(values + [0] * 6) ^ const
    data += [(mod >> 5 * (5 - i)) & 31 for i in range(6)]
    return hrp + "1" + "".join(legacy.BECH32[d] for d in data)


def test_address_validation():
    good = b58check(33, bytes(range(20)))
    assert good.startswith("E") and legacy.is_valid_address(good)
    assert legacy.is_valid_address(b58check(92, bytes(20)))  # script hash, 'e...'
    assert not legacy.is_valid_address(b58check(0, bytes(20)))  # bitcoin prefix
    assert not legacy.is_valid_address(good[:-1] + ("1" if good[-1] != "1" else "2"))  # checksum
    assert not legacy.is_valid_address("E" + "0" * 33)  # '0' is not base58
    assert legacy.is_valid_address(bech32("em", 0, bytes(20)))
    assert legacy.is_valid_address(bech32("em", 1, bytes(32)))
    assert not legacy.is_valid_address(bech32("bc", 0, bytes(20)))
    assert not legacy.is_valid_address(bech32("em", 0, bytes(21)))  # v0 needs 20 or 32 bytes


@pytest.fixture
async def api(pool):
    await sync(pool, FakeNode(main_chain()))
    app = create_app(pool=pool)
    async with httpx.AsyncClient(transport=httpx.ASGITransport(app=app), base_url="http://t") as c:
        yield c


async def test_legacy_blocks(api):
    latest = (await api.get("/api/block/latest/less")).json()
    assert latest["height"] == "3" and latest["nextblockhash"] == "" and "transactions" not in latest

    b = (await api.get("/api/block/height/2")).json()
    assert b["numtx"] == "3" and b["numvin"] == "3" and b["numvout"] == "5"
    assert b["valuein"] == "200" and b["fee"] == "0.02" and b["mint"] == "10"
    assert b["nextblockhash"] == latest["blockhash"] and b["coindaysdestroyed"] is None
    assert [t["numvout"] for t in b["transactions"]] == ["1", "2", "2"]
    assert "vin" not in b["transactions"][0]  # 'reduce' leaves inputs and outputs out

    full = (await api.get(f"/api/block/hash/{b['blockhash']}/full")).json()
    t1 = full["transactions"][1]
    assert t1["vin"][0]["address"] == A_ and t1["vin"][0]["value"] == "100"
    assert t1["vout"][0] == {
        "value": "60", "n": "0", "asm": None, "hex": t1["vout"][0]["hex"], "reqsigs": None,
        "type": "pubkeyhash", "address": C_,
    }
    assert (await api.get("/api/block/latest/full")).json()["height"] == "3"
    assert (await api.get("/api/block/latest")).json()["transactions"]

    assert (await api.get("/api/block/height/99")).json() == {"error": "Could not decode hash"}
    assert (await api.get("/api/block/hash/zz")).json() == {"error": "Could not decode hash"}
    assert (await api.get("/api/block/nonsense/1")).json() == {"error": "Unknown API call"}


async def test_legacy_tx(api):
    b = (await api.get("/api/block/height/2")).json()
    txid = b["transactions"][2]["tx_hash"]
    less = (await api.get(f"/api/tx/hash/{txid}/less")).json()
    assert less["fee"] == "0.01" and less["valuein"] == "100" and "vin" not in less
    reduce = (await api.get(f"/api/tx/hash/{txid}")).json()
    assert reduce["vin"] == [{"address": B_, "value": "100", "coindaysdestroyed": None,
                              "avgcoindaysdestroyed": None}]
    assert reduce["vout"] == [{"value": "0.01", "address": C_}, {"value": "99.98", "address": B_}]
    full = (await api.get(f"/api/tx/hash/{txid}/full")).json()
    assert full["vin"][0]["output_txid"] and full["vin"][0]["vout"] == "0"
    assert (await api.get("/api/tx/hash/" + "0" * 64)).json() == {"error": "Could not decode hash"}
    assert (await api.get("/api/tx/hash/' OR 1=1 --")).json() == {"error": "Could not decode hash"}


async def test_legacy_address_and_stats(api):
    less = (await api.get(f"/api/address/balance/{C_}/less")).json()
    assert less == {"address": C_, "balance": "61.01"}
    a = (await api.get(f"/api/address/balance/{C_}")).json()
    # C receives in t1, the name tx, the coinstake and the name update; spends in the last two
    assert a["count_received"] == "4" and a["count_sent"] == "2" and a["total_sent"] == "60.01"
    assert a["last_sent"] and "transactions" not in a
    full = (await api.get(f"/api/address/balance/{C_}/full")).json()
    assert len(full["transactions"]) == 4
    assert [t["sent"] for t in full["transactions"]] == ["0.01", "60", "", ""]
    assert (await api.get(f"/api/address/balance/{C_}x")).json() == {"error": "Could not decode hash"}

    r = await api.get("/api/address/isvalid/" + b58check(33, bytes(20)))
    assert r.text == "1"
    assert (await api.get(f"/api/address/isvalid/{A_}")).text == ""

    assert (await api.get("/api/stats/block_height")).json() == {"block_height": "3"}
    assert (await api.get("/api/stats/coin_supply")).json() == {"coin_supply": 210.97}  # fees are burned
    assert (await api.get("/api/stats/coin_supply/txt")).text == "210.97"
    assert (await api.get("/api/stats/known_addresses/30days")).json() == {"error": "Unknown API call"}


async def test_legacy_help_and_routing(api):
    help_ = await api.get("/api/help")
    assert help_.headers["content-type"] == "application/json"
    assert help_.text.startswith('{\n    "version"')  # pretty-printed like PHP
    assert "/api/stats/coin_supply" in help_.json()["commands"]
    assert (await api.get("/api/whatever")).json() == {"error": "Unknown API call"}
    assert (await api.get("/api/v1/nothing")).status_code == 404
    assert (await api.get("/api/v1/status")).json()["indexed_height"] == 3
