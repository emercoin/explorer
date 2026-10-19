"""The indexer process stops on SIGTERM at once (in a container it is PID 1)."""

import os
import signal
import subprocess
import sys
import time

import pytest

from conftest import TEST_DSN


def test_indexer_stops_on_sigterm():
    if not TEST_DSN:
        pytest.skip("TEST_DATABASE_URL is not set")
    env = dict(os.environ, DATABASE_URL=TEST_DSN, EMC_RPC_URL="http://u:p@127.0.0.1:9/",
               EMC_POLL_INTERVAL="60", LOG_LEVEL="INFO")
    proc = subprocess.Popen([sys.executable, "-m", "emcexplorer", "index"], env=env,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    try:
        deadline = time.monotonic() + 20
        out = ""
        while "retrying" not in out and time.monotonic() < deadline:  # waiting out the node it cannot reach
            out += proc.stdout.readline()
        assert "retrying" in out
        proc.send_signal(signal.SIGTERM)
        assert proc.wait(timeout=5) == 0  # handled: not killed by the signal (-15), not after the 60 s sleep
        assert "stopping" in proc.stdout.read()
    finally:
        proc.kill()
