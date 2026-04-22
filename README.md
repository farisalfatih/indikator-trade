# ========================
# FINAL BACKEND ARCHITECTURE (SIMPLIFIED)
# CRYPTO DATA PIPELINE BTC/IDR
# ========================

## 🎯 CORE GOAL
1. Ingest data (fetch market)
2. Transform (indikator teknikal)
3. Generate signal
4. Evaluate (ROI berbasis trade event)
5. Serve (API)

---

## 🧱 DATA LAYER

### 1. ticker_1m (RAW DATA)
- time (PK)
- price_last
- price_buy
- price_sell
- high
- low
- vol_btc
- vol_idr

---

### 2. indicator_1m (NUMERIC TRANSFORM)
- time (PK)
- rsi
- macd
- macd_signal
- bb_upper
- bb_lower
- stoch
- psar

---

### 3. signal_1m (ALL SIGNAL)
- time (PK)
- rsi        (-1 SELL, 0 HOLD, 1 BUY)
- macd       (-1 SELL, 0 HOLD, 1 BUY)
- bollinger  (-1 SELL, 0 HOLD, 1 BUY)
- stoch      (-1 SELL, 0 HOLD, 1 BUY)
- psar       (-1 SELL, 0 HOLD, 1 BUY)
- ensemble   (-1 SELL, 0 HOLD, 1 BUY)

RULE ENSEMBLE:
- BUY  jika ≥ 4 indikator = BUY
- SELL jika ≥ 4 indikator = SELL
- HOLD selain itu

---

### 4. trade_log (EVENT-BASED TRADING)
- id
- strategy (rsi/macd/bollinger/stoch/psar/ensemble)
- entry_time
- exit_time
- entry_price
- exit_price
- position (1 BUY, -1 SELL)
- roi

FORMULA:
ROI = (exit_price - entry_price) / entry_price * 100

NOTE:
- hanya disimpan saat trade CLOSE
- bukan per menit

---

### 5. performance_summary (OPTIONAL / DERIVED)
- strategy
- total_trade
- win_rate
- avg_roi
- profit_factor
- max_drawdown

---

## ⚙️ PIPELINE FLOW

[Worker Loop]

1. FETCH
   → insert ke ticker_1m

2. INDICATOR ENGINE
   → ticker_1m → indicator_1m

3. SIGNAL ENGINE
   → indicator_1m → signal_1m

4. TRADE ENGINE (STATE MACHINE)
   → signal_1m + price → trade_log

---

## 🧠 TRADE ENGINE LOGIC (CORE)

IF signal berubah dari HOLD → BUY:
    open position

IF signal berubah dari BUY → SELL:
    close position → hitung ROI

IF signal berubah dari HOLD → SELL:
    open short (optional)

IF signal berubah dari SELL → BUY:
    close position → hitung ROI

---

## 🐳 DOCKER ARCHITECTURE

SERVICES:

1. app (PHP)
   - REST API
   - indicator engine
   - signal engine
   - trade engine

2. worker (PHP CLI)
   - loop setiap 60 detik
   - menjalankan pipeline

3. db (PostgreSQL)

---

## 🔄 WORKER LOOP

while true:
    fetch data dari API
    insert ticker

    calculate indicator
    insert indicator

    generate signal
    insert signal

    run trade engine
    update trade_log

    sleep(60)

---

## 🌐 API ENDPOINT (MINIMAL)

GET /ticker/latest
GET /indicator/latest
GET /signal/latest
GET /trades
GET /performance

---

## 📊 FRONTEND (MINIMAL)

1. Price + Indicator Chart
2. Signal Overlay
3. Trade + ROI

---

## ⚖️ DESIGN PRINCIPLES

- modular pipeline
- event-based trading (bukan per menit)
- time-series consistency
- scalable worker
- minimal table, maksimal fungsi

---

## 🚀 MENTAL MODEL

RAW → TRANSFORM → SIGNAL → EVENT → ANALYTICS
