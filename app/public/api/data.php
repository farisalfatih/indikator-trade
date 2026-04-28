<?php
/**
 * REST API — Crypto Trading Dashboard
 *
 * Endpoint  : GET /api/data.php?action=<action>
 * Actions   : ticker | signals | performance | stats
 *            | raw_data | raw_signal | indicator_combo | trade_signals | roi_states | trade_states
 *
 * Data source: Binance BTCUSDT Kline 5m
 */

declare(strict_types=1);

// db.php berada dua level di atas (app/db.php)
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'ticker';

// ── Helper: cache header per jenis endpoint ──────────────────────────────────
// 'ticker' & 'raw_*' berubah tiap 5 menit → cache singkat (10 detik)
// 'performance' & 'stats' berubah lebih jarang → cache lebih lama (30 detik)
// Default: no-store untuk keamanan
$cacheSeconds = match($action) {
    'ticker', 'raw_data', 'raw_signal',
    'indicator_combo', 'trade_signals',
    'trade_states', 'signals'      => 10,
    'stats', 'performance',
    'roi_summary', 'roi_states'    => 30,
    default                        => 0,
};

if ($cacheSeconds > 0) {
    header("Cache-Control: public, max-age={$cacheSeconds}");
} else {
    header('Cache-Control: no-store, no-cache, must-revalidate');
}

try {
    $pdo = getDB();

    switch ($action) {

        // ── Ticker + indikator + sinyal terbaru ──────────────────────────────
        case 'ticker':
            $stmt = $pdo->query("
                SELECT
                    t.time, t.open, t.high, t.low, t.close, t.volume,
                    t.quote_volume, t.trades, t.open_time, t.close_time,
                    i.rsi, i.macd, i.macd_signal, i.macd_histogram,
                    i.bb_upper, i.bb_middle, i.bb_lower,
                    i.stoch_k, i.stoch_d, i.psar,
                    s.signal_rsi, s.signal_macd, s.signal_bb,
                    s.signal_stoch, s.signal_psar,
                    ts.ensemble_signal
                FROM raw_ticker_btcusdt t
                LEFT JOIN raw_indicator_btcusdt  i  ON i.ticker_time  = t.time
                LEFT JOIN raw_signal_btcusdt     s  ON s.ticker_time  = t.time
                LEFT JOIN trade_signal_btcusdt   ts ON ts.ticker_time = t.time
                ORDER BY t.time DESC
                LIMIT 1
            ");
            echo json_encode($stmt->fetch() ?: (object)[]);
            break;

        // ── 20 sinyal terbaru untuk tabel riwayat ────────────────────────────
        case 'signals':
            $stmt = $pdo->query("
                SELECT
                    t.time, t.close,
                    s.signal_rsi, s.signal_macd, s.signal_bb,
                    s.signal_stoch, s.signal_psar,
                    ts.ensemble_signal
                FROM raw_ticker_btcusdt t
                LEFT JOIN raw_signal_btcusdt   s  ON s.ticker_time  = t.time
                LEFT JOIN trade_signal_btcusdt ts ON ts.ticker_time = t.time
                ORDER BY t.time DESC
                LIMIT 20
            ");
            echo json_encode($stmt->fetchAll());
            break;

        // ── Summary performance terbaru ───────────────────────────────────────
        case 'performance':
            // Prioritas WHERE id=1 (post-migration). Fallback ORDER BY untuk DB lama.
            $stmt = $pdo->query("
                SELECT
                    total_trades, win_rate, avg_roi,
                    profit_factor, max_drawdown,
                    per_state_performance, calculated_at
                FROM summary_performance_btcusdt
                ORDER BY calculated_at DESC
                LIMIT 1
            ");
            $row = $stmt->fetch();
            if ($row && isset($row['per_state_performance'])) {
                $row['per_state_performance'] = json_decode($row['per_state_performance'], true);
            }
            echo json_encode($row ?: (object)[]);
            break;

        // ── Jumlah data per tabel (untuk status pill) ─────────────────────────
        case 'stats':
            // 1 query dengan subquery — hindari 4 round-trip terpisah
            $stmt = $pdo->query("
                SELECT
                    (SELECT COUNT(*) FROM raw_ticker_btcusdt)    AS raw_ticker_btcusdt,
                    (SELECT COUNT(*) FROM raw_indicator_btcusdt) AS raw_indicator_btcusdt,
                    (SELECT COUNT(*) FROM trade_signal_btcusdt)  AS trade_signal_btcusdt,
                    (SELECT COUNT(*) FROM raw_roi_btcusdt)       AS raw_roi_btcusdt
            ");
            echo json_encode($stmt->fetch());
            break;

        // ── HALAMAN 1: Raw Data (kline OHLCV) ────────────────────────────────
        case 'raw_data':
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            // Subquery: ambil N terbaru lalu ORDER ASC — hindari array_reverse PHP-side
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT time, open_time, open, high, low, close, volume,
                           quote_volume, trades, close_time, created_at
                    FROM raw_ticker_btcusdt
                    ORDER BY time DESC
                    LIMIT :limit
                ) sub ORDER BY time ASC
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        // ── HALAMAN 2: Raw Signal (sinyal per-indikator) ────────────────────────
        case 'raw_signal':
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT t.time, t.close,
                           s.signal_rsi, s.signal_macd, s.signal_bb,
                           s.signal_stoch, s.signal_psar
                    FROM raw_signal_btcusdt s
                    JOIN raw_ticker_btcusdt t ON t.time = s.ticker_time
                    ORDER BY t.time DESC
                    LIMIT :limit
                ) sub ORDER BY time ASC
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        // ── HALAMAN 3: Signal Indicator + Kombinasi (ensemble) ─────────────────
        case 'indicator_combo':
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT t.time, t.close,
                           s.signal_rsi, s.signal_macd, s.signal_bb,
                           s.signal_stoch, s.signal_psar,
                           ts.ensemble_signal
                    FROM raw_signal_btcusdt s
                    JOIN raw_ticker_btcusdt t ON t.time = s.ticker_time
                    JOIN trade_signal_btcusdt ts ON ts.ticker_time = t.time
                    ORDER BY t.time DESC
                    LIMIT :limit
                ) sub ORDER BY time ASC
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        // ── HALAMAN 4: Trade Signals + Trade States ─────────────────────────────
        case 'trade_signals':
            $limit = min((int)($_GET['limit'] ?? 50), 200);
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT ts.ticker_time as time, ts.ensemble_signal, t.close
                    FROM trade_signal_btcusdt ts
                    JOIN raw_ticker_btcusdt t ON t.time = ts.ticker_time
                    ORDER BY ts.ticker_time DESC
                    LIMIT :limit
                ) sub ORDER BY time ASC
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        case 'trade_states':
            $limit = min((int)($_GET['limit'] ?? 200), 500);
            $stmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT ts.ticker_time as time, ts.state_name, ts.position,
                           ts.entry_price, ts.exit_price, t.close
                    FROM trade_state_btcusdt ts
                    JOIN raw_ticker_btcusdt t ON t.time = ts.ticker_time
                    ORDER BY ts.ticker_time DESC
                    LIMIT :limit
                ) sub ORDER BY time ASC
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        // ── HALAMAN 5: ROI dari 6 State ─────────────────────────────────────────
        case 'roi_states':
            // Tambah LIMIT default — tanpa ini bisa tarik ribuan baris setelah running lama
            $limit = min((int)($_GET['limit'] ?? 500), 2000);
            $stmt = $pdo->prepare("
                SELECT r.ticker_time as time, r.state_name, r.roi, t.close
                FROM raw_roi_btcusdt r
                JOIN raw_ticker_btcusdt t ON t.time = r.ticker_time
                ORDER BY r.ticker_time ASC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        case 'roi_summary':
            $stmt = $pdo->query("
                SELECT state_name,
                       COUNT(*) as total_trades,
                       ROUND(AVG(roi)::numeric, 4) as avg_roi,
                       ROUND(SUM(CASE WHEN roi > 0 THEN 1 ELSE 0 END)::numeric / COUNT(*) * 100, 2) as win_rate,
                       ROUND(MIN(roi)::numeric, 4) as min_roi,
                       ROUND(MAX(roi)::numeric, 4) as max_roi,
                       ROUND(SUM(roi)::numeric, 4) as total_roi
                FROM raw_roi_btcusdt
                GROUP BY state_name
                ORDER BY state_name
            ");
            echo json_encode($stmt->fetchAll());
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    // Jangan expose pesan error DB ke client (bisa berisi kredensial/struktur query)
    error_log('[data.php] PDOException: ' . $e->getMessage());
    echo json_encode(['error' => 'Internal server error. Coba beberapa saat lagi.']);
}

