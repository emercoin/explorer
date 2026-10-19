# emcexplorer: каркас нового эксплорера Emercoin на Python

Индексатор блокчейна и HTTP API поверх PostgreSQL. Задуман как замена PHP-коду
из `../html` и `../tools`. Веб-интерфейс рендерится на сервере тем же процессом, что и API.

## Почему не готовый эксплорер

Сначала были рассмотрены живые open-source эксплореры (данные на октябрь 2026).
Главный вопрос: сколько стоит научить каждый из них Emercoin вообще и NVS в частности.

Как устроен Emercoin (по исходникам `emercoin/emercoin`):

* транзакция содержит поле `nTime` (наследие PPCoin), блоки бывают с auxpow
  (merged mining), есть PoS: coinstake с пустым первым выходом;
* NVS хранится прямо в выходах: префикс `OP_1/2/3 OP_DROP <name> <days> OP_2DROP <value…> OP_DROP…`
  перед обычным P2PKH/P2SH-скриптом, транзакция версии `0x0666`;
* у ноды есть собственный индекс имён: `name_show`, `name_history`, `name_filter`, `name_scan`;
* особые входы randpay ссылаются на виртуальный UTXO `ecec…ec:0` стоимостью 0.

| Эксплорер | Стек | Как получает данные | Что нужно для Emercoin | NVS |
|---|---|---|---|---|
| **Blockbook** (Trezor) | Go, RocksDB | сам разбирает сырые блоки | Emercoin и Peercoin не поддерживаются. Нужен свой парсер транзакций с `nTime` (парсер Namecoin умеет пропускать auxpow, но транзакции разбирает как обычный Bitcoin) | индекс имён пришлось бы добавлять в ядро (свои column family в RocksDB), интерфейс минимальный |
| **Esplora / electrs**, **mempool.space** | Rust + TS | сам разбирает сырые блоки | только Bitcoin и Liquid; пришлось бы поддерживать форк consensus-парсера на Rust | нет |
| **btc-rpc-explorer** | Node.js | RPC, своей БД нет | блоки и транзакции заработают почти сразу, но история адресов идёт через Electrum-сервер (electrs/Fulcrum), а он упирается в тот же формат транзакций | страницы можно собрать поверх `name_show`/`name_history`, но поиска и списков не будет |
| **eIquidus** | Node.js, MongoDB | RPC + своя БД | ближе всех: работает через RPC, PoS поддерживает, проект активен. Формат транзакций скрыт нодой | нет; нужны своя коллекция, хук в `lib/block_sync.js`, маршруты и шаблоны |
| xaya/explorer | Ruby on Rails | RPC | заброшен с 2018 года | только для Namecoin |

Вывод:

* Всё, что разбирает блоки само (Blockbook, Esplora, mempool), обходится дороже собственного индексатора: формат транзакций Emercoin отличается от Bitcoin, и форк парсера пришлось бы сопровождать всегда.
* Реальная альтернатива одна: **eIquidus** плюс дописанный модуль NVS. Получаем готовый интерфейс, richlist и графики, но взамен живём на форке чужого проекта на Node.js/MongoDB, а NVS остаётся надстройкой сбоку.
* Свой индексатор на Python не намного дороже (этот каркас уже индексирует цепочку и NVS). Схема и API при этом свои, NVS в них полноценная часть.

## Архитектура

```
emercoind (RPC) ──► indexer ──► PostgreSQL ◄── api (FastAPI): /api/v1 + HTML-страницы ◄── браузеры / внешние сервисы
```

* **Индексатор** (`indexer/sync.py`) идёт по лучшей цепочке ноды. Блоки
  запрашиваются через `getblock <hash> 2` с опережением (`EMC_PREFETCH`), а
  записываются строго по порядку, **каждый блок одной транзакцией БД**.
* **Реорганизации.** Перед каждым проходом хэш нашей вершины сверяется с нодой.
  При расхождении индексатор спускается до общего блока и откатывает всё, что выше.
  Вся связанная с блоком информация удаляется каскадом (`ON DELETE CASCADE` от `blocks`),
  затем снимаются пометки «потрачено» и пересчитываются `address_stats` по
  затронутым адресам. Тесты проверяют, что состояние после реорганизации
  побайтно совпадает с индексацией новой цепочки с нуля. Отстающие на 128 блоков
  копии базы (как в PHP-версии) больше не нужны.
* **Суммы** хранятся в `BIGINT` в минимальных единицах (1 EMC = 1 000 000).
  JSON от ноды разбирается через `Decimal`, без float. Эмиссия считается как
  «Σвыходов − Σвходов» каждого блока, поэтому сожжённые в PoS комиссии учитываются сами.
* **Входы** берут сумму и адрес из таблицы `outputs` (собственный UTXO-набор),
  без лишних `getrawtransaction`.
* **Мультиподписные выходы** (голый `multisig`, «M из N ключей»). Нода отдаёт
  адрес каждого ключа; они хранятся в `outputs.addresses`, число подписей в
  `outputs.required`. Сумма, как и в PHP-версии, засчитывается в баланс первого
  ключа (`outputs.address`), иначе одни и те же монеты попали бы в балансы
  нескольких адресов. Остальные ключи видят такие выходы на странице адреса и в
  `/addresses/{addr}/multisig`. В выходе транзакции API отдаёт `addresses` и
  `required` только у мультиподписных выходов. Для блоков, проиндексированных до
  появления этих колонок, индексатор один раз дозаполняет их через `decodescript`
  (отметка `multisig_backfill` в `meta`); полная переиндексация не нужна.
* **NVS** работает в два слоя:
  * `name_ops`: история операций, по одной строке на выход `(txid, n)`. С 0.8
    имя привязано к выходу, а не к транзакции, и одна транзакция может
    обновить сразу несколько имён. Поэтому на странице транзакции имя
    подписано у каждого входа и выхода, который его несёт, со ссылкой на
    историю имени. Индексатор сам разбирает скрипты (`nvs.py`, порт
    `DecodeNameScript()` из `src/script/script.cpp`);
  * `names`: текущее состояние имени. Его отдаёт нода через `name_show`, поэтому
    правила срока действия и приёма операций (`CalculateExpiresAt`, «только
    первый `name_new` в блоке» и т.п.) остаются за нодой и не дублируются здесь.
    Затронутые имена копятся в `names_dirty` и перечитываются пачками, когда
    индекс догнал ноду.
* **Статистика по дням** (`indexer/stats.py`, таблица `daily_stats`) считается
  из уже проиндексированных таблиц, когда индекс догнал ноду. Пересчитываются
  дни начиная с того, где был последний учтённый блок. При откате эта отметка
  опускается до общего блока, так что после реорганизации дни собираются заново
  из оставшихся блоков. Первый пересчёт после выкатки охватывает всю историю;
  индексатор на это время (оценочно несколько минут на VPS) приостанавливается.
* **Мемпул** (`api/mempool.py`). API спрашивает ноду (`getrawmempool true` и
  `getrawtransaction` пачкой для 100 самых новых) и отдаёт снимок не старше 10
  секунд. Суммы и адреса входов берутся из индекса или из другой транзакции
  мемпула. Без `EMC_RPC_URL` у процесса API мемпул отвечает 503, остальное
  работает.
* **Веб-интерфейс** (`web/`): шаблоны Jinja, без JS-сборки. Страницы берут данные
  у обработчиков `/api/v1`, так что SQL живёт в одном месте, а страница показывает
  ровно то, что отдаёт API. Отдельного фронтенд-сервиса нет. htmx (лежит в
  `web/static`) только обновляет таблицу блоков на главной раз в 30 секунд.
  Маршруты: `/`, `/blocks`, `/block/{h|hash}`, `/tx/{txid}`, `/address/{a}`,
  `/names?prefix=`, `/name/{name}`, `/richlist`, `/stats`, `/mempool`, `/search?q=`.
  Транзакция, которой ещё нет в блоке, показывается на `/tx/{txid}` из мемпула. Графики
  на `/stats` рисуются на сервере в SVG (`web/charts.py`). Светлая и тёмная
  тема переключаются по настройке системы.

## Запуск для разработки

Нужны [uv](https://docs.astral.sh/uv/), PostgreSQL 13+ и `emercoind` с `server=1`.
`txindex` (нужен `name_show`) в Emercoin включён по умолчанию.

```bash
cd pyexplorer
uv sync                 # .venv с зависимостями из uv.lock (вместе с dev-группой)
cp .env.example .env    # заполнить, затем экспортировать переменные
export $(grep -v '^#' .env | xargs)

uv run emcexplorer initdb     # создаёт таблицы (повторный запуск безопасен)
uv run emcexplorer index      # индексатор: долгоживущий процесс
uv run emcexplorer api --host 0.0.0.0 --port 8000   # сайт: /, документация API: /docs
```

Зависимости меняются через `uv add` / `uv remove`; `uv.lock` коммитится вместе с ними.

## Развёртывание

Всё на одном хосте, `deploy/docker-compose.yaml`:

| Сервис | Что делает | Наружу |
|---|---|---|
| `emc` | нода `emercoin/core:0.8.5` (из `emercoin/emer-ai-tools`), кошелёк отключён | P2P 6661 |
| `postgres` | база индекса | нет |
| `migrate` | разовый `emcexplorer initdb` перед стартом остальных | нет |
| `indexer` | `emcexplorer index`, один экземпляр | нет |
| `api` | `emcexplorer api` | нет |
| `caddy` | TLS (Let's Encrypt) для `EXPLORER_DOMAIN`, проксирует на `api` | 80, 443 |

RPC ноды и PostgreSQL доступны только во внутренней сети compose (`172.28.0.0/24`).
Образ `ghcr.io/emercoin/explorer` собирается из `Dockerfile` двумя стадиями: uv ставит
зависимости строго по `uv.lock`, в итоговый образ попадает только `.venv`.

### Первый запуск на сервере

```bash
git clone https://github.com/emercoin/explorer /opt/explorer
cd /opt/explorer/pyexplorer/deploy
docker volume create emer_explorer_chain   # внешние тома: `down -v` их не удалит
docker volume create emer_explorer_pg
cp emercoin.conf.example emercoin.conf     # rpcpassword
cp .env.example .env && chmod 600 .env     # тот же пароль в EMC_RPC_PASSWORD, POSTGRES_PASSWORD, домен
docker compose --env-file .env up -d --build    # до первого релиза образ собирается на месте
```

Если на хосте уже есть нода или веб-сервер, отличия задаются в
`deploy/docker-compose.override.yaml` (не в git; шаблон и пример с внешней нодой
лежат в `docker-compose.override.example.yaml`, подробности в
[docs/vps-run.md](docs/vps-run.md)).

### Testnet

Эксплорер testnet — второй экземпляр того же стека в том же compose, профиль
`testnet`: нода `emc-testnet` (`emercoind -testnet`, тот же `emercoin.conf`),
база `explorer_testnet` в том же PostgreSQL (её создаёт `migrate-testnet`),
`indexer-testnet` и `api-testnet`. Код один и тот же: адреса и типы скриптов
берутся у ноды, сеть он не различает. `EXPLORER_NETWORK=testnet` только ставит
метку в шапке и поле `network` в `/api/v1/status`.

```bash
docker volume create emer_explorer_testnet_chain
# в .env:
#   COMPOSE_PROFILES=testnet
#   EXPLORER_NETWORKS=mainnet=https://explorer3.emercoin.com,testnet=https://testnet3.emercoin.com
docker compose --env-file .env up -d
```

`EXPLORER_NETWORKS` включает переключатель Mainnet | Testnet в шапке обоих сайтов.
Наружу `api-testnet` публикуется так же, как `api`: через override и хостовый
nginx (порт 8001, см. [docs/vps-run.md](docs/vps-run.md)) или вторым сайтом в
Caddyfile. `deploy-service.sh` обновляет testnet вместе с mainnet: сервисы
профиля поднимаются тем же `docker compose up`.

### Релизы

Тег `vX.Y.Z` (версия должна совпадать с `pyproject.toml`) запускает
`.github/workflows/explorer-release.yml`: тесты, затем публикация
`ghcr.io/emercoin/explorer:X.Y.Z` и `:latest` в GitHub Container Registry
(встроенным токеном workflow, секреты реестра не нужны), затем выкатка по SSH.
На сервере выполняется `deploy/deploy-service.sh vX.Y.Z`: он переключает
checkout на тот же тег (чтобы compose и Caddyfile совпадали с образом),
записывает версию в `.env`, применяет схему и пересоздаёт `indexer` и `api`.
Секреты перечислены в шапке workflow. Ключ CI на сервере привязан к этому
скрипту через `command=` в `authorized_keys` (строка есть в шапке скрипта):
им можно только выкатить тег, но не выполнить произвольную команду.

Вместо тега релиз можно запустить вручную (Actions → explorer release → Run
workflow) на нужной ветке: workflow возьмёт версию из `pyproject.toml`,
соберёт образ, поставит тег `vX.Y.Z` на голову ветки и выкатит его. Если такой
тег уже есть, он остановится до сборки.

Каждый push (merge) в `develop`, затрагивающий `pyexplorer/`, тоже выкатывается:
сборка получает тег `vX.Y.Z-dev.N` (N — номер запуска workflow), образ
публикуется как `X.Y.Z-dev.N` и `:develop`, `:latest` не трогается. Запуски для
одной ветки идут по очереди, так что более старая сборка не перекроет новую.

Посмотреть состояние сервера без входа на него можно через
`.github/workflows/explorer-ops.yml` (ручной запуск): контейнеры, высота индекса
(и testnet, если он запущен), диск, память, последние сообщения health-check или
хвост логов `indexer`/`api`/`migrate` mainnet и testnet.
Он ходит отдельным ключом `OPS_SSH_KEY`, который на сервере привязан к
`deploy/ops/status.sh` (строка для `authorized_keys` есть в скрипте) и больше
ничего выполнить не может. Репозиторий публичный, лог workflow тоже, поэтому
логи ноды и Caddy (там IP-адреса) скрипт не выводит.

### Мониторинг и бэкап

В `deploy/ops/` лежат два скрипта и их systemd-таймеры:

* `healthcheck.sh` запускается каждые 5 минут. Проверяет, что API отвечает,
  индекс отстаёт от ноды не больше чем на `MAX_LAG` блоков, индексатор недавно
  опрашивал ноду, контейнеры не перезапускаются и на диске есть место.
  Сообщения пишутся в journal (`journalctl -t explorer-health`) и в Telegram,
  если заданы `TELEGRAM_BOT_TOKEN` и `TELEGRAM_CHAT_ID`. Скрипт сообщает
  только о смене состояния.
* `backup.sh` запускается ночью. Делает `pg_dump -Fc` в `/var/backups/explorer`,
  хранит последние `BACKUP_KEEP` дампов и может выгрузить новый командой из
  `BACKUP_UPLOAD`.

Настройки хранятся в `/etc/explorer-ops.env`. Установка:

```bash
cp deploy/ops/explorer-*.{service,timer} /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now explorer-healthcheck.timer explorer-backup.timer
```

Восстановление из дампа (индексатор на это время остановить):

```bash
docker compose --env-file .env stop indexer
docker compose --env-file .env exec -T postgres \
  pg_restore -U explorer -d explorer --clean --if-exists --no-owner < /var/backups/explorer/<dump>
docker compose --env-file .env start indexer
```

После этого индексатор сверит хэш вершины с нодой и доберёт новые блоки сам.

## API (`/api/v1`)

| Маршрут | Что возвращает |
|---|---|
| `GET /status` | высота индекса и ноды; `network` — `mainnet` или `testnet` |
| `GET /blocks?before=&limit=` | последние блоки |
| `GET /blocks/{height или hash}?offset=&limit=` | блок со списком транзакций; у каждой `kinds` — что она делает помимо платежа: `name` (имя на входе или выходе), `return` (выход OP_RETURN), `randpay` (вход randpay), в этом порядке; пустой список — обычный платёж |
| `GET /txs/{txid}` | транзакция: входы с суммами и адресами, выходы с «кем потрачен», операции NVS; `kinds` как у блока; у входа и выхода с именем есть `name` и `name_state`: `active` — имя сейчас на этом выходе, `past` — прошлый выход ещё активного имени, `expired`, `deleted` — имя истекло или удалено |
| `GET /addresses/{addr}` | баланс, получено, отправлено, число транзакций |
| `GET /addresses/{addr}/txs?before=<height:idx>&limit=` | история с курсорной пагинацией |
| `GET /addresses/{addr}/utxo` | непотраченные выходы |
| `GET /addresses/{addr}/multisig?limit=` | мультиподписные выходы, где адрес — один из ключей |
| `GET /richlist?limit=` | крупнейшие адреса; `last_height` и `last_time` — блок и время последней транзакции адреса |
| `GET /names?prefix=dns:&namespace=&after=&include_inactive=&sort=` | список и поиск имён: `sort=new` (по умолчанию) — сначала недавно изменённые, `sort=name` — по алфавиту; `after` — значение `next` прошлой страницы |
| `GET /names/{name}` | текущее значение, владелец, срок действия; у истёкшего имени `expired_time` — время блока, на котором оно истекло |
| `GET /names/{name}/history` | все операции с именем |
| `GET /search?q=` | что это: высота, хэш блока, txid, адрес или имя |
| `GET /mempool?limit=` | неподтверждённые транзакции, новые сначала (из ноды, кэш 10 с) |
| `GET /mempool/{txid}` | неподтверждённая транзакция с входами и выходами |
| `GET /stats/summary` | высота, эмиссия, всего транзакций и адресов, размер цепочки |
| `GET /stats/daily?days=` | итоги по дням (UTC по времени блока), новые сначала |

Суммы отдаются строками (`"12.300000"`). Имена и значения отдаются как
`{"text": …}`, а если это не UTF-8, то как `{"text": null, "base64": …}`. Все
SQL-запросы параметризованы, входные хэши и адреса проверяются по формату.

Ответы `/api/...` идут с `Cache-Control: public, max-age=10`, ошибки 5xx с
`no-store`. Статика подключается как `/static/<файл>?v=<хэш>` и отдаётся с
`max-age` на год и `immutable`: при изменении файла меняется хэш, а значит и адрес.
Все страницы и API отвечают и на `HEAD` (те же заголовки, что у `GET`, без тела).

### Старое API (`/api/...`)

Для внешних потребителей PHP-версии сохранены маршруты из её `/api/help`:
`/api/block/{hash|height}/<x>[/less|/full]`, `/api/block/latest[/less|/full]`,
`/api/tx/hash/<txid>[/less|/full]`, `/api/address/balance/<addr>[/less|/full]`,
`/api/address/isvalid/<addr>`, `/api/stats/block_height`,
`/api/stats/coin_supply[/txt]`. Код в `src/emcexplorer/api/legacy.py`.

Формат ответов как у PHP-версии:
* все значения строками;
* суммы без хвостовых нулей (`"12.5"`);
* ошибки — `{"error": ...}` с кодом 200;
* JSON с отступами.

`coin_supply` берётся из `supply` последнего блока, без обращения к ноде.

Отличия от PHP-версии:
* Поля, которых в новом индексе нет, равны `null`: coin-days destroyed,
  счётчики адресов по блокам, `entropybit`, `modifierchecksum`, `asm`,
  `reqsigs`, скрипты входов.
* `/api/address/balance/<addr>/full` отдаёт 1000 последних транзакций, а не все.
* `/api/address/isvalid` проверяет адрес локально (base58check с префиксами
  mainnet `E`/`e` и bech32 `em1...`), без обращения к ноде.
* Графики (`/api/stats/{known_addresses,addresses_in_use,empty_addresses,chain_size}/{30days,60days}`)
  строятся по `daily_stats`. Число адресов с балансом индексатор замеряет
  только для текущего дня, поэтому `addresses_in_use` и `empty_addresses`
  накапливаются с момента выкатки.
* `/api/address/ismine` не поддерживается.

## Тесты

```bash
uv run pytest tests/test_nvs.py                          # без БД
TEST_DATABASE_URL=postgresql://…/emc_test uv run pytest  # ВНИМАНИЕ: схема public в этой БД пересоздаётся
```

`tests/fakenode.py` имитирует JSON-RPC ноды через `httpx.MockTransport`.
Интеграционные тесты строят синтетические цепочки (PoW и PoS блоки, coinstake,
randpay, `name_new`/`name_update`) и проверяют балансы, эмиссию, NVS, API,
реорганизацию, смену цепочки во время предзагрузки и ограничение глубины отката.

## Что ещё не сделано

* **Сверка на testnet.** Mainnet проиндексирован с нуля и сверен с нодой
  (эмиссия, число транзакций, имена), см. [docs/vps-run.md](docs/vps-run.md).
  Стек для testnet готов (см. «Testnet»), сверить его с нодой предстоит после
  первого запуска.
  Первичная синхронизация занимает около двух часов, поэтому запись
  пачками пока не нужна.
* Кэш в приложении. Пока не понадобился: запросы идут по индексам. Ответы
  помечаются для HTTP-кэша (см. «API»). Если в логах появятся медленные
  запросы, первыми кандидатами будут старые `/api/address/balance/<addr>` и
  `/full` для адресов с огромной историей: счётчики там считаются по всей истории.
