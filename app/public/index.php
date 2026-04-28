<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BTC/USDT · Crypto Trading Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;500;700&family=Barlow:wght@400;600;700;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<style>
/* ══ DESIGN TOKENS ══════════════════════════════════════════════════════════ */
:root {
  --bg:       #060a0e;
  --bg2:      #0b1017;
  --surface:  #0f1923;
  --border:   #1a2535;
  --border2:  #243044;
  --accent:   #00e5a0;
  --accent-d: #00b37d;
  --gold:     #f5c542;
  --danger:   #ff3d5a;
  --purple:   #8b5cf6;
  --blue:     #38bdf8;
  --muted:    #3a4a5c;
  --dim:      #556678;
  --text:     #cdd9e8;
  --text2:    #8a9ab0;
  --buy:      #00e5a0;
  --sell:     #ff3d5a;
  --hold:     #f5c542;
  --mono:     'JetBrains Mono', monospace;
  --sans:     'Barlow', sans-serif;
  --radius:   10px;
  /* Override Bootstrap vars */
  --bs-body-bg: #060a0e;
  --bs-body-color: #cdd9e8;
  --bs-border-color: #1a2535;
  --bs-table-bg: transparent;
  --bs-table-striped-bg: rgba(255,255,255,0.02);
  --bs-table-hover-bg: rgba(0,229,160,0.05);
}

*, *::before, *::after { box-sizing: border-box; }
html { scroll-behavior: smooth; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  min-height: 100vh;
  overflow-x: hidden;
}
body::before {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,.07) 2px, rgba(0,0,0,.07) 4px);
}
body::after {
  content: '';
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image: linear-gradient(rgba(0,229,160,.02) 1px, transparent 1px), linear-gradient(90deg, rgba(0,229,160,.02) 1px, transparent 1px);
  background-size: 32px 32px;
}

/* ── NAVBAR ── */
.dash-navbar {
  position: relative; z-index: 100;
  background: rgba(11,16,23,0.97) !important;
  border-bottom: 1px solid var(--border);
  backdrop-filter: blur(10px);
  padding: 0.55rem 1.25rem;
}
.navbar-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
.logo-mark {
  width:38px; height:38px;
  background: linear-gradient(135deg, var(--accent), var(--accent-d));
  border-radius:9px;
  display:grid; place-items:center;
  font-size:1.25rem; font-weight:900; color:#000;
  box-shadow: 0 0 18px rgba(0,229,160,.3);
  flex-shrink:0;
}
.logo-name { font-size:1.1rem; font-weight:900; letter-spacing:-.5px; color:var(--accent); line-height:1.1; }
.logo-sub  { font-size:.58rem; color:var(--dim); font-family:var(--mono); letter-spacing:1.5px; text-transform:uppercase; }

.badge-live {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(0,229,160,.08); border:1px solid rgba(0,229,160,.3);
  color:var(--accent); border-radius:999px; padding:3px 10px;
  font-family:var(--mono); font-size:.6rem; letter-spacing:.5px;
}
.badge-live .dot { width:6px;height:6px;border-radius:50%;background:currentColor;animation:blink 1.6s ease-in-out infinite; }
.badge-count {
  display:inline-flex; align-items:center; gap:5px;
  background:rgba(255,255,255,.03); border:1px solid var(--border2);
  color:var(--dim); border-radius:999px; padding:3px 10px;
  font-family:var(--mono); font-size:.6rem;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }
#ts { font-family:var(--mono); font-size:.6rem; color:var(--dim); }

/* ── MAIN WRAP ── */
.main-wrap {
  position: relative; z-index: 1;
  max-width: 1540px; margin: 0 auto;
  padding: 20px 20px 60px;
}

/* ── ALERT / ERROR BANNER ── */
#err-banner {
  display:none;
  background:rgba(255,61,90,.07);
  border:1px solid rgba(255,61,90,.3);
  border-radius:8px;
  padding:10px 16px;
  font-family:var(--mono); font-size:.72rem;
  color:var(--danger); margin-bottom:16px;
}
#err-banner.on { display:flex; align-items:center; gap:8px; }

/* ── TAB NAV ── */
.dash-tabs {
  display:flex; gap:3px; padding:4px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:12px; margin-bottom:20px;
  overflow-x:auto; scrollbar-width:thin; scrollbar-color:var(--border2) transparent;
}
.tab-btn {
  flex:1; min-width:130px;
  padding:9px 13px; border:none; border-radius:8px;
  background:transparent; color:var(--dim);
  font-family:var(--mono); font-size:.6rem;
  font-weight:600; letter-spacing:.5px; text-transform:uppercase;
  cursor:pointer; transition:all .2s; white-space:nowrap;
}
.tab-btn:hover { background:rgba(255,255,255,.03); color:var(--text2); }
.tab-btn.active { background:rgba(0,229,160,.08); color:var(--accent); box-shadow:0 0 12px rgba(0,229,160,.1); }
.tab-btn .tab-num {
  display:inline-flex; align-items:center; justify-content:center;
  width:17px; height:17px; border-radius:4px;
  background:rgba(255,255,255,.05); font-size:.54rem; margin-right:5px;
}
.tab-btn.active .tab-num { background:rgba(0,229,160,.15); }

/* ── PAGE ── */
.page { display:none; }
.page.active { display:block; }

/* ── STAT CARDS ── */
.cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(165px,1fr)); gap:10px; margin-bottom:20px; }
.card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); padding:14px 16px;
  transition: border-color .2s, box-shadow .2s;
}
.card:hover { border-color:var(--border2); box-shadow:0 4px 20px rgba(0,0,0,.3); }
.card-lbl { font-size:.62rem; font-family:var(--mono); color:var(--dim); text-transform:uppercase; letter-spacing:.8px; margin-bottom:6px; }
.card-val { font-size:1.35rem; font-weight:700; color:var(--text); font-family:var(--mono); line-height:1.1; }
.card-val.buy  { color:var(--buy); }
.card-val.sell { color:var(--sell); }
.card-val.hold { color:var(--hold); }
.card-val.neu  { color:var(--blue); }
.card-sub { font-size:.6rem; color:var(--text2); margin-top:4px; font-family:var(--mono); }
.card-accent { border-left:3px solid var(--accent); }
.card-buy    { border-left:3px solid var(--buy); }
.card-sell   { border-left:3px solid var(--sell); }
.card-gold   { border-left:3px solid var(--gold); }

/* ── SECTION TITLE ── */
.stitle {
  font-size:.7rem; font-family:var(--mono); font-weight:600;
  letter-spacing:1px; text-transform:uppercase; color:var(--text2);
  margin:20px 0 10px; display:flex; align-items:center; gap:8px;
}
.stitle::after { content:''; flex:1; height:1px; background:var(--border); }

/* ── PAGE DESC ── */
.page-desc {
  background:rgba(56,189,248,.04); border:1px solid rgba(56,189,248,.12);
  border-radius:8px; padding:12px 16px;
  font-size:.78rem; color:var(--text2); line-height:1.7;
  margin-bottom:18px;
}

/* ── PIPELINE FLOW ── */
.pipeline-flow {
  display:flex; flex-wrap:wrap; align-items:center; gap:6px;
  margin-bottom:14px; padding:10px 14px;
  background:rgba(255,255,255,.02); border:1px solid var(--border);
  border-radius:8px;
}
.flow-step {
  padding:3px 10px; border-radius:5px; border:1px solid;
  font-family:var(--mono); font-size:.6rem; font-weight:500; letter-spacing:.5px;
}
.flow-step.s1 { background:rgba(56,189,248,.08);  color:var(--blue);   border-color:rgba(56,189,248,.2); }
.flow-step.s2 { background:rgba(139,92,246,.08);  color:var(--purple); border-color:rgba(139,92,246,.2); }
.flow-step.s3 { background:rgba(245,197,66,.08);  color:var(--gold);   border-color:rgba(245,197,66,.2); }
.flow-step.s4 { background:rgba(0,229,160,.08);   color:var(--accent); border-color:rgba(0,229,160,.2); }
.flow-step.s5 { background:rgba(255,61,90,.08);   color:var(--danger); border-color:rgba(255,61,90,.2); }
.flow-step.s6 { background:rgba(255,255,255,.04); color:var(--text2);  border-color:var(--border2); }
.flow-arrow   { color:var(--muted); font-size:.7rem; }

/* ── CHART GRID ── */
.chart-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px; }
.cc {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); padding:14px;
  transition: border-color .2s;
}
.cc:hover { border-color:var(--border2); }
.cc.wide { grid-column:1/-1; }
.cc-title { font-size:.6rem; font-family:var(--mono); color:var(--dim); text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
@media(max-width:700px) { .chart-grid { grid-template-columns:1fr; } .cc.wide { grid-column:1; } }

/* ── TABLES (Bootstrap override) ── */
.tbl-wrap {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); overflow:hidden; margin-bottom:20px; overflow-x:auto;
}
.tbl-wrap table { width:100%; border-collapse:collapse; min-width:500px; }
.tbl-wrap thead th {
  background:rgba(255,255,255,.03);
  font-family:var(--mono); font-size:.6rem; font-weight:600;
  letter-spacing:.8px; text-transform:uppercase; color:var(--dim);
  padding:10px 12px; border-bottom:1px solid var(--border2);
  white-space:nowrap;
}
.tbl-wrap tbody tr { border-bottom:1px solid rgba(255,255,255,.03); transition: background .15s; }
.tbl-wrap tbody tr:last-child { border-bottom:none; }
.tbl-wrap tbody tr:hover { background:rgba(0,229,160,.03); }
.tbl-wrap tbody td {
  padding:8px 12px; font-family:var(--mono); font-size:.7rem; color:var(--text);
  vertical-align:middle;
}
td.gold { color:var(--gold); font-weight:600; }
td.ts   { color:var(--dim); font-size:.65rem; }

/* ── SIGNAL BADGES ── */
.sig {
  display:inline-flex; align-items:center; justify-content:center;
  padding:2px 8px; border-radius:4px; font-family:var(--mono);
  font-size:.62rem; font-weight:700; letter-spacing:.5px; min-width:44px;
}
.sig-buy  { background:rgba(0,229,160,.12); color:var(--buy);  border:1px solid rgba(0,229,160,.25); }
.sig-sell { background:rgba(255,61,90,.12); color:var(--sell); border:1px solid rgba(255,61,90,.25); }
.sig-hold { background:rgba(245,197,66,.10); color:var(--hold); border:1px solid rgba(245,197,66,.25); }
.sig-ens  { font-size:.65rem; padding:3px 10px; border-radius:5px; font-weight:700; }

/* ── POSITION BADGES ── */
.pos-open   { background:rgba(0,229,160,.12); color:var(--buy);  border:1px solid rgba(0,229,160,.25); padding:2px 8px; border-radius:4px; font-family:var(--mono); font-size:.62rem; }
.pos-closed { background:rgba(56,189,248,.10); color:var(--blue); border:1px solid rgba(56,189,248,.25); padding:2px 8px; border-radius:4px; font-family:var(--mono); font-size:.62rem; }
.pos-none   { background:rgba(255,255,255,.04); color:var(--dim); border:1px solid var(--border2); padding:2px 8px; border-radius:4px; font-family:var(--mono); font-size:.62rem; }

/* ── FILTER BAR ── */
.filter-bar {
  display:flex; flex-wrap:wrap; align-items:center; gap:8px;
  margin-bottom:12px; padding:10px 14px;
  background:var(--surface); border:1px solid var(--border); border-radius:8px;
}
.filter-bar label { font-family:var(--mono); font-size:.62rem; color:var(--dim); }
.filter-bar select, .filter-bar input {
  background:var(--bg2); border:1px solid var(--border2); color:var(--text);
  border-radius:6px; padding:5px 10px; font-family:var(--mono); font-size:.68rem;
  outline:none; transition:border-color .2s;
}
.filter-bar select:focus, .filter-bar input:focus { border-color:var(--accent); }
.filter-bar .btn-refresh {
  background:rgba(0,229,160,.08); border:1px solid rgba(0,229,160,.25);
  color:var(--accent); border-radius:6px; padding:5px 14px;
  font-family:var(--mono); font-size:.65rem; font-weight:600; cursor:pointer; transition:all .2s;
}
.filter-bar .btn-refresh:hover { background:rgba(0,229,160,.16); }

/* ── ROI CARDS GRID ── */
.roi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; margin-bottom:20px; }
.roi-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); padding:16px;
  transition: border-color .2s, transform .15s;
}
.roi-card:hover { border-color:var(--border2); transform:translateY(-2px); }
.roi-card-name { font-family:var(--mono); font-size:.62rem; text-transform:uppercase; letter-spacing:1px; color:var(--dim); margin-bottom:10px; }
.roi-card-stat { margin-bottom:5px; }
.roi-card-stat-lbl { font-size:.58rem; font-family:var(--mono); color:var(--dim); }
.roi-card-stat-val { font-size:.9rem; font-weight:700; font-family:var(--mono); }
.roi-pos { color:var(--buy); }
.roi-neg { color:var(--sell); }
.roi-neu { color:var(--blue); }

/* ── PERF GRID ── */
.perf-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(155px,1fr)); gap:10px; margin-bottom:20px; }
.perf-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px; text-align:center; transition:border-color .2s; }
.perf-card:hover { border-color:var(--border2); }
.perf-lbl { font-family:var(--mono); font-size:.58rem; text-transform:uppercase; letter-spacing:.8px; color:var(--dim); margin-bottom:8px; }
.perf-val { font-size:1.5rem; font-weight:700; font-family:var(--mono); }
.perf-val.pos { color:var(--buy); }
.perf-val.neg { color:var(--sell); }
.perf-val.neu { color:var(--blue); }

/* ── PER-STATE TABLE ── */
#no-perf { background:rgba(245,197,66,.06); border:1px solid rgba(245,197,66,.2); border-radius:8px; padding:12px 16px; color:var(--gold); font-size:.78rem; margin-bottom:16px; display:none; }
#no-perf.on { display:block; }

/* ── EMPTY STATE ── */
.empty-state { text-align:center; padding:40px; color:var(--dim); font-size:.78rem; }
.empty-state .icon { font-size:2rem; margin-bottom:10px; }

/* ── TOAST (Bootstrap override) ── */
.toast-container { z-index:9999; }
.dash-toast { background:var(--surface) !important; border:1px solid var(--border2) !important; color:var(--text) !important; font-family:var(--mono); font-size:.7rem; }
.dash-toast .toast-header { background:rgba(0,229,160,.06) !important; color:var(--accent) !important; border-bottom:1px solid var(--border) !important; font-family:var(--mono); font-size:.65rem; }
.dash-toast .btn-close { filter:invert(1); }

/* ── SCROLLBAR ── */
::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-track { background:transparent; }
::-webkit-scrollbar-thumb { background:var(--border2); border-radius:3px; }
::-webkit-scrollbar-thumb:hover { background:var(--muted); }
</style>
</head>
<body>

<!-- ══ BOOTSTRAP NAVBAR ══════════════════════════════════════════════════ -->
<nav class="navbar dash-navbar sticky-top">
  <div class="container-fluid px-3">
    <a class="navbar-brand" href="#">
      <div class="logo-mark">&#8383;</div>
      <div>
        <div class="logo-name">BTC / USDT</div>
        <div class="logo-sub">Binance &middot; Kline 5m &middot; WebSocket</div>
      </div>
    </a>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <span class="badge-live"><span class="dot"></span>LIVE</span>
      <span class="badge-count" id="pill-count"><i class="bi bi-database me-1"></i>&mdash; data</span>
      <span id="ts"><i class="bi bi-clock me-1"></i>&ndash;</span>
    </div>
  </div>
</nav>

<!-- ══ TOAST CONTAINER ═══════════════════════════════════════════════════ -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="liveToast" class="toast dash-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
    <div class="toast-header">
      <i class="bi bi-broadcast me-2"></i>
      <strong class="me-auto">Data Update</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body" id="toast-msg">Sinyal baru tersedia.</div>
  </div>
</div>

<div class="main-wrap">

<!-- ── ERROR BANNER ── -->
<div id="err-banner"><i class="bi bi-exclamation-triangle-fill me-2"></i><span id="err-msg">Gagal terhubung ke database. Pipeline mungkin sedang mengumpulkan data awal.</span></div>

<!-- ══ TAB NAVIGATION ════════════════════════════════════════════════════ -->
<div class="dash-tabs">
  <button class="tab-btn active" onclick="switchTab(0)"><span class="tab-num">1</span><i class="bi bi-table me-1"></i>Raw Data</button>
  <button class="tab-btn" onclick="switchTab(1)"><span class="tab-num">2</span><i class="bi bi-activity me-1"></i>Raw Signal</button>
  <button class="tab-btn" onclick="switchTab(2)"><span class="tab-num">3</span><i class="bi bi-diagram-3 me-1"></i>Signal + Kombinasi</button>
  <button class="tab-btn" onclick="switchTab(3)"><span class="tab-num">4</span><i class="bi bi-lightning-charge me-1"></i>Sinyal Trade</button>
  <button class="tab-btn" onclick="switchTab(4)"><span class="tab-num">5</span><i class="bi bi-graph-up-arrow me-1"></i>ROI 6 State</button>
  <button class="tab-btn" onclick="switchTab(5)"><span class="tab-num">6</span><i class="bi bi-trophy me-1"></i>Summary</button>
</div>

<!-- ══ LIVE TICKER CARDS ══════════════════════════════════════════════════ -->
<div class="cards" id="live-cards">
  <div class="card card-accent">
    <div class="card-lbl"><i class="bi bi-currency-bitcoin me-1"></i>Close Price</div>
    <div class="card-val neu" id="lc-close">&mdash;</div>
    <div class="card-sub" id="lc-time">Menunggu data&hellip;</div>
  </div>
  <div class="card">
    <div class="card-lbl"><i class="bi bi-bar-chart me-1"></i>RSI (14)</div>
    <div class="card-val" id="lc-rsi">&mdash;</div>
    <div class="card-sub" id="lc-rsi-sub">&mdash;</div>
  </div>
  <div class="card">
    <div class="card-lbl"><i class="bi bi-graph-up me-1"></i>MACD</div>
    <div class="card-val" id="lc-macd">&mdash;</div>
    <div class="card-sub" id="lc-macd-sub">Hist: &mdash;</div>
  </div>
  <div class="card">
    <div class="card-lbl"><i class="bi bi-bezier me-1"></i>Bollinger</div>
    <div class="card-val" id="lc-bb">&mdash;</div>
    <div class="card-sub" id="lc-bb-sub">Mid: &mdash;</div>
  </div>
  <div class="card">
    <div class="card-lbl"><i class="bi bi-activity me-1"></i>Stoch K/D</div>
    <div class="card-val" id="lc-stoch">&mdash;</div>
    <div class="card-sub">&mdash;</div>
  </div>
  <div class="card">
    <div class="card-lbl"><i class="bi bi-arrow-up-right-circle me-1"></i>Volume (BTC)</div>
    <div class="card-val neu" id="lc-vol">&mdash;</div>
    <div class="card-sub" id="lc-trades">Trades: &mdash;</div>
  </div>
  <div class="card card-buy" id="lc-ens-card">
    <div class="card-lbl"><i class="bi bi-lightning-charge me-1"></i>Ensemble Signal</div>
    <div class="card-val" id="lc-ens">&mdash;</div>
    <div class="card-sub">Voting &ge;3 dari 5 indikator</div>
  </div>
</div>

<!-- ╔════════════════════════════════════════════════════════════════════╗
     ║  HALAMAN 1: RAW DATA — Kline OHLCV                             ║
     ╚════════════════════════════════════════════════════════════════════╝ -->
<div class="page active" id="page-0">
  <div class="pipeline-flow">
    <span class="flow-step s1">Binance REST + WS</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s1">raw_ticker_btcusdt</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s6">(Kline 5m OHLCV)</span>
  </div>

  <div class="page-desc">
    Data mentah kline BTC/USDT dari Binance (interval 5 menit). Diambil via REST API (500 data awal)
    dan dilanjutkan via WebSocket streaming real-time. Setiap baris merepresentasikan satu candle 5m
    dengan data Open, High, Low, Close, dan Volume.
  </div>

  <div class="stitle"><i class="bi bi-bar-chart-fill text-info me-1"></i> Harga BTC/USDT &middot; Chart 100 Data Terakhir</div>
  <div class="chart-grid">
    <div class="cc wide">
      <div class="cc-title">Candle: Open, High, Low, Close</div>
      <canvas id="p1Price" height="100"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Volume (BTC)</div>
      <canvas id="p1Vol" height="130"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Range (High - Low)</div>
      <canvas id="p1Range" height="130"></canvas>
    </div>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Tabel Raw Data (50 Terbaru)</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Open (USDT)</th>
          <th>High (USDT)</th>
          <th>Low (USDT)</th>
          <th>Close (USDT)</th>
          <th style="text-align:right">Volume (BTC)</th>
          <th style="text-align:right">Trades</th>
        </tr>
      </thead>
      <tbody id="p1-tbody">
        <tr><td colspan="7" style="text-align:center;color:var(--dim);padding:28px">Memuat raw data&hellip;</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ╔════════════════════════════════════════════════════════════════════╗
     ║  HALAMAN 2: RAW SIGNAL                                           ║
     ╚════════════════════════════════════════════════════════════════════╝ -->
<div class="page" id="page-1">
  <div class="pipeline-flow">
    <span class="flow-step s1">raw_ticker</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s2">Indikator (RSI, MACD, BB, Stoch, PSAR)</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s2">raw_signal_btcusdt</span>
  </div>

  <div class="page-desc">
    Sinyal mentah dari masing-masing indikator secara individual. Setiap indikator menghasilkan sinyal
    BUY / SELL / HOLD berdasarkan threshold-nya sendiri. Belum ada voting atau kombinasi di tahap ini.
    <br><strong>RSI:</strong> &lt;30=BUY, &gt;70=SELL &nbsp;|&nbsp;
    <strong>MACD:</strong> MACD&gt;Signal=BUY, else SELL &nbsp;|&nbsp;
    <strong>BB:</strong> &lt;Lower=BUY, &gt;Upper=SELL &nbsp;|&nbsp;
    <strong>Stoch:</strong> &lt;20=BUY, &gt;80=SELL &nbsp;|&nbsp;
    <strong>PSAR:</strong> Price&gt;PSAR=BUY, else SELL
    <br><br>Indikator dihitung dari <strong>500 data kline terakhir</strong>.
  </div>

  <div class="stitle"><i class="bi bi-graph-up text-warning me-1"></i> Distribusi Sinyal Per-Indikator</div>
  <div class="chart-grid">
    <div class="cc wide">
      <div class="cc-title">Jumlah Sinyal BUY / HOLD / SELL per Indikator</div>
      <canvas id="p2Dist" height="80"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Signal History (numeric: BUY=1, HOLD=0, SELL=-1)</div>
      <canvas id="p2Hist" height="130"></canvas>
    </div>
  </div>

  <div class="filter-bar">
    <label>Filter:</label>
    <select id="p2-filter-sig">
      <option value="">Semua Sinyal</option>
      <option value="BUY">BUY saja</option>
      <option value="SELL">SELL saja</option>
      <option value="HOLD">HOLD saja</option>
    </select>
    <select id="p2-filter-ind">
      <option value="">Semua Indikator</option>
      <option value="rsi">RSI</option>
      <option value="macd">MACD</option>
      <option value="bb">Bollinger Bands</option>
      <option value="stoch">Stochastic</option>
      <option value="psar">Parabolic SAR</option>
    </select>
    <button class="btn-refresh" onclick="fetchRawSignal()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Tabel Raw Signal (50 Terbaru)</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Close (USDT)</th>
          <th style="text-align:center">RSI</th>
          <th style="text-align:center">MACD</th>
          <th style="text-align:center">BB</th>
          <th style="text-align:center">Stoch</th>
          <th style="text-align:center">PSAR</th>
        </tr>
      </thead>
      <tbody id="p2-tbody">
        <tr><td colspan="7" style="text-align:center;color:var(--dim);padding:28px">Memuat raw signal&hellip;</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ╔════════════════════════════════════════════════════════════════════╗
     ║  HALAMAN 3: SIGNAL INDICATOR + KOMBINASI (ENSEMBLE)             ║
     ╚════════════════════════════════════════════════════════════════════╝ -->
<div class="page" id="page-2">
  <div class="pipeline-flow">
    <span class="flow-step s2">raw_signal (5 indikator)</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s3">Voting &ge;3 dari 5</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s3">trade_signal_btcusdt (ensemble)</span>
  </div>

  <div class="page-desc">
    Kombinasi sinyal dari 5 indikator menggunakan <strong>voting majority</strong>.
    Jika &ge;3 indikator menghasilkan BUY &rarr; Ensemble = BUY.
    Jika &ge;3 indikator menghasilkan SELL &rarr; Ensemble = SELL.
    Selain itu = HOLD. Tabel ini menampilkan semua sinyal individu + hasil ensemble per baris.
  </div>

  <div class="stitle"><i class="bi bi-bar-chart-fill text-info me-1"></i> Chart Ensemble Signal</div>
  <div class="chart-grid">
    <div class="cc wide">
      <div class="cc-title">Ensemble Signal History &middot; BUY=1 / HOLD=0 / SELL=-1</div>
      <canvas id="p3Ens" height="100"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Signal per Indikator vs Ensemble</div>
      <canvas id="p3Comp" height="130"></canvas>
    </div>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Tabel Signal Indicator + Ensemble (50 Terbaru)</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Close (USDT)</th>
          <th style="text-align:center">RSI</th>
          <th style="text-align:center">MACD</th>
          <th style="text-align:center">BB</th>
          <th style="text-align:center">Stoch</th>
          <th style="text-align:center">PSAR</th>
          <th style="text-align:center">Voting</th>
          <th style="text-align:center; background: rgba(0,229,160,.04)">ENSEMBLE</th>
        </tr>
      </thead>
      <tbody id="p3-tbody">
        <tr><td colspan="9" style="text-align:center;color:var(--dim);padding:28px">Memuat kombinasi sinyal&hellip;</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ╔════════════════════════════════════════════════════════════════════╗
     ║  HALAMAN 4: SIGNAL TRADE                                          ║
     ╚════════════════════════════════════════════════════════════════════╝ -->
<div class="page" id="page-3">
  <div class="pipeline-flow">
    <span class="flow-step s3">trade_signal (ensemble)</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s4">State Machine</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s4">NONE &rarr; OPEN &rarr; CLOSED</span>
  </div>

  <div class="page-desc">
    Sinyal trade yang dieksekusi oleh state machine. Setiap strategi (RSI, MACD, BB, Stoch, PSAR, Ensemble)
    menjalankan state machine-nya sendiri: <strong>NONE</strong> &rarr; jika BUY &rarr; <strong>OPEN</strong> (entry price dicatat) &rarr;
    jika SELL &rarr; <strong>CLOSED</strong> (exit price dicatat, ROI dihitung).
  </div>

  <div class="stitle"><i class="bi bi-graph-up text-warning me-1"></i> Ensemble Trade Signal Chart</div>
  <div class="chart-grid">
    <div class="cc wide">
      <div class="cc-title">Ensemble Signal + Harga Close BTC/USDT</div>
      <canvas id="p4TradeChart" height="100"></canvas>
    </div>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Tabel Sinyal Trade (50 Terbaru)</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Close (USDT)</th>
          <th style="text-align:center">Ensemble Signal</th>
        </tr>
      </thead>
      <tbody id="p4-sig-tbody">
        <tr><td colspan="3" style="text-align:center;color:var(--dim);padding:28px">Memuat sinyal trade&hellip;</td></tr>
      </tbody>
    </table>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Trade State per Strategi (200 Terbaru)</div>
  <div class="filter-bar">
    <label>Filter Strategi:</label>
    <select id="p4-filter-state">
      <option value="">Semua Strategi</option>
      <option value="rsi">RSI</option>
      <option value="macd">MACD</option>
      <option value="bb">Bollinger Bands</option>
      <option value="stoch">Stochastic</option>
      <option value="psar">Parabolic SAR</option>
      <option value="ensemble">Ensemble</option>
    </select>
    <select id="p4-filter-pos">
      <option value="">Semua Posisi</option>
      <option value="OPEN">OPEN saja</option>
      <option value="CLOSED">CLOSED saja</option>
      <option value="NONE">NONE saja</option>
    </select>
    <button class="btn-refresh" onclick="fetchTradeStates()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Strategi</th>
          <th style="text-align:center">Posisi</th>
          <th style="text-align:right">Entry Price</th>
          <th style="text-align:right">Exit Price</th>
          <th style="text-align:right">Close Price</th>
        </tr>
      </thead>
      <tbody id="p4-state-tbody">
        <tr><td colspan="6" style="text-align:center;color:var(--dim);padding:28px">Memuat trade state&hellip;</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ╔════════════════════════════════════════════════════════════════════╗
     ║  HALAMAN 5: ROI DARI 6 STATE                                     ║
     ╚════════════════════════════════════════════════════════════════════╝ -->
<div class="page" id="page-4">
  <div class="pipeline-flow">
    <span class="flow-step s4">Trade CLOSED</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s5">ROI = (Exit - Entry) / Entry * 100</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s5">raw_roi_btcusdt (per strategi)</span>
  </div>

  <div class="page-desc">
    Return on Investment (ROI) dari setiap trade yang ditutup (CLOSED), dipecah per 6 strategi/state:
    <strong>RSI, MACD, Bollinger Bands, Stochastic, Parabolic SAR, Ensemble</strong>.
    ROI dihitung saat posisi OPEN di-SELL dan berubah menjadi CLOSED.
  </div>

  <div class="stitle"><i class="bi bi-graph-up text-warning me-1"></i> Summary ROI per State</div>
  <div class="roi-grid" id="p5-roi-cards">
    <div class="empty-state" style="grid-column: 1/-1"><div class="icon">&#128200;</div>Memuat ROI data&hellip;</div>
  </div>

  <div class="stitle"><i class="bi bi-bar-chart-fill text-info me-1"></i> Chart ROI per State</div>
  <div class="chart-grid">
    <div class="cc wide">
      <div class="cc-title">Cumulative ROI per Strategi</div>
      <canvas id="p5CumRoi" height="110"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Avg ROI per Strategi</div>
      <canvas id="p5AvgRoi" height="130"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Win Rate per Strategi</div>
      <canvas id="p5WinRate" height="130"></canvas>
    </div>
  </div>

  <div class="filter-bar">
    <label>Filter Strategi:</label>
    <select id="p5-filter-state">
      <option value="">Semua Strategi</option>
      <option value="rsi">RSI</option>
      <option value="macd">MACD</option>
      <option value="bb">Bollinger Bands</option>
      <option value="stoch">Stochastic</option>
      <option value="psar">Parabolic SAR</option>
      <option value="ensemble">Ensemble</option>
    </select>
    <button class="btn-refresh" onclick="fetchROIStates()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Detail ROI per Trade</div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>Strategi</th>
          <th style="text-align:right">ROI (%)</th>
          <th style="text-align:right">Close (USDT)</th>
        </tr>
      </thead>
      <tbody id="p5-tbody">
        <tr><td colspan="4" style="text-align:center;color:var(--dim);padding:28px">Memuat ROI data&hellip;</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ╔════════════════════════════════════════════════════════════════════╗
     ║  HALAMAN 6: SUMMARY PERFORMANCE                                   ║
     ╚════════════════════════════════════════════════════════════════════╝ -->
<div class="page" id="page-5">
  <div class="pipeline-flow">
    <span class="flow-step s5">raw_roi_btcusdt</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s6">Aggregasi: Win Rate, Profit Factor, Max DD</span>
    <span class="flow-arrow">&rarr;</span>
    <span class="flow-step s6">summary_performance_btcusdt</span>
  </div>

  <div class="page-desc">
    Ringkasan performance keseluruhan dari seluruh trade yang telah ditutup. Termasuk win rate,
    average ROI, profit factor, max drawdown, dan breakdown per-state.
  </div>

  <div id="no-perf">&#8505; Belum ada data ROI &mdash; dibutuhkan minimal 1 trade selesai (BUY &rarr; SELL).</div>

  <div class="stitle"><i class="bi bi-bar-chart-fill text-info me-1"></i> Performance Overview</div>
  <div class="perf-grid">
    <div class="perf-card">
      <div class="perf-lbl">Total Trades</div>
      <div class="perf-val neu" id="p6-total">&mdash;</div>
    </div>
    <div class="perf-card">
      <div class="perf-lbl">Win Rate</div>
      <div class="perf-val" id="p6-wr">&mdash;</div>
    </div>
    <div class="perf-card">
      <div class="perf-lbl">Avg ROI</div>
      <div class="perf-val" id="p6-roi">&mdash;</div>
    </div>
    <div class="perf-card">
      <div class="perf-lbl">Profit Factor</div>
      <div class="perf-val neu" id="p6-pf">&mdash;</div>
    </div>
    <div class="perf-card">
      <div class="perf-lbl">Max Drawdown</div>
      <div class="perf-val neg" id="p6-dd">&mdash;</div>
    </div>
  </div>

  <div class="stitle"><i class="bi bi-graph-up text-warning me-1"></i> Performance Charts</div>
  <div class="chart-grid">
    <div class="cc">
      <div class="cc-title">Per-State Win Rate</div>
      <canvas id="p6WrChart" height="130"></canvas>
    </div>
    <div class="cc">
      <div class="cc-title">Per-State Avg ROI</div>
      <canvas id="p6RoiChart" height="130"></canvas>
    </div>
  </div>

  <div class="stitle"><i class="bi bi-table text-secondary me-1"></i> Breakdown per Strategi</div>
  <div class="tbl-wrap" id="p6-state-wrap" style="display:none">
    <table>
      <thead>
        <tr>
          <th>Strategi</th>
          <th style="text-align:center">Total Trades</th>
          <th style="text-align:center">Win Rate</th>
          <th style="text-align:center">Avg ROI</th>
        </tr>
      </thead>
      <tbody id="p6-state-tbody"></tbody>
    </table>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════════════════════ -->
<script>
'use strict';

/* ── Helpers ─────────────────────────────────────────────────────── */
const $ = id => document.getElementById(id);
const usdt = n => (n != null && n !== '') ? '$\u00a0' + new Intl.NumberFormat('en-US', {minimumFractionDigits:2,maximumFractionDigits:2}).format(parseFloat(n)) : '\u2013';
const f2  = n => (n != null && n !== '') ? parseFloat(n).toFixed(2) : '\u2013';
const f4  = n => (n != null && n !== '') ? parseFloat(n).toFixed(4) : '\u2013';
const ts  = epoch => new Date(epoch * 1000).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
const td  = epoch => new Date(epoch * 1000).toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + ts(epoch);

const API = '/api/data.php';

/* ── Tab Navigation ──────────────────────────────────────────────── */
let currentTab = 0;

function switchTab(idx) {
  currentTab = idx;
  document.querySelectorAll('.tab-btn').forEach((btn, i) => {
    btn.classList.toggle('active', i === idx);
  });
  document.querySelectorAll('.page').forEach((page, i) => {
    page.classList.toggle('active', i === idx);
  });
  refreshPage(idx);
}

function refreshPage(idx) {
  // fetchTicker() dipanggil HANYA sekali, hasilnya dishare via tickerPromise
  // sehingga switch-case di bawah tidak perlu duplikasi request
  switch(idx) {
    case 0: fetchTicker(); fetchRawData(); break;
    case 1: fetchTicker(); fetchRawSignal(); break;
    case 2: fetchTicker(); fetchIndicatorCombo(); break;
    case 3: fetchTicker(); fetchTradeSignals(); fetchTradeStates(); break;
    case 4: fetchTicker(); fetchROISummary(); fetchROIStates(); break;
    case 5: fetchTicker(); fetchPerf(); break;
  }
}

/* ── Chart defaults ──────────────────────────────────────────────── */
Chart.defaults.color       = '#556678';
Chart.defaults.font.family = 'JetBrains Mono';
Chart.defaults.font.size   = 10;

const baseScales = {
  x: { grid: { color: '#1a2535' }, ticks: { maxTicksLimit: 8, color: '#3a4a5c' } },
  y: { grid: { color: '#1a2535' }, ticks: { color: '#3a4a5c' } },
};
const baseOpts = {
  responsive: true,
  animation: { duration: 180 },
  plugins: { legend: { labels: { boxWidth: 10, padding: 10 } } },
  scales: baseScales,
};

function setChart(chart, labels, ...cols) {
  chart.data.labels = labels;
  cols.forEach((d, i) => chart.data.datasets[i].data = d);
  chart.update('none');
}

/* ── Chart instances (lazy init) ─────────────────────────────────── */
const charts = {};

function getChart(id, config) {
  if (!charts[id]) {
    charts[id] = new Chart($(id).getContext('2d'), config);
  }
  return charts[id];
}

/* ═══════════════════════════════════════════════════════════════════
   LIVE TICKER — dijalankan setiap refresh, update semua stat cards
   ═══════════════════════════════════════════════════════════════════ */
let _tickerInflight = null;

async function fetchTicker() {
  // Deduplicate: jika request sedang berjalan, kembalikan promise yang sama
  // (tidak perlu throttle waktu — cukup hindari 2 request paralel)
  if (_tickerInflight) return _tickerInflight;
  _tickerInflight = _doFetchTicker().finally(() => { _tickerInflight = null; });
  return _tickerInflight;
}

async function _doFetchTicker() {
  try {
    const d = await fetch(API + '?action=ticker').then(r => r.json());
    if (!d || !d.time) { showErr('Data ticker kosong – pipeline sedang berjalan?'); return; }
    hideErr();

    // Close price
    const closeVal = parseFloat(d.close);
    const openVal  = parseFloat(d.open);
    const isGreen  = closeVal >= openVal;
    const closeEl  = $('lc-close');
    closeEl.textContent = usdt(d.close);
    closeEl.className   = 'card-val ' + (isGreen ? 'buy' : 'sell');
    $('lc-time').textContent = '⏱ ' + td(d.time);

    // RSI
    const rsi = parseFloat(d.rsi);
    const rsiEl = $('lc-rsi');
    rsiEl.textContent = isNaN(rsi) ? '–' : f2(rsi);
    rsiEl.className   = 'card-val ' + (rsi < 30 ? 'buy' : rsi > 70 ? 'sell' : 'hold');
    $('lc-rsi-sub').textContent = rsi < 30 ? '↑ Oversold' : rsi > 70 ? '↓ Overbought' : '— Neutral';

    // MACD
    const macd = parseFloat(d.macd);
    const macdsig = parseFloat(d.macd_signal);
    const macdEl = $('lc-macd');
    macdEl.textContent = isNaN(macd) ? '–' : f4(macd);
    macdEl.className   = 'card-val ' + (macd > macdsig ? 'buy' : 'sell');
    $('lc-macd-sub').textContent = 'Hist: ' + (isNaN(d.macd_histogram) ? '–' : f4(d.macd_histogram));

    // BB
    const close = closeVal;
    const bbUpper = parseFloat(d.bb_upper);
    const bbLower = parseFloat(d.bb_lower);
    const bbMid   = parseFloat(d.bb_middle);
    const bbEl = $('lc-bb');
    bbEl.textContent = isNaN(bbUpper) ? '–' : usdt(bbUpper) + ' / ' + usdt(bbLower);
    bbEl.className   = 'card-val ' + (close < bbLower ? 'buy' : close > bbUpper ? 'sell' : 'hold');
    $('lc-bb-sub').textContent = 'Mid: ' + usdt(bbMid);

    // Stoch
    const stochK = parseFloat(d.stoch_k);
    const stochD = parseFloat(d.stoch_d);
    const stochEl = $('lc-stoch');
    stochEl.textContent = isNaN(stochK) ? '–' : f2(stochK) + ' / ' + f2(stochD);
    stochEl.className   = 'card-val ' + (stochK < 20 ? 'buy' : stochK > 80 ? 'sell' : 'hold');

    // Volume
    $('lc-vol').textContent    = isNaN(d.volume)  ? '–' : parseFloat(d.volume).toFixed(4);
    $('lc-trades').textContent = 'Trades: ' + (d.trades || '–');

    // Ensemble signal card
    const ens = d.ensemble_signal;
    const ensEl  = $('lc-ens');
    const ensCard = $('lc-ens-card');
    const ensIcon = ens === 'BUY' ? '↑ ' : ens === 'SELL' ? '↓ ' : '— ';
    ensEl.textContent = ensIcon + (ens || '–');
    ensEl.className   = 'card-val ' + (ens === 'BUY' ? 'buy' : ens === 'SELL' ? 'sell' : 'hold');
    ensCard.className = 'card ' + (ens === 'BUY' ? 'card-buy' : ens === 'SELL' ? 'card-sell' : 'card-gold');

    // Navbar timestamp
    $('ts').innerHTML = '<i class="bi bi-clock me-1"></i>' + td(d.time);

    // Toast kalau data fresh (< 6 menit)
    const ageMin = (Date.now() / 1000 - d.time) / 60;
    if (ageMin < 6) showToast('Kline: ' + ts(d.time) + ' | ' + usdt(d.close) + ' | ' + (ens || '–'));
  } catch (e) {
    showErr('Gagal mengambil ticker: ' + e.message);
  }
}

/* ═══════════════════════════════════════════════════════════════════
   HALAMAN 1: RAW DATA — Kline OHLCV
   ═══════════════════════════════════════════════════════════════════ */
async function fetchRawData() {
  try {
    const rows = await fetch(API + '?action=raw_data&limit=100').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p1-tbody').innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--dim);padding:28px">Belum ada raw data.</td></tr>';
      return;
    }

    const labels = rows.map(r => ts(r.time));

    // Price chart: Open, High, Low, Close
    const cPrice = getChart('p1Price', {
      type: 'line',
      data: {
        labels: [],
        datasets: [
          { label: 'Close', data: [], borderColor: '#00e5a0', borderWidth: 1.5, pointRadius: 0, tension: .2, fill: false },
          { label: 'High',  data: [], borderColor: 'rgba(245,197,66,.4)', borderWidth: 1, pointRadius: 0, borderDash: [3,3], fill: false },
          { label: 'Low',   data: [], borderColor: 'rgba(56,189,248,.4)',  borderWidth: 1, pointRadius: 0, borderDash: [3,3], fill: false },
        ],
      },
      options: baseOpts,
    });

    setChart(cPrice, labels,
      rows.map(r => r.close),
      rows.map(r => r.high),
      rows.map(r => r.low)
    );

    // Volume chart (BTC)
    const cVol = getChart('p1Vol', {
      type: 'bar',
      data: {
        labels: [],
        datasets: [
          { label: 'Volume (BTC)', data: [], backgroundColor: 'rgba(139,92,246,.3)', borderColor: 'rgba(139,92,246,.5)', borderWidth: 1 },
        ],
      },
      options: baseOpts,
    });
    setChart(cVol, labels, rows.map(r => parseFloat(r.volume)));

    // Range chart (High - Low)
    const cRange = getChart('p1Range', {
      type: 'line',
      data: {
        labels: [],
        datasets: [
          { label: 'Range (H-L)', data: [], borderColor: '#f5c542', borderWidth: 1.5, pointRadius: 0, tension: .2, fill: false },
        ],
      },
      options: baseOpts,
    });
    setChart(cRange, labels, rows.map(r => parseFloat(r.high) - parseFloat(r.low)));

    // Table (show latest 50)
    const tbody = $('p1-tbody');
    const tableRows = rows.slice(-50);
    tbody.innerHTML = tableRows.map(r => {
      const isOpenGreen  = parseFloat(r.close) >= parseFloat(r.open);
      const closeColor  = isOpenGreen ? 'var(--buy)' : 'var(--sell)';
      return `
        <tr>
          <td class="dim">${ts(r.time)}</td>
          <td>${usdt(r.open)}</td>
          <td style="color:var(--buy)">${usdt(r.high)}</td>
          <td style="color:var(--sell)">${usdt(r.low)}</td>
          <td style="color:${closeColor};font-weight:700">${usdt(r.close)}</td>
          <td style="text-align:right;color:var(--text2)">${parseFloat(r.volume).toFixed(6)}</td>
          <td style="text-align:right;color:var(--dim)">${r.trades || '\u2013'}</td>
        </tr>
      `;
    }).join('');
  } catch (e) { console.error('fetchRawData:', e); }
}

/* ═══════════════════════════════════════════════════════════════════
   HALAMAN 2: RAW SIGNAL
   ═══════════════════════════════════════════════════════════════════ */
async function fetchRawSignal() {
  try {
    const rows = await fetch(API + '?action=raw_signal&limit=100').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p2-tbody').innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--dim);padding:28px">Belum ada raw signal.</td></tr>';
      return;
    }

    // Distribution chart
    const indNames = ['signal_rsi', 'signal_macd', 'signal_bb', 'signal_stoch', 'signal_psar'];
    const indLabels = ['RSI', 'MACD', 'BB', 'Stoch', 'PSAR'];
    const buyCounts = [], sellCounts = [], holdCounts = [];
    indNames.forEach(ind => {
      buyCounts.push(rows.filter(r => r[ind] === 'BUY').length);
      sellCounts.push(rows.filter(r => r[ind] === 'SELL').length);
      holdCounts.push(rows.filter(r => r[ind] === 'HOLD').length);
    });

    const cDist = getChart('p2Dist', {
      type: 'bar',
      data: {
        labels: indLabels,
        datasets: [
          { label: 'BUY',  data: buyCounts,  backgroundColor: 'rgba(0,229,160,.5)',  borderColor: '#00e5a0', borderWidth: 1 },
          { label: 'HOLD', data: holdCounts, backgroundColor: 'rgba(245,197,66,.3)', borderColor: '#f5c542', borderWidth: 1 },
          { label: 'SELL', data: sellCounts, backgroundColor: 'rgba(255,61,90,.5)',   borderColor: '#ff3d5a', borderWidth: 1 },
        ],
      },
      options: baseOpts,
    });
    setChart(cDist, indLabels, buyCounts, holdCounts, sellCounts);

    // History chart (last 50)
    const last50 = rows.slice(-50);
    const labels = last50.map(r => ts(r.time));
    const sigMap = { BUY: 1, HOLD: 0, SELL: -1 };

    const cHist = getChart('p2Hist', {
      type: 'line',
      data: {
        labels: [],
        datasets: [
          { label: 'RSI',   data: [], borderColor: '#a78bfa', borderWidth: 1.2, pointRadius: 2, tension: 0, fill: false, stepped: 'before' },
          { label: 'MACD',  data: [], borderColor: '#38bdf8', borderWidth: 1.2, pointRadius: 2, tension: 0, fill: false, stepped: 'before' },
          { label: 'BB',    data: [], borderColor: '#fbbf24', borderWidth: 1.2, pointRadius: 2, tension: 0, fill: false, stepped: 'before' },
          { label: 'Stoch', data: [], borderColor: '#34d399', borderWidth: 1.2, pointRadius: 2, tension: 0, fill: false, stepped: 'before' },
          { label: 'PSAR',  data: [], borderColor: '#fb7185', borderWidth: 1.2, pointRadius: 2, tension: 0, fill: false, stepped: 'before' },
        ],
      },
      options: {
        ...baseOpts,
        scales: {
          ...baseScales,
          y: { ...baseScales.y, min: -1.5, max: 1.5, ticks: { ...baseScales.y.ticks, callback: v => ({ 1:'BUY', 0:'HOLD', '-1':'SELL' }[v] ?? '') } },
        },
      },
    });
    setChart(cHist, labels,
      last50.map(r => sigMap[r.signal_rsi] ?? null),
      last50.map(r => sigMap[r.signal_macd] ?? null),
      last50.map(r => sigMap[r.signal_bb] ?? null),
      last50.map(r => sigMap[r.signal_stoch] ?? null),
      last50.map(r => sigMap[r.signal_psar] ?? null)
    );

    // Filter
    const filterSig = $('p2-filter-sig').value;
    const filterInd = $('p2-filter-ind').value;
    let filtered = last50;
    if (filterSig) {
      if (filterInd) {
        filtered = filtered.filter(r => r['signal_' + filterInd] === filterSig);
      } else {
        filtered = filtered.filter(r =>
          r.signal_rsi === filterSig || r.signal_macd === filterSig ||
          r.signal_bb === filterSig || r.signal_stoch === filterSig || r.signal_psar === filterSig
        );
      }
    } else if (filterInd) {
      filtered = filtered.filter(r => r['signal_' + filterInd] !== 'HOLD');
    }

    const b = s => {
      const cls = s === 'BUY' ? 'sig sig-buy' : s === 'SELL' ? 'sig sig-sell' : s === 'HOLD' ? 'sig sig-hold' : 'sig sig-hold';
      const icon = s === 'BUY' ? '&#x2191;' : s === 'SELL' ? '&#x2193;' : s ? '&#x2212;' : '';
      return s ? `<span class="${cls}">${icon} ${s}</span>` : '<span style="color:var(--dim)">–</span>';
    };
    const tbody = $('p2-tbody');
    tbody.innerHTML = filtered.length > 0 ? filtered.map(r => `
      <tr>
        <td class="dim">${ts(r.time)}</td>
        <td class="gold">${usdt(r.close)}</td>
        <td class="center">${b(r.signal_rsi)}</td>
        <td class="center">${b(r.signal_macd)}</td>
        <td class="center">${b(r.signal_bb)}</td>
        <td class="center">${b(r.signal_stoch)}</td>
        <td class="center">${b(r.signal_psar)}</td>
      </tr>
    `).join('') : '<tr><td colspan="7" style="text-align:center;color:var(--dim);padding:28px">Tidak ada data yang cocok dengan filter.</td></tr>';
  } catch (e) { console.error('fetchRawSignal:', e); }
}

/* ═══════════════════════════════════════════════════════════════════
   HALAMAN 3: SIGNAL INDICATOR + KOMBINASI
   ═══════════════════════════════════════════════════════════════════ */
async function fetchIndicatorCombo() {
  try {
    const rows = await fetch(API + '?action=indicator_combo&limit=100').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p3-tbody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--dim);padding:28px">Belum ada data kombinasi sinyal.</td></tr>';
      return;
    }

    const labels = rows.map(r => ts(r.time));
    const ensMap = { BUY: 1, HOLD: 0, SELL: -1 };

    // Ensemble chart
    const cEns = getChart('p3Ens', {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          label: 'Ensemble',
          data: [],
          borderColor: '#00e5a0', borderWidth: 2,
          pointRadius: 4,
          pointBackgroundColor: ctx => {
            const v = parseFloat(ctx.raw);
            return v === 1 ? '#00e5a0' : v === -1 ? '#ff3d5a' : '#f5c542';
          },
          stepped: 'before', fill: false,
        }],
      },
      options: {
        ...baseOpts,
        plugins: { legend: { display: false } },
        scales: {
          ...baseScales,
          y: { ...baseScales.y, min: -1.5, max: 1.5, ticks: { ...baseScales.y.ticks, callback: v => ({ 1:'BUY', 0:'HOLD', '-1':'SELL' }[v] ?? '') } },
        },
      },
    });
    setChart(cEns, labels, rows.map(r => r.ensemble_signal != null ? (ensMap[r.ensemble_signal] ?? null) : null));

    // Comparison chart
    const agreement = rows.map(r => {
      const signals = [r.signal_rsi, r.signal_macd, r.signal_bb, r.signal_stoch, r.signal_psar];
      const ens = r.ensemble_signal;
      return signals.filter(s => s === ens).length;
    });

    const cComp = getChart('p3Comp', {
      type: 'bar',
      data: {
        labels: [],
        datasets: [
          { label: 'Kesepakatan (dari 5)', data: [], backgroundColor: ctx => {
            const v = parseFloat(ctx.raw);
            if (v >= 4) return 'rgba(0,229,160,.4)';
            if (v >= 3) return 'rgba(245,197,66,.3)';
            return 'rgba(255,61,90,.3)';
          }, borderWidth: 0 },
        ],
      },
      options: { ...baseOpts, plugins: { legend: { display: false } }, scales: { ...baseScales, y: { ...baseScales.y, min: 0, max: 5 } } },
    });
    setChart(cComp, labels, agreement);

    // Table
    const b = s => {
      const cls = s === 'BUY' ? 'sig sig-buy' : s === 'SELL' ? 'sig sig-sell' : s === 'HOLD' ? 'sig sig-hold' : 'sig sig-hold';
      const icon = s === 'BUY' ? '&#x2191;' : s === 'SELL' ? '&#x2193;' : s ? '&#x2212;' : '';
      return s ? `<span class="${cls}">${icon} ${s}</span>` : '<span style="color:var(--dim)">–</span>';
    };
    const vb = s => `<span class="voting-bar ${s}">${s}</span>`;

    const tbody = $('p3-tbody');
    const tableRows = rows.slice(-50);
    tbody.innerHTML = tableRows.map(r => {
      const signals = [r.signal_rsi, r.signal_macd, r.signal_bb, r.signal_stoch, r.signal_psar];
      const voting = `<span class="voting-row">${signals.map(s => vb(s)).join('')}</span>`;
      const ensCls = r.ensemble_signal || 'none';
      const ensBg = ensCls === 'BUY' ? 'background:rgba(0,229,160,.06)' : ensCls === 'SELL' ? 'background:rgba(255,61,90,.06)' : '';
      return `
        <tr>
          <td class="dim">${ts(r.time)}</td>
          <td class="gold">${usdt(r.close)}</td>
          <td class="center">${b(r.signal_rsi)}</td>
          <td class="center">${b(r.signal_macd)}</td>
          <td class="center">${b(r.signal_bb)}</td>
          <td class="center">${b(r.signal_stoch)}</td>
          <td class="center">${b(r.signal_psar)}</td>
          <td class="center">${voting}</td>
          <td class="center" style="${ensBg}"><span class="badge ${ensCls}" style="font-size:.62rem;padding:3px 10px">${r.ensemble_signal || '\u2013'}</span></td>
        </tr>
      `;
    }).join('');
  } catch (e) { console.error('fetchIndicatorCombo:', e); }
}

/* ═══════════════════════════════════════════════════════════════════
   HALAMAN 4: SIGNAL TRADE
   ═══════════════════════════════════════════════════════════════════ */
async function fetchTradeSignals() {
  try {
    const rows = await fetch(API + '?action=trade_signals&limit=100').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p4-sig-tbody').innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--dim);padding:28px">Belum ada trade signal.</td></tr>';
      return;
    }

    const labels = rows.map(r => ts(r.time));
    const ensMap = { BUY: 1, HOLD: 0, SELL: -1 };

    const cTrade = getChart('p4TradeChart', {
      type: 'line',
      data: {
        labels: [],
        datasets: [
          { label: 'Close BTC/USDT', data: [], borderColor: '#00e5a0', borderWidth: 1.5, pointRadius: 0, tension: .2, fill: false, yAxisID: 'y' },
          { label: 'Signal', data: [], type: 'line', borderColor: '#f5c542', borderWidth: 2, pointRadius: 4,
            pointBackgroundColor: ctx => { const v = parseFloat(ctx.raw); return v === 1 ? '#00e5a0' : v === -1 ? '#ff3d5a' : '#f5c542'; },
            stepped: 'before', fill: false, yAxisID: 'y1' },
        ],
      },
      options: {
        ...baseOpts,
        scales: {
          x: baseScales.x,
          y: { ...baseScales.y, position: 'left' },
          y1: { ...baseScales.y, position: 'right', min: -1.5, max: 1.5, grid: { display: false }, ticks: { callback: v => ({ 1:'BUY', 0:'HOLD', '-1':'SELL' }[v] ?? '') } },
        },
      },
    });
    setChart(cTrade, labels,
      rows.map(r => parseFloat(r.close)),
      rows.map(r => r.ensemble_signal != null ? (ensMap[r.ensemble_signal] ?? null) : null)
    );

    const b = s => {
      const cls = s === 'BUY' ? 'sig sig-buy' : s === 'SELL' ? 'sig sig-sell' : s === 'HOLD' ? 'sig sig-hold' : 'sig sig-hold';
      const icon = s === 'BUY' ? '&#x2191;' : s === 'SELL' ? '&#x2193;' : s ? '&#x2212;' : '';
      return s ? `<span class="${cls}">${icon} ${s}</span>` : '<span style="color:var(--dim)">–</span>';
    };
    $('p4-sig-tbody').innerHTML = rows.slice(-50).map(r => `
      <tr>
        <td class="dim">${ts(r.time)}</td>
        <td class="gold">${usdt(r.close)}</td>
        <td class="center">${b(r.ensemble_signal)}</td>
      </tr>
    `).join('');
  } catch (e) { console.error('fetchTradeSignals:', e); }
}

async function fetchTradeStates() {
  try {
    const rows = await fetch(API + '?action=trade_states&limit=200').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p4-state-tbody').innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--dim);padding:28px">Belum ada trade state.</td></tr>';
      return;
    }

    const filterState = $('p4-filter-state').value;
    const filterPos = $('p4-filter-pos').value;
    let filtered = rows;
    if (filterState) filtered = filtered.filter(r => r.state_name === filterState);
    if (filterPos) filtered = filtered.filter(r => r.position === filterPos);

    const posBadge = p => {
      const cls = p === 'OPEN' ? 'badge-pos' : p === 'CLOSED' ? 'badge-neg' : 'badge-neutral';
      return `<span class="${cls}" style="padding:2px 8px;border-radius:3px;font-family:var(--mono);font-size:.57rem;font-weight:700;display:inline-block">${p}</span>`;
    };

    const tbody = $('p4-state-tbody');
    tbody.innerHTML = filtered.length > 0 ? filtered.slice(-200).map(r => `
      <tr>
        <td class="dim">${ts(r.time)}</td>
        <td style="text-transform:uppercase;font-weight:700;letter-spacing:1px;color:var(--accent)">${r.state_name}</td>
        <td class="center">${posBadge(r.position)}</td>
        <td style="text-align:right">${r.entry_price ? usdt(r.entry_price) : '\u2013'}</td>
        <td style="text-align:right">${r.exit_price ? usdt(r.exit_price) : '\u2013'}</td>
        <td style="text-align:right;color:var(--text2)">${usdt(r.close)}</td>
      </tr>
    `).join('') : '<tr><td colspan="6" style="text-align:center;color:var(--dim);padding:28px">Tidak ada data yang cocok dengan filter.</td></tr>';
  } catch (e) { console.error('fetchTradeStates:', e); }
}

/* ═══════════════════════════════════════════════════════════════════
   HALAMAN 5: ROI 6 STATE
   ═══════════════════════════════════════════════════════════════════ */
const stateColors = {
  rsi:      { bg: 'rgba(167,139,250,.4)',  border: '#a78bfa' },
  macd:     { bg: 'rgba(56,189,248,.4)',   border: '#38bdf8' },
  bb:       { bg: 'rgba(251,191,36,.4)',   border: '#fbbf24' },
  stoch:    { bg: 'rgba(52,211,153,.4)',   border: '#34d399' },
  psar:     { bg: 'rgba(251,113,133,.4)',  border: '#fb7185' },
  ensemble: { bg: 'rgba(0,229,160,.4)',    border: '#00e5a0' },
};
const stateNames = { rsi: 'RSI', macd: 'MACD', bb: 'Bollinger Bands', stoch: 'Stochastic', psar: 'Parabolic SAR', ensemble: 'Ensemble' };

async function fetchROISummary() {
  try {
    const rows = await fetch(API + '?action=roi_summary').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p5-roi-cards').innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="icon">&#128200;</div>Belum ada ROI data.</div>';
      return;
    }

    $('p5-roi-cards').innerHTML = rows.map(r => {
      const avgRoi = parseFloat(r.avg_roi);
      const wr = parseFloat(r.win_rate);
      const color = avgRoi >= 0 ? 'var(--buy)' : 'var(--sell)';
      const wrColor = wr >= 50 ? 'var(--buy)' : 'var(--sell)';
      return `
        <div class="roi-card">
          <div class="roi-card-title">${stateNames[r.state_name] || r.state_name.toUpperCase()}</div>
          <div class="roi-card-row"><span class="label">Total Trades</span><span class="value">${r.total_trades}</span></div>
          <div class="roi-card-row"><span class="label">Win Rate</span><span class="value" style="color:${wrColor}">${f2(r.win_rate)}%</span></div>
          <div class="roi-card-row"><span class="label">Avg ROI</span><span class="value" style="color:${color}">${f2(r.avg_roi)}%</span></div>
          <div class="roi-card-row"><span class="label">Total ROI</span><span class="value" style="color:${color}">${f2(r.total_roi)}%</span></div>
          <div class="roi-card-row"><span class="label">Min ROI</span><span class="value" style="color:var(--sell)">${f2(r.min_roi)}%</span></div>
          <div class="roi-card-row"><span class="label">Max ROI</span><span class="value" style="color:var(--buy)">${f2(r.max_roi)}%</span></div>
        </div>
      `;
    }).join('');

    const labels = rows.map(r => stateNames[r.state_name] || r.state_name);
    const bgColors = rows.map(r => stateColors[r.state_name]?.bg || 'rgba(255,255,255,.1)');
    const bdColors = rows.map(r => stateColors[r.state_name]?.border || '#fff');

    getChart('p5AvgRoi', {
      type: 'bar',
      data: { labels: [], datasets: [{ label: 'Avg ROI (%)', data: [], backgroundColor: bgColors, borderColor: bdColors, borderWidth: 1 }] },
      options: baseOpts,
    });
    setChart(charts['p5AvgRoi'], labels, rows.map(r => parseFloat(r.avg_roi)));

    getChart('p5WinRate', {
      type: 'bar',
      data: { labels: [], datasets: [{ label: 'Win Rate (%)', data: [], backgroundColor: bgColors, borderColor: bdColors, borderWidth: 1 }] },
      options: baseOpts,
    });
    setChart(charts['p5WinRate'], labels, rows.map(r => parseFloat(r.win_rate)));

  } catch (e) { console.error('fetchROISummary:', e); }
}

async function fetchROIStates() {
  try {
    const rows = await fetch(API + '?action=roi_states').then(r => r.json());
    if (!Array.isArray(rows) || rows.length === 0) {
      $('p5-tbody').innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--dim);padding:28px">Belum ada ROI data.</td></tr>';
      return;
    }

    const filterState = $('p5-filter-state').value;
    let filtered = rows;
    if (filterState) filtered = filtered.filter(r => r.state_name === filterState);

    // Cumulative ROI chart
    const states = ['rsi', 'macd', 'bb', 'stoch', 'psar', 'ensemble'];
    const cumData = {};
    const roiByTime = {};
    rows.forEach(r => {
      if (!roiByTime[r.time]) roiByTime[r.time] = {};
      roiByTime[r.time][r.state_name] = parseFloat(r.roi);
    });

    states.forEach(s => { cumData[s] = []; });
    let cum = {};
    states.forEach(s => { cum[s] = 0; });
    Object.keys(roiByTime).sort((a,b) => a - b).forEach(t => {
      states.forEach(s => {
        if (roiByTime[t][s] != null) cum[s] += roiByTime[t][s];
        cumData[s].push(cum[s]);
      });
    });
    const cLabels = Object.keys(roiByTime).sort((a,b) => a - b).map(t => ts(parseInt(t)));

    const cCumRoi = getChart('p5CumRoi', {
      type: 'line',
      data: {
        labels: [],
        datasets: states.map(s => ({
          label: stateNames[s],
          data: [],
          borderColor: stateColors[s].border,
          backgroundColor: stateColors[s].bg,
          borderWidth: 1.5,
          pointRadius: 0,
          tension: .2,
          fill: false,
        })),
      },
      options: baseOpts,
    });
    setChart(cCumRoi, cLabels, ...states.map(s => cumData[s]));

    // Table
    const tbody = $('p5-tbody');
    tbody.innerHTML = filtered.length > 0 ? filtered.slice(-100).reverse().map(r => {
      const roi = parseFloat(r.roi);
      const color = roi >= 0 ? 'var(--buy)' : 'var(--sell)';
      return `
        <tr>
          <td class="dim">${ts(r.time)}</td>
          <td style="text-transform:uppercase;font-weight:700;letter-spacing:1px;color:${stateColors[r.state_name]?.border || 'var(--text)'}">${stateNames[r.state_name] || r.state_name}</td>
          <td style="text-align:right;font-weight:700;color:${color}">${roi >= 0 ? '+' : ''}${f4(r.roi)}%</td>
          <td style="text-align:right;color:var(--text2)">${usdt(r.close)}</td>
        </tr>
      `;
    }).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--dim);padding:28px">Tidak ada data yang cocok dengan filter.</td></tr>';
  } catch (e) { console.error('fetchROIStates:', e); }
}

/* ═══════════════════════════════════════════════════════════════════
   HALAMAN 6: SUMMARY PERFORMANCE
   ═══════════════════════════════════════════════════════════════════ */
async function fetchPerf() {
  try {
    const d = await fetch(API + '?action=performance').then(r => r.json());

    if (!d || !d.total_trades) {
      $('no-perf').classList.add('on'); return;
    }
    $('no-perf').classList.remove('on');

    const wr  = parseFloat(d.win_rate);
    const roi = parseFloat(d.avg_roi);

    $('p6-total').textContent = d.total_trades;
    $('p6-wr').textContent    = f2(d.win_rate) + '%';
    $('p6-wr').className      = 'perf-val ' + (wr  >= 50 ? 'pos' : 'neg');
    $('p6-roi').textContent   = f2(d.avg_roi)  + '%';
    $('p6-roi').className     = 'perf-val ' + (roi >= 0  ? 'pos' : 'neg');
    $('p6-pf').textContent    = f2(d.profit_factor);
    $('p6-dd').textContent    = f2(d.max_drawdown) + '%';

    if (d.per_state_performance && Object.keys(d.per_state_performance).length) {
      $('p6-state-wrap').style.display = 'block';
      const perfData = d.per_state_performance;

      $('p6-state-tbody').innerHTML = Object.entries(perfData).map(([st, p]) => `
        <tr>
          <td style="text-transform:uppercase;font-weight:700;letter-spacing:1px;color:${stateColors[st]?.border || 'var(--text)'}">${stateNames[st] || st}</td>
          <td class="center">${p.total_trades}</td>
          <td class="center" style="color:${parseFloat(p.win_rate) >= 50 ? 'var(--buy)' : 'var(--sell)'}">${f2(p.win_rate)}%</td>
          <td class="center" style="color:${parseFloat(p.avg_roi)  >= 0  ? 'var(--buy)' : 'var(--sell)'}">${f2(p.avg_roi)}%</td>
        </tr>
      `).join('');

      const sLabels = Object.keys(perfData).map(s => stateNames[s] || s);
      const sColors = Object.keys(perfData).map(s => stateColors[s]?.bg || 'rgba(255,255,255,.1)');
      const sBorders = Object.keys(perfData).map(s => stateColors[s]?.border || '#fff');

      getChart('p6WrChart', {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Win Rate (%)', data: [], backgroundColor: sColors, borderColor: sBorders, borderWidth: 1 }] },
        options: baseOpts,
      });
      setChart(charts['p6WrChart'], sLabels, Object.values(perfData).map(p => parseFloat(p.win_rate)));

      getChart('p6RoiChart', {
        type: 'bar',
        data: { labels: [], datasets: [{ label: 'Avg ROI (%)', data: [], backgroundColor: sColors, borderColor: sBorders, borderWidth: 1 }] },
        options: baseOpts,
      });
      setChart(charts['p6RoiChart'], sLabels, Object.values(perfData).map(p => parseFloat(p.avg_roi)));
    }
  } catch (e) { console.error('fetchPerf:', e); }
}

/* ── Fetch: stats pill ───────────────────────────────────────────── */
function showToast(msg) {
  const el = document.getElementById('liveToast');
  if (!el) return;
  document.getElementById('toast-msg').textContent = msg;
  const t = bootstrap.Toast.getOrCreateInstance(el);
  t.show();
}

async function fetchStats() {
  try {
    const d = await fetch(API + '?action=stats').then(r => r.json());
    if (d.raw_ticker_btcusdt != null)
      $('pill-count').innerHTML = '<i class="bi bi-database me-1"></i>' + new Intl.NumberFormat('en-US').format(d.raw_ticker_btcusdt) + ' klines &nbsp;·&nbsp; BTC/USDT';
  } catch (e) {}
}

/* ── Error helpers ───────────────────────────────────────────────── */
function showErr(m) { $('err-banner').classList.add('on');    $('err-msg').textContent = m; }
function hideErr()  { $('err-banner').classList.remove('on'); }

/* ── Boot ────────────────────────────────────────────────────────── */
// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
  });
});

refreshPage(0);
fetchStats();

setInterval(() => refreshPage(currentTab), 15_000);
setInterval(fetchStats, 60_000);

// Filter event listeners
$('p2-filter-sig').addEventListener('change', fetchRawSignal);
$('p2-filter-ind').addEventListener('change', fetchRawSignal);
$('p4-filter-state').addEventListener('change', fetchTradeStates);
$('p4-filter-pos').addEventListener('change', fetchTradeStates);
$('p5-filter-state').addEventListener('change', fetchROIStates);
</script>
</div><!-- /.main-wrap -->
</body>
</html>
