# Crypto Trading Pipeline — BTC/USDT

Dashboard trading real-time berbasis **Binance API** (REST + WebSocket) dengan indikator teknikal
(RSI, MACD, Bollinger Bands, Stochastic, Parabolic SAR) dan ensemble signal.

---

## Struktur Proyek

```
indikator-trade/
├── docker-compose.yml       ← Orkestrasi semua service
├── .env.example             ← Template .env untuk referensi
│
├── fetch/                   ← Worker: pipeline PHP
│   ├── Dockerfile
│   ├── composer.json
│   └── pipeline.php         ← Loop fetch → indicator → signal → trade state
│
├── app/                     ← Dashboard web PHP
│   ├── Dockerfile
│   ├── db.php               ← Koneksi database (singleton PDO)
│   └── public/              ← Apache document root
│       ├── .htaccess
│       ├── index.php        ← Dashboard frontend (HTML + Chart.js)
│       └── api/
│           └── data.php     ← REST API endpoint
│
└── postgres/
    └── init/
        ├── 01_schema.sql    ← Schema awal (dijalankan saat init container)
        └── 02_migrate.sql   ← Migration: summary_performance single-row design
```

---

## Cara Menjalankan

### 1. Pastikan Docker & Docker Compose terinstall
```bash
docker --version
docker compose version
```

### 2. Clone / salin proyek
```bash
cd /opt  # atau direktori pilihan Anda
# letakkan folder indikator-trade di sini
```

### 3. Salin dan sesuaikan .env
```bash
cp .env.example .env
# Edit .env jika ingin mengganti kredensial, symbol, atau timezone
```

### 4. Build dan jalankan
```bash
docker compose up -d --build
```

### 5. Cek status semua container
```bash
docker compose ps
docker compose logs -f fetch   # melihat log pipeline
```

---

## Akses Dashboard

Buka browser:
```
http://localhost:8080
```

Atau jika menggunakan Nginx Proxy Manager, konfigurasi Proxy Host:
- **Scheme**: `http`
- **Forward Hostname**: `crypto_app`
- **Forward Port**: `80`

API endpoint tersedia di:
```
/api/data.php?action=ticker
/api/data.php?action=signals
/api/data.php?action=raw_data
/api/data.php?action=raw_signal
/api/data.php?action=indicator_combo
/api/data.php?action=trade_signals
/api/data.php?action=trade_states
/api/data.php?action=roi_states
/api/data.php?action=roi_summary
/api/data.php?action=performance
/api/data.php?action=stats
```

---

## Arsitektur

```
[Binance REST + WebSocket]
     │
     ▼
[fetch container]  ─────────────────────────┐
  pipeline.php                              │
  1. Fetch kline via REST (500 data awal)   │ crypto_internal network
  2. Stream kline via WebSocket real-time  │
  3. Hitung RSI, MACD, BB, Stoch, PSAR     │
  4. Generate signal per indikator          │
  5. Ensemble voting (>=3 dari 5 = BUY/SELL) │
  6. Trade state machine (OPEN/CLOSED)      │
  7. Hitung ROI & performance summary       │
     │                                      │
     └──────────────── [postgres] ◄─────────┘
                            │
                       [app container]
                       db.php + REST API + Dashboard
                            │
                  [Nginx Proxy Manager] (opsional)
                            │
                        [Browser]
                     Dashboard Chart.js
```

---

## Tabel Database

| Tabel | Isi |
|---|---|
| `raw_ticker_btcusdt` | Data kline mentah dari Binance (OHLCV) |
| `raw_indicator_btcusdt` | Nilai RSI, MACD, BB, Stochastic, PSAR |
| `raw_signal_btcusdt` | Sinyal per indikator (BUY/SELL/HOLD) |
| `trade_signal_btcusdt` | Ensemble signal hasil voting |
| `trade_state_btcusdt` | State machine per strategi (OPEN/CLOSED) |
| `raw_roi_btcusdt` | ROI setiap trade yang ditutup |
| `summary_performance_btcusdt` | Agregat performa (win rate, avg ROI, dll) |

---

## Troubleshooting

**Pipeline tidak berjalan:**
```bash
docker compose logs fetch
```

**Database tidak bisa diakses:**
```bash
docker compose logs postgres
docker exec -it crypto_postgres psql -U postgres -d trading_db
```

**Rebuild setelah perubahan kode:**
```bash
docker compose up -d --build fetch   # rebuild worker saja
docker compose up -d --build app     # rebuild dashboard saja
```

**Reset total (hapus semua data):**
```bash
docker compose down -v   # menghapus volume postgres_data
docker compose up -d --build
```
