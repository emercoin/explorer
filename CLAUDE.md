# Emercoin explorer

Отвечать по-русски.

Новый эксплорер на Python лежит в `pyexplorer/` (индексатор, API и сайт на FastAPI,
PostgreSQL). Старая PHP-версия (`html/`, `tools/`, `*.sql`) не развивается.
Подробности: `pyexplorer/README.md`, запуск на сервере: `pyexplorer/docs/vps-run.md`.

## Ветки и PR

* Основная ветка разработки — `develop`. `master` — старая стабильная ветка.
* Работа ведётся в ветке с понятным именем от свежего `develop`: `feature/<что>`,
  `fix/<что>`, `docs/<что>`. Не в `claude/<случайное-имя>`, даже если сессия
  назначила такую ветку: владелец репозитория разрешил эти имена.
* В `develop` — только через PR. Прямо в `develop` не пушить: каждый push туда
  сразу выкатывается на боевой сервер.
* Коммиты и описание PR пишутся по-английски.

## Проверки перед push

```bash
cd pyexplorer
uv sync --frozen
uv run ruff check --select E,F,W,B --line-length 110 src tests
uv run pytest -q   # интеграционным тестам нужен TEST_DATABASE_URL (схема public пересоздаётся)
```

## Выкатка

Push (merge) в `develop`, затрагивающий `pyexplorer/`, запускает
`.github/workflows/explorer-release.yml`: тесты → образ
`ghcr.io/emercoin/explorer:X.Y.Z-dev.N` и `:develop` → тег `vX.Y.Z-dev.N` →
по SSH `pyexplorer/deploy/deploy-service.sh` на VPS (`/opt/explorer`).
Тег `vX.Y.Z` (версия из `pyexplorer/pyproject.toml`) делает обычный релиз с `:latest`.

На VPS стек поднят с `deploy/docker-compose.override.yaml`: своя нода и Caddy
выключены профилями, на хосте уже работают нода `emc-public` и nginx на :80.
Логи Actions публичные (репозиторий открытый): не выводить в них секреты, IP
пиров и посетителей.
