<?php
/**
 * Crypto Trading Pipeline - Indodax BTC/IDR
 * 
 * Jalankan: php crypto_pipeline.php
 * Berhenti: Ctrl+C
 * 
 * Persyaratan: PHP 7.4+, PostgreSQL, PDO_PGSQL
 */

declare(strict_types=1);

// Konfigurasi Database
const DB_HOST = 'localhost';
const DB_PORT = '5432';
const DB_NAME = 'trading_db';
const DB_USER = 'postgres';
const DB_PASS = 'postgres';

// Konfigurasi Pipeline
const API_URL = 'https://indodax.com/api/tickers/btc_idr';
const FETCH_INTERVAL_SECONDS = 60;
const INDICATOR_WINDOW = 100;
const RSI_PERIOD = 14;
const MACD_FAST = 12;
const MACD_SLOW = 26;
const MACD_SIGNAL = 9;
const BB_PERIOD = 20;
const BB_STDDEV = 2;
const STOCH_K_PERIOD = 14;
const STOCH_D_PERIOD = 3;

// Koneksi database global
$pdo = null;

/**
 * Inisialisasi koneksi database
 */
function initDatabase(): void {
    global $pdo;
    $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
    createTablesIfNotExists();
}

/**
 * Membuat tabel jika belum ada
 */
function createTablesIfNotExists(): void {
    global $pdo;
    
    $queries = [
        // 1. Raw ticker
        "CREATE TABLE IF NOT EXISTS raw_ticker_btcidr (
            id SERIAL PRIMARY KEY,
            time BIGINT NOT NULL UNIQUE,
            buy DECIMAL(20,8) NOT NULL,
            sell DECIMAL(20,8) NOT NULL,
            last DECIMAL(20,8) NOT NULL,
            high DECIMAL(20,8) NOT NULL,
            low DECIMAL(20,8) NOT NULL,
            volume DECIMAL(20,8) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        // 2. Raw indicator
        "CREATE TABLE IF NOT EXISTS raw_indicator_btcidr (
            id SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcidr(time) ON DELETE CASCADE,
            rsi DECIMAL(10,4),
            macd DECIMAL(20,8),
            macd_signal DECIMAL(20,8),
            macd_histogram DECIMAL(20,8),
            bb_upper DECIMAL(20,8),
            bb_middle DECIMAL(20,8),
            bb_lower DECIMAL(20,8),
            stoch_k DECIMAL(10,4),
            stoch_d DECIMAL(10,4),
            psar DECIMAL(20,8),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time)
        )",
        
        // 3. Raw signal (per indicator)
        "CREATE TABLE IF NOT EXISTS raw_signal_btcidr (
            id SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcidr(time) ON DELETE CASCADE,
            signal_rsi VARCHAR(10) CHECK (signal_rsi IN ('BUY','SELL','HOLD')),
            signal_macd VARCHAR(10) CHECK (signal_macd IN ('BUY','SELL','HOLD')),
            signal_bb VARCHAR(10) CHECK (signal_bb IN ('BUY','SELL','HOLD')),
            signal_stoch VARCHAR(10) CHECK (signal_stoch IN ('BUY','SELL','HOLD')),
            signal_psar VARCHAR(10) CHECK (signal_psar IN ('BUY','SELL','HOLD')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time)
        )",
        
        // 4. Ensemble signal
        "CREATE TABLE IF NOT EXISTS trade_signal_btcidr (
            id SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcidr(time) ON DELETE CASCADE,
            ensemble_signal VARCHAR(10) CHECK (ensemble_signal IN ('BUY','SELL','HOLD')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time)
        )",
        
        // 5. Trade state (per state per time)
        "CREATE TABLE IF NOT EXISTS trade_state_btcidr (
            id SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcidr(time) ON DELETE CASCADE,
            state_name VARCHAR(20) NOT NULL CHECK (state_name IN ('rsi','macd','bb','stoch','psar','ensemble')),
            position VARCHAR(10) CHECK (position IN ('OPEN','CLOSED','NONE')),
            entry_price DECIMAL(20,8),
            exit_price DECIMAL(20,8),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time, state_name)
        )",
        
        // 6. ROI calculation
        "CREATE TABLE IF NOT EXISTS raw_roi_btcidr (
            id SERIAL PRIMARY KEY,
            ticker_time BIGINT NOT NULL REFERENCES raw_ticker_btcidr(time) ON DELETE CASCADE,
            state_name VARCHAR(20) NOT NULL,
            roi DECIMAL(10,4),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(ticker_time, state_name)
        )",
        
        // 7. Summary performance
        "CREATE TABLE IF NOT EXISTS summary_performance_btcidr (
            id SERIAL PRIMARY KEY,
            calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total_trades INT NOT NULL,
            win_rate DECIMAL(5,2),
            avg_roi DECIMAL(10,4),
            profit_factor DECIMAL(10,4),
            max_drawdown DECIMAL(10,4),
            per_state_performance JSONB
        )"
    ];
    
    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Fetch data dari API Indodax
 */
function fetchTickerFromAPI(): ?array {
    $json = @file_get_contents(API_URL);
    if ($json === false) {
        echo "[" . date('Y-m-d H:i:s') . "] Gagal fetch API\n";
        return null;
    }
    $data = json_decode($json, true);
    if (!isset($data['ticker'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Format API tidak sesuai\n";
        return null;
    }
    $ticker = $data['ticker'];
    
    return [
        'time' => (int) ($data['server_time'] ?? time()),
        'buy' => (float) $ticker['buy'],
        'sell' => (float) $ticker['sell'],
        'last' => (float) $ticker['last'],
        'high' => (float) $ticker['high'],
        'low' => (float) $ticker['low'],
        'vol_btc' => (float) $ticker['vol_btc'],
        'volume' => (float) $ticker['vol_btc'] * (float) $ticker['last']
    ];
}

/**
 * Simpan raw ticker jika belum ada (berdasarkan time)
 */
function saveRawTicker(array $ticker): bool {
    global $pdo;
    
    $sql = "INSERT INTO raw_ticker_btcidr (time, buy, sell, last, high, low, volume)
            VALUES (:time, :buy, :sell, :last, :high, :low, :volume)
            ON CONFLICT (time) DO NOTHING";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':time' => $ticker['time'],
        ':buy' => $ticker['buy'],
        ':sell' => $ticker['sell'],
        ':last' => $ticker['last'],
        ':high' => $ticker['high'],
        ':low' => $ticker['low'],
        ':volume' => $ticker['volume']
    ]);
}

/**
 * Ambil N data ticker terbaru untuk perhitungan indikator
 */
function getRecentTickers(int $limit = INDICATOR_WINDOW): array {
    global $pdo;
    
    $sql = "SELECT time, last, high, low FROM raw_ticker_btcidr
            ORDER BY time DESC LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    
    // Balik urutan agar ascending (terlama ke terbaru)
    return array_reverse($rows);
}

/**
 * Hitung RSI
 */
function calculateRSI(array $prices, int $period = RSI_PERIOD): ?float {
    if (count($prices) < $period + 1) return null;
    
    $gains = $losses = [];
    for ($i = 1; $i <= $period; $i++) {
        $change = $prices[$i] - $prices[$i-1];
        $gains[] = max($change, 0);
        $losses[] = max(-$change, 0);
    }
    
    $avgGain = array_sum($gains) / $period;
    $avgLoss = array_sum($losses) / $period;
    
    if ($avgLoss == 0) return 100.0;
    $rs = $avgGain / $avgLoss;
    return 100 - (100 / (1 + $rs));
}

/**
 * Hitung MACD (return array [macd, signal, histogram])
 */
function calculateMACD(array $prices, int $fast = MACD_FAST, int $slow = MACD_SLOW, int $signal = MACD_SIGNAL): ?array {
    if (count($prices) < $slow) return null;
    
    $ema = function($data, $period) {
        $k = 2 / ($period + 1);
        $ema = array_sum(array_slice($data, 0, $period)) / $period;
        for ($i = $period; $i < count($data); $i++) {
            $ema = ($data[$i] * $k) + ($ema * (1 - $k));
        }
        return $ema;
    };
    
    // Hitung semua MACD untuk dapat signal line
    $macdValues = [];
    for ($i = $slow - 1; $i < count($prices); $i++) {
        $subset = array_slice($prices, 0, $i + 1);
        $ef = $ema($subset, $fast);
        $es = $ema($subset, $slow);
        $macdValues[] = $ef - $es;
    }
    
    if (count($macdValues) < $signal) return null;
    
    $signalLine = $ema($macdValues, $signal);
    $currentMacd = end($macdValues);
    
    return [
        'macd' => $currentMacd,
        'signal' => $signalLine,
        'histogram' => $currentMacd - $signalLine
    ];
}

/**
 * Hitung Bollinger Bands
 */
function calculateBollingerBands(array $prices, int $period = BB_PERIOD, float $stdDev = BB_STDDEV): ?array {
    if (count($prices) < $period) return null;
    
    $subset = array_slice($prices, -$period);
    $mean = array_sum($subset) / $period;
    
    $variance = 0.0;
    foreach ($subset as $p) {
        $variance += pow($p - $mean, 2);
    }
    $std = sqrt($variance / $period);
    
    return [
        'upper' => $mean + ($stdDev * $std),
        'middle' => $mean,
        'lower' => $mean - ($stdDev * $std)
    ];
}

/**
 * Hitung Stochastic Oscillator
 */
function calculateStochastic(array $highs, array $lows, array $closes, int $kPeriod = STOCH_K_PERIOD, int $dPeriod = STOCH_D_PERIOD): ?array {
    if (count($closes) < $kPeriod) return null;
    
    // Hitung semua %K terlebih dahulu
    $kValues = [];
    for ($i = $kPeriod - 1; $i < count($closes); $i++) {
        $h = array_slice($highs, $i - $kPeriod + 1, $kPeriod);
        $l = array_slice($lows, $i - $kPeriod + 1, $kPeriod);
        $hh = max($h);
        $ll = min($l);
        $c = $closes[$i];
        if ($hh == $ll) {
            $kValues[] = 50.0;
        } else {
            $kValues[] = (($c - $ll) / ($hh - $ll)) * 100;
        }
    }
    
    if (count($kValues) < $dPeriod) return null;
    
    $k = end($kValues);
    $d = array_sum(array_slice($kValues, -$dPeriod)) / $dPeriod;
    
    return ['k' => $k, 'd' => $d];
}

/**
 * Hitung Parabolic SAR (versi sederhana, dengan casting float ketat)
 */
function calculatePSAR(array $highs, array $lows, float $afStart = 0.02, float $afMax = 0.2, float $afStep = 0.02): ?float {
    if (count($highs) < 2) return null;
    
    // Pastikan semua elemen float
    $highs = array_map('floatval', $highs);
    $lows = array_map('floatval', $lows);
    
    $psar = $lows[0];
    $ep = $highs[0];
    $af = $afStart;
    $trend = 'up';
    
    for ($i = 1; $i < count($highs); $i++) {
        $prevPsar = $psar;
        $psar = $prevPsar + $af * ($ep - $prevPsar);
        
        if ($trend == 'up') {
            if ($lows[$i] < $psar) {
                $trend = 'down';
                $psar = $ep;
                $ep = $lows[$i];
                $af = $afStart;
            } else {
                if ($highs[$i] > $ep) {
                    $ep = $highs[$i];
                    $af = min($af + $afStep, $afMax);
                }
                if ($i > 0) {
                    $psar = min($psar, $lows[$i-1]);
                }
            }
        } else {
            if ($highs[$i] > $psar) {
                $trend = 'up';
                $psar = $ep;
                $ep = $highs[$i];
                $af = $afStart;
            } else {
                if ($lows[$i] < $ep) {
                    $ep = $lows[$i];
                    $af = min($af + $afStep, $afMax);
                }
                if ($i > 0) {
                    $psar = max($psar, $highs[$i-1]);
                }
            }
        }
    }
    
    return (float) $psar;
}

/**
 * Simpan indikator ke database
 */
function saveIndicator(int $time, array $indicators): void {
    global $pdo;
    
    $sql = "INSERT INTO raw_indicator_btcidr 
            (ticker_time, rsi, macd, macd_signal, macd_histogram, bb_upper, bb_middle, bb_lower, stoch_k, stoch_d, psar)
            VALUES (:time, :rsi, :macd, :macd_signal, :macd_histogram, :bb_upper, :bb_middle, :bb_lower, :stoch_k, :stoch_d, :psar)
            ON CONFLICT (ticker_time) DO UPDATE SET
                rsi = EXCLUDED.rsi,
                macd = EXCLUDED.macd,
                macd_signal = EXCLUDED.macd_signal,
                macd_histogram = EXCLUDED.macd_histogram,
                bb_upper = EXCLUDED.bb_upper,
                bb_middle = EXCLUDED.bb_middle,
                bb_lower = EXCLUDED.bb_lower,
                stoch_k = EXCLUDED.stoch_k,
                stoch_d = EXCLUDED.stoch_d,
                psar = EXCLUDED.psar";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':time' => $time,
        ':rsi' => $indicators['rsi'],
        ':macd' => $indicators['macd'],
        ':macd_signal' => $indicators['macd_signal'],
        ':macd_histogram' => $indicators['macd_histogram'],
        ':bb_upper' => $indicators['bb_upper'],
        ':bb_middle' => $indicators['bb_middle'],
        ':bb_lower' => $indicators['bb_lower'],
        ':stoch_k' => $indicators['stoch_k'],
        ':stoch_d' => $indicators['stoch_d'],
        ':psar' => $indicators['psar']
    ]);
}

/**
 * Generate sinyal dari indikator
 */
function generateSignals(array $ticker, array $indicators): array {
    $last = (float) $ticker['last'];
    
    $signalRsi = 'HOLD';
    if ($indicators['rsi'] !== null) {
        if ($indicators['rsi'] < 30) $signalRsi = 'BUY';
        elseif ($indicators['rsi'] > 70) $signalRsi = 'SELL';
    }
    
    $signalMacd = 'HOLD';
    if ($indicators['macd'] !== null && $indicators['macd_signal'] !== null) {
        $signalMacd = ($indicators['macd'] > $indicators['macd_signal']) ? 'BUY' : 'SELL';
    }
    
    $signalBb = 'HOLD';
    if ($indicators['bb_lower'] !== null && $indicators['bb_upper'] !== null) {
        if ($last < $indicators['bb_lower']) $signalBb = 'BUY';
        elseif ($last > $indicators['bb_upper']) $signalBb = 'SELL';
    }
    
    $signalStoch = 'HOLD';
    if ($indicators['stoch_k'] !== null) {
        if ($indicators['stoch_k'] < 20) $signalStoch = 'BUY';
        elseif ($indicators['stoch_k'] > 80) $signalStoch = 'SELL';
    }
    
    $signalPsar = 'HOLD';
    if ($indicators['psar'] !== null) {
        $signalPsar = ($last > $indicators['psar']) ? 'BUY' : 'SELL';
    }
    
    return compact('signalRsi', 'signalMacd', 'signalBb', 'signalStoch', 'signalPsar');
}

/**
 * Simpan sinyal mentah
 */
function saveRawSignal(int $time, array $signals): void {
    global $pdo;
    
    $sql = "INSERT INTO raw_signal_btcidr 
            (ticker_time, signal_rsi, signal_macd, signal_bb, signal_stoch, signal_psar)
            VALUES (:time, :rsi, :macd, :bb, :stoch, :psar)
            ON CONFLICT (ticker_time) DO UPDATE SET
                signal_rsi = EXCLUDED.signal_rsi,
                signal_macd = EXCLUDED.signal_macd,
                signal_bb = EXCLUDED.signal_bb,
                signal_stoch = EXCLUDED.signal_stoch,
                signal_psar = EXCLUDED.signal_psar";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':time' => $time,
        ':rsi' => $signals['signalRsi'],
        ':macd' => $signals['signalMacd'],
        ':bb' => $signals['signalBb'],
        ':stoch' => $signals['signalStoch'],
        ':psar' => $signals['signalPsar']
    ]);
}

/**
 * Ensemble signal (kombinasi)
 */
function ensembleSignal(array $signals): string {
    $buyCount = 0;
    $sellCount = 0;
    $signalMap = [
        $signals['signalRsi'],
        $signals['signalMacd'],
        $signals['signalBb'],
        $signals['signalStoch'],
        $signals['signalPsar']
    ];
    foreach ($signalMap as $sig) {
        if ($sig === 'BUY') $buyCount++;
        elseif ($sig === 'SELL') $sellCount++;
    }
    
    if ($buyCount >= 4) return 'BUY';
    if ($sellCount >= 4) return 'SELL';
    return 'HOLD';
}

/**
 * Simpan trade signal ensemble
 */
function saveTradeSignal(int $time, string $ensemble): void {
    global $pdo;
    
    $sql = "INSERT INTO trade_signal_btcidr (ticker_time, ensemble_signal)
            VALUES (:time, :ensemble)
            ON CONFLICT (ticker_time) DO UPDATE SET ensemble_signal = EXCLUDED.ensemble_signal";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':time' => $time, ':ensemble' => $ensemble]);
}

/**
 * Update trade state machine untuk semua state (6 state)
 */
function updateTradeState(int $time, float $lastPrice, array $signals, string $ensembleSignal): void {
    global $pdo;
    
    $states = [
        'rsi' => $signals['signalRsi'],
        'macd' => $signals['signalMacd'],
        'bb' => $signals['signalBb'],
        'stoch' => $signals['signalStoch'],
        'psar' => $signals['signalPsar'],
        'ensemble' => $ensembleSignal
    ];
    
    foreach ($states as $stateName => $signal) {
        $sqlSelect = "SELECT position, entry_price FROM trade_state_btcidr 
                      WHERE state_name = :state_name ORDER BY ticker_time DESC LIMIT 1";
        $stmtSelect = $pdo->prepare($sqlSelect);
        $stmtSelect->execute([':state_name' => $stateName]);
        $lastState = $stmtSelect->fetch();
        
        $position = $lastState['position'] ?? 'NONE';
        $entryPrice = isset($lastState['entry_price']) ? (float)$lastState['entry_price'] : null;
        $exitPrice = null;
        $roi = null;
        
        if ($position === 'NONE' || $position === 'CLOSED') {
            if ($signal === 'BUY') {
                $position = 'OPEN';
                $entryPrice = $lastPrice;
            }
        } elseif ($position === 'OPEN') {
            if ($signal === 'SELL') {
                $position = 'CLOSED';
                $exitPrice = $lastPrice;
                if ($entryPrice > 0) {
                    $roi = (($exitPrice - $entryPrice) / $entryPrice) * 100;
                }
            }
        }
        
        $sqlInsert = "INSERT INTO trade_state_btcidr (ticker_time, state_name, position, entry_price, exit_price)
                      VALUES (:time, :state_name, :position, :entry, :exit)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            ':time' => $time,
            ':state_name' => $stateName,
            ':position' => $position,
            ':entry' => $entryPrice,
            ':exit' => $exitPrice
        ]);
        
        if ($roi !== null) {
            $sqlRoi = "INSERT INTO raw_roi_btcidr (ticker_time, state_name, roi)
                       VALUES (:time, :state_name, :roi)";
            $stmtRoi = $pdo->prepare($sqlRoi);
            $stmtRoi->execute([':time' => $time, ':state_name' => $stateName, ':roi' => $roi]);
        }
    }
}

/**
 * Hitung dan simpan summary performance
 */
function updateSummaryPerformance(): void {
    global $pdo;
    
    $sqlRoi = "SELECT state_name, roi FROM raw_roi_btcidr ORDER BY ticker_time";
    $stmtRoi = $pdo->query($sqlRoi);
    $rois = $stmtRoi->fetchAll();
    
    if (empty($rois)) return;
    
    $totalTrades = count($rois);
    $winTrades = count(array_filter($rois, fn($r) => $r['roi'] > 0));
    $winRate = ($totalTrades > 0) ? ($winTrades / $totalTrades) * 100 : 0;
    $avgRoi = array_sum(array_column($rois, 'roi')) / $totalTrades;
    
    $grossProfit = array_sum(array_map(fn($r) => max($r['roi'], 0), $rois));
    $grossLoss = abs(array_sum(array_map(fn($r) => min($r['roi'], 0), $rois)));
    $profitFactor = ($grossLoss > 0) ? $grossProfit / $grossLoss : 0;
    
    $cumulative = 0;
    $peak = 0;
    $maxDrawdown = 0;
    foreach ($rois as $r) {
        $cumulative += $r['roi'];
        if ($cumulative > $peak) $peak = $cumulative;
        $drawdown = $peak - $cumulative;
        if ($drawdown > $maxDrawdown) $maxDrawdown = $drawdown;
    }
    
    $perState = [];
    foreach ($rois as $r) {
        $state = $r['state_name'];
        if (!isset($perState[$state])) {
            $perState[$state] = ['count' => 0, 'total_roi' => 0, 'wins' => 0];
        }
        $perState[$state]['count']++;
        $perState[$state]['total_roi'] += $r['roi'];
        if ($r['roi'] > 0) $perState[$state]['wins']++;
    }
    
    $perStatePerformance = [];
    foreach ($perState as $state => $data) {
        $perStatePerformance[$state] = [
            'total_trades' => $data['count'],
            'win_rate' => ($data['count'] > 0) ? ($data['wins'] / $data['count']) * 100 : 0,
            'avg_roi' => $data['total_roi'] / $data['count']
        ];
    }
    
    $jsonPerState = json_encode($perStatePerformance);
    
    $sqlInsert = "INSERT INTO summary_performance_btcidr 
                  (total_trades, win_rate, avg_roi, profit_factor, max_drawdown, per_state_performance)
                  VALUES (:total, :win_rate, :avg_roi, :pf, :max_dd, :perf)";
    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute([
        ':total' => $totalTrades,
        ':win_rate' => $winRate,
        ':avg_roi' => $avgRoi,
        ':pf' => $profitFactor,
        ':max_dd' => $maxDrawdown,
        ':perf' => $jsonPerState
    ]);
}

/**
 * Proses data baru: indikator, sinyal, ensemble, state, summary
 */
function processNewData(int $time): void {
    global $pdo;
    
    $sql = "SELECT * FROM raw_ticker_btcidr WHERE time = :time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':time' => $time]);
    $ticker = $stmt->fetch();
    if (!$ticker) return;
    
    $recent = getRecentTickers(INDICATOR_WINDOW);
    $dataCount = count($recent);
    $minRequired = max(RSI_PERIOD, MACD_SLOW, BB_PERIOD, STOCH_K_PERIOD) + 1;
    
    echo "[" . date('Y-m-d H:i:s') . "] Data terkumpul: $dataCount / $minRequired minimum\n";
    
    if ($dataCount < $minRequired) {
        return;
    }
    
    // Casting ke float untuk perhitungan presisi
    $closes = array_map('floatval', array_column($recent, 'last'));
    $highs = array_map('floatval', array_column($recent, 'high'));
    $lows = array_map('floatval', array_column($recent, 'low'));
    
    $rsi = calculateRSI($closes);
    $macdData = calculateMACD($closes);
    $bb = calculateBollingerBands($closes);
    $stoch = calculateStochastic($highs, $lows, $closes);
    $psar = calculatePSAR($highs, $lows);
    
    $indicators = [
        'rsi' => $rsi,
        'macd' => $macdData['macd'] ?? null,
        'macd_signal' => $macdData['signal'] ?? null,
        'macd_histogram' => $macdData['histogram'] ?? null,
        'bb_upper' => $bb['upper'] ?? null,
        'bb_middle' => $bb['middle'] ?? null,
        'bb_lower' => $bb['lower'] ?? null,
        'stoch_k' => $stoch['k'] ?? null,
        'stoch_d' => $stoch['d'] ?? null,
        'psar' => $psar
    ];
    
    saveIndicator($time, $indicators);
    
    $signals = generateSignals($ticker, $indicators);
    saveRawSignal($time, $signals);
    
    $ensemble = ensembleSignal($signals);
    saveTradeSignal($time, $ensemble);
    
    updateTradeState($time, (float)$ticker['last'], $signals, $ensemble);
    
    updateSummaryPerformance();
    
    echo "[" . date('Y-m-d H:i:s') . "] Data time $time diproses. Ensemble: $ensemble\n";
}

/**
 * Main loop
 */
function mainLoop(): void {
    echo "Crypto Trading Pipeline started. Press Ctrl+C to stop.\n";
    
    while (true) {
        $ticker = fetchTickerFromAPI();
        if ($ticker) {
            $saved = saveRawTicker($ticker);
            if ($saved) {
                processNewData($ticker['time']);
            } else {
                // Cek apakah indikator sudah ada
                global $pdo;
                $check = $pdo->prepare("SELECT 1 FROM raw_indicator_btcidr WHERE ticker_time = ?");
                $check->execute([$ticker['time']]);
                if (!$check->fetch()) {
                    processNewData($ticker['time']);
                }
            }
        }
        
        sleep(FETCH_INTERVAL_SECONDS);
    }
}

// Signal handling
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() {
        echo "\nPipeline dihentikan oleh user.\n";
        exit(0);
    });
}

// Jalankan
initDatabase();
mainLoop();
