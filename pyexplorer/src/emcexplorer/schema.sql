-- Emercoin explorer schema (PostgreSQL 13+).
--
-- Rules the indexer relies on:
--  * every row that belongs to a block references blocks(height) with
--    ON DELETE CASCADE, so a reorg rollback is "DELETE FROM blocks WHERE height > $1"
--    plus undoing the few aggregates that are not cascaded (spent marks, address_stats);
--  * amounts are BIGINT in the smallest unit (1 EMC = 1 000 000), never floating point;
--  * hashes are BYTEA in RPC (display) byte order.

CREATE TABLE IF NOT EXISTS meta (
    key   TEXT PRIMARY KEY,
    value JSONB NOT NULL
);

CREATE TABLE IF NOT EXISTS blocks (
    height      INTEGER PRIMARY KEY,
    hash        BYTEA   NOT NULL UNIQUE,
    prev_hash   BYTEA,
    time        BIGINT  NOT NULL,
    mediantime  BIGINT,
    version     INTEGER NOT NULL,
    merkleroot  BYTEA   NOT NULL,
    bits        TEXT    NOT NULL,
    nonce       BIGINT  NOT NULL,
    difficulty  NUMERIC NOT NULL,
    size        INTEGER NOT NULL,
    is_pos      BOOLEAN NOT NULL,
    flags       TEXT    NOT NULL,
    proofhash   BYTEA,
    modifier    TEXT,
    is_auxpow   BOOLEAN NOT NULL DEFAULT FALSE,
    mint        BIGINT  NOT NULL,           -- newly created coins, as reported by the node
    tx_count    INTEGER NOT NULL,
    value_out   BIGINT  NOT NULL,           -- sum of all outputs in the block
    fees        BIGINT  NOT NULL,           -- fees paid by ordinary transactions (burned in PoS coins)
    supply      BIGINT  NOT NULL            -- coins in existence after this block
);
CREATE INDEX IF NOT EXISTS blocks_time ON blocks (time);

CREATE TABLE IF NOT EXISTS transactions (
    txid         BYTEA   PRIMARY KEY,
    height       INTEGER NOT NULL REFERENCES blocks (height) ON DELETE CASCADE,
    idx          INTEGER NOT NULL,          -- position in the block
    version      INTEGER NOT NULL,
    time         BIGINT,                    -- PPCoin-style transaction timestamp (nTime)
    locktime     BIGINT  NOT NULL,
    size         INTEGER NOT NULL,
    is_coinbase  BOOLEAN NOT NULL,
    is_coinstake BOOLEAN NOT NULL,
    value_in     BIGINT  NOT NULL,
    value_out    BIGINT  NOT NULL,
    fee          BIGINT  NOT NULL,          -- 0 for coinbase and coinstake
    UNIQUE (height, idx)
);

CREATE TABLE IF NOT EXISTS outputs (
    txid         BYTEA   NOT NULL REFERENCES transactions (txid) ON DELETE CASCADE,
    n            INTEGER NOT NULL,
    height       INTEGER NOT NULL,
    value        BIGINT  NOT NULL,
    script_type  TEXT,
    address      TEXT,                      -- first address only; NULL for nonstandard/OP_RETURN
    script       BYTEA   NOT NULL,
    spent_txid   BYTEA,
    spent_n      INTEGER,
    spent_height INTEGER,
    PRIMARY KEY (txid, n)
);
CREATE INDEX IF NOT EXISTS outputs_unspent_by_address
    ON outputs (address) WHERE spent_txid IS NULL AND address IS NOT NULL;
CREATE INDEX IF NOT EXISTS outputs_spent_height ON outputs (spent_height) WHERE spent_height IS NOT NULL;

CREATE TABLE IF NOT EXISTS inputs (
    txid       BYTEA   NOT NULL REFERENCES transactions (txid) ON DELETE CASCADE,
    n          INTEGER NOT NULL,
    prev_txid  BYTEA,                       -- NULL for coinbase
    prev_n     INTEGER,
    coinbase   BYTEA,
    is_randpay BOOLEAN NOT NULL DEFAULT FALSE,
    sequence   BIGINT  NOT NULL,
    value      BIGINT  NOT NULL,            -- copied from the spent output at index time
    address    TEXT,
    PRIMARY KEY (txid, n)
);

-- One row per (address, transaction): the address history and the source for
-- address_stats.
CREATE TABLE IF NOT EXISTS address_txs (
    address  TEXT    NOT NULL,
    height   INTEGER NOT NULL REFERENCES blocks (height) ON DELETE CASCADE,
    idx      INTEGER NOT NULL,
    txid     BYTEA   NOT NULL,
    received BIGINT  NOT NULL,
    sent     BIGINT  NOT NULL,
    PRIMARY KEY (address, height, idx)
);
CREATE INDEX IF NOT EXISTS address_txs_height ON address_txs (height);

CREATE TABLE IF NOT EXISTS address_stats (
    address      TEXT PRIMARY KEY,
    balance      BIGINT  NOT NULL,
    received     BIGINT  NOT NULL,
    sent         BIGINT  NOT NULL,
    tx_count     INTEGER NOT NULL,
    first_height INTEGER NOT NULL,
    last_height  INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS address_stats_balance ON address_stats (balance DESC);
CREATE INDEX IF NOT EXISTS address_stats_first_height ON address_stats (first_height);

-- Every name operation found in the chain (decoded from output scripts).
CREATE TABLE IF NOT EXISTS name_ops (
    txid    BYTEA    NOT NULL REFERENCES transactions (txid) ON DELETE CASCADE,
    n       INTEGER  NOT NULL,
    height  INTEGER  NOT NULL,
    idx     INTEGER  NOT NULL,
    op      SMALLINT NOT NULL,              -- 1 name_new, 2 name_update, 3 name_delete
    name    BYTEA    NOT NULL,
    value   BYTEA    NOT NULL,
    days    INTEGER  NOT NULL,
    address TEXT,
    PRIMARY KEY (txid, n)
);
CREATE INDEX IF NOT EXISTS name_ops_name ON name_ops (name, height DESC, idx DESC);
CREATE INDEX IF NOT EXISTS name_ops_height ON name_ops (height);

-- Current state of each name, as reported by the node's own name index
-- (`name_show`), so expiry rules stay the node's responsibility.
CREATE TABLE IF NOT EXISTS names (
    name       BYTEA   PRIMARY KEY,
    name_text  TEXT,                        -- NULL if not valid UTF-8 or contains NUL
    namespace  TEXT,
    value      BYTEA   NOT NULL,
    address    TEXT,
    txid       BYTEA   NOT NULL,
    vout       INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,            -- block height
    deleted    BOOLEAN NOT NULL,
    time       BIGINT
);
-- Byte order ("C"): one index serves both `LIKE 'prefix%'` and ORDER BY of the
-- /names listing (a text_pattern_ops index cannot do the ORDER BY).
DROP INDEX IF EXISTS names_text_prefix;
CREATE INDEX IF NOT EXISTS names_text_c ON names ((name_text COLLATE "C"));
CREATE INDEX IF NOT EXISTS names_namespace ON names (namespace);
CREATE INDEX IF NOT EXISTS names_address ON names (address);

-- Names whose state must be re-read from the node (touched by a new or a
-- rolled-back block). Persisted so a crash does not lose the work.
CREATE TABLE IF NOT EXISTS names_dirty (
    name BYTEA PRIMARY KEY
);

-- Per-day totals (UTC days by block time) for the statistics page and the old
-- chart API. Derived from the tables above and rebuilt from them (indexer/stats.py),
-- so it needs no undo on reorgs: the days from the fork on are recomputed.
CREATE TABLE IF NOT EXISTS daily_stats (
    day              DATE    PRIMARY KEY,
    first_height     INTEGER NOT NULL,
    last_height      INTEGER NOT NULL,
    blocks           INTEGER NOT NULL,
    pos_blocks       INTEGER NOT NULL,
    size             BIGINT  NOT NULL,      -- bytes of all blocks of the day
    txs              INTEGER NOT NULL,      -- all transactions, coinbase and coinstake included
    payments         INTEGER NOT NULL,      -- transactions that are neither coinbase nor coinstake
    volume           BIGINT  NOT NULL,      -- outputs of those payments
    fees             BIGINT  NOT NULL,
    mint             BIGINT  NOT NULL,
    supply           BIGINT  NOT NULL,      -- after the last block of the day
    difficulty_pow   NUMERIC,               -- average over the day's PoW blocks
    difficulty_pos   NUMERIC,
    new_addresses    INTEGER NOT NULL,      -- addresses first seen that day
    active_addresses INTEGER NOT NULL,      -- addresses in any transaction that day
    funded_addresses INTEGER                -- addresses with a balance, sampled while the day
                                            -- was the latest one; NULL for days before that
);

-- Every block marks older outputs as spent and bumps address totals, so these
-- two tables collect dead rows steadily; vacuum them before they bloat.
ALTER TABLE outputs SET (autovacuum_vacuum_scale_factor = 0.02, autovacuum_analyze_scale_factor = 0.02);
ALTER TABLE address_stats SET (autovacuum_vacuum_scale_factor = 0.02, autovacuum_analyze_scale_factor = 0.02);
