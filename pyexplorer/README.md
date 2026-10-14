# emcexplorer: каркас нового эксплорера Emercoin на Python

Индексатор блокчейна и HTTP API поверх PostgreSQL. Задуман как замена PHP-коду
из `../html` и `../tools`. Веб-интерфейса пока нет.

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
emercoind (RPC) ──► indexer ──► PostgreSQL ◄── api (FastAPI) ◄── фронтенд / внешние сервисы
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
* **NVS** работает в два слоя:
  * `name_ops`: история операций. Индексатор сам разбирает скрипты
    (`nvs.py`, порт `DecodeNameScript()` из `src/script/script.cpp`);
  * `names`: текущее состояние имени. Его отдаёт нода через `name_show`, поэтому
    правила срока действия и приёма операций (`CalculateExpiresAt`, «только
    первый `name_new` в блоке» и т.п.) остаются за нодой и не дублируются здесь.
    Затронутые имена копятся в `names_dirty` и перечитываются пачками, когда
    индекс догнал ноду.

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
uv run emcexplorer api --host 0.0.0.0 --port 8000   # документация API: /docs
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
Образ `emercoin/explorer` собирается из `Dockerfile` двумя стадиями: uv ставит
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

`getinfo` не вызывайте: emercoind 0.8.5 с `disablewallet=1` падает на нём по
SIGSEGV. Для проверки ноды есть `getblockchaininfo`.

### Релизы

Тег `vX.Y.Z` (версия должна совпадать с `pyproject.toml`) запускает
`.github/workflows/explorer-release.yml`: тесты, затем публикация
`emercoin/explorer:X.Y.Z` и `:latest` в Docker Hub, затем выкатка по SSH.
На сервере выполняется `deploy/deploy-service.sh vX.Y.Z`: он переключает
checkout на тот же тег (чтобы compose и Caddyfile совпадали с образом),
записывает версию в `.env`, применяет схему и пересоздаёт `indexer` и `api`.
Секреты перечислены в шапке workflow. Ключ CI на сервере стоит ограничить
одной командой через `command=` в `authorized_keys`; пример есть в скрипте.

## API (`/api/v1`)

| Маршрут | Что возвращает |
|---|---|
| `GET /status` | высота индекса и ноды |
| `GET /blocks?before=&limit=` | последние блоки |
| `GET /blocks/{height или hash}?offset=&limit=` | блок со списком транзакций |
| `GET /txs/{txid}` | транзакция: входы с суммами и адресами, выходы с «кем потрачен», операции NVS |
| `GET /addresses/{addr}` | баланс, получено, отправлено, число транзакций |
| `GET /addresses/{addr}/txs?before=<height:idx>&limit=` | история с курсорной пагинацией |
| `GET /addresses/{addr}/utxo` | непотраченные выходы |
| `GET /richlist?limit=` | крупнейшие адреса |
| `GET /names?prefix=dns:&namespace=&after=&include_inactive=` | список и поиск имён |
| `GET /names/{name}` | текущее значение, владелец, срок действия |
| `GET /names/{name}/history` | все операции с именем |
| `GET /search?q=` | что это: высота, хэш блока, txid, адрес или имя |

Суммы отдаются строками (`"12.300000"`). Имена и значения отдаются как
`{"text": …}`, а если это не UTF-8, то как `{"text": null, "base64": …}`. Все
SQL-запросы параметризованы, входные хэши и адреса проверяются по формату.

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

* **Прогон на testnet.** Mainnet проиндексирован с нуля и сверен с нодой
  (эмиссия, число транзакций, имена), см. [docs/vps-run.md](docs/vps-run.md).
  Первичная синхронизация на одном ядре занимает около 1 ч 46 мин, поэтому запись
  пачками пока не нужна.
* **Совместимость со старым API.** Нужно решить, какие маршруты `/api/...` из `html/api.php`
  нужны внешним потребителям, и сделать для них адаптер.
* **Мультиадресные выходы.** Пока сохраняется только первый адрес выхода (как в PHP-версии).
* Мемпул, графики и статистика (агрегаты по дням), кэширование, фронтенд,
  Docker/systemd для развёртывания.
