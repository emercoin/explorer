"""Settings are read from environment variables (see .env.example)."""

from __future__ import annotations

import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    # http://user:password@127.0.0.1:6662/  (emercoind uses 6662 for every network by default)
    rpc_url: str
    # postgresql://user:password@host:5432/dbname
    database_url: str
    # Seconds between polls of the node when the index is at the tip.
    poll_interval: float = 5.0
    # How many blocks are fetched from the node ahead of the block being written.
    prefetch: int = 16
    # Refuse to roll back deeper than this; a deeper reorg needs a human.
    max_reorg_depth: int = 500
    rpc_timeout: float = 60.0

    @classmethod
    def from_env(cls) -> "Settings":
        def req(name: str) -> str:
            value = os.environ.get(name)
            if not value:
                raise SystemExit(f"environment variable {name} is required")
            return value

        return cls(
            rpc_url=req("EMC_RPC_URL"),
            database_url=req("DATABASE_URL"),
            poll_interval=float(os.environ.get("EMC_POLL_INTERVAL", cls.poll_interval)),
            prefetch=int(os.environ.get("EMC_PREFETCH", cls.prefetch)),
            max_reorg_depth=int(os.environ.get("EMC_MAX_REORG_DEPTH", cls.max_reorg_depth)),
            rpc_timeout=float(os.environ.get("EMC_RPC_TIMEOUT", cls.rpc_timeout)),
        )


def network_name() -> str:
    """The network this explorer shows (EXPLORER_NETWORK): mainnet unless set."""
    return os.environ.get("EXPLORER_NETWORK", "mainnet")


def network_links() -> list[tuple[str, str]]:
    """Explorers of every network, for the switch in the page header, from
    EXPLORER_NETWORKS="mainnet=https://explorer3.emercoin.com,testnet=https://testnet3.emercoin.com".
    Empty: no switch."""
    links = []
    for item in os.environ.get("EXPLORER_NETWORKS", "").split(","):
        name, sep, url = item.partition("=")
        if sep and name.strip() and url.strip():
            links.append((name.strip(), url.strip()))
    return links
