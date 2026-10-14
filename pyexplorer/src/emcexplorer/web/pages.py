"""HTML pages of the explorer, rendered on the server.

Every page gets its data from the /api/v1 handlers in ..api.app, so the SQL
lives in one place and the pages show exactly what the API returns.
"""

from __future__ import annotations

from datetime import UTC, datetime
from importlib.resources import files
from typing import Any
from urllib.parse import quote

from fastapi import APIRouter, HTTPException, Query, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from ..api import app as api

router = APIRouter(include_in_schema=False)
templates = Jinja2Templates(directory=str(files("emcexplorer.web").joinpath("templates")))
static_files = StaticFiles(directory=str(files("emcexplorer.web").joinpath("static")))

PAGE = 50


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
        return await _error(request, e)
    return await _render(request, "tx.html", t=t)


@router.get("/address/{address}", response_class=HTMLResponse)
async def address(request: Request, address: str, before: str | None = None) -> HTMLResponse:
    try:
        a = await api.address(request, address)
        txs = await api.address_txs(request, address, before=before, limit=PAGE)
    except HTTPException as e:
        return await _error(request, e)
    return await _render(request, "address.html", a=a, txs=txs, first_page=before is None)


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
