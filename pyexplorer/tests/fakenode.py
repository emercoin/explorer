"""A tiny in-memory stand-in for emercoind's JSON-RPC, for tests.

Blocks are built with ChainBuilder and served through httpx.MockTransport, so
the real RpcClient (including Decimal parsing of amounts) is exercised.
"""

from __future__ import annotations

import base64
import hashlib
import json
import re
from dataclasses import dataclass, field
from decimal import Decimal
from typing import Any

import httpx

from emcexplorer.nvs import BLOCKS_PER_DAY, NAMECOIN_TX_VERSION, decode_name_script

OP_DROP, OP_2DROP = 0x75, 0x6D


def h(seed: str) -> str:
    return hashlib.sha256(seed.encode()).hexdigest()


def push(data: bytes) -> bytes:
    if len(data) < 0x4C:
        return bytes([len(data)]) + data
    if len(data) <= 0xFF:
        return bytes([0x4C, len(data)]) + data
    return bytes([0x4D]) + len(data).to_bytes(2, "little") + data


def script_num(n: int) -> bytes:
    if n == 0:
        return b""
    out = bytearray()
    neg, v = n < 0, abs(n)
    while v:
        out.append(v & 0xFF)
        v >>= 8
    if out[-1] & 0x80:
        out.append(0x80 if neg else 0)
    elif neg:
        out[-1] |= 0x80
    return bytes(out)


def p2pkh(addr: str) -> bytes:
    return bytes([0x76, 0xA9, 0x14]) + hashlib.sha256(addr.encode()).digest()[:20] + bytes([0x88, 0xAC])


def name_script(op: int, name: bytes, value: bytes = b"", days: int = 0, addr: str = "x",
                value_chunks: int = 1) -> bytes:
    s = bytes([0x50 + op, OP_DROP]) + push(name)
    if op == 3:
        return s + bytes([OP_DROP]) + p2pkh(addr)
    s += push(script_num(days)) + bytes([OP_2DROP])
    size = max(1, -(-len(value) // value_chunks))
    chunks = [value[i : i + size] for i in range(0, len(value), size)] or [b""]
    for c in chunks:
        s += push(c)
    drops = len(chunks)
    s += bytes([OP_2DROP]) * (drops // 2) + bytes([OP_DROP]) * (drops % 2)
    return s + p2pkh(addr)


@dataclass
class Out:
    value: str  # decimal string, e.g. "1.5"
    addr: str | None
    script: bytes | None = None  # defaults to p2pkh(addr)
    type: str = "pubkeyhash"


@dataclass
class TxSpec:
    vin: list[tuple[str, int]]  # (txid hex, n); empty list = coinbase
    vout: list[Out]
    version: int = 1
    txid: str = ""
    coinbase: bool = False


def render_tx(t: TxSpec, seed: str, time: int) -> dict[str, Any]:
    """The node's JSON for a transaction (as in getblock 2 / getrawtransaction 1)."""
    txid = t.txid or h(seed)
    t.txid = txid
    vin = (
        [{"coinbase": "0400", "sequence": 4294967295}]
        if t.coinbase
        else [{"txid": a, "vout": n, "scriptSig": {"asm": "", "hex": ""}, "sequence": 4294967295}
              for a, n in t.vin]
    )
    vout = []
    for n, o in enumerate(t.vout):
        script = o.script if o.script is not None else (p2pkh(o.addr) if o.addr else b"")
        spk: dict[str, Any] = {"asm": "", "hex": script.hex(), "type": o.type}
        if o.addr:
            spk["addresses"] = [o.addr]
        vout.append({"value": Decimal(o.value).quantize(Decimal("0.000001")), "n": n,
                     "scriptPubKey": spk})
    return {"txid": txid, "hash": txid, "version": t.version, "size": 200,
            "locktime": 0, "time": time, "vin": vin, "vout": vout}


@dataclass
class ChainBuilder:
    tag: str
    blocks: list[dict[str, Any]] = field(default_factory=list)

    def add_block(
        self, txs: list[TxSpec], *, pos: bool = False, mint: str = "0", time: int | None = None
    ) -> dict[str, Any]:
        height = len(self.blocks)
        btime = 1_500_000_000 + height * 600 if time is None else time
        prev = self.blocks[-1]["hash"] if self.blocks else None
        bhash = h(f"{self.tag}-block-{height}")
        rendered = [render_tx(t, f"{self.tag}-{height}-{i}", btime) for i, t in enumerate(txs)]
        block = {
            "hash": bhash, "confirmations": 1, "size": 1000, "height": height, "version": 1,
            "merkleroot": h(f"{self.tag}-mr-{height}"), "tx": rendered,
            "time": btime, "mediantime": btime,
            "nonce": 0, "bits": "1d00ffff", "difficulty": Decimal("1.5"),
            "mint": Decimal(mint).quantize(Decimal("0.000001")),
            "flags": "proof-of-stake" if pos else "proof-of-work",
            "proofhash": bhash, "entropybit": 0, "modifier": "0000000000000000",
        }
        if prev:
            block["previousblockhash"] = prev
        self.blocks.append(block)
        return block

    def fork(self, tag: str, keep: int) -> "ChainBuilder":
        """New chain sharing the first `keep` blocks."""
        return ChainBuilder(tag, [dict(b) for b in self.blocks[:keep]])


def _dumps(obj: Any, ensure_ascii: bool = True) -> str:
    def enc(o: Any) -> str:
        if isinstance(o, Decimal):
            return f"@@{o}@@"
        raise TypeError(o)

    return re.sub(r'"@@(-?[0-9.]+)@@"', r"\1", json.dumps(obj, default=enc, ensure_ascii=ensure_ascii))


class FakeNode:
    def __init__(self, chain: ChainBuilder):
        self.chain = chain
        # txid -> (rendered tx, fee): what getrawmempool / getrawtransaction serve
        self.mempool: dict[str, tuple[dict[str, Any], str]] = {}
        self._switch: tuple[str, int, ChainBuilder] | None = None
        self._calls = 0

    def add_to_mempool(self, t: TxSpec, fee: str, time: int) -> str:
        raw = render_tx(t, f"pool-{len(self.mempool)}", time)
        self.mempool[raw["txid"]] = (raw, fee)
        return raw["txid"]

    def switch_after(self, method: str, n: int, chain: ChainBuilder) -> None:
        """Serve n more `method` calls from the current chain, then switch to `chain`."""
        self._calls = 0
        self._switch = (method, n, chain)

    def _count(self, method: str) -> None:
        if self._switch and self._switch[0] == method:
            self._calls += 1
            if self._calls == self._switch[1]:
                self.chain = self._switch[2]
                self._switch = None

    @property
    def transport(self) -> httpx.MockTransport:
        return httpx.MockTransport(self._handle)

    def _by_hash(self) -> dict[str, dict[str, Any]]:
        return {b["hash"]: b for b in self.chain.blocks}

    def _name_show(self, name: str) -> dict[str, Any]:
        """Name state with the node's rules simplified for test chains."""
        state: dict[str, Any] | None = None
        for b in self.chain.blocks:
            for tx in b["tx"]:
                if tx["version"] != NAMECOIN_TX_VERSION:
                    continue
                for out in tx["vout"]:
                    op = decode_name_script(bytes.fromhex(out["scriptPubKey"]["hex"]))
                    if op is None or op.name != name.encode():
                        continue
                    if op.op == 1:
                        state = {"start": b["height"], "days": op.days}
                    elif op.op == 2:
                        state["days"] += op.days
                    state.update(op=op.op, value=op.value, txid=tx["txid"], vout=out["n"],
                                 address=out["scriptPubKey"].get("addresses", [""])[0],
                                 time=tx["time"])
        if state is None:
            raise _RpcFail(-4, "failed to read from name DB")
        deleted = state["op"] == 3
        res = {
            "name": name, "value": base64.b64encode(state["value"]).decode(),
            "txid": state["txid"], "address": state["address"], "vout": state["vout"],
            "expires_at": 0 if deleted else state["start"] + BLOCKS_PER_DAY * state["days"],
            "time": state["time"],
        }
        if deleted:
            res["deleted"] = True
        return res

    def _call(self, method: str, params: list[Any]) -> Any:
        blocks = self.chain.blocks
        if method == "getblockcount":
            return len(blocks) - 1
        if method == "getblockhash":
            if params[0] >= len(blocks):
                raise _RpcFail(-8, "Block height out of range")
            self._count(method)
            return blocks[params[0]]["hash"]
        if method == "getblock":
            block = self._by_hash().get(params[0])
            if block is None:
                # the hash belongs to the chain we switched away from
                raise _RpcFail(-5, "Block not found")
            self._count(method)
            return block
        if method == "name_show":
            return self._name_show(params[0])
        if method == "getrawmempool":
            assert params == [True], "only the verbose form is served"
            return {
                txid: {"size": raw["size"], "vsize": raw["size"], "fee": Decimal(fee),
                       "fees": {"base": Decimal(fee)}, "time": raw["time"],
                       "depends": [i["txid"] for i in raw["vin"] if i.get("txid") in self.mempool]}
                for txid, (raw, fee) in self.mempool.items()
            }
        if method == "getrawtransaction":
            if params[0] in self.mempool:
                return self.mempool[params[0]][0]
            for b in blocks:
                for tx in b["tx"]:
                    if tx["txid"] == params[0]:
                        return tx
            raise _RpcFail(-5, "No such mempool or blockchain transaction")
        raise _RpcFail(-32601, f"Method not found: {method}")

    def _one(self, req: dict[str, Any]) -> dict[str, Any]:
        try:
            return {"result": self._call(req["method"], req["params"]), "error": None, "id": req["id"]}
        except _RpcFail as e:
            return {"result": None, "error": {"code": e.code, "message": e.message}, "id": req["id"]}

    def _handle(self, request: httpx.Request) -> httpx.Response:
        # Like emercoind 0.8.5, keep only the low byte of a \uXXXX escape
        # ("\u263c" -> "<"); raw UTF-8 is parsed fine.
        body = json.loads(
            _NON_ASCII_ESCAPE.sub(lambda m: b"\\u00" + m[1][-2:], request.content)
        )
        if isinstance(body, list):
            # ...and its batch replies escape each byte >= 0x80 on its own:
            # "ü" (c3 bc) goes out as "\u00C3\u00BC".
            raw = _dumps([self._one(r) for r in body], ensure_ascii=False).encode()
            raw = re.sub(rb"[\x80-\xff]", lambda m: b"\\u00%02X" % m[0][0], raw)
            return httpx.Response(200, content=raw)
        resp = self._one(body)
        return httpx.Response(500 if resp["error"] else 200, text=_dumps(resp))


_NON_ASCII_ESCAPE = re.compile(rb"\\u(?!00[0-7])([0-9a-fA-F]{4})")


class _RpcFail(Exception):
    def __init__(self, code: int, message: str):
        self.code, self.message = code, message
