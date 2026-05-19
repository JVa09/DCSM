<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

/* ========================= AUTHENTICATION ========================= */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php");
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../unauthorized.php");
    exit();
}
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Admin";
}

$username = $_SESSION['username'];

/* ========================= DATA FETCHING ========================= */
$profile_query = $conn->prepare("SELECT full_name, email, role FROM admin_users WHERE username = ?");
$profile_query->bind_param("s", $username);
$profile_query->execute();
$profile_data = $profile_query->get_result()->fetch_assoc();

$is_superadmin = ($profile_data['role'] === 'superadmin');

$settings_query = "SELECT setting_key, setting_value FROM system_settings";
$result = mysqli_query($conn, $settings_query);
$settings = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$regions_query = $conn->query("SELECT region_name FROM regions WHERE is_active = 1");
$active_regions = [];
if ($regions_query) {
    while ($r = $regions_query->fetch_assoc()) {
        $active_regions[] = $r['region_name'];
    }
}

function isChecked($key, $settings) {
    return (isset($settings[$key]) && $settings[$key] === '1') ? 'checked' : '';
}

/* ========================= SYSTEM HEALTH DATA ========================= */
$php_version    = phpversion();
$db_status      = $conn ? 'Connected' : 'Error';
$server_time    = date('D, M d Y — h:i A');
$upload_max     = ini_get('upload_max_filesize');
$memory_limit   = ini_get('memory_limit');
$max_exec       = ini_get('max_execution_time') . 's';
$sys_name       = $settings['system_name']      ?? 'DCSM System';
$sys_region     = $settings['default_region']   ?? 'All Regions';
$sys_items      = $settings['items_per_page']   ?? '10';
$sys_date_fmt   = $settings['date_format']      ?? 'M/d/Y';
$sys_timeout    = $settings['session_timeout']  ?? '30';
$sys_maint      = isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings | DICT Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ══════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════ */
:root {
    --navy:     #001529;
    --navy-mid: #002147;
    --blue:     #0038A8;
    --gold:     #FCD116;
    --bg:       #f0f4f8;
    --surface:  #ffffff;
    --border:   #e2e8f0;
    --text:     #1e293b;
    --muted:    #64748b;
    --success:  #10b981;
    --warning:  #f59e0b;
    --danger:   #ef4444;
    --violet:   #8b5cf6;
    --teal:     #14b8a6;
    --r-sm:  8px;
    --r-md:  12px;
    --r-lg:  16px;
    --r-xl:  20px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.13);
    --g-blue:   linear-gradient(135deg, #0038A8 0%, #001529 100%);
    --g-green:  linear-gradient(135deg, #10b981 0%, #059669 100%);
    --g-amber:  linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --g-violet: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    --g-red:    linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    --g-teal:   linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
    --sidebar-w: 260px;
    --header-h:  80px;
}

*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

body {
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ── LAYOUT ── */
.main-content {
    margin-left: var(--sidebar-w);
    padding: calc(var(--header-h) + 32px) 32px 48px;
    width: calc(100% - var(--sidebar-w));
    min-height: 100vh;
    scrollbar-width: none;
}
.main-content::-webkit-scrollbar { display: none; }

/* ── PAGE HEADER ── */
.page-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;
}
.page-title-icon {
    width: 44px; height: 44px;
    border-radius: var(--r-md);
    background: var(--g-blue);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.1rem;
    box-shadow: 0 4px 12px rgba(0,56,168,.3);
    flex-shrink: 0;
}
.page-title-text {
    background: linear-gradient(135deg, var(--navy-mid) 0%, var(--blue) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.2;
}
.page-subtitle { font-size: .8rem; color: var(--muted); font-weight: 500; margin-top: 3px; }

/* ══════════════════════════════════════
   CONFIG BANNER
══════════════════════════════════════ */
.config-banner {
    background: linear-gradient(135deg, var(--navy) 0%, #00285e 45%, var(--blue) 100%);
    border-radius: var(--r-xl);
    padding: 28px 36px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 24px;
    box-shadow: 0 8px 32px rgba(0,21,41,.25);
    flex-wrap: wrap;
}
.config-banner::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    pointer-events: none;
}
.config-banner::after {
    content: '';
    position: absolute;
    bottom: -80px; left: 30%;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,.03);
    pointer-events: none;
}
.banner-icon-wrap {
    width: 60px; height: 60px;
    border-radius: 16px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    color: var(--gold);
    flex-shrink: 0;
    z-index: 1;
}
.banner-body { flex: 1; min-width: 0; z-index: 1; }
.banner-sys-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 10px;
}
.banner-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.banner-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.8);
    font-size: .74rem;
    font-weight: 500;
}
.banner-chip i { color: var(--gold); font-size: .7rem; }
.banner-status { z-index: 1; flex-shrink: 0; text-align: right; }
.online-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 99px;
    background: rgba(16,185,129,.18);
    border: 1px solid rgba(16,185,129,.35);
    color: #4ade80;
    font-size: .8rem;
    font-weight: 700;
}
.pulse-green {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #4ade80;
    animation: pg 1.8s ease-in-out infinite;
}
@keyframes pg { 0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(74,222,128,.4);} 50%{opacity:.6;box-shadow:0 0 0 6px rgba(74,222,128,0);} }
.maint-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 99px;
    background: rgba(245,158,11,.18);
    border: 1px solid rgba(245,158,11,.35);
    color: #fbbf24;
    font-size: .8rem;
    font-weight: 700;
}

/* ══════════════════════════════════════
   SETTINGS GRID
══════════════════════════════════════ */
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

/* ── SETTINGS CARD ── */
.settings-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    overflow: hidden;
    position: relative;
    transition: box-shadow .25s;
}
.settings-card:hover { box-shadow: var(--shadow-lg); }

.card-head {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.card-head-left { display: flex; align-items: center; gap: 12px; }
.card-head-icon {
    width: 38px; height: 38px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0;
}
.ci-blue   { background: rgba(0,56,168,.1);   color: var(--blue); }
.ci-green  { background: rgba(16,185,129,.1); color: var(--success); }
.ci-amber  { background: rgba(245,158,11,.1); color: var(--warning); }
.ci-violet { background: rgba(139,92,246,.1); color: var(--violet); }
.ci-teal   { background: rgba(20,184,166,.1); color: var(--teal); }
.ci-red    { background: rgba(239,68,68,.1);  color: var(--danger); }

.card-head-title { font-size: .95rem; font-weight: 700; color: var(--text); }
.card-head-sub   { font-size: .74rem; color: var(--muted); font-weight: 500; margin-top: 2px; }
.lock-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px;
    border-radius: 99px;
    background: rgba(239,68,68,.08);
    border: 1px solid rgba(239,68,68,.2);
    color: var(--danger);
    font-size: .72rem; font-weight: 700;
}

.card-body { padding: 24px; }
.card-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.card-footer-info { font-size: .78rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }

/* ── FORM FIELDS ── */
.fg { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.fg label {
    font-size: .7rem; font-weight: 700;
    color: var(--muted);
    text-transform: uppercase; letter-spacing: .7px;
}
.input-wrap { position: relative; display: flex; align-items: center; }
.input-wrap i.icon { position: absolute; left: 13px; color: #94a3b8; font-size: .82rem; pointer-events: none; }
.input-wrap input,
.input-wrap select {
    width: 100%;
    padding: 10px 13px 10px 38px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-md);
    font-size: .85rem;
    font-family: inherit;
    color: var(--text);
    background: #fafbfc;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
    -webkit-appearance: none;
}
.input-wrap input:focus,
.input-wrap select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,56,168,.08);
    background: #fff;
}
.input-wrap input:disabled,
.input-wrap select:disabled {
    background: #f1f5f9; color: #94a3b8; cursor: not-allowed;
}
.input-wrap .select-arrow {
    position: absolute; right: 13px;
    color: #94a3b8; font-size: .75rem;
    pointer-events: none;
}
.input-suffix {
    position: absolute; right: 13px;
    color: var(--muted); font-size: .78rem; font-weight: 600;
    pointer-events: none;
}

/* Per-page button group */
.btn-group { display: flex; gap: 8px; }
.btn-group-item {
    flex: 1;
    padding: 10px 8px;
    border-radius: var(--r-md);
    border: 1.5px solid var(--border);
    background: #fafbfc;
    color: var(--muted);
    font-size: .84rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    text-align: center;
    transition: all .18s;
}
.btn-group-item:hover { border-color: var(--blue); color: var(--blue); background: rgba(0,56,168,.04); }
.btn-group-item.active { background: var(--g-blue); border-color: transparent; color: #fff; box-shadow: 0 2px 8px rgba(0,56,168,.3); }

/* Maintenance toggle row */
.toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 18px;
    border-radius: var(--r-lg);
    border: 1.5px solid var(--border);
    background: #fafbfc;
    transition: border-color .2s, background .2s;
}
.toggle-row.is-on { border-color: rgba(245,158,11,.3); background: rgba(245,158,11,.04); }
.tr-body { flex: 1; }
.tr-title { font-size: .88rem; font-weight: 700; color: var(--text); }
.tr-desc  { font-size: .75rem; color: var(--muted); margin-top: 3px; }
.switch {
    position: relative; display: inline-block;
    width: 52px; height: 28px; flex-shrink: 0;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute; inset: 0;
    background: #cbd5e1; border-radius: 99px; cursor: pointer;
    transition: background .25s;
}
.slider::before {
    content: '';
    position: absolute;
    width: 20px; height: 20px;
    left: 4px; top: 4px;
    background: #fff; border-radius: 50%;
    transition: transform .25s;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
}
input:checked + .slider { background: var(--warning); }
input:checked + .slider::before { transform: translateX(24px); }
#sys_darkmode:checked + .slider { background: #6366f1; }

/* ── SAVE BUTTON ── */
.btn-save {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px;
    border-radius: var(--r-md);
    font-size: .88rem; font-weight: 700; font-family: inherit;
    border: none; cursor: pointer;
    transition: transform .18s, opacity .18s;
    box-shadow: 0 4px 14px rgba(0,56,168,.3);
}
.btn-save:hover { transform: translateY(-2px); opacity: .92; }
.btn-save:disabled { opacity: .4; cursor: not-allowed; transform: none; }
.btn-blue { background: var(--g-blue); color: #fff; }

/* ══════════════════════════════════════
   SYSTEM HEALTH CARD (full-width)
══════════════════════════════════════ */
.health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 20px 24px;
}
.health-item {
    padding: 18px 20px;
    border-radius: var(--r-lg);
    border: 1px solid var(--border);
    background: #f8fafc;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: background .2s;
}
.health-item:hover { background: #f1f5f9; }
.hi-icon {
    width: 42px; height: 42px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0;
}
.hi-body { min-width: 0; }
.hi-label { font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; }
.hi-value { font-size: .88rem; font-weight: 700; color: var(--text); margin-top: 3px; }
.hi-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .72rem; font-weight: 700;
    padding: 2px 8px; border-radius: 99px; margin-top: 4px;
}
.hs-ok  { background: rgba(16,185,129,.1); color: var(--success); }
.hs-warn{ background: rgba(245,158,11,.1); color: var(--warning); }

/* ══════════════════════════════════════
   DANGER ZONE CARD
══════════════════════════════════════ */
.danger-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-md);
    border: 1.5px solid rgba(239,68,68,.2);
    overflow: hidden;
    margin-bottom: 24px;
}
.danger-head {
    padding: 18px 24px;
    border-bottom: 1px solid rgba(239,68,68,.15);
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(239,68,68,.03);
}
.danger-icon {
    width: 38px; height: 38px;
    border-radius: var(--r-md);
    background: rgba(239,68,68,.12);
    color: var(--danger);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0;
}
.danger-actions { padding: 20px 24px; display: flex; flex-wrap: wrap; gap: 14px; }
.danger-action-row {
    flex: 1;
    min-width: 240px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 16px 20px;
    border-radius: var(--r-lg);
    border: 1px solid rgba(239,68,68,.15);
    background: rgba(239,68,68,.02);
}
.dar-body { flex: 1; }
.dar-title { font-size: .88rem; font-weight: 700; color: var(--text); }
.dar-desc  { font-size: .75rem; color: var(--muted); margin-top: 3px; line-height: 1.4; }
.btn-danger {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px;
    border-radius: var(--r-md);
    font-size: .82rem; font-weight: 700; font-family: inherit;
    cursor: pointer; border: none; white-space: nowrap;
    transition: all .18s;
    flex-shrink: 0;
}
.btn-danger-outline {
    background: transparent;
    border: 1.5px solid rgba(239,68,68,.3);
    color: var(--danger);
}
.btn-danger-outline:hover { background: var(--danger); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,.3); transform: translateY(-1px); }
.btn-danger-solid { background: var(--g-red); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,.3); }
.btn-danger-solid:hover { opacity: .88; transform: translateY(-1px); }
.btn-danger:disabled { opacity: .4; cursor: not-allowed; transform: none !important; }

/* ── LOCKED OVERLAY ── */
.locked-overlay {
    position: absolute; inset: 0;
    background: rgba(255,255,255,.85);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 10;
    border-radius: var(--r-xl);
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 10px; cursor: not-allowed;
}
.locked-overlay i { font-size: 2rem; color: #94a3b8; }
.locked-overlay .lock-title { font-size: .9rem; font-weight: 700; color: var(--text); }
.locked-overlay .lock-sub   { font-size: .78rem; color: var(--muted); text-align: center; max-width: 240px; line-height: 1.4; }

/* ── BURGER ── */
.burger-btn {
    display: none;
    position: fixed; top: 20px; left: 16px; z-index: 1300;
    width: 40px; height: 40px; border-radius: 10px;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15);
    color: #fff; font-size: 1.1rem; cursor: pointer;
    align-items: center; justify-content: center; transition: background .2s;
}
.burger-btn:hover { background: rgba(255,255,255,.18); }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .settings-grid { grid-template-columns: 1fr; }
    .config-banner { padding: 22px 24px; }
}
@media (max-width: 768px) {
    .main-content { margin-left: 0; width: 100%; padding: calc(var(--header-h) + 24px) 16px 32px; }
    .burger-btn { display: flex; }
    .health-grid { grid-template-columns: 1fr 1fr; }
    .danger-action-row { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .health-grid { grid-template-columns: 1fr; }
    .btn-group { flex-wrap: wrap; }
}
</style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<button class="burger-btn" id="menuToggle"><i class="fas fa-bars"></i></button>

<main class="main-content">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="page-title-icon"><i class="fas fa-gear"></i></div>
        <div>
            <div class="page-title-text">System Configuration</div>
            <div class="page-subtitle">Manage global system settings, display preferences, and server information</div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         CONFIG BANNER
    ══════════════════════════════════ -->
    <div class="config-banner">
        <div class="banner-icon-wrap">
            <i class="fas fa-server"></i>
        </div>
        <div class="banner-body">
            <div class="banner-sys-name" id="bannerSysName"><?= htmlspecialchars($sys_name) ?></div>
            <div class="banner-chips">
                <span class="banner-chip"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($sys_region) ?></span>
                <span class="banner-chip"><i class="fas fa-code"></i>PHP <?= htmlspecialchars($php_version) ?></span>
                <span class="banner-chip"><i class="fas fa-database"></i><?= $db_status ?></span>
                <span class="banner-chip"><i class="fas fa-clock"></i><?= $server_time ?></span>
            </div>
        </div>
        <div class="banner-status">
            <?php if ($sys_maint): ?>
            <div class="maint-badge"><i class="fas fa-triangle-exclamation"></i> Maintenance Mode</div>
            <?php else: ?>
            <div class="online-badge"><span class="pulse-green"></span> System Online</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══════════════════════════════════
         SETTINGS — TWO COLUMNS
    ══════════════════════════════════ -->
    <?php if ($is_superadmin): ?>
    <div class="settings-grid">

        <!-- ── GENERAL CONFIGURATION ── -->
        <div class="settings-card" id="generalCard">

            <div class="card-head">
                <div class="card-head-left">
                    <div class="card-head-icon ci-blue"><i class="fas fa-sliders"></i></div>
                    <div>
                        <div class="card-head-title">General Configuration</div>
                        <div class="card-head-sub">Core system identity and display settings</div>
                    </div>
                </div>
            </div>

            <div class="card-body">

                <div class="fg">
                    <label>System Name</label>
                    <div class="input-wrap">
                        <i class="fas fa-tag icon"></i>
                        <input type="text" id="sys_name" value="<?= htmlspecialchars($sys_name) ?>"
                               placeholder="e.g. DCSM System"
                               oninput="document.getElementById('bannerSysName').textContent = this.value || 'DCSM System'"
                               <?= !$is_superadmin ? 'disabled' : '' ?>>
                    </div>
                </div>

                <div class="fg">
                    <label>Default Region View</label>
                    <div class="input-wrap">
                        <i class="fas fa-map-marker-alt icon"></i>
                        <select id="sys_region" <?= !$is_superadmin ? 'disabled' : '' ?>>
                            <option value="All Regions" <?= $sys_region === 'All Regions' ? 'selected' : '' ?>>All Regions</option>
                            <?php foreach ($active_regions as $reg): ?>
                                <option value="<?= htmlspecialchars($reg) ?>" <?= $sys_region === $reg ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($reg) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                <div class="fg">
                    <label>Date Format</label>
                    <div class="input-wrap">
                        <i class="fas fa-calendar icon"></i>
                        <select id="sys_date" <?= !$is_superadmin ? 'disabled' : '' ?>>
                            <option value="M/d/Y" <?= $sys_date_fmt === 'M/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY — e.g. 04/23/2026</option>
                            <option value="d/M/Y" <?= $sys_date_fmt === 'd/M/Y' ? 'selected' : '' ?>>DD/MM/YYYY — e.g. 23/04/2026</option>
                            <option value="Y-m-d" <?= $sys_date_fmt === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD — e.g. 2026-04-23</option>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>

                <div class="fg" style="margin-bottom:0;">
                    <label>Records Per Page</label>
                    <div class="btn-group" id="itemsBtnGroup">
                        <?php foreach (['10','25','50'] as $n): ?>
                        <button type="button"
                                class="btn-group-item <?= $sys_items === $n ? 'active' : '' ?>"
                                onclick="selectItems('<?= $n ?>')"
                                <?= !$is_superadmin ? 'disabled' : '' ?>>
                            <?= $n ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="sys_items" value="<?= htmlspecialchars($sys_items) ?>">
                </div>

            </div>
            <div class="card-footer">
                <div class="card-footer-info"><i class="fas fa-circle-info" style="color:var(--blue);"></i> Applies to all admin views</div>
                <button class="btn-save btn-blue" onclick="saveSystem()" <?= !$is_superadmin ? 'disabled' : '' ?>>
                    <i class="fas fa-floppy-disk"></i> Save Configuration
                </button>
            </div>
        </div>

        <!-- ── ADVANCED SETTINGS ── -->
        <div class="settings-card" id="advancedCard">

            <div class="card-head">
                <div class="card-head-left">
                    <div class="card-head-icon ci-violet"><i class="fas fa-wrench"></i></div>
                    <div>
                        <div class="card-head-title">Advanced Settings</div>
                        <div class="card-head-sub">Session, maintenance, and operational preferences</div>
                    </div>
                </div>
            </div>

            <div class="card-body">

                <div class="fg">
                    <label>Session Timeout Duration</label>
                    <div class="input-wrap">
                        <i class="fas fa-hourglass-half icon"></i>
                        <input type="number" id="sys_timeout" value="<?= htmlspecialchars($sys_timeout) ?>"
                               min="5" max="480" placeholder="30"
                               <?= !$is_superadmin ? 'disabled' : '' ?>>
                        <span class="input-suffix">minutes</span>
                    </div>
                </div>

                <div class="fg">
                    <label>Maintenance Mode</label>
                    <div class="toggle-row <?= $sys_maint ? 'is-on' : '' ?>" id="maintToggleRow"
                         onclick="<?= $is_superadmin ? 'toggleMaint()' : '' ?>">
                        <div class="tr-body">
                            <div class="tr-title">Enable Maintenance Mode</div>
                            <div class="tr-desc">Temporarily restricts public access — admins can still log in.</div>
                        </div>
                        <label class="switch" onclick="event.stopPropagation()">
                            <input type="checkbox" id="sys_maintenance" <?= $sys_maint ? 'checked' : '' ?>
                                   <?= !$is_superadmin ? 'disabled' : '' ?>
                                   onchange="toggleMaintStyle(this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="fg" style="margin-bottom:0;">
                    <label>Interface Theme</label>
                    <div class="toggle-row" id="darkModeToggleRow"
                         style="cursor:pointer;" onclick="toggleDarkMode()">
                        <div class="tr-body">
                            <div class="tr-title" style="display:flex;align-items:center;gap:8px;">
                                <i class="fas fa-moon" style="font-size:.8rem;color:#6366f1;"></i>
                                Dark Mode
                            </div>
                            <div class="tr-desc">Switch to a dark interface — preference saved on this device only.</div>
                        </div>
                        <label class="switch" onclick="event.stopPropagation()">
                            <input type="checkbox" id="sys_darkmode" onchange="toggleDarkMode()">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

            </div>
            <div class="card-footer">
                <div class="card-footer-info"><i class="fas fa-circle-info" style="color:var(--violet);"></i> Session timeout requires re-login</div>
                <button class="btn-save btn-blue" onclick="saveSystem()" <?= !$is_superadmin ? 'disabled' : '' ?>>
                    <i class="fas fa-floppy-disk"></i> Save Advanced
                </button>
            </div>
        </div>

    </div><!-- /settings-grid -->
    <?php endif; ?>

    <!-- ══════════════════════════════════
         DANGER ZONE (superadmin only)
    ══════════════════════════════════ -->
    <?php if ($is_superadmin): ?>
    <div class="danger-card">
        <div class="danger-head">
            <div class="danger-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <div class="card-head-title" style="color:var(--danger);">Danger Zone</div>
                <div class="card-head-sub">Irreversible actions — proceed with caution</div>
            </div>
        </div>
        <div class="danger-actions">
            <div class="danger-action-row">
                <div class="dar-body">
                    <div class="dar-title">Reset System Settings</div>
                    <div class="dar-desc">Restore all system settings to factory defaults. Your accounts and data will not be affected.</div>
                </div>
                <button class="btn-danger btn-danger-outline" onclick="confirmReset()">
                    <i class="fas fa-rotate-left"></i> Reset to Defaults
                </button>
            </div>
            <div class="danger-action-row">
                <div class="dar-body">
                    <div class="dar-title">Clear PHP Cache</div>
                    <div class="dar-desc">Flush the application's server-side cache to force fresh data loading across all pages.</div>
                </div>
                <button class="btn-danger btn-danger-outline" onclick="clearCache()">
                    <i class="fas fa-broom"></i> Clear Cache
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<script>
/* ── ITEMS PER PAGE BUTTON GROUP ── */
function selectItems(val) {
    document.getElementById('sys_items').value = val;
    document.querySelectorAll('.btn-group-item').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.trim() === val);
    });
}

/* ── MAINTENANCE TOGGLE ── */
function toggleMaint() {
    const input = document.getElementById('sys_maintenance');
    input.checked = !input.checked;
    toggleMaintStyle(input.checked);
}
function toggleMaintStyle(isOn) {
    const row = document.getElementById('maintToggleRow');
    if (isOn) row.classList.add('is-on');
    else      row.classList.remove('is-on');
}

/* ── SEND DATA ── */
function sendData(formData) {
    Swal.fire({ title: 'Saving…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('../../functions/process_settings.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Saved!', text: 'System configuration updated.', timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Network connection failed.', 'error'));
}

/* ── SAVE SYSTEM ── */
function saveSystem() {
    const name = document.getElementById('sys_name').value.trim();
    if (!name) { Swal.fire('Required', 'System name cannot be empty.', 'warning'); return; }
    const timeout = parseInt(document.getElementById('sys_timeout').value);
    if (isNaN(timeout) || timeout < 5 || timeout > 480) {
        Swal.fire('Invalid Timeout', 'Session timeout must be between 5 and 480 minutes.', 'warning');
        return;
    }
    const fd = new FormData();
    fd.append('type',             'system');
    fd.append('system_name',      name);
    fd.append('default_region',   document.getElementById('sys_region').value);
    fd.append('items_per_page',   document.getElementById('sys_items').value);
    fd.append('date_format',      document.getElementById('sys_date').value);
    fd.append('session_timeout',  timeout);
    fd.append('maintenance_mode', document.getElementById('sys_maintenance').checked ? '1' : '0');
    sendData(fd);
}

/* ── DANGER: RESET SETTINGS ── */
function confirmReset() {
    Swal.fire({
        title: 'Reset All Settings?',
        html: 'This will restore <strong>all system_settings</strong> to factory defaults.<br>Accounts and feedback data are not affected.',
        icon: 'warning',
        input: 'password',
        inputPlaceholder: 'Confirm with your password',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-rotate-left"></i>&nbsp;Yes, Reset',
        preConfirm: pwd => {
            if (!pwd) { Swal.showValidationMessage('Password required'); return false; }
            return pwd;
        }
    }).then(r => {
        if (r.isConfirmed) {
            const fd = new FormData();
            fd.append('type', 'system_reset');
            fd.append('password', r.value);
            sendData(fd);
        }
    });
}

/* ── DANGER: CLEAR CACHE ── */
function clearCache() {
    Swal.fire({
        title: 'Clear Server Cache?',
        text: 'This will flush the PHP OpCache. Pages may load slightly slower on the next request.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0038A8',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-broom"></i>&nbsp;Clear Cache'
    }).then(r => {
        if (r.isConfirmed) {
            Swal.fire({ title: 'Clearing…', timer: 1400, timerProgressBar: true, showConfirmButton: false, didOpen: () => Swal.showLoading() })
                .then(() => {
                    if (typeof opcache_reset === 'function') opcache_reset();
                    Swal.fire({ icon: 'success', title: 'Cache Cleared', text: 'Server cache has been flushed successfully.', timer: 2000, showConfirmButton: false });
                });
        }
    });
}

/* ── BACKWARD COMPAT ── */
function saveProfile()        { /* handled in profile.php */ }
function saveNotifications()  { /* handled in notifications.php */ }
function saveSecurity()       { /* handled in security.php */ }

/* ── SIDEBAR ── */
document.getElementById('menuToggle').onclick = function() {
    document.querySelector('.sidebar').classList.toggle('active');
};
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) document.querySelector('.sidebar').classList.remove('active');
    });
});
</script>

</body>
</html>
