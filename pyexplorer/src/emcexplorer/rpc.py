"""Minimal async JSON-RPC client for emercoind.

Amounts are parsed as Decimal (never float): the node prints values with
exactly 6 decimals (COIN = 1 000 000), and a float round-trip loses that.
"""

from __future__ import annotations

import json
from decimal import Decimal
from typing import Any, Iterable
from urllib.parse import urlsplit, urlunsplit

import httpx


class RpcError(Exception):
    def __init__(self, method: str, error: Any):
        self.method = method
        self.error = error
        code = error.get("code") if isinstance(error, dict) else None
        message = error.get("message") if isinstance(error, dict) else error
        self.code = code
        super().__init__(f"{method}: [{code}] {message}")


def _loads(text: str) -> Any:
    return json.loads(text, parse_float=Decimal)


class RpcClient:
    def __init__(
        self,
        url: str,
        *,
        timeout: float = 60.0,
        transport: httpx.AsyncBaseTransport | None = None,
    ):
        parts = urlsplit(url)
        auth = None
        if parts.username is not None:
            auth = (parts.username, parts.password or "")
            netloc = parts.hostname or ""
            if parts.port:
                netloc += f":{parts.port}"
            url = urlunsplit((parts.scheme, netloc, parts.path or "/", parts.query, ""))
        self._client = httpx.AsyncClient(
            base_url=url, auth=auth, timeout=timeout, transport=transport
        )
        self._next_id = 0

    async def close(self) -> None:
        await self._client.aclose()

    async def __aenter__(self) -> "RpcClient":
        return self

    async def __aexit__(self, *exc: object) -> None:
        await self.close()

    def _id(self) -> int:
        self._next_id += 1
        return self._next_id

    async def _post(self, payload: Any) -> Any:
        resp = await self._client.post(
            "", content=json.dumps(payload), headers={"content-type": "application/json"}
        )
        # bitcoind-style nodes answer RPC errors with HTTP 500 and a JSON body.
        if resp.status_code not in (200, 500):
            resp.raise_for_status()
        return _loads(resp.text)

    async def call(self, method: str, *params: Any) -> Any:
        body = await self._post(
            {"jsonrpc": "1.0", "id": self._id(), "method": method, "params": list(params)}
        )
        if body.get("error"):
            raise RpcError(method, body["error"])
        return body["result"]

    async def batch(
        self, calls: Iterable[tuple[str, list[Any]]], *, return_exceptions: bool = False
    ) -> list[Any]:
        """Send several calls in one HTTP request; results come back in call order.

        With return_exceptions=True a failed call yields its RpcError in place
        of a result instead of raising.
        """
        calls = list(calls)
        if not calls:
            return []
        first = self._next_id + 1
        payload = [
            {"jsonrpc": "1.0", "id": self._id(), "method": m, "params": p} for m, p in calls
        ]
        body = await self._post(payload)
        by_id = {item["id"]: item for item in body}
        results = []
        for offset, (method, _) in enumerate(calls):
            item = by_id[first + offset]
            if item.get("error"):
                if not return_exceptions:
                    raise RpcError(method, item["error"])
                results.append(RpcError(method, item["error"]))
            else:
                results.append(item["result"])
        return results
