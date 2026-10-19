# Emercoin Explorer

A blockchain explorer for [Emercoin](https://emercoin.com) with native support for
its name-value storage (NVS): https://explorer3.emercoin.com

The explorer lives in [pyexplorer/](pyexplorer): an indexer, an HTTP API and the site,
in Python on PostgreSQL. Its README (in Russian) covers the design, how to run it and
the API; the API is also described at [/docs](https://explorer3.emercoin.com/docs).

## Branches and releases

* `master`: what is released. `develop` is merged into it for a release, and a tag
  `vX.Y.Z` builds the image `ghcr.io/emercoin/explorer:X.Y.Z` and `:latest`.
* `develop`: work in progress. Changes reach it only through pull requests, and every
  merge that touches `pyexplorer/` is deployed to https://explorer3.emercoin.com as
  `X.Y.Z-dev.N`.

Pull requests go to `develop`, from a branch named `feature/…`, `fix/…` or `docs/…`.

## The old PHP explorer

The previous explorer (PHP and MySQL) has been removed from the tree; it is in the git
history up to tag `v0.1.0`. Its `/api/...` routes are still served by the new explorer.
