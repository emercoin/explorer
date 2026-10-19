# Развёртывание на сервере и сверка mainnet (14.10.2026)

Полная синхронизация индекса с нуля и сверка результата с нодой.

| | |
|---|---|
| Нода | `emercoin/core:0.8.5`, txindex включён |
| Цепочка | 816 138 блоков, 1 981 643 транзакции, 260 753 NVS-операции |

### Отличия от `deploy/docker-compose.yaml`

Если на хосте уже есть нода и веб-сервер, стек поднимается с
`deploy/docker-compose.override.yaml` (не в git; шаблон —
`docker-compose.override.example.yaml`):

* своя нода (`emc`) и Caddy выключены через `profiles`;
* `indexer` и `api` ходят в RPC существующей ноды по отдельной docker-сети;
  порт RPC на хост не публикуется;
* `api` слушает только `127.0.0.1:8000`, наружу его отдаёт веб-сервер хоста;
* у PostgreSQL ограничена память и подобраны параметры (см. ниже).

### Testnet

Тот же стек с профилем `testnet` (см. «Testnet» в README): своя нода testnet,
база `explorer_testnet` в том же PostgreSQL, `indexer-testnet`, `api-testnet` на
`127.0.0.1:8001`. Запуск, в `/opt/explorer/pyexplorer/deploy`:

1. `docker volume create emer_explorer_testnet_chain`.
2. `emercoin.conf`: если своя нода mainnet выключена, файла может не быть.
   Если его нет: `cp emercoin.conf.example emercoin.conf`, `rpcpassword` такой же,
   как `EMC_RPC_PASSWORD` в `.env`. Без файла docker создаст на его месте каталог,
   и нода не стартует.
3. В `docker-compose.override.yaml` добавить блок testnet из
   `docker-compose.override.example.yaml`: лимиты памяти и порт 8001.
4. В `.env`:
   ```
   COMPOSE_PROFILES=testnet
   EXPLORER_NETWORKS=mainnet=https://explorer3.emercoin.com,testnet=https://testnet3.emercoin.com
   ```
5. На веб-сервере хоста — сайт testnet на порт 8001, например для nginx:
   ```nginx
   server {
       listen 80;
       server_name testnet3.emercoin.com;
       location / {
           proxy_pass http://127.0.0.1:8001;
           proxy_set_header Host $host;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```
   `nginx -t && systemctl reload nginx`.
6. `docker compose --env-file .env up -d`: создаются база и схема, нода начинает
   синхронизацию, индексатор идёт за ней. `api` и `indexer` mainnet при этом
   пересоздаются (у них появилась `EXPLORER_NETWORKS`), это несколько секунд.

Ход синхронизации и память видны в explorer ops → `status` (разделы
`index testnet` и `memory`). Если памяти перестанет хватать, testnet выключается удалением
`COMPOSE_PROFILES` из `.env` и `docker compose --env-file .env up -d --remove-orphans`.

## Итоги сверки

| Проверка | Результат |
|---|---|
| `supply` против `moneysupply` ноды | **совпадает до 1e-6 EMC** на всех 2755 высотах 813383…816137, на вершине 56 096 504.665112 EMC |
| `mint` ноды против `Σвыходов − Σвходов + комиссии` | совпадает во всех 816 138 блоках, так что эмиссия верна и ниже 813383 |
| Число транзакций | 1 981 643, как счётчик `tx=` в `UpdateTip` ноды |
| Дубли txid (PK `transactions`) | не встретились |
| `IndexCorrupted`, неожиданные поля JSON | не было |
| Имена против `name_show <name> base64` | 200 случайных + **все 1207 не-ASCII**: value, address, txid/vout, expires_at совпадают |
| Код ошибки `name_show` для несуществующего имени | `-4` (`failed to read from name DB`), как в `RPC_WALLET_ERROR` |
| Имена в `name_ops`, которых нет в `names` | 18: 16 не-UTF-8 (`name_show` их не принимает) и 2, которых нет в базе имён самой ноды (`name_filter` их тоже не видит) |

`moneysupply` ноды на нужной высоте взят из её `debug.log` (строки `UpdateTip`).
RPC, возвращающего его для произвольного блока, нет: `getblock` и `getblockstats`
этого поля не содержат. Лог покрывает высоты от 813383. Для более ранних
блоков проверялось поле `mint`. При реорге в лог попадают оба варианта
блока, поэтому брать надо последнее значение для высоты. На 814232 был реорг
на один блок.

## Найденные ошибки (исправлены в этой ветке)

1. **Имя с байтом NUL роняло индексатор.** В mainnet есть 7 таких имён
   (`b"dns:test\x00"`, `b"\x00"`, …). Это корректный UTF-8, но в колонку
   `TEXT` PostgreSQL NUL не принимает (`CharacterNotInRepertoireError`). Индексатор
   падал сразу после выхода на вершину и уходил в перезапуски. Теперь у таких
   имён `name_text = NULL` (`nvs.as_db_text`), как у не-UTF-8, а значение и
   история сохраняются.
2. **`name_show` не находил имена с не-ASCII символами.** `json.dumps` по
   умолчанию шлёт `\uXXXX`, а emercoind 0.8.5 оставляет от такой escape-последовательности
   младший байт: `"dns:\u263c.coin"` (☼) читается как `"dns:<.coin"`. В
   итоге 1153 имени считались несуществующими, а 70 получили состояние
   *чужого* имени. Теперь RPC-запросы отправляются в сыром UTF-8
   (`RpcClient._post`). Кроме того, ответ `name_show` записывается только если нода
   ответила про то же имя (`store._same_name`).
3. **Особенность пакетных ответов ноды.** В ответе на batch-запрос emercoind
   экранирует каждый байт ≥ 0x80 отдельно (`"ü"` → `"\u00C3\u00BC"`), и имя
   приходит в Latin-1-кракозябрах. Одиночный ответ приходит в нормальном UTF-8.
   `_same_name` понимает оба варианта. Fakenode в тестах воспроизводит оба
   поведения ноды.
4. **Список имён по префиксу читал всю таблицу.** Индекс `text_pattern_ops` не
   работает для `ORDER BY name_text`. Заменён на индекс `(name_text COLLATE "C")`,
   запрос тоже идёт в `COLLATE "C"`: `/names?prefix=dns:` ускорился с 68 до 2 мс.
   Сортировка списка теперь побайтовая.

## Размеры

| | |
|---|---|
| База `explorer` (`pg_database_size`) | **3.3 GB** |
| Том PostgreSQL целиком | 5.6 GB (из них WAL 2 GB при `max_wal_size=2GB`) |
| Данные ноды (с txindex) | 1.3 GB |

| Таблица | всего | heap | строк |
|---|---|---|---|
| outputs | 1019 MB | 664 MB | 3.99 M |
| address_txs | 620 MB | 325 MB | 2.71 M |
| inputs | 595 MB | 398 MB | 2.66 M |
| transactions | 425 MB | 238 MB | 1.98 M |
| blocks | 331 MB | 236 MB | 816 K |
| address_stats | 142 MB | 76 MB | 688 K |
| name_ops | 111 MB | 66 MB | 261 K |
| names | 57 MB | 31 MB | 127 K |

## Параметры PostgreSQL

Задаются в `docker-compose.override.yaml` под память хоста (пример — в
`docker-compose.override.example.yaml`). Кроме размеров памяти:

| Параметр | Значение | Зачем |
|---|---|---|
| `random_page_cost` | 1.1 | SSD: планировщик охотнее берёт индексы |
| `max_wal_size` / `checkpoint_timeout` | 2GB / 15min на время первичной загрузки, потом 512MB / 15min | реже checkpoint при массовой загрузке |
| `wal_compression` | on | |
| `synchronous_commit` | off | индексатор продолжает с последнего блока, потеря последних коммитов при сбое безопасна |

Для `outputs` и `address_stats` (в них каждый блок пишет UPDATE) схема
выставляет `autovacuum_vacuum_scale_factor = 0.02`.

## Что дальше

* **Ускорение первичной загрузки** (запись пачками по N блоков, индексы после
  загрузки) на этих объёмах не нужно: она занимает около двух часов.
* Полную переиндексацию (смена схемы, ошибка в разборе) удобнее делать на
  отдельной машине и разворачивать дамп (`pg_dump -Fc` / `pg_restore`). Индексатор
  продолжит с вершины дампа, сверив её хэш с нодой.
* Ошибки emercoind (сверено с develop @ c22419a и `v0.8.6rc0emc`):
  * разбор `\uXXXX` в запросах исправлен в develop (`33a3be7`, есть в
    `v0.8.6rc0emc`): escape-последовательности от `\u0100` декодируются в UTF-8.
    Но `\u0080`–`\u00FF` по-прежнему читаются как отдельные байты (так задумано
    для совместимости с json_spirit), поэтому сырой UTF-8 в `RpcClient` нужен и с
    новой нодой;
  * пакетные ответы в develop по-прежнему пишутся legacy-кодировщиком
    (`JSONRPCExecBatch`), а одиночные нет. `store._same_name` это учитывает.
