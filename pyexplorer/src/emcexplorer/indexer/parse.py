"""Turns `getblock <hash> 2` JSON into plain dataclasses. No I/O here."""

from __future__ import annotations

from dataclasses import dataclass, field
from decimal import Decimal
from typing import Any

from ..amounts import to_units
from ..nvs import NAMECOIN_TX_VERSION, NameOp, decode_name_script

# Emercoin "randpay" inputs spend a virtual UTXO <ec..ec>:0 worth 0 EMC that
# can be spent any number of times (src/coins.cpp: randpaytx).
RANDPAY_TXID = bytes([0xEC]) * 32


@dataclass
class TxIn:
    n: int
    prev_txid: bytes | None
    prev_n: int | None
    coinbase: bytes | None
    sequence: int

    @property
    def is_randpay(self) -> bool:
        return self.prev_txid == RANDPAY_TXID

    @property
    def spends_output(self) -> bool:
        return self.prev_txid is not None and not self.is_randpay


@dataclass
class TxOut:
    n: int
    value: int
    script_type: str | None
    address: str | None  # the first address; the one credited with the value
    script: bytes
    # bare multisig only: every key's address and how many must sign
    addresses: list[str] | None = None
    required: int | None = None

    @property
    def is_empty(self) -> bool:
        return self.value == 0 and not self.script


@dataclass
class NameOutput:
    n: int
    op: NameOp
    address: str | None


@dataclass
class Tx:
    txid: bytes
    idx: int
    version: int
    time: int | None
    locktime: int
    size: int
    inputs: list[TxIn]
    outputs: list[TxOut]
    name_ops: list[NameOutput] = field(default_factory=list)

    @property
    def is_coinbase(self) -> bool:
        return len(self.inputs) == 1 and self.inputs[0].coinbase is not None

    @property
    def is_coinstake(self) -> bool:
        # ppcoin: the coin stake transaction is marked with the first output empty
        return (
            bool(self.inputs)
            and self.inputs[0].prev_txid is not None
            and len(self.outputs) >= 2
            and self.outputs[0].is_empty
        )


@dataclass
class Block:
    height: int
    hash: bytes
    prev_hash: bytes | None
    time: int
    mediantime: int | None
    version: int
    merkleroot: bytes
    bits: str
    nonce: int
    difficulty: Decimal
    size: int
    flags: str
    proofhash: bytes | None
    modifier: str | None
    is_auxpow: bool
    mint: int
    txs: list[Tx]

    @property
    def is_pos(self) -> bool:
        return "proof-of-stake" in self.flags


def _address(spk: dict[str, Any]) -> str | None:
    if spk.get("address"):
        return spk["address"]
    addrs = spk.get("addresses") or []
    return addrs[0] if addrs else None


def parse_tx(raw: dict[str, Any], idx: int) -> Tx:
    inputs = []
    for n, vin in enumerate(raw["vin"]):
        if "coinbase" in vin:
            inputs.append(TxIn(n, None, None, bytes.fromhex(vin["coinbase"]), vin["sequence"]))
        else:
            inputs.append(
                TxIn(n, bytes.fromhex(vin["txid"]), vin["vout"], None, vin["sequence"])
            )

    outputs = []
    for vout in raw["vout"]:
        spk = vout.get("scriptPubKey", {})
        multisig = spk.get("type") == "multisig"
        outputs.append(
            TxOut(
                n=vout["n"],
                value=to_units(vout["value"]),
                script_type=spk.get("type"),
                address=_address(spk),
                script=bytes.fromhex(spk.get("hex", "")),
                addresses=list(spk.get("addresses") or []) if multisig else None,
                required=spk.get("reqSigs") if multisig else None,
            )
        )

    tx = Tx(
        txid=bytes.fromhex(raw["txid"]),
        idx=idx,
        version=raw["version"],
        time=raw.get("time"),
        locktime=raw["locktime"],
        size=raw["size"],
        inputs=inputs,
        outputs=outputs,
    )
    if tx.version == NAMECOIN_TX_VERSION:
        for out in outputs:
            op = decode_name_script(out.script)
            if op is not None:
                tx.name_ops.append(NameOutput(out.n, op, out.address))
    return tx


def parse_block(raw: dict[str, Any]) -> Block:
    prev = raw.get("previousblockhash")
    return Block(
        height=raw["height"],
        hash=bytes.fromhex(raw["hash"]),
        prev_hash=bytes.fromhex(prev) if prev else None,
        time=raw["time"],
        mediantime=raw.get("mediantime"),
        version=raw["version"],
        merkleroot=bytes.fromhex(raw["merkleroot"]),
        bits=raw["bits"],
        nonce=raw["nonce"],
        difficulty=Decimal(raw["difficulty"]),
        size=raw["size"],
        flags=raw.get("flags", ""),
        proofhash=bytes.fromhex(raw["proofhash"]) if raw.get("proofhash") else None,
        modifier=raw.get("modifier"),
        is_auxpow="auxpow" in raw,
        mint=to_units(raw.get("mint", 0)),
        txs=[parse_tx(t, i) for i, t in enumerate(raw["tx"])],
    )
