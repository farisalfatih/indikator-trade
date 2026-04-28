-- ══════════════════════════════════════════════════════════════════════════
--  Crypto Trading Pipeline — Initial Schema (Binance BTCUSDT Kline 5m)
--  Source: Binance WebSocket + REST API
-- ══════════════════════════════════════════════════════════════════════════

-- 1. Raw kline data (OHLCV) dari Binance
CREATE TABLE IF NOT EXISTS raw_ticker_btcusdt (
    id           SERIAL PRIMARY KEY,
    time         BIGINT NOT NULL UNIQUE,          -- kline open_time (epoch seconds)
    open_time    BIGINT NOT NULL,                 -- kline open_time (ms, original from Binance)
    open         DECIMAL(20,8) NOT NULL,
    high         DECIMAL(20,8) NOT NULL,
    low          DECIMAL(20,8) NOT NULL,
    close        DECIMAL(20,8) NOT NULL,
    volume       DECIMAL(20,8) NOT NULL,          -- base asset volume (BTC)
    close_time   BIGINT NOT NULL,                 -- kline close_time (ms)
    quote_volume DECIMAL(20,8) NOT NULL,          -- quote asset volume (USDT)
    trades       INT NOT NULL DEFAULT 0,           -- number of trades in the kline
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Technical indicators
CREATE TABLE IF NOT EXISTS raw_indicator_btcusdt (
    id             SERIAL PRIMARY KEY,
    ticker_time    BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
    rsi            DECIMAL(10,4),
    macd           DECIMAL(20,8),
    macd_signal    DECIMAL(20,8),
    macd_histogram DECIMAL(20,8),
    bb_upper       DECIMAL(20,8),
    bb_middle      DECIMAL(20,8),
    bb_lower       DECIMAL(20,8),
    stoch_k        DECIMAL(10,4),
    stoch_d        DECIMAL(10,4),
    psar           DECIMAL(20,8),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker_time)
);

-- 3. Per-indicator signals
CREATE TABLE IF NOT EXISTS raw_signal_btcusdt (
    id           SERIAL PRIMARY KEY,
    ticker_time  BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
    signal_rsi   VARCHAR(10) CHECK (signal_rsi   IN ('BUY','SELL','HOLD')),
    signal_macd  VARCHAR(10) CHECK (signal_macd  IN ('BUY','SELL','HOLD')),
    signal_bb    VARCHAR(10) CHECK (signal_bb    IN ('BUY','SELL','HOLD')),
    signal_stoch VARCHAR(10) CHECK (signal_stoch IN ('BUY','SELL','HOLD')),
    signal_psar  VARCHAR(10) CHECK (signal_psar  IN ('BUY','SELL','HOLD')),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker_time)
);

-- 4. Ensemble (voting) signal
CREATE TABLE IF NOT EXISTS trade_signal_btcusdt (
    id              SERIAL PRIMARY KEY,
    ticker_time     BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
    ensemble_signal VARCHAR(10) CHECK (ensemble_signal IN ('BUY','SELL','HOLD')),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker_time)
);

-- 5. Trade state machine (per strategy)
CREATE TABLE IF NOT EXISTS trade_state_btcusdt (
    id          SERIAL PRIMARY KEY,
    ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
    state_name  VARCHAR(20) NOT NULL CHECK (state_name IN ('rsi','macd','bb','stoch','psar','ensemble')),
    position    VARCHAR(10) CHECK (position IN ('OPEN','CLOSED','NONE')),
    entry_price DECIMAL(20,8),
    exit_price  DECIMAL(20,8),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker_time, state_name)
);

-- 6. ROI per closed trade
CREATE TABLE IF NOT EXISTS raw_roi_btcusdt (
    id          SERIAL PRIMARY KEY,
    ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
    state_name  VARCHAR(20) NOT NULL,
    roi         DECIMAL(10,4),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(ticker_time, state_name)
);

-- 7. Aggregate performance summary
CREATE TABLE IF NOT EXISTS summary_performance_btcusdt (
    id                    INT PRIMARY KEY DEFAULT 1,  -- single-row UPSERT, selalu id=1
    calculated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_trades          INT NOT NULL DEFAULT 0,
    win_rate              DECIMAL(5,2),
    avg_roi               DECIMAL(10,4),
    profit_factor         DECIMAL(10,4),
    max_drawdown          DECIMAL(10,4),
    per_state_performance JSONB,
    CONSTRAINT chk_single_row CHECK (id = 1)
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_ticker_time     ON raw_ticker_btcusdt(time DESC);
CREATE INDEX IF NOT EXISTS idx_indicator_time  ON raw_indicator_btcusdt(ticker_time DESC);
CREATE INDEX IF NOT EXISTS idx_signal_time     ON raw_signal_btcusdt(ticker_time DESC);
CREATE INDEX IF NOT EXISTS idx_tradesig_time   ON trade_signal_btcusdt(ticker_time DESC);
CREATE INDEX IF NOT EXISTS idx_tradestate_name ON trade_state_btcusdt(state_name, ticker_time DESC);
CREATE INDEX IF NOT EXISTS idx_roi_state       ON raw_roi_btcusdt(state_name);
-- tambahan: raw_roi ORDER BY ticker_time (dipakai max drawdown & per-state GROUP BY)
CREATE INDEX IF NOT EXISTS idx_roi_time        ON raw_roi_btcusdt(ticker_time ASC);
-- tambahan: composite untuk DISTINCT ON di updateTradeState
CREATE INDEX IF NOT EXISTS idx_tradestate_composite ON trade_state_btcusdt(state_name, ticker_time DESC, position, entry_price);
