<?php
/**
 * Crypto Trading Pipeline — Binance BTCUSDT Kline 5m
 *
 * Data source:
 *   - REST API  : https://api.binance.com/api/v3/klines?symbol=BTCUSDT&interval=5m&limit=500
 *                 (dipakai saat pertama kali run untuk seeding 500 data awal)
 *   - WebSocket : wss://stream.binance.com:9443/ws/btcusdt@kline_5m
 *                 (streaming real-time, proses saat kline CLOSED)
 *
 * Binance WebSocket best practices:
 *   - Proactive reconnect setelah 23 jam (82800 detik)
 *   - Ping/pong handling otomatis oleh library
 *   - Exponential backoff saat disconnect
 *   - Timeout handling (connect, read)
 *
 * Indikator dihitung dari 500 data terakhir.
 */

declare(strict_types=1);

use WebSocket\Client;
use WebSocket\ConnectionException;

require_once __DIR__ . '/vendor/autoload.php';

// ── Konfigurasi Database (dari environment) ──────────────────────────────────
define('DB_HOST', getenv('DB_HOST') ?: 'postgres');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'trading_db');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: 'postgres');

// ── Konfigurasi Binance ─────────────────────────────────────────────────────
define('BINANCE_SYMBOL',    getenv('BINANCE_SYMBOL') ?: 'BTCUSDT');
define('BINANCE_INTERVAL',  getenv('BINANCE_INTERVAL') ?: '5m');
define('BINANCE_KLINE_LIMIT', (int)(getenv('BINANCE_KLINE_LIMIT') ?: 500));
define('BINANCE_REST_URL',  'https://api.binance.com/api/v3/klines');
define('BINANCE_WS_URL',    getenv('BINANCE_WS_URL') ?: 'wss://stream.binance.com:9443/ws/btcusdt@kline_5m');
define('SESSION_MAX',       (int)(getenv('BINANCE_WS_SESSION_MAX') ?: 82800)); // 23 jam

// ── Konfigurasi Indicator ─────────────────────────────────────────────────────
define('INDICATOR_WINDOW', (int)(getenv('INDICATOR_WINDOW') ?: 500));
define('RSI_PERIOD',       (int)(getenv('RSI_PERIOD') ?: 14));
define('MACD_FAST',        (int)(getenv('MACD_FAST') ?: 12));
define('MACD_SLOW',        (int)(getenv('MACD_SLOW') ?: 26));
define('MACD_SIGNAL',      (int)(getenv('MACD_SIGNAL') ?: 9));
define('BB_PERIOD',        (int)(getenv('BB_PERIOD') ?: 20));
define('BB_STDDEV',        (float)(getenv('BB_STDDEV') ?: 2.0));
define('STOCH_K_PERIOD',   (int)(getenv('STOCH_K_PERIOD') ?: 14));
define('STOCH_D_PERIOD',   (int)(getenv('STOCH_D_PERIOD') ?: 3));

// ── Global DB handle ─────────────────────────────────────────────────────────
$pdo = null;

// ─────────────────────────────────────────────────────────────────────────────
//  LOGGING
// ─────────────────────────────────────────────────────────────────────────────

function log_msg(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

// ─────────────────────────────────────────────────────────────────────────────
//  EXPONENTIAL BACKOFF
// ─────────────────────────────────────────────────────────────────────────────

function backoff_sleep(int $attempt, string $tag): void
{
    $maxDelay = 30;
    $delay    = min(2 ** $attempt, $maxDelay);
    $jitter   = mt_rand(0, (int)($delay * 1000)) / 1000;
    $total    = $delay + $jitter;
    log_msg("[{$tag}] Reconnect in {$total}s (attempt {$attempt})...");
    sleep((int)$total);
}

// ─────────────────────────────────────────────────────────────────────────────
//  DATABASE
// ─────────────────────────────────────────────────────────────────────────────

function initDatabase(): void
{
    global $pdo;

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);

    $attempts = 0;
    while (true) {
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            break;
        } catch (PDOException $e) {
            $attempts++;
            log_msg("DB connection failed (attempt {$attempts}): " . $e->getMessage());
            if ($attempts >= 10) {
                die("Cannot connect to database after {$attempts} attempts. Exiting.\n");
            }
            sleep(5);
        }
    }

    createTablesIfNotExists();
    log_msg('Database ready.');
}

function createTablesIfNotExists(): void
{
    global $pdo;

    $queries = [
        // 1. Raw kline data
        "CREATE TABLE IF NOT EXISTS raw_ticker_btcusdt (
            id           SERIAL PRIMARY KEY,
            time         BIGINT NOT NULL UNIQUE,
            open_time    BIGINT NOT NULL,
            open         DECIMAL(20,8) NOT NULL,
            high         DECIMAL(20,8) NOT NULL,
            low          DECIMAL(20,8) NOT NULL,
            close        DECIMAL(20,8) NOT NULL,
            volume       DECIMAL(20,8) NOT NULL,
            close_time   BIGINT NOT NULL,
            quote_volume DECIMAL(20,8) NOT NULL,
            trades       INT NOT NULL DEFAULT 0,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        // 2. Technical indicators
        "CREATE TABLE IF NOT EXISTS raw_indicator_btcusdt (
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
        )",

        // 3. Per-indicator signals
        "CREATE TABLE IF NOT EXISTS raw_signal_btcusdt (
            id           SERIAL PRIMARY KEY,
            ticker_time  BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
            signal_rsi   VARCHAR(10) CHECK (signal_rsi  IN ('BUY','SELL','HOLD')),
            signal_macd  VARCHAR(10) CHECK (signal_macd IN ('BUY','SELL','HOLD')),
            signal_bb    VARCHAR(10) CHECK (signal_bb   IN ('BUY','SELL','HOLD')),
            signal_stoch VARCHAR(10) CHECK (signal_stoch IN ('BUY','SELL','HOLD')),
            signal_psar  VARCHAR(10) CHECK (signal_psar IN ('BUY','SELL','HOLD')),
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time)
        )",

        // 4. Ensemble signal
        "CREATE TABLE IF NOT EXISTS trade_signal_btcusdt (
            id              SERIAL PRIMARY KEY,
            ticker_time     BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
            ensemble_signal VARCHAR(10) CHECK (ensemble_signal IN ('BUY','SELL','HOLD')),
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time)
        )",

        // 5. Trade state machine
        "CREATE TABLE IF NOT EXISTS trade_state_btcusdt (
            id          SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
            state_name  VARCHAR(20) NOT NULL CHECK (state_name IN ('rsi','macd','bb','stoch','psar','ensemble')),
            position    VARCHAR(10) CHECK (position IN ('OPEN','CLOSED','NONE')),
            entry_price DECIMAL(20,8),
            exit_price  DECIMAL(20,8),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time, state_name)
        )",

        // 6. ROI per closed trade
        "CREATE TABLE IF NOT EXISTS raw_roi_btcusdt (
            id          SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcusdt(time) ON DELETE CASCADE,
            state_name  VARCHAR(20) NOT NULL,
            roi         DECIMAL(10,4),
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time, state_name)
        )",

        // 7. Aggregate performance summary (single-row UPSERT, selalu id=1)
        "CREATE TABLE IF NOT EXISTS summary_performance_btcusdt (
            id                    INT PRIMARY KEY DEFAULT 1,
            calculated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total_trades          INT NOT NULL DEFAULT 0,
            win_rate              DECIMAL(5,2),
            avg_roi               DECIMAL(10,4),
            profit_factor         DECIMAL(10,4),
            max_drawdown          DECIMAL(10,4),
            per_state_performance JSONB,
            CONSTRAINT chk_single_row CHECK (id = 1)
        )",

        // Indexes
        "CREATE INDEX IF NOT EXISTS idx_ticker_time      ON raw_ticker_btcusdt(time DESC)",
        "CREATE INDEX IF NOT EXISTS idx_indicator_time   ON raw_indicator_btcusdt(ticker_time DESC)",
        "CREATE INDEX IF NOT EXISTS idx_signal_time      ON raw_signal_btcusdt(ticker_time DESC)",
        "CREATE INDEX IF NOT EXISTS idx_tradesig_time    ON trade_signal_btcusdt(ticker_time DESC)",
        "CREATE INDEX IF NOT EXISTS idx_tradestate_name  ON trade_state_btcusdt(state_name, ticker_time DESC)",
        "CREATE INDEX IF NOT EXISTS idx_roi_state        ON raw_roi_btcusdt(state_name)",
        "CREATE INDEX IF NOT EXISTS idx_roi_time         ON raw_roi_btcusdt(ticker_time ASC)",
        "CREATE INDEX IF NOT EXISTS idx_tradestate_composite ON trade_state_btcusdt(state_name, ticker_time DESC, position, entry_price)",
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  BINANCE REST API — INITIAL DATA SEED (500 klines)
// ─────────────────────────────────────────────────────────────────────────────

function fetchInitialKlines(): array
{
    $url = BINANCE_REST_URL . '?' . http_build_query([
        'symbol'   => BINANCE_SYMBOL,
        'interval' => BINANCE_INTERVAL,
        'limit'    => BINANCE_KLINE_LIMIT,
    ]);

    log_msg("[REST] Fetching " . BINANCE_KLINE_LIMIT . " initial klines from Binance...");

    $ctx  = stream_context_create(['http' => ['timeout' => 30]]);
    $json = @file_get_contents($url, false, $ctx);

    if ($json === false) {
        log_msg("[REST] Failed to fetch initial klines!");
        return [];
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        log_msg("[REST] Invalid response from Binance API.");
        return [];
    }

    log_msg("[REST] Received " . count($data) . " klines.");

    // Parse Binance kline array format:
    // [0] open_time, [1] open, [2] high, [3] low, [4] close,
    // [5] volume, [6] close_time, [7] quote_volume, [8] trades, ...
    $klines = [];
    foreach ($data as $k) {
        $klines[] = [
            'open_time'    => (int)$k[0],
            'open'         => (float)$k[1],
            'high'         => (float)$k[2],
            'low'          => (float)$k[3],
            'close'        => (float)$k[4],
            'volume'       => (float)$k[5],
            'close_time'   => (int)$k[6],
            'quote_volume' => (float)$k[7],
            'trades'       => (int)$k[8],
            // time dalam detik (epoch) sebagai primary key
            'time'         => (int)((int)$k[0] / 1000),
        ];
    }

    return $klines;
}

function seedDatabase(array $klines): int
{
    global $pdo;

    if (empty($klines)) return 0;

    $stmt = $pdo->prepare("
        INSERT INTO raw_ticker_btcusdt
            (time, open_time, open, high, low, close, volume, close_time, quote_volume, trades)
        VALUES
            (:time, :open_time, :open, :high, :low, :close, :volume, :close_time, :quote_volume, :trades)
        ON CONFLICT (time) DO NOTHING
    ");

    $inserted = 0;
    foreach ($klines as $k) {
        $stmt->execute([
            ':time'         => $k['time'],
            ':open_time'    => $k['open_time'],
            ':open'         => $k['open'],
            ':high'         => $k['high'],
            ':low'          => $k['low'],
            ':close'        => $k['close'],
            ':volume'       => $k['volume'],
            ':close_time'   => $k['close_time'],
            ':quote_volume' => $k['quote_volume'],
            ':trades'       => $k['trades'],
        ]);
        if ($stmt->rowCount() > 0) $inserted++;
    }

    return $inserted;
}

// ─────────────────────────────────────────────────────────────────────────────
//  SAVE KLINE FROM WEBSOCKET
// ─────────────────────────────────────────────────────────────────────────────

function saveKline(array $k): bool
{
    global $pdo;

    // Hanya simpan candle yang sudah CLOSED (x === true)
    if (!isset($k['x']) || $k['x'] !== true) {
        return false;
    }

    $time = (int)($k['t'] / 1000); // convert ms to seconds

    $stmt = $pdo->prepare("
        INSERT INTO raw_ticker_btcusdt
            (time, open_time, open, high, low, close, volume, close_time, quote_volume, trades)
        VALUES
            (:time, :open_time, :open, :high, :low, :close, :volume, :close_time, :quote_volume, :trades)
        ON CONFLICT (time) DO UPDATE SET
            open         = EXCLUDED.open,
            high         = EXCLUDED.high,
            low          = EXCLUDED.low,
            close        = EXCLUDED.close,
            volume       = EXCLUDED.volume,
            close_time   = EXCLUDED.close_time,
            quote_volume = EXCLUDED.quote_volume,
            trades       = EXCLUDED.trades
    ");

    $stmt->execute([
        ':time'         => $time,
        ':open_time'    => (int)$k['t'],
        ':open'         => (float)$k['o'],
        ':high'         => (float)$k['h'],
        ':low'          => (float)$k['l'],
        ':close'        => (float)$k['c'],
        ':volume'       => (float)$k['v'],
        ':close_time'   => (int)$k['T'],
        ':quote_volume' => (float)$k['q'],
        ':trades'       => (int)$k['n'],
    ]);

    return $stmt->rowCount() > 0;
}

// ─────────────────────────────────────────────────────────────────────────────
//  INDICATORS (dihitung dari 500 data terakhir)
// ─────────────────────────────────────────────────────────────────────────────

// ── In-memory kline cache (hindari re-query setiap kline) ─────────────────────
$klineCache = ['rows' => [], 'last_time' => 0];

function getRecentKlines(int $limit = INDICATOR_WINDOW): array
{
    global $pdo, $klineCache;

    // Cek apakah cache sudah cukup dan masih segar
    if (
        count($klineCache['rows']) >= $limit &&
        $klineCache['last_time'] > 0
    ) {
        // Verifikasi dengan 1 query ringan: apakah ada data baru sejak cache terakhir?
        $stmt = $pdo->prepare('SELECT time FROM raw_ticker_btcusdt ORDER BY time DESC LIMIT 1');
        $stmt->execute();
        $latestTime = (int)($stmt->fetchColumn() ?: 0);

        if ($latestTime === $klineCache['last_time']) {
            // Tidak ada data baru, kembalikan cache
            return array_slice($klineCache['rows'], -$limit);
        }
    }

    // Cache stale atau kosong — query penuh
    $stmt = $pdo->prepare("
        SELECT time, open, high, low, close
        FROM raw_ticker_btcusdt
        ORDER BY time DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll()); // ascending (oldest → newest)

    // Update cache
    $klineCache['rows']      = $rows;
    $klineCache['last_time'] = (int)end($rows)['time'];

    return $rows;
}

function calculateRSI(array $prices, int $period = RSI_PERIOD): ?float
{
    if (count($prices) < $period + 1) return null;

    $gains  = [];
    $losses = [];

    for ($i = 1; $i <= $period; $i++) {
        $change   = $prices[$i] - $prices[$i - 1];
        $gains[]  = max($change, 0.0);
        $losses[] = max(-$change, 0.0);
    }

    $avgGain = array_sum($gains)  / $period;
    $avgLoss = array_sum($losses) / $period;

    // Wilder smoothing untuk sisa data
    for ($i = $period + 1, $n = count($prices); $i < $n; $i++) {
        $change   = $prices[$i] - $prices[$i - 1];
        $avgGain  = ($avgGain * ($period - 1) + max($change, 0.0)) / $period;
        $avgLoss  = ($avgLoss * ($period - 1) + max(-$change, 0.0)) / $period;
    }

    if ($avgLoss < 1e-10) return 100.0;

    $rs = $avgGain / $avgLoss;
    return 100.0 - (100.0 / (1.0 + $rs));
}

function calculateMACD(
    array $prices,
    int $fast   = MACD_FAST,
    int $slow   = MACD_SLOW,
    int $signal = MACD_SIGNAL
): ?array {
    $n = count($prices);
    if ($n < $slow) return null;

    // ── O(n) incremental EMA ── tidak lagi memanggil array_slice per candle
    $kFast = 2.0 / ($fast + 1);
    $kSlow = 2.0 / ($slow + 1);

    // Seed awal: SMA dari $slow candle pertama (covering both periods)
    $sumFast = 0.0;
    $sumSlow = 0.0;
    for ($i = 0; $i < $slow; $i++) {
        $sumSlow += $prices[$i];
        if ($i >= $slow - $fast) $sumFast += $prices[$i]; // last $fast candles
    }
    $emaFast = $sumFast / $fast;
    $emaSlow = $sumSlow / $slow;

    // Candle pertama yang menghasilkan MACD value (index $slow-1)
    $macdValues   = [$emaFast - $emaSlow];

    // Lanjut EMA secara incremental untuk sisa candle
    for ($i = $slow; $i < $n; $i++) {
        $emaFast    = ($prices[$i] * $kFast) + ($emaFast * (1.0 - $kFast));
        $emaSlow    = ($prices[$i] * $kSlow) + ($emaSlow * (1.0 - $kSlow));
        $macdValues[] = $emaFast - $emaSlow;
    }

    $mn = count($macdValues);
    if ($mn < $signal) return null;

    // Signal line: EMA dari macdValues
    $kSig   = 2.0 / ($signal + 1);
    $sigVal = array_sum(array_slice($macdValues, 0, $signal)) / $signal;
    for ($i = $signal; $i < $mn; $i++) {
        $sigVal = ($macdValues[$i] * $kSig) + ($sigVal * (1.0 - $kSig));
    }

    $currentMacd = $macdValues[$mn - 1];

    return [
        'macd'      => $currentMacd,
        'signal'    => $sigVal,
        'histogram' => $currentMacd - $sigVal,
    ];
}

function calculateBollingerBands(
    array $prices,
    int   $period = BB_PERIOD,
    float $stdDev = BB_STDDEV
): ?array {
    if (count($prices) < $period) return null;

    $subset   = array_slice($prices, -$period);
    $mean     = array_sum($subset) / $period;
    $variance = 0.0;

    foreach ($subset as $p) {
        $variance += ($p - $mean) ** 2;
    }

    $std = sqrt($variance / $period);

    return [
        'upper'  => $mean + ($stdDev * $std),
        'middle' => $mean,
        'lower'  => $mean - ($stdDev * $std),
    ];
}

function calculateStochastic(
    array $highs,
    array $lows,
    array $closes,
    int   $kPeriod = STOCH_K_PERIOD,
    int   $dPeriod = STOCH_D_PERIOD
): ?array {
    if (count($closes) < $kPeriod) return null;

    // ── Step 1: Hitung Fast %K (raw) per baris ──
    $fastKValues = [];
    for ($i = $kPeriod - 1, $n = count($closes); $i < $n; $i++) {
        $h  = array_slice($highs, $i - $kPeriod + 1, $kPeriod);
        $l  = array_slice($lows,  $i - $kPeriod + 1, $kPeriod);
        $hh = max($h);
        $ll = min($l);
        $c  = $closes[$i];

        $fastKValues[] = ($hh == $ll) ? 50.0 : (($c - $ll) / ($hh - $ll)) * 100.0;
    }

    if (count($fastKValues) < $dPeriod) return null;

    // ── Step 2: Slow %K = SMA(Fast %K, dPeriod) — standar industri (TradingView, MetaTrader) ──
    // ── Step 3: %D = SMA(Slow %K, dPeriod) — double-smoothed signal line ──
    $slowKValues = [];
    for ($i = $dPeriod - 1, $kn = count($fastKValues); $i < $kn; $i++) {
        $slowKValues[] = array_sum(array_slice($fastKValues, $i - $dPeriod + 1, $dPeriod)) / $dPeriod;
    }

    if (count($slowKValues) < $dPeriod) return null;

    // %D = SMA dari Slow %K values terakhir
    $slowK = end($slowKValues);
    $slowD = array_sum(array_slice($slowKValues, -$dPeriod)) / $dPeriod;

    return [
        'k' => $slowK,
        'd' => $slowD,
    ];
}

function calculatePSAR(
    array $highs,
    array $lows,
    float $afStart = 0.02,
    float $afMax   = 0.20,
    float $afStep  = 0.02
): ?float {
    $n = count($highs);
    if ($n < 2) return null;

    $highs = array_map('floatval', $highs);
    $lows  = array_map('floatval', $lows);

    $psar  = $lows[0];
    $ep    = $highs[0];
    $af    = $afStart;
    $trend = 'up';

    for ($i = 1; $i < $n; $i++) {
        $prevPsar = $psar;
        $psar     = $prevPsar + $af * ($ep - $prevPsar);

        if ($trend === 'up') {
            if ($lows[$i] < $psar) {
                $trend = 'down';
                $psar  = $ep;
                $ep    = $lows[$i];
                $af    = $afStart;
            } else {
                if ($highs[$i] > $ep) {
                    $ep = $highs[$i];
                    $af = min($af + $afStep, $afMax);
                }
                // Proteksi PSAR: cek 2 periode terakhir agar PSAR tidak masuk ke price range
                if ($i >= 2) {
                    $psar = min($psar, min($lows[$i - 1], $lows[$i - 2]));
                } elseif ($i > 0) {
                    $psar = min($psar, $lows[$i - 1]);
                }
            }
        } else {
            if ($highs[$i] > $psar) {
                $trend = 'up';
                $psar  = $ep;
                $ep    = $highs[$i];
                $af    = $afStart;
            } else {
                if ($lows[$i] < $ep) {
                    $ep = $lows[$i];
                    $af = min($af + $afStep, $afMax);
                }
                // Proteksi PSAR: cek 2 periode terakhir agar PSAR tidak masuk ke price range
                if ($i >= 2) {
                    $psar = max($psar, max($highs[$i - 1], $highs[$i - 2]));
                } elseif ($i > 0) {
                    $psar = max($psar, $highs[$i - 1]);
                }
            }
        }
    }

    return (float)$psar;
}

function saveIndicator(int $time, array $ind): void
{
    global $pdo;

    $pdo->prepare("
        INSERT INTO raw_indicator_btcusdt
            (ticker_time, rsi, macd, macd_signal, macd_histogram,
             bb_upper, bb_middle, bb_lower, stoch_k, stoch_d, psar)
        VALUES
            (:time, :rsi, :macd, :macd_signal, :macd_histogram,
             :bb_upper, :bb_middle, :bb_lower, :stoch_k, :stoch_d, :psar)
        ON CONFLICT (ticker_time) DO UPDATE SET
            rsi            = EXCLUDED.rsi,
            macd           = EXCLUDED.macd,
            macd_signal    = EXCLUDED.macd_signal,
            macd_histogram = EXCLUDED.macd_histogram,
            bb_upper       = EXCLUDED.bb_upper,
            bb_middle      = EXCLUDED.bb_middle,
            bb_lower       = EXCLUDED.bb_lower,
            stoch_k        = EXCLUDED.stoch_k,
            stoch_d        = EXCLUDED.stoch_d,
            psar           = EXCLUDED.psar
    ")->execute([
        ':time'          => $time,
        ':rsi'           => $ind['rsi'],
        ':macd'          => $ind['macd'],
        ':macd_signal'   => $ind['macd_signal'],
        ':macd_histogram'=> $ind['macd_histogram'],
        ':bb_upper'      => $ind['bb_upper'],
        ':bb_middle'     => $ind['bb_middle'],
        ':bb_lower'      => $ind['bb_lower'],
        ':stoch_k'       => $ind['stoch_k'],
        ':stoch_d'       => $ind['stoch_d'],
        ':psar'          => $ind['psar'],
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
//  SIGNALS
// ─────────────────────────────────────────────────────────────────────────────

function generateSignals(float $closePrice, array $ind): array
{
    // RSI
    $signalRsi = 'HOLD';
    if ($ind['rsi'] !== null) {
        if ($ind['rsi'] < 30)      $signalRsi = 'BUY';
        elseif ($ind['rsi'] > 70)  $signalRsi = 'SELL';
    }

    // MACD
    $signalMacd = 'HOLD';
    if ($ind['macd'] !== null && $ind['macd_signal'] !== null) {
        $signalMacd = ($ind['macd'] > $ind['macd_signal']) ? 'BUY' : 'SELL';
    }

    // Bollinger Bands
    $signalBb = 'HOLD';
    if ($ind['bb_lower'] !== null && $ind['bb_upper'] !== null) {
        if ($closePrice < $ind['bb_lower'])      $signalBb = 'BUY';
        elseif ($closePrice > $ind['bb_upper'])  $signalBb = 'SELL';
    }

    // Stochastic
    $signalStoch = 'HOLD';
    if ($ind['stoch_k'] !== null) {
        if ($ind['stoch_k'] < 20)      $signalStoch = 'BUY';
        elseif ($ind['stoch_k'] > 80)  $signalStoch = 'SELL';
    }

    // Parabolic SAR
    $signalPsar = 'HOLD';
    if ($ind['psar'] !== null) {
        $signalPsar = ($closePrice > $ind['psar']) ? 'BUY' : 'SELL';
    }

    return compact('signalRsi', 'signalMacd', 'signalBb', 'signalStoch', 'signalPsar');
}

function ensembleSignal(array $signals): string
{
    $buy  = 0;
    $sell = 0;

    foreach ($signals as $sig) {
        if ($sig === 'BUY')  $buy++;
        if ($sig === 'SELL') $sell++;
    }

    if ($buy >= 3 && $buy > $sell) return 'BUY';
    if ($sell >= 3 && $sell > $buy) return 'SELL';
    return 'HOLD';
}

function saveRawSignal(int $time, array $signals): void
{
    global $pdo;

    $pdo->prepare("
        INSERT INTO raw_signal_btcusdt
            (ticker_time, signal_rsi, signal_macd, signal_bb, signal_stoch, signal_psar)
        VALUES (:time, :rsi, :macd, :bb, :stoch, :psar)
        ON CONFLICT (ticker_time) DO UPDATE SET
            signal_rsi   = EXCLUDED.signal_rsi,
            signal_macd  = EXCLUDED.signal_macd,
            signal_bb    = EXCLUDED.signal_bb,
            signal_stoch = EXCLUDED.signal_stoch,
            signal_psar  = EXCLUDED.signal_psar
    ")->execute([
        ':time'  => $time,
        ':rsi'   => $signals['signalRsi'],
        ':macd'  => $signals['signalMacd'],
        ':bb'    => $signals['signalBb'],
        ':stoch' => $signals['signalStoch'],
        ':psar'  => $signals['signalPsar'],
    ]);
}

function saveTradeSignal(int $time, string $ensemble): void
{
    global $pdo;

    $pdo->prepare("
        INSERT INTO trade_signal_btcusdt (ticker_time, ensemble_signal)
        VALUES (:time, :ensemble)
        ON CONFLICT (ticker_time) DO UPDATE SET ensemble_signal = EXCLUDED.ensemble_signal
    ")->execute([':time' => $time, ':ensemble' => $ensemble]);
}

// ─────────────────────────────────────────────────────────────────────────────
//  TRADE STATE MACHINE
// ─────────────────────────────────────────────────────────────────────────────

function updateTradeState(
    int    $time,
    float  $closePrice,
    array  $signals,
    string $ensemble
): void {
    global $pdo;

    $states = [
        'rsi'      => $signals['signalRsi'],
        'macd'     => $signals['signalMacd'],
        'bb'       => $signals['signalBb'],
        'stoch'    => $signals['signalStoch'],
        'psar'     => $signals['signalPsar'],
        'ensemble' => $ensemble,
    ];

    // ── 1 SELECT untuk ambil posisi terakhir SEMUA state sekaligus ───────────
    $stateNames  = array_keys($states);
    $placeholders = implode(',', array_map(fn($i) => ":n{$i}", array_keys($stateNames)));
    $fetchStmt   = $pdo->prepare("
        SELECT DISTINCT ON (state_name)
            state_name, position, entry_price
        FROM trade_state_btcusdt
        WHERE state_name IN ({$placeholders})
        ORDER BY state_name, ticker_time DESC
    ");
    foreach ($stateNames as $i => $name) {
        $fetchStmt->bindValue(":n{$i}", $name);
    }
    $fetchStmt->execute();
    $lastStates = [];
    foreach ($fetchStmt->fetchAll() as $row) {
        $lastStates[$row['state_name']] = $row;
    }

    // ── Hitung state machine, kumpulkan INSERT values ─────────────────────
    $insertRows = [];   // untuk batch INSERT trade_state
    $roiRows    = [];   // untuk batch INSERT raw_roi

    foreach ($states as $stateName => $signal) {
        $last       = $lastStates[$stateName] ?? null;
        $position   = $last['position']    ?? 'NONE';
        $entryPrice = isset($last['entry_price']) ? (float)$last['entry_price'] : null;
        $exitPrice  = null;
        $roi        = null;

        if ($position === 'NONE' || $position === 'CLOSED') {
            if ($signal === 'BUY') {
                $position   = 'OPEN';
                $entryPrice = $closePrice;
            }
        } elseif ($position === 'OPEN') {
            if ($signal === 'SELL') {
                $position  = 'CLOSED';
                $exitPrice = $closePrice;
                if ($entryPrice !== null && $entryPrice > 0) {
                    $roi = (($exitPrice - $entryPrice) / $entryPrice) * 100.0;
                }
            }
        }

        $insertRows[$stateName] = [
            'pos'   => $position,
            'entry' => $entryPrice,
            'exit'  => $exitPrice,
        ];

        if ($roi !== null) {
            $roiRows[$stateName] = $roi;
        }
    }

    // ── 1 INSERT batch untuk semua state sekaligus ────────────────────────
    $valClauses = [];
    $valParams  = [':time' => $time];
    foreach ($insertRows as $name => $row) {
        $k = preg_replace('/\W/', '', $name); // safe key
        $valClauses[]       = "(:time, :{$k}_name, :{$k}_pos, :{$k}_entry, :{$k}_exit)";
        $valParams[":{$k}_name"]  = $name;
        $valParams[":{$k}_pos"]   = $row['pos'];
        $valParams[":{$k}_entry"] = $row['entry'];
        $valParams[":{$k}_exit"]  = $row['exit'];
    }

    if ($valClauses) {
        $pdo->prepare("
            INSERT INTO trade_state_btcusdt
                (ticker_time, state_name, position, entry_price, exit_price)
            VALUES " . implode(',', $valClauses) . "
            ON CONFLICT (ticker_time, state_name) DO UPDATE SET
                position    = EXCLUDED.position,
                entry_price = EXCLUDED.entry_price,
                exit_price  = EXCLUDED.exit_price
        ")->execute($valParams);
    }

    // ── Batch INSERT ROI (hanya jika ada trade yang ditutup) ──────────────
    if ($roiRows) {
        $roiClauses = [];
        $roiParams  = [':time' => $time];
        foreach ($roiRows as $name => $roi) {
            $k = preg_replace('/\W/', '', $name);
            $roiClauses[]          = "(:time, :{$k}_rname, :{$k}_roi)";
            $roiParams[":{$k}_rname"] = $name;
            $roiParams[":{$k}_roi"]   = $roi;
        }
        $pdo->prepare("
            INSERT INTO raw_roi_btcusdt (ticker_time, state_name, roi)
            VALUES " . implode(',', $roiClauses) . "
            ON CONFLICT (ticker_time, state_name) DO NOTHING
        ")->execute($roiParams);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  PERFORMANCE SUMMARY
// ─────────────────────────────────────────────────────────────────────────────

function updateSummaryPerformance(): void
{
    global $pdo;

    // ── Aggregate langsung di PostgreSQL (hindari tarik semua baris ke PHP) ──
    $agg = $pdo->query("
        SELECT
            COUNT(*)                                           AS total,
            SUM(CASE WHEN roi > 0 THEN 1 ELSE 0 END)         AS wins,
            AVG(roi)                                           AS avg_roi,
            SUM(CASE WHEN roi > 0 THEN roi ELSE 0 END)        AS gross_profit,
            ABS(SUM(CASE WHEN roi < 0 THEN roi ELSE 0 END))   AS gross_loss
        FROM raw_roi_btcusdt
    ")->fetch();

    if (!$agg || (int)$agg['total'] === 0) return;

    $total        = (int)$agg['total'];
    $winRate      = ($total > 0) ? ((float)$agg['wins'] / $total) * 100.0 : 0.0;
    $avgRoi       = (float)$agg['avg_roi'];
    $grossLoss    = (float)$agg['gross_loss'];
    $profitFactor = ($grossLoss > 0) ? (float)$agg['gross_profit'] / $grossLoss : 0.0;

    // Max Drawdown — dihitung dari ensemble strategy saja (ROI campuran multi-strategy tidak bermakna)
    $rois = $pdo->prepare("SELECT roi FROM raw_roi_btcusdt WHERE state_name = 'ensemble' ORDER BY ticker_time");
    $rois->execute();
    $rois = $rois->fetchAll(PDO::FETCH_COLUMN);
    $cumulative  = 0.0;
    $peak        = 0.0;
    $maxDrawdown = 0.0;
    foreach ($rois as $roi) {
        $cumulative += (float)$roi;
        if ($cumulative > $peak) $peak = $cumulative;
        $dd = $peak - $cumulative;
        if ($dd > $maxDrawdown) $maxDrawdown = $dd;
    }

    // Per-state breakdown — aggregate di SQL
    $perStateRows = $pdo->query("
        SELECT
            state_name,
            COUNT(*)                                        AS cnt,
            SUM(CASE WHEN roi > 0 THEN 1 ELSE 0 END)       AS wins,
            AVG(roi)                                        AS avg_roi
        FROM raw_roi_btcusdt
        GROUP BY state_name
    ")->fetchAll();

    $perStatePerf = [];
    foreach ($perStateRows as $r) {
        $cnt = (int)$r['cnt'];
        $perStatePerf[$r['state_name']] = [
            'total_trades' => $cnt,
            'win_rate'     => $cnt > 0 ? ((float)$r['wins'] / $cnt) * 100.0 : 0.0,
            'avg_roi'      => (float)$r['avg_roi'],
        ];
    }

    // ── UPSERT ke baris tunggal id=1 (bukan INSERT baru setiap kline) ────────
    $pdo->prepare("
        INSERT INTO summary_performance_btcusdt
            (id, total_trades, win_rate, avg_roi, profit_factor, max_drawdown, per_state_performance, calculated_at)
        VALUES (1, :total, :wr, :roi, :pf, :dd, :perf, NOW())
        ON CONFLICT (id) DO UPDATE SET
            total_trades         = EXCLUDED.total_trades,
            win_rate             = EXCLUDED.win_rate,
            avg_roi              = EXCLUDED.avg_roi,
            profit_factor        = EXCLUDED.profit_factor,
            max_drawdown         = EXCLUDED.max_drawdown,
            per_state_performance = EXCLUDED.per_state_performance,
            calculated_at        = EXCLUDED.calculated_at
    ")->execute([
        ':total' => $total,
        ':wr'    => $winRate,
        ':roi'   => $avgRoi,
        ':pf'    => $profitFactor,
        ':dd'    => $maxDrawdown,
        ':perf'  => json_encode($perStatePerf),
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
//  PROCESS NEW KLINE DATA
// ─────────────────────────────────────────────────────────────────────────────

/**
 * @param int        $time        epoch time kline yang akan diproses
 * @param array|null $recentKlines jika sudah ada (initial loop), pass langsung;
 *                                 null = ambil dari DB via getRecentKlines()
 */
function processNewData(int $time, ?array $recentKlines = null): void
{
    global $pdo;

    // Ambil data kline yang baru disimpan
    $tickerStmt = $pdo->prepare("SELECT * FROM raw_ticker_btcusdt WHERE time = :time");
    $tickerStmt->execute([':time' => $time]);
    $ticker = $tickerStmt->fetch();
    if (!$ticker) return;

    $recent    = $recentKlines ?? getRecentKlines(INDICATOR_WINDOW);
    $dataCount = count($recent);
    $minNeeded = INDICATOR_WINDOW; // Indikator dihitung setelah 500 fact data siap

    if ($dataCount < $minNeeded) {
        log_msg("  Data terkumpul: {$dataCount} / {$minNeeded} (menunggu 500 fact data siap untuk indikator)");
        return;
    }

    $closes = array_map('floatval', array_column($recent, 'close'));
    $highs  = array_map('floatval', array_column($recent, 'high'));
    $lows   = array_map('floatval', array_column($recent, 'low'));

    // Hitung semua indikator
    $macdData  = calculateMACD($closes);
    $bb        = calculateBollingerBands($closes);
    $stoch     = calculateStochastic($highs, $lows, $closes);

    $indicators = [
        'rsi'           => calculateRSI($closes),
        'macd'          => $macdData['macd']      ?? null,
        'macd_signal'   => $macdData['signal']    ?? null,
        'macd_histogram'=> $macdData['histogram'] ?? null,
        'bb_upper'      => $bb['upper']           ?? null,
        'bb_middle'     => $bb['middle']          ?? null,
        'bb_lower'      => $bb['lower']           ?? null,
        'stoch_k'       => $stoch['k']            ?? null,
        'stoch_d'       => $stoch['d']            ?? null,
        'psar'          => calculatePSAR($highs, $lows),
    ];

    saveIndicator($time, $indicators);

    $closePrice = (float)$ticker['close'];
    $signals    = generateSignals($closePrice, $indicators);
    $ensemble   = ensembleSignal($signals);

    saveRawSignal($time, $signals);
    saveTradeSignal($time, $ensemble);
    updateTradeState($time, $closePrice, $signals, $ensemble);
    updateSummaryPerformance();

    log_msg("  Processed time={$time} | close=$" . number_format($closePrice, 2, '.', ',') . " USDT | ensemble={$ensemble}");
}

// ─────────────────────────────────────────────────────────────────────────────
//  WEBSOCKET STREAMING LOOP (mengikuti Binance best practices)
// ─────────────────────────────────────────────────────────────────────────────

function binanceWebSocketLoop(): void
{
    $attempt = 0;

    while (true) {
        try {
            log_msg("[BINANCE] Connecting to WebSocket: " . BINANCE_WS_URL);

            $ws = new Client(BINANCE_WS_URL, [
                'timeout'      => 15,
                'fragment_size' => 65536,
                'headers'       => [
                    'User-Agent' => 'crypto-pipeline-php/1.0',
                ],
            ]);

            log_msg("[BINANCE] Connected successfully.");
            $attempt = 0;
            $sessionStart = microtime(true);

            // WebSocket message loop
            while (true) {
                try {
                    $message = $ws->receive();

                    if ($message === null) {
                        log_msg("[BINANCE] Connection closed by server.");
                        break;
                    }

                    $data = json_decode($message, true);

                    // Filter hanya event kline
                    if (!isset($data['e']) || $data['e'] !== 'kline') {
                        continue;
                    }

                    $k = $data['k'];

                    // Proses hanya saat kline CLOSED (x === true)
                    if (!isset($k['x']) || $k['x'] !== true) {
                        continue;
                    }

                    $time = (int)($k['t'] / 1000);
                    log_msg("[BINANCE] Kline CLOSED | time={$time} | open={$k['o']} | high={$k['h']} | low={$k['l']} | close={$k['c']} | vol={$k['v']}");

                    // Simpan kline ke database
                    $isNew = saveKline($k);

                    if ($isNew) {
                        // Data baru → proses penuh (indikator, signal, trade state)
                        processNewData($time);
                    } else {
                        // Data sudah ada, cek apakah indikator sudah dihitung
                        global $pdo;
                        static $checkStmt = null;
                        if ($checkStmt === null) {
                            $checkStmt = $pdo->prepare("SELECT 1 FROM raw_indicator_btcusdt WHERE ticker_time = ?");
                        }
                        $checkStmt->execute([$time]);
                        if (!$checkStmt->fetch()) {
                            processNewData($time);
                        } else {
                            log_msg("  Kline time={$time} sudah diproses, skip.");
                        }
                    }

                    // Cek batas session 23 jam (proactive reconnect)
                    $elapsed = microtime(true) - $sessionStart;
                    if ($elapsed >= SESSION_MAX) {
                        log_msg("[BINANCE] " . (SESSION_MAX / 3600) . "h session limit reached, reconnecting proactively.");
                        break;
                    }

                } catch (ConnectionException $e) {
                    log_msg("[BINANCE] Connection error during receive: " . $e->getMessage());
                    break;
                }
            }

            // Close WebSocket gracefully
            try {
                $ws->close();
            } catch (\Throwable $e) {
                // ignore close errors
            }

        } catch (ConnectionException $e) {
            log_msg("[BINANCE] Failed to connect: " . $e->getMessage());
        } catch (\Throwable $e) {
            log_msg("[BINANCE] Unexpected error: " . get_class($e) . " — " . $e->getMessage());
        }

        // Exponential backoff sebelum reconnect
        backoff_sleep($attempt, 'BINANCE');
        $attempt++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
//  SIGNAL HANDLING (Ctrl+C)
// ─────────────────────────────────────────────────────────────────────────────

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT,  function () { log_msg('Pipeline stopped by user (SIGINT).');  exit(0); });
    pcntl_signal(SIGTERM, function () { log_msg('Pipeline stopped by system (SIGTERM).'); exit(0); });
}

// ─────────────────────────────────────────────────────────────────────────────
//  BOOT SEQUENCE
// ─────────────────────────────────────────────────────────────────────────────

log_msg('=== Crypto Trading Pipeline — Binance BTCUSDT Kline 5m ===');
log_msg('Indicator window: ' . INDICATOR_WINDOW . ' data');

initDatabase();

// Cek apakah database sudah punya data
global $pdo;
$existingCount = (int)$pdo->query("SELECT COUNT(*) FROM raw_ticker_btcusdt")->fetchColumn();

if ($existingCount < BINANCE_KLINE_LIMIT) {
    log_msg("Database has {$existingCount} rows, seeding " . BINANCE_KLINE_LIMIT . " initial klines...");

    // Fetch dari REST API
    $klines   = fetchInitialKlines();
    $inserted = seedDatabase($klines);

    $totalAfterSeed = (int)$pdo->query("SELECT COUNT(*) FROM raw_ticker_btcusdt")->fetchColumn();
    log_msg("Seeded {$inserted} new klines. Total in DB: {$totalAfterSeed}");

    // ── Hitung indikator hanya setelah 500 fact data siap ───────────────
    // Kline ke-1 s.d. ke-499 hanya disimpan sebagai fact data (tanpa indikator).
    // Indikator mulai dihitung dari kline ke-500, menggunakan 500 data terakhir.
    $allTimes = $pdo->query("
        SELECT time FROM raw_ticker_btcusdt
        ORDER BY time ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $total = count($allTimes);
    $startIndex = max(0, INDICATOR_WINDOW - 1); // mulai dari kline ke-500 (index 499)
    $processable = $total - $startIndex;
    log_msg("Fact data tersedia: {$total} klines. Indikator dihitung mulai kline ke-" . ($startIndex + 1) . " ({$processable} klines)...");

    foreach ($allTimes as $i => $time) {
        // Skip kline yang belum mencapai window 500 data
        if ($i < $startIndex) {
            continue;
        }
        // Ambil data spesifik sampai kline saat ini (hindari cache yang mengembalikan window yang salah)
        $windowStmt = $pdo->prepare(
            "SELECT time, open, high, low, close FROM raw_ticker_btcusdt WHERE time <= :t ORDER BY time DESC LIMIT :lim"
        );
        $windowStmt->bindValue(':t', (int)$time, PDO::PARAM_INT);
        $windowStmt->bindValue(':lim', INDICATOR_WINDOW, PDO::PARAM_INT);
        $windowStmt->execute();
        $recentForTime = array_reverse($windowStmt->fetchAll());
        processNewData((int)$time, $recentForTime);
        // Log progress setiap 50 kline supaya tidak bisu
        $progress = $i - $startIndex + 1;
        if ($progress % 50 === 0 || $i === $total - 1) {
            log_msg("  Indicator progress: {$progress}/{$processable}");
        }
    }

    // Invalidate cache setelah backfill selesai supaya data real-time menggunakan fresh query
    global $klineCache;
    $klineCache = ['rows' => [], 'last_time' => 0];
    log_msg("Initial indicator calculation complete (dari 500 data terbaru).");

} else {
    log_msg("Database already has {$existingCount} klines, skipping seed.");

    // Cek kline mana yang belum punya indikator (misalnya pipeline restart)
    $missingTimes = $pdo->query("
        SELECT t.time
        FROM raw_ticker_btcusdt t
        LEFT JOIN raw_indicator_btcusdt i ON i.ticker_time = t.time
        WHERE i.ticker_time IS NULL
        ORDER BY t.time ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($missingTimes)) {
        $missing = count($missingTimes);
        log_msg("Found {$missing} klines without indicators, recalculating...");
        foreach ($missingTimes as $i => $time) {
            // Ambil data spesifik sampai kline saat ini (hindari cache yang mengembalikan window yang salah)
            $windowStmt = $pdo->prepare(
                "SELECT time, open, high, low, close FROM raw_ticker_btcusdt WHERE time <= :t ORDER BY time DESC LIMIT :lim"
            );
            $windowStmt->bindValue(':t', (int)$time, PDO::PARAM_INT);
            $windowStmt->bindValue(':lim', INDICATOR_WINDOW, PDO::PARAM_INT);
            $windowStmt->execute();
            $recentForTime = array_reverse($windowStmt->fetchAll());
            processNewData((int)$time, $recentForTime);
            if (($i + 1) % 50 === 0 || ($i + 1) === $missing) {
                log_msg("  Catchup progress: " . ($i + 1) . "/{$missing}");
            }
        }
        // Invalidate cache setelah catchup selesai
        $klineCache = ['rows' => [], 'last_time' => 0];
        log_msg("Catchup complete.");
    } else {
        // Semua sudah ada — proses kline terakhir saja untuk pastikan state fresh
        $lastKline = $pdo->query("SELECT time FROM raw_ticker_btcusdt ORDER BY time DESC LIMIT 1")->fetch();
        if ($lastKline) {
            processNewData((int)$lastKline['time']);
        }
    }
}


log_msg('Starting WebSocket stream...');

// Masuk ke WebSocket loop (infinite)
binanceWebSocketLoop();
