"""Small single-series SVG charts rendered on the server (no JavaScript).

Each mark carries a <title>, so hovering a bar or a point shows its date and
value in the browser's tooltip; the page also has the numbers as a table.
Colors come from CSS variables in style.css (--series, --line, --muted, --bg);
the presentation attributes on each element are only a fallback for a page
whose stylesheet is missing or stale (without them a rect fills black).
"""

from __future__ import annotations

import math
from dataclasses import dataclass
from html import escape

from markupsafe import Markup

W, H = 520, 200
LEFT, RIGHT, TOP, BOTTOM = 44, 10, 14, 24
PLOT_W, PLOT_H = W - LEFT - RIGHT, H - TOP - BOTTOM

# Element attributes: the CSS class, plus fallback colors for a missing stylesheet.
GRID = 'class="grid" stroke="#8a8f99" stroke-opacity="0.3"'
TICK = 'class="tick" fill="#8a8f99" font-size="12"'
BAR = 'class="bar" fill="#2a78d6"'
HIT = 'class="hit" fill="transparent"'
AREA = 'class="area" fill="#2a78d6" fill-opacity="0.1"'
LINE = 'class="line" fill="none" stroke="#2a78d6" stroke-width="2"'
DOT = 'class="dot" fill="#2a78d6"'
VALUE = 'class="value" fill="currentColor" font-size="13"'


@dataclass
class Point:
    label: str  # x label, e.g. "2024-03-01"
    value: float
    tip: str  # tooltip text


def _nice_ticks(top: float, count: int = 4) -> list[float]:
    if top <= 0:
        return [0.0, 1.0]
    raw = top / count
    mag = 10 ** math.floor(math.log10(raw))
    step = next(m * mag for m in (1, 2, 2.5, 5, 10) if m * mag >= raw)
    return [i * step for i in range(int(math.ceil(top / step)) + 1)]


def compact(v: float) -> str:
    for size, suffix in ((1e9, "B"), (1e6, "M"), (1e3, "k")):
        if abs(v) >= size:
            s = f"{v / size:.1f}".rstrip("0").rstrip(".")
            return s + suffix
    return f"{v:g}"


def _frame(points: list[Point], ticks: list[float], title: str) -> tuple[list[str], float]:
    top = ticks[-1]
    parts = [
        f'<svg class="chart" viewBox="0 0 {W} {H}" role="img" aria-label="{escape(title)}" '
        f'preserveAspectRatio="xMidYMid meet">'
    ]
    for t in ticks:
        y = TOP + PLOT_H - t / top * PLOT_H
        parts.append(f'<line {GRID} x1="{LEFT}" x2="{W - RIGHT}" y1="{y:.1f}" y2="{y:.1f}"/>')
        parts.append(
            f'<text {TICK} x="{LEFT - 6}" y="{y + 4:.1f}" text-anchor="end">{compact(t)}</text>'
        )
    # x labels: first, middle and last point
    n = len(points)
    for i, anchor in ((0, "start"), (n // 2, "middle"), (n - 1, "end")):
        x = LEFT + (i + 0.5) / n * PLOT_W if anchor == "middle" else (LEFT if i == 0 else W - RIGHT)
        parts.append(
            f'<text {TICK} x="{x:.1f}" y="{H - 6}" text-anchor="{anchor}">'
            f"{escape(points[i].label)}</text>"
        )
    return parts, top


def bar_chart(points: list[Point], title: str) -> Markup:
    if not points:
        return Markup("")
    ticks = _nice_ticks(max(p.value for p in points))
    parts, top = _frame(points, ticks, title)
    band = PLOT_W / len(points)
    width = max(min(24.0, band - 2), 1.0)  # 2px surface gap between neighbours, capped at 24px
    r = min(4.0, width / 2)
    base = TOP + PLOT_H
    for i, p in enumerate(points):
        x = LEFT + i * band + (band - width) / 2
        h = p.value / top * PLOT_H
        tip = f"<title>{escape(p.tip)}</title>"
        if h > 0:
            rr = min(r, h)
            # rounded data end, square at the baseline
            parts.append(
                f'<path {BAR} d="M{x:.1f},{base:.1f}V{base - h + rr:.1f}'
                f"Q{x:.1f},{base - h:.1f} {x + rr:.1f},{base - h:.1f}H{x + width - rr:.1f}"
                f'Q{x + width:.1f},{base - h:.1f} {x + width:.1f},{base - h + rr:.1f}V{base:.1f}Z"/>'
            )
        # hit target: the whole column, wider than the bar
        parts.append(
            f'<rect {HIT} x="{LEFT + i * band:.1f}" y="{TOP}" width="{band:.1f}" '
            f'height="{PLOT_H}">{tip}</rect>'
        )
    parts.append("</svg>")
    return Markup("".join(parts))


def line_chart(points: list[Point], title: str, last_label: str) -> Markup:
    if len(points) < 2:
        return Markup("")
    ticks = _nice_ticks(max(p.value for p in points))
    parts, top = _frame(points, ticks, title)
    n = len(points)
    xy = [
        (LEFT + i / (n - 1) * PLOT_W, TOP + PLOT_H - p.value / top * PLOT_H) for i, p in enumerate(points)
    ]
    line = " ".join(f"{x:.1f},{y:.1f}" for x, y in xy)
    parts.append(
        f'<polygon {AREA} points="{LEFT},{TOP + PLOT_H} {line} {W - RIGHT},{TOP + PLOT_H}"/>'
        f'<polyline {LINE} points="{line}"/>'
    )
    x, y = xy[-1]
    parts.append(f'<circle {DOT} cx="{x:.1f}" cy="{y:.1f}" r="4"/>')
    parts.append(
        f'<text {VALUE} x="{x - 8:.1f}" y="{max(y - 10, TOP + 10):.1f}" text-anchor="end">'
        f"{escape(last_label)}</text>"
    )
    step = PLOT_W / (n - 1)
    for (x, _), p in zip(xy, points, strict=True):
        parts.append(
            f'<rect {HIT} x="{x - step / 2:.1f}" y="{TOP}" width="{step:.1f}" height="{PLOT_H}">'
            f"<title>{escape(p.tip)}</title></rect>"
        )
    parts.append("</svg>")
    return Markup("".join(parts))
