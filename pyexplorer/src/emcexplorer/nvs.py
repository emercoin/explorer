"""Decoding of Emercoin NVS (name-value storage) output scripts.

Port of DecodeNameScript() from emercoin src/script/script.cpp. Script layout:

    name_new|name_update: <op> OP_DROP <name> <days> OP_2DROP <v1> .. <vn> <n drops> <address script>
    name_delete:          <op> OP_DROP <name> OP_DROP <address script>

where <op> is OP_1 (new), OP_2 (update) or OP_3 (delete), and the value pushes
are followed by OP_DROP/OP_2DROP opcodes that drop exactly n items.

Only transactions with version NAMECOIN_TX_VERSION carry name operations.

The decoder answers "what name operation does this output encode". Whether the
node accepted the operation into its name index (expiry, duplicate name_new in
one block, ...) is the node's business; the indexer reads the resulting name
state back with `name_show` instead of re-implementing those rules.
"""

from __future__ import annotations

from dataclasses import dataclass

NAMECOIN_TX_VERSION = 0x0666
MAX_NAME_LENGTH = 512
MAX_VALUE_LENGTH = 20 * 1024
MAX_RENTAL_DAYS = 366_000_000
# CalculateExpiresAt(): "175 is average number of blocks per day"
BLOCKS_PER_DAY = 175

OP_NAME_NEW = 1
OP_NAME_UPDATE = 2
OP_NAME_DELETE = 3
OP_NAMES = {OP_NAME_NEW: "name_new", OP_NAME_UPDATE: "name_update", OP_NAME_DELETE: "name_delete"}

_OP_PUSHDATA1 = 0x4C
_OP_PUSHDATA2 = 0x4D
_OP_PUSHDATA4 = 0x4E
_OP_1 = 0x51
_OP_16 = 0x60
_OP_2DROP = 0x6D
_OP_DROP = 0x75


@dataclass(frozen=True)
class NameOp:
    op: int
    name: bytes
    value: bytes  # empty for name_delete
    days: int  # rental days added; -1 for name_delete

    @property
    def op_name(self) -> str:
        return OP_NAMES[self.op]


class _Reader:
    """Walks a script the way CScript::GetOp does."""

    def __init__(self, script: bytes):
        self.s = script
        self.pc = 0

    def get_op(self) -> tuple[int, bytes] | None:
        s, pc = self.s, self.pc
        if pc >= len(s):
            return None
        opcode = s[pc]
        pc += 1
        data = b""
        if opcode <= _OP_PUSHDATA4:
            if opcode < _OP_PUSHDATA1:
                size = opcode
            else:
                width = {_OP_PUSHDATA1: 1, _OP_PUSHDATA2: 2, _OP_PUSHDATA4: 4}[opcode]
                if len(s) - pc < width:
                    return None
                size = int.from_bytes(s[pc : pc + width], "little")
                pc += width
            if len(s) - pc < size:
                return None
            data = s[pc : pc + size]
            pc += size
        self.pc = pc
        return opcode, data


def _is_push(opcode: int) -> bool:
    return 0 <= opcode <= _OP_PUSHDATA4


def _script_num(data: bytes) -> int | None:
    """CScriptNum(vch, fRequireMinimal=false) with the default 4-byte limit."""
    if len(data) > 4:
        return None
    if not data:
        return 0
    value = int.from_bytes(data, "little")
    if data[-1] & 0x80:
        return -(value & ~(0x80 << (8 * (len(data) - 1))))
    return value


def decode_name_script(script: bytes) -> NameOp | None:
    r = _Reader(script)

    item = r.get_op()
    if item is None or not (_OP_1 <= item[0] <= _OP_16):
        return None
    op = item[0] - _OP_1 + 1
    if op not in OP_NAMES:
        return None

    item = r.get_op()
    if item is None or item[0] != _OP_DROP:
        return None

    item = r.get_op()
    if item is None or not _is_push(item[0]):
        return None
    name = item[1]

    if op == OP_NAME_DELETE:
        item = r.get_op()
        if item is None or item[0] != _OP_DROP:
            return None
        return NameOp(op, name, b"", -1)

    item = r.get_op()
    if item is None or not _is_push(item[0]):
        return None
    days = _script_num(item[1])
    if days is None:
        return None

    item = r.get_op()
    if item is None or item[0] != _OP_2DROP:
        return None

    value = bytearray()
    pushes = 0
    while True:
        item = r.get_op()
        if item is None:
            return None
        if item[0] in (_OP_DROP, _OP_2DROP):
            break
        if not _is_push(item[0]):
            return None
        value += item[1]
        pushes += 1

    dropped = 2 if item[0] == _OP_2DROP else 1
    while True:
        item = r.get_op()
        if item is None or item[0] not in (_OP_DROP, _OP_2DROP):
            break
        dropped += 2 if item[0] == _OP_2DROP else 1
    if pushes != dropped:
        return None

    # checkNameValues()
    if len(name) > MAX_NAME_LENGTH or len(value) > MAX_VALUE_LENGTH:
        return None
    if op == OP_NAME_NEW and days < 1:
        return None
    if op == OP_NAME_UPDATE and days < 0:
        return None
    if days > MAX_RENTAL_DAYS:
        return None
    return NameOp(op, name, bytes(value), days)


def namespace_of(name: bytes) -> str | None:
    """'dns:example.emc' -> 'dns'. Names without a colon have no namespace."""
    head, sep, _ = name.partition(b":")
    if not sep or b"\x00" in head:
        return None
    return head.decode("utf-8", "replace")


def as_text(data: bytes) -> str | None:
    try:
        return data.decode("utf-8")
    except UnicodeDecodeError:
        return None


def as_db_text(data: bytes) -> str | None:
    """Like as_text, but None for NUL bytes too: PostgreSQL TEXT cannot hold them.

    Mainnet has such names (b"dns:test\\x00", b"\\x00"); they are valid UTF-8 and
    name_show answers for them, they just cannot go into names.name_text.
    """
    if b"\x00" in data:
        return None
    return as_text(data)
