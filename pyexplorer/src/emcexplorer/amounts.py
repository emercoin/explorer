"""EMC amounts are stored as integers in the smallest unit (1 EMC = 1 000 000)."""

from __future__ import annotations

from decimal import Decimal

COIN = 1_000_000


def to_units(value: Decimal | int | str) -> int:
    d = Decimal(value) * COIN
    if d != d.to_integral_value():
        raise ValueError(f"amount {value!r} has more than 6 decimals")
    return int(d)


def format_units(units: int) -> str:
    """Render as a decimal string, the way the node does ("12.300000")."""
    sign = "-" if units < 0 else ""
    q, r = divmod(abs(units), COIN)
    return f"{sign}{q}.{r:06d}"
