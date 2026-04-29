<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

/* ── AUTH CHECK ───────────────────────────────────────────── */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php"); exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../unauthorized.php"); exit();
}
if (!isset($_SESSION['username'])) $_SESSION['username'] = "Admin";

$username = $_SESSION['username'];

// Profile
$profile_query = $conn->prepare("SELECT full_name, email, role FROM admin_users WHERE username = ?");
$profile_query->bind_param("s", $username);
$profile_query->execute();
$profile_data = $profile_query->get_result()->fetch_assoc();
$is_superadmin = ($profile_data['role'] === 'superadmin');

// System settings
$result = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
$settings = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result))
        $settings[$row['setting_key']] = $row['setting_value'];
}

// Active regions
$regions_query = $conn->query("SELECT region_name FROM regions WHERE is_active = 1");
$active_regions = [];
if ($regions_query) {
    while ($r = $regions_query->fetch_assoc()) $active_regions[] = $r['region_name'];
}

function isChecked($key, $settings) {
    return (isset($settings[$key]) && $settings[$key] === '1') ? 'checked' : '';
}

// Failed logins today
$today = date('Y-m-d');
$fl_today_res = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs WHERE action LIKE '%Failed%' AND DATE(created_at) = '$today'");
$failed_today = ($fl_today_res && $fl_today_res->num_rows > 0) ? (int)$fl_today_res->fetch_assoc()['cnt'] : 0;

// Failed logins this week
$week_start = date('Y-m-d', strtotime('monday this week'));
$fl_week_res = $conn->query("SELECT COUNT(*) AS cnt FROM activity_logs WHERE action LIKE '%Failed%' AND DATE(created_at) >= '$week_start'");
$failed_week = ($fl_week_res && $fl_week_res->num_rows > 0) ? (int)$fl_week_res->fetch_assoc()['cnt'] : 0;

// Recent security events (last 5)
$ev_res = $conn->query(
    "SELECT username, action, ip_address, created_at FROM activity_logs
     WHERE action LIKE '%Login%' OR action LIKE '%Failed%'
        OR action LIKE '%Security%' OR action LIKE '%Password%'
     ORDER BY created_at DESC LIMIT 5"
);
$recent_events = [];
if ($ev_res) { while ($ev = $ev_res->fetch_assoc()) $recent_events[] = $ev; }

// Security score (out of 5 key features)
$sec_score = 0;
if (($settings['two_factor_auth']          ?? '0') === '1') $sec_score++;
if (($settings['session_timeout_enabled']  ?? '0') === '1') $sec_score++;
if (($settings['login_alerts']             ?? '0') === '1') $sec_score++;
if ((int)($settings['min_password_length'] ?? '6') >= 8)     $sec_score++;
if (($settings['require_special_chars']    ?? '0') === '1') $sec_score++;

$sec_pct   = round(($sec_score / 5) * 100);
$sec_label = $sec_pct >= 80 ? 'Strong' : ($sec_pct >= 60 ? 'Good' : ($sec_pct >= 40 ? 'Fair' : 'Weak'));
$gauge_color = $sec_pct >= 80 ? '#10b981' : ($sec_pct >= 60 ? '#3b82f6' : ($sec_pct >= 40 ? '#f59e0b' : '#ef4444'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Security | DICT Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ── DESIGN TOKENS ─────────────────────────────────────────── */
:root {
  --navy:     #001f4d;
  --navy-mid: #002966;
  --blue:     #0038A8;
  --gold:     #FFD700;
  --bg:       #f0f4f8;
  --surface:  #ffffff;
  --border:   #e2e8f0;
  --text:     #1e293b;
  --muted:    #64748b;
  --success:  #10b981;
  --warning:  #f59e0b;
  --danger:   #ef4444;
  --violet:   #7c3aed;
  --indigo:   #4f46e5;

  --grad-navy:   linear-gradient(135deg,#001f4d 0%,#0038A8 100%);
  --grad-green:  linear-gradient(135deg,#059669 0%,#10b981 100%);
  --grad-red:    linear-gradient(135deg,#dc2626 0%,#ef4444 100%);
  --grad-amber:  linear-gradient(135deg,#d97706 0%,#f59e0b 100%);
  --grad-blue:   linear-gradient(135deg,#1d4ed8 0%,#3b82f6 100%);
  --grad-violet: linear-gradient(135deg,#6d28d9 0%,#7c3aed 100%);

  --shadow-sm: 0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);
  --shadow-md: 0 4px 16px rgba(0,0,0,.08);
  --shadow-lg: 0 10px 40px rgba(0,0,0,.12);
}

/* ── RESET ──────────────────────────────────────────────────── */
*,*::before,*::after { margin:0; padding:0; box-sizing:border-box; }
html,body { height:100%; -ms-overflow-style:none; scrollbar-width:none; }
html::-webkit-scrollbar,body::-webkit-scrollbar { display:none; }
body {
  font-family:'Inter','Segoe UI',sans-serif;
  background:var(--bg); color:var(--text);
  display:flex; padding-top:64px;
}

/* ── LAYOUT ─────────────────────────────────────────────────── */
.main-content {
  margin-left:260px; padding:32px;
  width:calc(100% - 260px);
  min-height:calc(100vh - 64px);
  overflow-y:auto; -ms-overflow-style:none; scrollbar-width:none;
}
.main-content::-webkit-scrollbar { display:none; }

/* ── PAGE HEADER ────────────────────────────────────────────── */
.page-header { display:flex; align-items:center; gap:16px; margin-bottom:28px; }
.page-title-icon {
  width:52px; height:52px; background:var(--grad-navy);
  border-radius:14px; display:flex; align-items:center; justify-content:center;
  color:white; font-size:22px; flex-shrink:0;
  box-shadow:0 4px 12px rgba(0,31,77,.3);
}
.page-header h1 { font-size:26px; font-weight:800; color:var(--navy); line-height:1.2; }
.page-header p  { font-size:14px; color:var(--muted); margin-top:2px; }
.burger-btn {
  margin-left:auto; display:none; background:var(--surface);
  border:1px solid var(--border); border-radius:10px;
  width:42px; height:42px; align-items:center; justify-content:center;
  cursor:pointer; font-size:16px; color:var(--navy);
  box-shadow:var(--shadow-sm); flex-shrink:0;
}

/* ── SECURITY SCORE BANNER ──────────────────────────────────── */
.sec-banner {
  background:var(--grad-navy); border-radius:20px;
  padding:28px 32px; margin-bottom:24px;
  display:flex; align-items:center; gap:28px;
  position:relative; overflow:hidden;
  box-shadow:var(--shadow-lg);
}
.sec-banner::before {
  content:''; position:absolute; top:-70px; right:-70px;
  width:240px; height:240px;
  background:rgba(255,255,255,.04); border-radius:50%;
}
.sec-banner::after {
  content:''; position:absolute; bottom:-50px; left:200px;
  width:180px; height:180px;
  background:rgba(255,215,0,.05); border-radius:50%;
}

.gauge-wrap { position:relative; width:116px; height:116px; flex-shrink:0; z-index:1; }
.gauge-wrap svg { width:116px; height:116px; transform:rotate(-90deg); }
.gauge-track { fill:none; stroke:rgba(255,255,255,.12); stroke-width:10; }
.gauge-fill  {
  fill:none; stroke-width:10; stroke-linecap:round;
  transition:stroke-dashoffset 1.3s cubic-bezier(.4,0,.2,1);
}
.gauge-center {
  position:absolute; top:50%; left:50%;
  transform:translate(-50%,-50%);
  text-align:center; color:white; z-index:2;
}
.gauge-center .gpct { font-size:22px; font-weight:800; line-height:1; }
.gauge-center .glbl { font-size:10px; font-weight:600; opacity:.65;
  text-transform:uppercase; letter-spacing:.5px; }

.banner-info { z-index:1; flex-shrink:0; }
.banner-info h2 { color:white; font-size:21px; font-weight:800; }
.banner-info p  { color:rgba(255,255,255,.6); font-size:13px; margin-top:3px; }
.score-badge {
  display:inline-flex; align-items:center; gap:5px;
  margin-top:10px; padding:5px 13px; border-radius:20px;
  font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px;
}
.sb-strong { background:rgba(16,185,129,.2); color:#6ee7b7; border:1px solid rgba(16,185,129,.3); }
.sb-good   { background:rgba(59,130,246,.2); color:#93c5fd; border:1px solid rgba(59,130,246,.3); }
.sb-fair   { background:rgba(245,158,11,.2); color:#fcd34d; border:1px solid rgba(245,158,11,.3); }
.sb-weak   { background:rgba(239,68,68,.2);  color:#fca5a5; border:1px solid rgba(239,68,68,.3); }

.feature-chips { display:flex; flex-wrap:wrap; gap:8px; z-index:1; margin-left:auto; }
.feature-chip {
  display:flex; align-items:center; gap:5px;
  padding:6px 12px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.15);
  border-radius:20px; font-size:11px; font-weight:600;
  color:rgba(255,255,255,.65); transition:.2s;
}
.feature-chip.on {
  background:rgba(16,185,129,.18);
  border-color:rgba(16,185,129,.4); color:#6ee7b7;
}
.feature-chip.on i { color:#10b981; }
.feature-chip i { font-size:11px; }

/* ── STAT CARDS ─────────────────────────────────────────────── */
.stats-grid {
  display:grid; grid-template-columns:repeat(4,1fr);
  gap:16px; margin-bottom:24px;
}
.stat-card {
  background:var(--surface); border-radius:16px; padding:22px;
  position:relative; overflow:hidden;
  box-shadow:var(--shadow-sm); border:1px solid var(--border);
  transition:transform .2s,box-shadow .2s;
}
.stat-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
.stat-card::before {
  content:''; position:absolute; top:0; left:0; right:0;
  height:3px; border-radius:16px 16px 0 0;
}
.stat-card .blob {
  position:absolute; top:-20px; right:-20px;
  width:90px; height:90px; border-radius:50%; opacity:.08;
}
.s-blue::before   { background:var(--grad-blue); }  .s-blue .blob   { background:var(--blue); }
.s-green::before  { background:var(--grad-green); } .s-green .blob  { background:var(--success); }
.s-red::before    { background:var(--grad-red); }   .s-red .blob    { background:var(--danger); }
.s-amber::before  { background:var(--grad-amber); } .s-amber .blob  { background:var(--warning); }
.s-violet::before { background:var(--grad-violet);} .s-violet .blob { background:var(--violet); }

.stat-icon {
  width:42px; height:42px; border-radius:11px;
  display:flex; align-items:center; justify-content:center;
  font-size:18px; margin-bottom:14px;
}
.s-blue  .stat-icon { background:#eff6ff; color:#1d4ed8; }
.s-green .stat-icon { background:#ecfdf5; color:#059669; }
.s-red   .stat-icon { background:#fef2f2; color:#dc2626; }
.s-amber .stat-icon { background:#fffbeb; color:#d97706; }
.s-violet .stat-icon{ background:#f5f3ff; color:#6d28d9; }

.stat-val   { font-size:30px; font-weight:800; color:var(--navy); line-height:1; }
.stat-label { font-size:13px; font-weight:500; color:var(--muted); margin-top:4px; }
.stat-sub   { font-size:11px; color:var(--muted); margin-top:5px; }
.stat-pill  {
  display:inline-block; padding:2px 8px; border-radius:20px;
  font-size:11px; font-weight:600; margin-top:6px;
}
.pill-green  { background:#ecfdf5; color:#059669; }
.pill-red    { background:#fef2f2; color:#dc2626; }
.pill-amber  { background:#fffbeb; color:#d97706; }
.pill-blue   { background:#eff6ff; color:#1d4ed8; }
.pill-gray   { background:#f1f5f9; color:var(--muted); }

/* ── SETTINGS GRID ──────────────────────────────────────────── */
.settings-grid {
  display:grid; grid-template-columns:repeat(3,1fr);
  gap:20px; margin-bottom:24px;
}

/* ── CARD ───────────────────────────────────────────────────── */
.card {
  background:var(--surface); border-radius:18px;
  border:1px solid var(--border); box-shadow:var(--shadow-sm);
  overflow:hidden; position:relative; transition:box-shadow .2s;
}
.card:hover { box-shadow:var(--shadow-md); }
.card-header {
  padding:20px 22px 16px; border-bottom:1px solid var(--border);
  display:flex; align-items:center; gap:12px;
}
.card-header-icon {
  width:40px; height:40px; border-radius:11px;
  display:flex; align-items:center; justify-content:center;
  font-size:17px; flex-shrink:0;
}
.ch-blue   .card-header-icon { background:#eff6ff; color:#1d4ed8; }
.ch-green  .card-header-icon { background:#ecfdf5; color:#059669; }
.ch-amber  .card-header-icon { background:#fffbeb; color:#d97706; }
.ch-violet .card-header-icon { background:#f5f3ff; color:#6d28d9; }
.ch-slate  .card-header-icon { background:#f1f5f9; color:#475569; }

.card-header-title { font-size:15px; font-weight:700; color:var(--navy); }
.card-header-sub   { font-size:12px; color:var(--muted); margin-top:1px; }
.sa-tag {
  margin-left:auto; display:flex; align-items:center; gap:4px;
  padding:3px 10px; background:#fef2f2; color:#dc2626;
  border-radius:20px; font-size:11px; font-weight:600; flex-shrink:0;
}
.card-body { padding:20px 22px; }

/* ── TOGGLE ROW ─────────────────────────────────────────────── */
.toggle-row {
  display:flex; align-items:center; gap:12px;
  padding:13px 0; border-bottom:1px solid var(--border);
}
.toggle-row:last-of-type { border-bottom:none; }
.ti {
  width:34px; height:34px; border-radius:9px;
  display:flex; align-items:center; justify-content:center;
  font-size:14px; flex-shrink:0;
}
.ti-blue   { background:#eff6ff; color:#1d4ed8; }
.ti-green  { background:#ecfdf5; color:#059669; }
.ti-amber  { background:#fffbeb; color:#d97706; }
.ti-violet { background:#f5f3ff; color:#6d28d9; }
.ti-red    { background:#fef2f2; color:#dc2626; }

.tgl-info { flex:1; min-width:0; }
.tgl-label  { font-size:13px; font-weight:600; color:var(--text); }
.tgl-desc   { font-size:11px; color:var(--muted); margin-top:1px; line-height:1.35; }
.tgl-status { font-size:10px; font-weight:700; text-transform:uppercase;
  letter-spacing:.3px; margin-top:3px; }
.ts-on  { color:var(--success); }
.ts-off { color:var(--muted); }

/* ── SWITCH ─────────────────────────────────────────────────── */
.switch { position:relative; display:inline-block; width:46px; height:24px; flex-shrink:0; }
.switch input { opacity:0; width:0; height:0; }
.slider {
  position:absolute; cursor:pointer;
  top:0; left:0; right:0; bottom:0;
  background:#cbd5e1; transition:.3s; border-radius:24px;
}
.slider::before {
  content:''; position:absolute;
  height:18px; width:18px; left:3px; bottom:3px;
  background:white; transition:.3s; border-radius:50%;
  box-shadow:0 1px 3px rgba(0,0,0,.2);
}
input:checked + .slider { background:var(--success); }
input:checked + .slider::before { transform:translateX(22px); }

/* ── FORM GROUPS ────────────────────────────────────────────── */
.form-group { margin-top:14px; }
.form-group label {
  font-size:11px; font-weight:700; color:var(--muted);
  text-transform:uppercase; letter-spacing:.5px;
  display:block; margin-bottom:6px;
}
.input-wrap { position:relative; display:flex; align-items:center; }
.input-wrap input, .input-wrap select {
  width:100%; padding:10px 14px;
  border:1.5px solid var(--border); border-radius:10px;
  font-size:14px; font-family:inherit; color:var(--text);
  background:var(--surface); transition:border-color .2s,box-shadow .2s;
  -moz-appearance:textfield;
}
.input-wrap input:focus, .input-wrap select:focus {
  outline:none; border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(0,56,168,.1);
}
.input-wrap input[type="number"]::-webkit-outer-spin-button,
.input-wrap input[type="number"]::-webkit-inner-spin-button { -webkit-appearance:none; }
.input-suffix {
  position:absolute; right:12px;
  font-size:12px; color:var(--muted); pointer-events:none;
}
.range-hint { font-size:11px; color:var(--muted); margin-top:4px; }

/* ── POLICY REQUIREMENTS ────────────────────────────────────── */
.policy-req-list { display:flex; flex-direction:column; gap:7px; margin-top:14px; }
.policy-req {
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; background:var(--bg);
  border-radius:8px; font-size:12px; font-weight:500;
  color:var(--muted); border:1px solid var(--border); transition:.2s;
}
.policy-req.on { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
.policy-req i   { font-size:11px; width:12px; text-align:center; }
.policy-req.on i { color:var(--success); }

/* ── SAVE BUTTON ────────────────────────────────────────────── */
.save-btn {
  width:100%; padding:12px 20px;
  background:var(--grad-navy); color:white;
  border:none; border-radius:11px;
  font-size:14px; font-weight:700; font-family:inherit;
  cursor:pointer; transition:.2s; margin-top:18px;
  display:flex; align-items:center; justify-content:center; gap:8px;
}
.save-btn:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); box-shadow:0 4px 12px rgba(0,56,168,.3); }
.save-btn:disabled { background:#cbd5e1; cursor:not-allowed; }

/* ── EVENTS TABLE ───────────────────────────────────────────── */
.events-card { margin-bottom:24px; }
.events-card .card-body { padding:0; }
table { width:100%; border-collapse:collapse; font-size:13px; }
thead tr { background:var(--grad-navy); }
thead th {
  padding:14px 18px; text-align:left;
  font-size:11px; font-weight:700; text-transform:uppercase;
  letter-spacing:.6px; color:rgba(255,255,255,.8); white-space:nowrap;
}
tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:#f8fafc; }
tbody td { padding:13px 18px; vertical-align:middle; }

.evt-badge {
  display:inline-flex; align-items:center; gap:5px;
  padding:4px 10px; border-radius:20px;
  font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.3px;
}
.evt-success { background:#ecfdf5; color:#059669; }
.evt-danger  { background:#fef2f2; color:#dc2626; }
.evt-warning { background:#fffbeb; color:#d97706; }
.evt-info    { background:#eff6ff; color:#1d4ed8; }

.user-chip { display:inline-flex; align-items:center; gap:7px; font-weight:600; color:var(--text); }
.u-initials {
  width:28px; height:28px; background:var(--grad-navy); border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  color:white; font-size:10px; font-weight:800; text-transform:uppercase; flex-shrink:0;
}
.ip-chip {
  font-family:'Courier New',monospace; font-size:12px;
  background:#f8fafc; padding:3px 8px; border-radius:6px;
  border:1px solid var(--border); color:var(--muted);
}
.ts-cell .date { font-size:12px; font-weight:600; color:var(--text); }
.ts-cell .time { font-size:11px; color:var(--muted); }

.empty-state { text-align:center; padding:44px; color:var(--muted); }
.empty-state i { font-size:32px; margin-bottom:10px; display:block; opacity:.25; }
.empty-state p { font-size:13px; }

/* ── LOCKED OVERLAY ─────────────────────────────────────────── */
.locked-overlay {
  position:absolute; top:0; left:0; right:0; bottom:0;
  background:rgba(255,255,255,.72);
  backdrop-filter:blur(3px); -webkit-backdrop-filter:blur(3px);
  z-index:10; border-radius:18px; cursor:not-allowed;
  display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:8px;
}
.locked-overlay i    { font-size:26px; color:var(--muted); opacity:.45; }
.locked-overlay span { font-size:12px; font-weight:600; color:var(--muted); }

/* ── FLOATING UNSAVED BAR ───────────────────────────────────── */
.unsaved-bar {
  position:fixed; bottom:-90px; left:50%;
  transform:translateX(-50%);
  background:var(--navy); color:white;
  padding:14px 24px; border-radius:16px;
  display:flex; align-items:center; gap:14px;
  box-shadow:var(--shadow-lg); z-index:999;
  transition:bottom .35s cubic-bezier(.34,1.56,.64,1);
  white-space:nowrap; min-width:380px;
}
.unsaved-bar.visible { bottom:28px; }
.bar-count {
  background:var(--gold); color:var(--navy);
  width:22px; height:22px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:11px; font-weight:800;
}
.bar-msg  { font-size:14px; font-weight:500; flex:1; }
.bar-disc {
  padding:7px 14px; background:rgba(255,255,255,.1);
  border:1px solid rgba(255,255,255,.2); color:white;
  border-radius:9px; font-size:13px; font-weight:600;
  cursor:pointer; font-family:inherit; transition:.15s;
}
.bar-disc:hover { background:rgba(255,255,255,.18); }
.bar-save {
  padding:7px 16px; background:var(--gold); color:var(--navy);
  border:none; border-radius:9px;
  font-size:13px; font-weight:700; cursor:pointer;
  font-family:inherit; transition:.15s;
}
.bar-save:hover { opacity:.9; }

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media(max-width:1100px) {
  .settings-grid { grid-template-columns:repeat(2,1fr); }
  .stats-grid    { grid-template-columns:repeat(2,1fr); }
}
@media(max-width:768px) {
  .main-content  { margin-left:0; width:100%; padding:20px 16px; }
  .burger-btn    { display:flex; }
  .settings-grid { grid-template-columns:1fr; }
  .stats-grid    { grid-template-columns:repeat(2,1fr); }
  .sec-banner    { flex-direction:column; align-items:flex-start; gap:20px; padding:22px; }
  .feature-chips { margin-left:0; }
}
</style>
</head>

<body>
<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<main class="main-content">

<!-- ── PAGE HEADER ─────────────────────────────────────── -->
<div class="page-header">
  <div class="page-title-icon"><i class="fas fa-shield-halved"></i></div>
  <div>
    <h1>Security Settings</h1>
    <p>Manage authentication, access controls, and system protection</p>
  </div>
  <button class="burger-btn" id="menuToggle"><i class="fas fa-bars"></i></button>
</div>

<!-- ── SECURITY SCORE BANNER ──────────────────────────── -->
<div class="sec-banner">
  <!-- Circular gauge -->
  <div class="gauge-wrap">
    <svg viewBox="0 0 116 116">
      <circle class="gauge-track" cx="58" cy="58" r="48"
              stroke-dasharray="301.59" stroke-dashoffset="0"/>
      <circle class="gauge-fill" cx="58" cy="58" r="48"
              id="gaugeFill"
              stroke="<?php echo $gauge_color; ?>"
              stroke-dasharray="301.59"
              stroke-dashoffset="301.59"/>
    </svg>
    <div class="gauge-center">
      <div class="gpct" id="scoreDisplay">0%</div>
      <div class="glbl">Score</div>
    </div>
  </div>

  <!-- Info text -->
  <div class="banner-info">
    <h2>Security Overview</h2>
    <p><?php echo $sec_score; ?> of 5 protection features active</p>
    <div class="score-badge sb-<?php echo strtolower($sec_label); ?>">
      <i class="fas fa-<?php echo $sec_label==='Strong'?'shield-check':($sec_label==='Good'?'shield':'shield-exclamation'); ?>"></i>
      <?php echo $sec_label; ?> Security
    </div>
  </div>

  <!-- Feature status chips -->
  <div class="feature-chips">
    <div class="feature-chip <?php echo ($settings['two_factor_auth']??'0')==='1'?'on':''; ?>">
      <i class="fas fa-<?php echo ($settings['two_factor_auth']??'0')==='1'?'check-circle':'times-circle'; ?>"></i> 2FA
    </div>
    <div class="feature-chip <?php echo ($settings['session_timeout_enabled']??'0')==='1'?'on':''; ?>">
      <i class="fas fa-<?php echo ($settings['session_timeout_enabled']??'0')==='1'?'check-circle':'times-circle'; ?>"></i> Session Guard
    </div>
    <div class="feature-chip <?php echo ($settings['login_alerts']??'0')==='1'?'on':''; ?>">
      <i class="fas fa-<?php echo ($settings['login_alerts']??'0')==='1'?'check-circle':'times-circle'; ?>"></i> Login Alerts
    </div>
    <div class="feature-chip <?php echo (int)($settings['min_password_length']??'6')>=8?'on':''; ?>">
      <i class="fas fa-<?php echo (int)($settings['min_password_length']??'6')>=8?'check-circle':'times-circle'; ?>"></i> Pwd Policy
    </div>
    <div class="feature-chip <?php echo ($settings['require_special_chars']??'0')==='1'?'on':''; ?>">
      <i class="fas fa-<?php echo ($settings['require_special_chars']??'0')==='1'?'check-circle':'times-circle'; ?>"></i> Special Chars
    </div>
  </div>
</div>

<!-- ── STAT CARDS ──────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card s-blue">
    <div class="blob"></div>
    <div class="stat-icon"><i class="fas fa-shield-halved"></i></div>
    <div class="stat-val" data-target="<?php echo $sec_pct; ?>" data-suffix="%">0%</div>
    <div class="stat-label">Security Score</div>
    <span class="stat-pill <?php echo $sec_pct>=80?'pill-green':($sec_pct>=60?'pill-blue':($sec_pct>=40?'pill-amber':'pill-red')); ?>">
      <?php echo $sec_label; ?>
    </span>
  </div>
  <div class="stat-card s-red">
    <div class="blob"></div>
    <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
    <div class="stat-val" data-target="<?php echo $failed_today; ?>">0</div>
    <div class="stat-label">Failed Logins Today</div>
    <div class="stat-sub"><?php echo $failed_week; ?> this week</div>
  </div>
  <div class="stat-card s-green">
    <div class="blob"></div>
    <div class="stat-icon"><i class="fas fa-lock"></i></div>
    <div class="stat-val"><?php echo ($settings['two_factor_auth']??'0')==='1'?'ON':'OFF'; ?></div>
    <div class="stat-label">Two-Factor Auth</div>
    <span class="stat-pill <?php echo ($settings['two_factor_auth']??'0')==='1'?'pill-green':'pill-gray'; ?>">
      <?php echo ($settings['two_factor_auth']??'0')==='1'?'Enabled':'Disabled'; ?>
    </span>
  </div>
  <div class="stat-card s-amber">
    <div class="blob"></div>
    <div class="stat-icon"><i class="fas fa-clock-rotate-left"></i></div>
    <div class="stat-val"><?php echo htmlspecialchars($settings['session_timeout']??'30'); ?>m</div>
    <div class="stat-label">Session Timeout</div>
    <span class="stat-pill <?php echo ($settings['session_timeout_enabled']??'0')==='1'?'pill-green':'pill-gray'; ?>">
      <?php echo ($settings['session_timeout_enabled']??'0')==='1'?'Active':'Inactive'; ?>
    </span>
  </div>
</div>

<!-- ── SETTINGS GRID ───────────────────────────────────── -->
<div class="settings-grid">

  <!-- Core Security -->
  <div class="card ch-blue">
    <?php if(!$is_superadmin): ?>
    <div class="locked-overlay"><i class="fas fa-lock"></i><span>Superadmin Only</span></div>
    <?php endif; ?>
    <div class="card-header">
      <div class="card-header-icon"><i class="fas fa-shield-halved"></i></div>
      <div>
        <div class="card-header-title">Core Security</div>
        <div class="card-header-sub">Authentication &amp; session controls</div>
      </div>
      <?php if(!$is_superadmin): ?><div class="sa-tag"><i class="fas fa-lock"></i> Superadmin</div><?php endif; ?>
    </div>
    <div class="card-body">
      <div class="toggle-row">
        <div class="ti ti-blue"><i class="fas fa-mobile-screen-button"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Two-Factor Authentication</div>
          <div class="tgl-desc">Require a one-time code on every login</div>
          <div class="tgl-status <?php echo ($settings['two_factor_auth']??'0')==='1'?'ts-on':'ts-off'; ?>" id="st-2fa">
            <?php echo ($settings['two_factor_auth']??'0')==='1'?'● Enabled':'○ Disabled'; ?>
          </div>
        </div>
        <label class="switch">
          <input type="checkbox" id="sec_2fa" <?php echo isChecked('two_factor_auth',$settings); ?>
            onchange="trackChanges();updateSt(this,'st-2fa')">
          <span class="slider"></span>
        </label>
      </div>

      <div class="toggle-row">
        <div class="ti ti-amber"><i class="fas fa-clock"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Auto Session Timeout</div>
          <div class="tgl-desc">Automatically log out on inactivity</div>
          <div class="tgl-status <?php echo ($settings['session_timeout_enabled']??'0')==='1'?'ts-on':'ts-off'; ?>" id="st-timeout">
            <?php echo ($settings['session_timeout_enabled']??'0')==='1'?'● Enabled':'○ Disabled'; ?>
          </div>
        </div>
        <label class="switch">
          <input type="checkbox" id="sec_timeout_toggle" <?php echo isChecked('session_timeout_enabled',$settings); ?>
            onchange="trackChanges();updateSt(this,'st-timeout')">
          <span class="slider"></span>
        </label>
      </div>

      <div class="toggle-row">
        <div class="ti ti-red"><i class="fas fa-bell"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Login Alerts</div>
          <div class="tgl-desc">Email notification on new logins</div>
          <div class="tgl-status <?php echo ($settings['login_alerts']??'0')==='1'?'ts-on':'ts-off'; ?>" id="st-alerts">
            <?php echo ($settings['login_alerts']??'0')==='1'?'● Enabled':'○ Disabled'; ?>
          </div>
        </div>
        <label class="switch">
          <input type="checkbox" id="sec_alerts" <?php echo isChecked('login_alerts',$settings); ?>
            onchange="trackChanges();updateSt(this,'st-alerts')">
          <span class="slider"></span>
        </label>
      </div>

      <div class="form-group">
        <label>Session Timeout Limit</label>
        <div class="input-wrap">
          <input type="number" id="sec_timeout_mins" min="5" max="480"
            value="<?php echo htmlspecialchars($settings['session_timeout']??'30'); ?>"
            oninput="trackChanges()">
          <span class="input-suffix">min</span>
        </div>
        <div class="range-hint">Range: 5 – 480 minutes</div>
      </div>

      <button class="save-btn" onclick="saveSecurity()" <?php echo !$is_superadmin?'disabled':''; ?>>
        <i class="fas fa-check"></i> Save Core Security
      </button>
    </div>
  </div>

  <!-- Password Policy -->
  <div class="card ch-green">
    <?php if(!$is_superadmin): ?>
    <div class="locked-overlay"><i class="fas fa-lock"></i><span>Superadmin Only</span></div>
    <?php endif; ?>
    <div class="card-header">
      <div class="card-header-icon"><i class="fas fa-key"></i></div>
      <div>
        <div class="card-header-title">Password Policy</div>
        <div class="card-header-sub">Enforce password strength requirements</div>
      </div>
      <?php if(!$is_superadmin): ?><div class="sa-tag"><i class="fas fa-lock"></i> Superadmin</div><?php endif; ?>
    </div>
    <div class="card-body">
      <div class="form-group">
        <label>Minimum Password Length</label>
        <div class="input-wrap">
          <input type="number" id="pwd_min_len" min="6" max="32"
            value="<?php echo htmlspecialchars($settings['min_password_length']??'8'); ?>"
            oninput="trackChanges();updatePolicyReqs()">
          <span class="input-suffix">chars</span>
        </div>
        <div class="range-hint">Min 6 · Recommended 8+</div>
      </div>
      <div class="form-group">
        <label>Password Expiry</label>
        <div class="input-wrap">
          <input type="number" id="pwd_expiry" min="0" max="365"
            value="<?php echo htmlspecialchars($settings['password_expiry_days']??'90'); ?>"
            oninput="trackChanges()">
          <span class="input-suffix">days</span>
        </div>
        <div class="range-hint">0 = passwords never expire</div>
      </div>

      <div class="toggle-row">
        <div class="ti ti-green"><i class="fas fa-font"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Require Uppercase</div>
          <div class="tgl-desc">At least one capital letter (A–Z)</div>
        </div>
        <label class="switch">
          <input type="checkbox" id="pwd_uppercase" <?php echo isChecked('require_uppercase',$settings); ?>
            onchange="trackChanges();updatePolicyReqs()">
          <span class="slider"></span>
        </label>
      </div>
      <div class="toggle-row">
        <div class="ti ti-blue"><i class="fas fa-hashtag"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Require Numbers</div>
          <div class="tgl-desc">At least one numeric digit (0–9)</div>
        </div>
        <label class="switch">
          <input type="checkbox" id="pwd_numbers" <?php echo isChecked('require_numbers',$settings); ?>
            onchange="trackChanges();updatePolicyReqs()">
          <span class="slider"></span>
        </label>
      </div>
      <div class="toggle-row">
        <div class="ti ti-violet"><i class="fas fa-at"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Require Special Characters</div>
          <div class="tgl-desc">e.g. @, #, $, !, %, ^</div>
        </div>
        <label class="switch">
          <input type="checkbox" id="pwd_special" <?php echo isChecked('require_special_chars',$settings); ?>
            onchange="trackChanges();updatePolicyReqs()">
          <span class="slider"></span>
        </label>
      </div>

      <!-- Live policy preview -->
      <div class="policy-req-list" id="policyList">
        <div class="policy-req <?php echo (int)($settings['min_password_length']??'6')>=8?'on':''; ?>" id="req-len">
          <i class="fas fa-<?php echo (int)($settings['min_password_length']??'6')>=8?'check':'xmark'; ?>"></i>
          Min <?php echo htmlspecialchars($settings['min_password_length']??'8'); ?> characters
        </div>
        <div class="policy-req <?php echo ($settings['require_uppercase']??'0')==='1'?'on':''; ?>" id="req-up">
          <i class="fas fa-<?php echo ($settings['require_uppercase']??'0')==='1'?'check':'xmark'; ?>"></i>
          Uppercase letter required
        </div>
        <div class="policy-req <?php echo ($settings['require_numbers']??'0')==='1'?'on':''; ?>" id="req-num">
          <i class="fas fa-<?php echo ($settings['require_numbers']??'0')==='1'?'check':'xmark'; ?>"></i>
          Number required
        </div>
        <div class="policy-req <?php echo ($settings['require_special_chars']??'0')==='1'?'on':''; ?>" id="req-spc">
          <i class="fas fa-<?php echo ($settings['require_special_chars']??'0')==='1'?'check':'xmark'; ?>"></i>
          Special character required
        </div>
      </div>

      <button class="save-btn" onclick="savePasswordPolicy()" <?php echo !$is_superadmin?'disabled':''; ?>>
        <i class="fas fa-key"></i> Save Password Policy
      </button>
    </div>
  </div>

  <!-- Login Protection -->
  <div class="card ch-amber">
    <?php if(!$is_superadmin): ?>
    <div class="locked-overlay"><i class="fas fa-lock"></i><span>Superadmin Only</span></div>
    <?php endif; ?>
    <div class="card-header">
      <div class="card-header-icon"><i class="fas fa-user-shield"></i></div>
      <div>
        <div class="card-header-title">Login Protection</div>
        <div class="card-header-sub">Brute-force and lockout controls</div>
      </div>
      <?php if(!$is_superadmin): ?><div class="sa-tag"><i class="fas fa-lock"></i> Superadmin</div><?php endif; ?>
    </div>
    <div class="card-body">
      <div class="toggle-row">
        <div class="ti ti-red"><i class="fas fa-ban"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">Account Lockout</div>
          <div class="tgl-desc">Lock account after failed attempts</div>
          <div class="tgl-status <?php echo ($settings['account_lockout']??'0')==='1'?'ts-on':'ts-off'; ?>" id="st-lockout">
            <?php echo ($settings['account_lockout']??'0')==='1'?'● Enabled':'○ Disabled'; ?>
          </div>
        </div>
        <label class="switch">
          <input type="checkbox" id="lp_lockout" <?php echo isChecked('account_lockout',$settings); ?>
            onchange="trackChanges();updateSt(this,'st-lockout')">
          <span class="slider"></span>
        </label>
      </div>

      <div class="toggle-row">
        <div class="ti ti-amber"><i class="fas fa-robot"></i></div>
        <div class="tgl-info">
          <div class="tgl-label">CAPTCHA on Login</div>
          <div class="tgl-desc">Show CAPTCHA after failed attempts</div>
          <div class="tgl-status <?php echo ($settings['login_captcha']??'0')==='1'?'ts-on':'ts-off'; ?>" id="st-captcha">
            <?php echo ($settings['login_captcha']??'0')==='1'?'● Enabled':'○ Disabled'; ?>
          </div>
        </div>
        <label class="switch">
          <input type="checkbox" id="lp_captcha" <?php echo isChecked('login_captcha',$settings); ?>
            onchange="trackChanges();updateSt(this,'st-captcha')">
          <span class="slider"></span>
        </label>
      </div>

      <div class="form-group">
        <label>Max Login Attempts</label>
        <div class="input-wrap">
          <input type="number" id="lp_max_attempts" min="3" max="20"
            value="<?php echo htmlspecialchars($settings['max_login_attempts']??'5'); ?>"
            oninput="trackChanges()">
          <span class="input-suffix">tries</span>
        </div>
        <div class="range-hint">Before account lockout (3 – 20)</div>
      </div>
      <div class="form-group">
        <label>Lockout Duration</label>
        <div class="input-wrap">
          <input type="number" id="lp_lockout_mins" min="1" max="1440"
            value="<?php echo htmlspecialchars($settings['lockout_duration']??'15'); ?>"
            oninput="trackChanges()">
          <span class="input-suffix">min</span>
        </div>
        <div class="range-hint">How long an account stays locked</div>
      </div>
      <div class="form-group">
        <label>Remember Me Duration</label>
        <div class="input-wrap">
          <input type="number" id="lp_remember_days" min="1" max="90"
            value="<?php echo htmlspecialchars($settings['remember_me_days']??'30'); ?>"
            oninput="trackChanges()">
          <span class="input-suffix">days</span>
        </div>
        <div class="range-hint">How long "remember me" persists</div>
      </div>

      <button class="save-btn" onclick="saveLoginProtection()" <?php echo !$is_superadmin?'disabled':''; ?>>
        <i class="fas fa-user-shield"></i> Save Login Protection
      </button>
    </div>
  </div>

</div><!-- /settings-grid -->

<!-- ── RECENT SECURITY EVENTS ─────────────────────────── -->
<div class="card events-card">
  <div class="card-header ch-slate">
    <div class="card-header-icon" style="background:#f1f5f9;color:#475569;">
      <i class="fas fa-list-check"></i>
    </div>
    <div>
      <div class="card-header-title">Recent Security Events</div>
      <div class="card-header-sub">Login attempts and access-related activity (last 5)</div>
    </div>
    <a href="activity_logs.php"
       style="margin-left:auto;font-size:13px;font-weight:600;color:var(--blue);
              text-decoration:none;display:flex;align-items:center;gap:5px;">
      View All <i class="fas fa-arrow-right"></i>
    </a>
  </div>
  <div class="card-body">
    <?php if (!empty($recent_events)): ?>
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Event</th>
          <th>IP Address</th>
          <th>Timestamp</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($recent_events as $ev):
          $uname = $ev['username'] ?? '–';
          $parts = explode(' ', $uname);
          $initials = strtoupper(substr($parts[0],0,1)) . (isset($parts[1]) ? strtoupper(substr($parts[1],0,1)) : '');

          $al = strtolower($ev['action'] ?? '');
          if      (str_contains($al,'fail') || str_contains($al,'deny'))
            [$ec,$ei] = ['evt-danger','fa-times-circle'];
          elseif  (str_contains($al,'login') || str_contains($al,'sign'))
            [$ec,$ei] = ['evt-success','fa-right-to-bracket'];
          elseif  (str_contains($al,'password') || str_contains($al,'change'))
            [$ec,$ei] = ['evt-warning','fa-key'];
          else
            [$ec,$ei] = ['evt-info','fa-info-circle'];

          $ts   = $ev['created_at'] ?? '';
          $dstr = $ts ? date('M j, Y', strtotime($ts)) : '–';
          $tstr = $ts ? date('h:i A',  strtotime($ts)) : '';
        ?>
        <tr>
          <td>
            <div class="user-chip">
              <div class="u-initials"><?php echo htmlspecialchars($initials); ?></div>
              <?php echo htmlspecialchars($uname); ?>
            </div>
          </td>
          <td>
            <span class="evt-badge <?php echo $ec; ?>">
              <i class="fas <?php echo $ei; ?>"></i>
              <?php echo htmlspecialchars($ev['action'] ?? '–'); ?>
            </span>
          </td>
          <td><span class="ip-chip"><?php echo htmlspecialchars($ev['ip_address'] ?? '–'); ?></span></td>
          <td class="ts-cell">
            <div class="date"><?php echo $dstr; ?></div>
            <div class="time"><?php echo $tstr; ?></div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <i class="fas fa-shield-check"></i>
      <p>No recent security events found</p>
    </div>
    <?php endif; ?>
  </div>
</div>

</main>

<!-- ── FLOATING UNSAVED BAR ───────────────────────────── -->
<div class="unsaved-bar" id="unsavedBar">
  <div class="bar-count" id="changeCount">0</div>
  <span class="bar-msg">Unsaved changes</span>
  <button class="bar-disc" onclick="discardChanges()">Discard</button>
  <button class="bar-save" onclick="saveAll()">Save All</button>
</div>

<script>
/* ── Helper ─────────────────────────────────────────────── */
function sendData(fd) {
  Swal.fire({ title:'Saving…', allowOutsideClick:false, didOpen:()=>Swal.showLoading() });
  fetch('../../functions/process_settings.php', { method:'POST', body:fd })
    .then(r => r.text())
    .then(d => {
      if (d.trim() === 'success') {
        Swal.fire({ icon:'success', title:'Saved!', text:'Settings updated.',
          timer:2000, showConfirmButton:false }).then(() => location.reload());
      } else {
        Swal.fire('Error', d, 'error');
      }
    })
    .catch(() => Swal.fire('Error','Network connection failed.','error'));
}

/* ── Toggle status labels ──────────────────────────────── */
function updateSt(chk, id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.className = 'tgl-status ' + (chk.checked ? 'ts-on' : 'ts-off');
  el.textContent = chk.checked ? '● Enabled' : '○ Disabled';
}

/* ── Unsaved-changes tracker ───────────────────────────── */
const allIds = [
  'sec_2fa','sec_timeout_toggle','sec_alerts','sec_timeout_mins',
  'pwd_min_len','pwd_expiry','pwd_uppercase','pwd_numbers','pwd_special',
  'lp_lockout','lp_captcha','lp_max_attempts','lp_lockout_mins','lp_remember_days'
];
const orig = {};
allIds.forEach(id => {
  const el = document.getElementById(id);
  if (el) orig[id] = el.type === 'checkbox' ? el.checked : el.value;
});

let pending = 0;
const bar = document.getElementById('unsavedBar');

function trackChanges() {
  pending = allIds.filter(id => {
    const el = document.getElementById(id);
    if (!el) return false;
    return (el.type === 'checkbox' ? el.checked : el.value) !== orig[id];
  }).length;
  document.getElementById('changeCount').textContent = pending;
  bar.classList.toggle('visible', pending > 0);
}

function discardChanges() {
  allIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.type === 'checkbox') el.checked = orig[id];
    else el.value = orig[id];
  });
  [['sec_2fa','st-2fa'],['sec_timeout_toggle','st-timeout'],['sec_alerts','st-alerts'],
   ['lp_lockout','st-lockout'],['lp_captcha','st-captcha']].forEach(([cId,lId]) => {
     const el = document.getElementById(cId);
     if (el) updateSt(el, lId);
  });
  updatePolicyReqs();
  bar.classList.remove('visible');
  pending = 0;
}

/* ── Password policy live preview ──────────────────────── */
function updatePolicyReqs() {
  const len  = parseInt(document.getElementById('pwd_min_len')?.value || '8');
  const up   = document.getElementById('pwd_uppercase')?.checked;
  const num  = document.getElementById('pwd_numbers')?.checked;
  const spc  = document.getElementById('pwd_special')?.checked;
  const setR = (id, on, txt) => {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'policy-req' + (on ? ' on' : '');
    el.innerHTML = `<i class="fas fa-${on?'check':'xmark'}"></i> ${txt}`;
  };
  setR('req-len', len >= 8, `Min ${len} characters`);
  setR('req-up',  up,       'Uppercase letter required');
  setR('req-num', num,      'Number required');
  setR('req-spc', spc,      'Special character required');
}

/* ── Save functions ─────────────────────────────────────── */
function saveSecurity() {
  const t = parseInt(document.getElementById('sec_timeout_mins').value);
  if (t < 5 || t > 480) {
    Swal.fire('Validation','Session timeout must be 5–480 minutes.','warning'); return;
  }
  const fd = new FormData();
  fd.append('type',                    'security');
  fd.append('two_factor_auth',          document.getElementById('sec_2fa').checked           ? '1':'0');
  fd.append('session_timeout_enabled',  document.getElementById('sec_timeout_toggle').checked ? '1':'0');
  fd.append('login_alerts',             document.getElementById('sec_alerts').checked         ? '1':'0');
  fd.append('session_timeout',          t);
  sendData(fd);
}

function savePasswordPolicy() {
  const l = parseInt(document.getElementById('pwd_min_len').value);
  if (l < 6 || l > 32) {
    Swal.fire('Validation','Minimum password length must be 6–32 characters.','warning'); return;
  }
  const fd = new FormData();
  fd.append('type',                  'password_policy');
  fd.append('min_password_length',   l);
  fd.append('password_expiry_days',  document.getElementById('pwd_expiry').value);
  fd.append('require_uppercase',     document.getElementById('pwd_uppercase').checked ? '1':'0');
  fd.append('require_numbers',       document.getElementById('pwd_numbers').checked   ? '1':'0');
  fd.append('require_special_chars', document.getElementById('pwd_special').checked   ? '1':'0');
  sendData(fd);
}

function saveLoginProtection() {
  const a = parseInt(document.getElementById('lp_max_attempts').value);
  if (a < 3 || a > 20) {
    Swal.fire('Validation','Max login attempts must be 3–20.','warning'); return;
  }
  const fd = new FormData();
  fd.append('type',              'login_protection');
  fd.append('account_lockout',   document.getElementById('lp_lockout').checked  ? '1':'0');
  fd.append('login_captcha',     document.getElementById('lp_captcha').checked  ? '1':'0');
  fd.append('max_login_attempts',a);
  fd.append('lockout_duration',  document.getElementById('lp_lockout_mins').value);
  fd.append('remember_me_days',  document.getElementById('lp_remember_days').value);
  sendData(fd);
}

function saveAll() {
  const t = parseInt(document.getElementById('sec_timeout_mins').value);
  const l = parseInt(document.getElementById('pwd_min_len').value);
  const a = parseInt(document.getElementById('lp_max_attempts').value);
  if (t < 5 || t > 480) {
    Swal.fire('Validation','Session timeout must be 5–480 minutes.','warning'); return;
  }
  if (l < 6 || l > 32) {
    Swal.fire('Validation','Min password length must be 6–32 characters.','warning'); return;
  }
  if (a < 3 || a > 20) {
    Swal.fire('Validation','Max login attempts must be 3–20.','warning'); return;
  }
  const fd = new FormData();
  fd.append('type',                    'security');
  fd.append('two_factor_auth',          document.getElementById('sec_2fa').checked           ? '1':'0');
  fd.append('session_timeout_enabled',  document.getElementById('sec_timeout_toggle').checked ? '1':'0');
  fd.append('login_alerts',             document.getElementById('sec_alerts').checked         ? '1':'0');
  fd.append('session_timeout',          t);
  fd.append('min_password_length',      l);
  fd.append('password_expiry_days',     document.getElementById('pwd_expiry').value);
  fd.append('require_uppercase',        document.getElementById('pwd_uppercase').checked ? '1':'0');
  fd.append('require_numbers',          document.getElementById('pwd_numbers').checked   ? '1':'0');
  fd.append('require_special_chars',    document.getElementById('pwd_special').checked   ? '1':'0');
  fd.append('account_lockout',          document.getElementById('lp_lockout').checked    ? '1':'0');
  fd.append('login_captcha',            document.getElementById('lp_captcha').checked    ? '1':'0');
  fd.append('max_login_attempts',       a);
  fd.append('lockout_duration',         document.getElementById('lp_lockout_mins').value);
  fd.append('remember_me_days',         document.getElementById('lp_remember_days').value);
  sendData(fd);
}

/* ── Animated counter ───────────────────────────────────── */
document.querySelectorAll('.stat-val[data-target]').forEach(el => {
  const target = parseInt(el.dataset.target);
  const suffix = el.dataset.suffix || '';
  if (target === 0) { el.textContent = '0' + suffix; return; }
  let cur = 0;
  const step = target / (1200 / 16);
  const tick = () => {
    cur = Math.min(cur + step, target);
    el.textContent = Math.round(cur) + suffix;
    if (cur < target) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
});

/* ── SVG gauge animation ────────────────────────────────── */
(function () {
  const fill    = document.getElementById('gaugeFill');
  const display = document.getElementById('scoreDisplay');
  if (!fill || !display) return;
  const target = <?php echo $sec_pct; ?>;
  const circum  = 301.59;
  let cur = 0;
  const step = target / (1400 / 16);
  const tick = () => {
    cur = Math.min(cur + step, target);
    fill.style.strokeDashoffset = circum * (1 - cur / 100);
    display.textContent = Math.round(cur) + '%';
    if (cur < target) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
})();

/* ── Sidebar toggle ─────────────────────────────────────── */
document.getElementById('menuToggle').onclick = () =>
  document.querySelector('.sidebar').classList.toggle('active');

document.querySelectorAll('.sidebar-menu a').forEach(a =>
  a.addEventListener('click', () => {
    if (window.innerWidth <= 768)
      document.querySelector('.sidebar').classList.remove('active');
  })
);
</script>
</body>
</html>
