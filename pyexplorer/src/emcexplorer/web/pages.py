"""HTML pages of the explorer, rendered on the server.

Every page gets its data from the /api/v1 handlers in ..api.app, so the SQL
lives in one place and the pages show exactly what the API returns.
"""

from __future__ import annotations

import hashlib
from datetime import UTC, datetime
from importlib.resources import files
from typing import Any
from urllib.parse import quote

from fastapi import APIRouter, HTTPException, Query, Request
from fastapi.responses import FileResponse, HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from ..amounts import format_units
from ..api import app as api
from .charts import Point, bar_chart, compact, line_chart

router = APIRouter(include_in_schema=False)
templates = Jinja2Templates(directory=str(files("emcexplorer.web").joinpath("templates")))
static_files = StaticFiles(directory=str(files("emcexplorer.web").joinpath("static")))

PAGE = 50
MAX_MULTISIG = 100  # multisig outputs listed on an address page


def _utc(ts: int | None) -> str:
    if ts is None:
        return ""
    return datetime.fromtimestamp(ts, UTC).strftime("%Y-%m-%d %H:%M:%S")


def _ago(ts: int | None) -> str:
    if ts is None:
        return ""
    s = int(datetime.now(UTC).timestamp()) - ts
    for unit, size in (("d", 86400), ("h", 3600), ("min", 60)):
        if s >= size:
            return f"{s // size} {unit} ago"
    return f"{max(s, 0)} s ago"


def _short(h: str | None, n: int = 10) -> str:
    if not h:
        return ""
    return h if len(h) <= 2 * n + 1 else f"{h[:n]}…{h[-n:]}"


def _field(f: dict[str, Any] | None) -> str:
    """Name or value as the API gives it: text, or base64 of binary data."""
    if not f:
        return ""
    return f["text"] if f["text"] is not None else f"base64:{f['base64']}"


def _name_url(f: dict[str, Any]) -> str | None:
    # Names that are not text (or hold NUL) cannot be looked up by URL.
    text = f.get("text")
    if text is None or "\x00" in text:
        return None
    return "/name/" + quote(text, safe="")


templates.env.filters.update(utc=_utc, ago=_ago, short=_short, field=_field, name_url=_name_url)


def _asset_version() -> str:
    """Hash of the static files, appended to their URLs so that a release is
    never served a stylesheet cached by Cloudflare or the browser."""
    digest = hashlib.sha256()
    static = files("emcexplorer.web").joinpath("static")
    for name in ("style.css", "htmx.min.js", "favicon.ico"):
        digest.update(static.joinpath(name).read_bytes())
    return digest.hexdigest()[:10]


templates.env.globals["asset_version"] = _asset_version()


async def _render(request: Request, template: str, status_code: int = 200, **ctx: Any) -> HTMLResponse:
    st = await api.status(request)
    return templates.TemplateResponse(
        request, template, {"status": st, **ctx}, status_code=status_code
    )


async def _error(request: Request, e: HTTPException) -> HTMLResponse:
    return await _render(request, "error.html", e.status_code, code=e.status_code, detail=e.detail)


@router.get("/", response_class=HTMLResponse)
async def index(request: Request) -> HTMLResponse:
    blocks = await api.recent_blocks(request, before=None, limit=20)
    return await _render(request, "index.html", blocks=blocks)


@router.get("/_latest", response_class=HTMLResponse)
async def latest(request: Request) -> HTMLResponse:
    """Block table of the home page, refreshed by htmx."""
    blocks = await api.recent_blocks(request, before=None, limit=20)
    return templates.TemplateResponse(request, "_blocks.html", {"blocks": blocks})


@router.get("/blocks", response_class=HTMLResponse)
async def blocks(request: Request, before: int | None = Query(None, ge=1)) -> HTMLResponse:
    rows = await api.recent_blocks(request, before=before, limit=PAGE)
    nxt = rows[-1]["height"] if len(rows) == PAGE and rows[-1]["height"] > 0 else None
    return await _render(request, "blocks.html", blocks=rows, next_before=nxt)


@router.get("/block/{ref}", response_class=HTMLResponse)
async def block(request: Request, ref: str, page: int = Query(1, ge=1)) -> HTMLResponse:
    try:
        b = await api.block(request, ref, offset=(page - 1) * PAGE, limit=PAGE)
    except HTTPException as e:
        return await _error(request, e)
    pages = (b["tx_count"] + PAGE - 1) // PAGE
    return await _render(request, "block.html", b=b, page=page, pages=pages)


@router.get("/tx/{txid}", response_class=HTMLResponse)
async def tx(request: Request, txid: str) -> HTMLResponse:
    try:
        t = await api.transaction(request, txid)
    except HTTPException as e:
        if e.status_code != 404:
            return await _error(request, e)
        # not in a block yet? then maybe in the node's memory pool
        try:
            t = await api.mempool_tx(request, txid)
        except HTTPException:
            return await _error(request, e)
    return await _render(request, "tx.html", t=t)


@router.get("/mempool", response_class=HTMLResponse)
async def mempool(request: Request) -> HTMLResponse:
    try:
        pool = await api.mempool(request, limit=100)
    except HTTPException as e:
        return await _error(request, e)
    return await _render(request, "mempool.html", pool=pool)


@router.get("/favicon.ico", include_in_schema=False)
async def favicon() -> FileResponse:
    # Browsers ask for /favicon.ico on their own (feed readers, bookmarks), so it
    # lives here as well as at the versioned /static URL the pages link to.
    return FileResponse(
        str(files("emcexplorer.web").joinpath("static", "favicon.ico")),
        media_type="image/x-icon",
        headers={"cache-control": "public, max-age=86400"},
    )


@router.get("/address/{address}", response_class=HTMLResponse)
async def address(request: Request, address: str, before: str | None = None) -> HTMLResponse:
    try:
        multisig = await api.address_multisig(request, address, limit=MAX_MULTISIG) if before is None else []
        try:
            a = await api.address(request, address)
        except HTTPException as e:
            if e.status_code != 404 or not multisig:
                raise
            # only a non-first key of multisig outputs: nothing is credited to it
            zero = format_units(0)
            a = {"address": address, "balance": zero, "received": zero, "sent": zero, "tx_count": 0,
                 "first_height": None, "last_height": None}
        txs = await api.address_txs(request, address, before=before, limit=PAGE)
    except HTTPException as e:
        return await _error(request, e)
    return await _render(
        request, "address.html", a=a, txs=txs, multisig=multisig, first_page=before is None
    )


@router.get("/names", response_class=HTMLResponse)
async def names(
    request: Request,
    prefix: str = Query("", max_length=600),
    after: str | None = Query(None, max_length=600),
    inactive: bool = False,
) -> HTMLResponse:
    res = await api.names(
        request, prefix=prefix, namespace=None, after=after, include_inactive=inactive, limit=PAGE
    )
    return await _render(request, "names.html", res=res, prefix=prefix, inactive=inactive)


@router.get("/name/{name:path}", response_class=HTMLResponse)
async def name(request: Request, name: str) -> HTMLResponse:
    try:
        history = await api.name_history(request, name)
    except HTTPException as e:
        return await _error(request, e)
    try:
        current = await api.name(request, name)
    except HTTPException:
        current = None  # in the chain, but the node's name index no longer has it
    return await _render(request, "name.html", name=name, current=current, history=history)


@router.get("/richlist", response_class=HTMLResponse)
async def richlist(request: Request) -> HTMLResponse:
    rows = await api.richlist(request, limit=100)
    return await _render(request, "richlist.html", rows=rows)


def _sample(days: list[dict[str, Any]], every: int) -> list[dict[str, Any]]:
    """Every `every`-th day of a long history (oldest first), always keeping the last one."""
    picked = days[::every]
    if picked[-1] is not days[-1]:
        picked.append(days[-1])
    return picked


@router.get("/stats", response_class=HTMLResponse)
async def stats(request: Request) -> HTMLResponse:
    try:
        summary = await api.stats_summary(request)
    except HTTPException as e:
        return await _error(request, e)
    history = list(reversed(await api.stats_daily(request, days=10000)))  # oldest first
    recent = history[-90:]
    long = _sample(history, max(len(history) // 300, 1)) if history else []
    charts = {}
    if history:
        charts = {
            "payments": bar_chart(
                [Point(d["date"], d["payments"], f"{d['date']}: {d['payments']:,} transactions")
                 for d in recent],
                "Transactions per day, last 90 days",
            ),
            "active": bar_chart(
                [Point(d["date"], d["active_addresses"], f"{d['date']}: {d['active_addresses']:,} addresses")
                 for d in recent],
                "Active addresses per day, last 90 days",
            ),
            "supply": line_chart(
                [Point(d["date"], float(d["supply"]), f"{d['date']}: {float(d['supply']):,.0f} EMC")
                 for d in long],
                "Coin supply, EMC",
                compact(float(long[-1]["supply"])),
            ),
            "size": line_chart(
                [Point(d["date"], d["chain_size"] / 1e6, f"{d['date']}: {d['chain_size'] / 1e6:,.1f} MB")
                 for d in long],
                "Chain size, MB",
                f"{long[-1]['chain_size'] / 1e6:,.0f} MB",
            ),
        }
    return await _render(
        request, "stats.html", summary=summary, charts=charts, table=list(reversed(history[-30:]))
    )


@router.get("/search")
async def search(request: Request, q: str = Query("", max_length=600)):
    q = q.strip()
    if not q:
        return RedirectResponse("/", status_code=303)
    try:
        hit = await api.search(request, q)
    except HTTPException:
        return await _render(request, "error.html", 404, code=404, detail=f"Nothing found for “{q}”")
    target = {
        "block": "/block/{}",
        "tx": "/tx/{}",
        "address": "/address/{}",
        "name": "/name/{}",
    }[hit["type"]].format(quote(hit["id"], safe=""))
    return RedirectResponse(target, status_code=303)
