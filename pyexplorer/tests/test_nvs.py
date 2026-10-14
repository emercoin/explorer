import pytest

from emcexplorer.amounts import format_units, to_units
from emcexplorer.nvs import (
    OP_NAME_DELETE,
    OP_NAME_NEW,
    OP_NAME_UPDATE,
    _script_num,
    decode_name_script,
    namespace_of,
)
from fakenode import name_script, p2pkh, push, script_num


def test_name_new():
    op = decode_name_script(name_script(1, b"dns:example.emc", b"A=1.2.3.4", 365))
    assert op is not None
    assert (op.op, op.name, op.value, op.days) == (OP_NAME_NEW, b"dns:example.emc", b"A=1.2.3.4", 365)


def test_name_update_with_value_split_over_pushes():
    value = b"x" * 1500
    op = decode_name_script(name_script(2, b"ssh:host", value, 0, value_chunks=3))
    assert op is not None
    assert (op.op, op.value, op.days) == (OP_NAME_UPDATE, value, 0)


def test_name_delete():
    op = decode_name_script(name_script(3, b"dns:gone.emc"))
    assert op is not None
    assert (op.op, op.name, op.value, op.days) == (OP_NAME_DELETE, b"dns:gone.emc", b"", -1)


def test_plain_script_is_not_a_name():
    assert decode_name_script(p2pkh("addr")) is None
    assert decode_name_script(b"") is None


def test_drop_count_must_match_value_pushes():
    s = bytes([0x51, 0x75]) + push(b"n") + push(script_num(10)) + bytes([0x6D])
    s += push(b"v1") + push(b"v2") + bytes([0x75])  # two pushes, one drop
    assert decode_name_script(s + p2pkh("a")) is None


def test_limits():
    assert decode_name_script(name_script(1, b"n" * 513, b"v", 1)) is None
    assert decode_name_script(name_script(1, b"n", b"v", 0)) is None  # name_new needs >= 1 day
    assert decode_name_script(name_script(2, b"n", b"v", -1)) is None
    assert decode_name_script(name_script(1, b"n", b"v" * (20 * 1024 + 1), 1, value_chunks=100)) is None


def test_truncated_script():
    s = name_script(1, b"dns:a", b"v", 1)
    assert decode_name_script(s[:6]) is None


@pytest.mark.parametrize("n", [0, 1, 127, 128, 255, 365, 65535, -1, -128, 2**31 - 1])
def test_script_num_roundtrip(n):
    assert _script_num(script_num(n)) == n


def test_namespace():
    assert namespace_of(b"dns:example.emc") == "dns"
    assert namespace_of(b"plainname") is None


def test_amounts():
    assert to_units("1.5") == 1_500_000
    assert to_units("0.000001") == 1
    with pytest.raises(ValueError):
        to_units("0.0000001")
    assert format_units(1_500_000) == "1.500000"
    assert format_units(-1) == "-0.000001"
