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
function isOn($key, $settings) {
    return (isset($settings[$key]) && $settings[$key] === '1');
}

/* Count enabled notifications */
$notif_keys     = ['email_notifications','low_rating_alerts','weekly_reports','system_updates'];
$enabled_count  = 0;
foreach ($notif_keys as $k) {
    if (isOn($k, $settings)) $enabled_count++;
}

$delivery_email = $profile_data['email'] ?? 'Not set';
$display_name   = $profile_data['full_name'] ?? $username;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications | DICT Admin</title>
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
    --r-sm:  8px;
    --r-md:  12px;
    --r-lg:  16px;
    --r-xl:  20px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.12);
    --g-blue:   linear-gradient(135deg, #0038A8 0%, #001529 100%);
    --g-green:  linear-gradient(135deg, #10b981 0%, #059669 100%);
    --g-amber:  linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --g-violet: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    --g-red:    linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
    justify-content: space-between;
    margin-bottom: 28px;
    gap: 16px;
    flex-wrap: wrap;
}
.page-title-group { display: flex; align-items: center; gap: 12px; }
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

/* ── SAVE BAR (unsaved changes) ── */
.save-bar {
    display: none;
    position: fixed;
    bottom: 28px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 999;
    background: var(--navy);
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 99px;
    padding: 12px 20px;
    box-shadow: var(--shadow-lg);
    align-items: center;
    gap: 14px;
    min-width: 360px;
    backdrop-filter: blur(8px);
    animation: slideUp .25s ease;
}
@keyframes slideUp { from { transform: translateX(-50%) translateY(20px); opacity:0; } to { transform: translateX(-50%) translateY(0); opacity:1; } }
.save-bar.visible { display: flex; }
.save-bar-text { font-size: .84rem; color: rgba(255,255,255,.8); font-weight: 500; flex: 1; }
.save-bar-text strong { color: #fff; }
.btn-save-bar {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 20px;
    border-radius: 99px;
    font-size: .84rem; font-weight: 700; font-family: inherit;
    border: none; cursor: pointer;
    transition: opacity .18s, transform .18s;
}
.btn-save-bar:hover { transform: scale(1.03); opacity: .92; }
.btn-confirm { background: var(--g-green); color: #fff; }
.btn-discard {
    background: rgba(255,255,255,.1);
    color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.15);
}

/* ── STAT CARDS ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
    transition: transform .25s, box-shadow .25s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: var(--r-xl) var(--r-xl) 0 0;
}
.s-green::before  { background: var(--g-green); }
.s-blue::before   { background: var(--g-blue); }
.s-amber::before  { background: var(--g-amber); }
.s-violet::before { background: var(--g-violet); }

.stat-icon-box {
    width: 46px; height: 46px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
}
.s-green  .stat-icon-box { background: rgba(16,185,129,.1);  color: var(--success); }
.s-blue   .stat-icon-box { background: rgba(0,56,168,.1);    color: var(--blue); }
.s-amber  .stat-icon-box { background: rgba(245,158,11,.1);  color: var(--warning); }
.s-violet .stat-icon-box { background: rgba(139,92,246,.1);  color: var(--violet); }

.stat-body { flex: 1; min-width: 0; }
.stat-value { font-size: 1.6rem; font-weight: 800; color: var(--text); line-height: 1.1; letter-spacing: -.4px; }
.stat-label { font-size: .7rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .7px; margin-top: 4px; }

/* ── MAIN SETTINGS CARD ── */
.settings-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    overflow: hidden;
    position: relative;
    margin-bottom: 24px;
}

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
    font-size: .95rem;
    flex-shrink: 0;
}
.ci-blue   { background: rgba(0,56,168,.1);   color: var(--blue); }
.ci-green  { background: rgba(16,185,129,.1); color: var(--success); }
.ci-amber  { background: rgba(245,158,11,.1); color: var(--warning); }
.ci-violet { background: rgba(139,92,246,.1); color: var(--violet); }
.ci-red    { background: rgba(239,68,68,.1);  color: var(--danger); }

.card-head-title { font-size: .95rem; font-weight: 700; color: var(--text); }
.card-head-sub   { font-size: .74rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* Lock badge */
.lock-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px;
    border-radius: 99px;
    background: rgba(239,68,68,.08);
    border: 1px solid rgba(239,68,68,.2);
    color: var(--danger);
    font-size: .72rem;
    font-weight: 700;
}

/* ── SECTION SEPARATOR ── */
.notif-section { padding: 0 24px; }
.notif-section-label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 18px 0 10px;
    font-size: .7rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .9px;
}
.notif-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--border);
}

/* ── TOGGLE CARD ── */
.toggle-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 18px;
    border-radius: var(--r-lg);
    border: 1.5px solid var(--border);
    background: #fafbfc;
    margin-bottom: 12px;
    cursor: pointer;
    transition: border-color .2s, background .2s, box-shadow .2s;
    user-select: none;
}
.toggle-card:last-child { margin-bottom: 0; }
.toggle-card.is-on {
    border-color: transparent;
    box-shadow: 0 0 0 1.5px currentColor;
}
.toggle-card.tc-green.is-on  { border-color: rgba(16,185,129,.3);  background: rgba(16,185,129,.04);  box-shadow: none; }
.toggle-card.tc-red.is-on    { border-color: rgba(239,68,68,.3);   background: rgba(239,68,68,.04);   box-shadow: none; }
.toggle-card.tc-blue.is-on   { border-color: rgba(0,56,168,.25);   background: rgba(0,56,168,.04);    box-shadow: none; }
.toggle-card.tc-violet.is-on { border-color: rgba(139,92,246,.3);  background: rgba(139,92,246,.04);  box-shadow: none; }

.tc-icon {
    width: 44px; height: 44px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: transform .2s;
}
.toggle-card.is-on .tc-icon { transform: scale(1.06); }
.tc-icon.green  { background: rgba(16,185,129,.1);  color: var(--success); }
.tc-icon.red    { background: rgba(239,68,68,.1);   color: var(--danger); }
.tc-icon.blue   { background: rgba(0,56,168,.1);    color: var(--blue); }
.tc-icon.violet { background: rgba(139,92,246,.1);  color: var(--violet); }
.tc-icon.amber  { background: rgba(245,158,11,.1);  color: var(--warning); }

.tc-body { flex: 1; min-width: 0; }
.tc-title { font-size: .88rem; font-weight: 700; color: var(--text); }
.tc-desc  { font-size: .76rem; color: var(--muted); margin-top: 3px; line-height: 1.4; }
.tc-tag   {
    display: inline-flex; align-items: center; gap: 4px;
    margin-top: 6px;
    font-size: .68rem; font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    color: var(--muted);
}
.toggle-card.is-on .tc-tag { background: rgba(16,185,129,.1); color: var(--success); }
.toggle-card.tc-red.is-on    .tc-tag { background: rgba(239,68,68,.1);  color: var(--danger); }
.toggle-card.tc-blue.is-on   .tc-tag { background: rgba(0,56,168,.1);   color: var(--blue); }
.toggle-card.tc-violet.is-on .tc-tag { background: rgba(139,92,246,.1); color: var(--violet); }

/* ── MODERN SWITCH ── */
.switch {
    position: relative;
    display: inline-block;
    width: 52px; height: 28px;
    flex-shrink: 0;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
    position: absolute;
    inset: 0;
    background: #cbd5e1;
    border-radius: 99px;
    cursor: pointer;
    transition: background .25s;
}
.slider::before {
    content: '';
    position: absolute;
    width: 20px; height: 20px;
    left: 4px; top: 4px;
    background: #fff;
    border-radius: 50%;
    transition: transform .25s;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
}
input:checked + .slider { background: var(--success); }
input:checked + .slider::before { transform: translateX(24px); }
.tc-red    input:checked + .slider { background: var(--danger); }
.tc-blue   input:checked + .slider { background: var(--blue); }
.tc-violet input:checked + .slider { background: var(--violet); }

/* ── LOCKED OVERLAY ── */
.locked-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 10;
    border-radius: var(--r-xl);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    cursor: not-allowed;
}
.locked-overlay i { font-size: 2rem; color: #94a3b8; }
.locked-overlay .lock-title { font-size: .9rem; font-weight: 700; color: var(--text); }
.locked-overlay .lock-sub   { font-size: .78rem; color: var(--muted); text-align: center; max-width: 260px; line-height: 1.4; }

/* ── CARD BODY / FOOTER ── */
.card-body   { padding: 20px 24px 24px; }
.card-footer {
    padding: 18px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.card-footer-info { font-size: .78rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }
.btn-save {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 24px;
    border-radius: var(--r-md);
    font-size: .88rem; font-weight: 700; font-family: inherit;
    border: none; cursor: pointer;
    transition: transform .18s, opacity .18s;
}
.btn-save:hover { transform: translateY(-2px); opacity: .92; }
.btn-save:disabled { opacity: .45; cursor: not-allowed; transform: none; }
.btn-blue-grad { background: var(--g-blue); color: #fff; box-shadow: 0 4px 14px rgba(0,56,168,.3); }

/* ── DELIVERY INFO CARD ── */
.delivery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    padding: 20px 24px;
}
.delivery-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 18px;
    border-radius: var(--r-lg);
    background: #f8fafc;
    border: 1px solid var(--border);
}
.di-icon {
    width: 40px; height: 40px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}
.di-body { min-width: 0; }
.di-label { font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .7px; }
.di-value { font-size: .84rem; font-weight: 600; color: var(--text); margin-top: 4px; word-break: break-all; }
.di-hint  { font-size: .72rem; color: var(--muted); margin-top: 3px; }

/* Status pill */
.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: .72rem; font-weight: 700;
}
.sp-green  { background: rgba(16,185,129,.1);  color: var(--success); }
.sp-red    { background: rgba(239,68,68,.1);   color: var(--danger); }
.sp-amber  { background: rgba(245,158,11,.1);  color: var(--warning); }
.pulse-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: pd 1.8s ease-in-out infinite; }
@keyframes pd { 0%,100%{opacity:1;} 50%{opacity:.3;} }

/* ── BURGER ── */
.burger-btn {
    display: none;
    position: fixed;
    top: 20px; left: 16px;
    z-index: 1300;
    width: 40px; height: 40px;
    border-radius: 10px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    color: #fff; font-size: 1.1rem;
    cursor: pointer;
    align-items: center; justify-content: center;
    transition: background .2s;
}
.burger-btn:hover { background: rgba(255,255,255,.18); }

/* ── RESPONSIVE ── */
@media (max-width: 768px) {
    .main-content { margin-left: 0; width: 100%; padding: calc(var(--header-h) + 24px) 16px 32px; }
    .burger-btn { display: flex; }
    .page-header { flex-direction: column; align-items: flex-start; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .save-bar { min-width: calc(100% - 32px); }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
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
        <div class="page-title-group">
            <div class="page-title-icon"><i class="fas fa-bell"></i></div>
            <div>
                <div class="page-title-text">Notification Settings</div>
                <div class="page-subtitle">Configure system alerts, email delivery, and report preferences</div>
            </div>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="stats-grid">
        <div class="stat-card s-green">
            <div class="stat-icon-box"><i class="fas fa-bell"></i></div>
            <div class="stat-body">
                <div class="stat-value" id="statEnabled"><?= $enabled_count ?></div>
                <div class="stat-label">Active Notifications</div>
            </div>
        </div>
        <div class="stat-card s-blue">
            <div class="stat-icon-box"><i class="fas fa-envelope"></i></div>
            <div class="stat-body">
                <div class="stat-value" style="font-size:1rem; padding-top:4px; letter-spacing:0;"><?= htmlspecialchars(explode('@', $delivery_email)[0] ?? '—') ?></div>
                <div class="stat-label">Email Recipient</div>
            </div>
        </div>
        <div class="stat-card s-amber">
            <div class="stat-icon-box"><i class="fas fa-shield-halved"></i></div>
            <div class="stat-body">
                <div class="stat-value" style="font-size:1rem; padding-top:4px; letter-spacing:0;"><?= $is_superadmin ? 'Full Access' : 'Read-Only' ?></div>
                <div class="stat-label">Permission Level</div>
            </div>
        </div>
        <div class="stat-card s-violet">
            <div class="stat-icon-box"><i class="fas fa-sliders"></i></div>
            <div class="stat-body">
                <div class="stat-value">4</div>
                <div class="stat-label">Total Toggles</div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         NOTIFICATION TOGGLES CARD
    ══════════════════════════════════ -->
    <div class="settings-card" id="notifCard">

        <?php if (!$is_superadmin): ?>
        <div class="locked-overlay">
            <i class="fas fa-lock"></i>
            <div class="lock-title">Superadmin Access Required</div>
            <div class="lock-sub">Only Superadministrators can modify notification settings for the system.</div>
        </div>
        <?php endif; ?>

        <div class="card-head">
            <div class="card-head-left">
                <div class="card-head-icon ci-blue"><i class="fas fa-sliders"></i></div>
                <div>
                    <div class="card-head-title">Notification Preferences</div>
                    <div class="card-head-sub">Control which alerts and reports are active system-wide</div>
                </div>
            </div>
            <?php if (!$is_superadmin): ?>
            <span class="lock-badge"><i class="fas fa-lock"></i> Superadmin Only</span>
            <?php endif; ?>
        </div>

        <div class="notif-section">

            <!-- SECTION: Alerts -->
            <div class="notif-section-label"><i class="fas fa-triangle-exclamation"></i> Alert Notifications</div>

            <div class="toggle-card tc-green <?= isOn('email_notifications',$settings) ? 'is-on' : '' ?>"
                 id="tc_email" onclick="toggleCard('tc_email','notif_email')">
                <div class="tc-icon green"><i class="fas fa-envelope-circle-check"></i></div>
                <div class="tc-body">
                    <div class="tc-title">Email Notifications</div>
                    <div class="tc-desc">Receive email alerts whenever a new feedback submission arrives in the system.</div>
                    <span class="tc-tag"><span class="pulse-dot"></span><?= isOn('email_notifications',$settings) ? 'Active — sending to ' . htmlspecialchars($delivery_email) : 'Currently disabled' ?></span>
                </div>
                <label class="switch tc-green" onclick="event.stopPropagation()">
                    <input type="checkbox" id="notif_email" <?= isChecked('email_notifications',$settings) ?> onchange="onToggleChange('tc_email', this.checked, 'tc-green')">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="toggle-card tc-red <?= isOn('low_rating_alerts',$settings) ? 'is-on' : '' ?>"
                 id="tc_low" onclick="toggleCard('tc_low','notif_low')">
                <div class="tc-icon red"><i class="fas fa-star-half-stroke"></i></div>
                <div class="tc-body">
                    <div class="tc-title">Low Rating Alerts</div>
                    <div class="tc-desc">Instant alert when a citizen rates any service below 3 stars — catch poor experiences fast.</div>
                    <span class="tc-tag"><span class="pulse-dot"></span><?= isOn('low_rating_alerts',$settings) ? 'Active — monitoring ratings' : 'Currently disabled' ?></span>
                </div>
                <label class="switch tc-red" onclick="event.stopPropagation()">
                    <input type="checkbox" id="notif_low" <?= isChecked('low_rating_alerts',$settings) ?> onchange="onToggleChange('tc_low', this.checked, 'tc-red')">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- SECTION: Reports -->
            <div class="notif-section-label"><i class="fas fa-chart-line"></i> Reports &amp; Insights</div>

            <div class="toggle-card tc-blue <?= isOn('weekly_reports',$settings) ? 'is-on' : '' ?>"
                 id="tc_weekly" onclick="toggleCard('tc_weekly','notif_weekly')">
                <div class="tc-icon blue"><i class="fas fa-file-chart-column"></i></div>
                <div class="tc-body">
                    <div class="tc-title">Weekly Performance Reports</div>
                    <div class="tc-desc">Automated summary of feedback trends, satisfaction scores, and service performance delivered every week.</div>
                    <span class="tc-tag"><span class="pulse-dot"></span><?= isOn('weekly_reports',$settings) ? 'Active — sent every Monday' : 'Currently disabled' ?></span>
                </div>
                <label class="switch tc-blue" onclick="event.stopPropagation()">
                    <input type="checkbox" id="notif_weekly" <?= isChecked('weekly_reports',$settings) ?> onchange="onToggleChange('tc_weekly', this.checked, 'tc-blue')">
                    <span class="slider"></span>
                </label>
            </div>

            <!-- SECTION: System -->
            <div class="notif-section-label"><i class="fas fa-gear"></i> System</div>

            <div class="toggle-card tc-violet <?= isOn('system_updates',$settings) ? 'is-on' : '' ?>"
                 id="tc_sys" onclick="toggleCard('tc_sys','notif_sys')" style="margin-bottom:0;">
                <div class="tc-icon violet"><i class="fas fa-bell-ring"></i></div>
                <div class="tc-body">
                    <div class="tc-title">System Update Alerts</div>
                    <div class="tc-desc">Get notified about platform maintenance windows, version updates, and critical system events.</div>
                    <span class="tc-tag"><span class="pulse-dot"></span><?= isOn('system_updates',$settings) ? 'Active — monitoring system' : 'Currently disabled' ?></span>
                </div>
                <label class="switch tc-violet" onclick="event.stopPropagation()">
                    <input type="checkbox" id="notif_sys" <?= isChecked('system_updates',$settings) ?> onchange="onToggleChange('tc_sys', this.checked, 'tc-violet')">
                    <span class="slider"></span>
                </label>
            </div>

            <div style="padding-bottom:4px;"></div>
        </div><!-- /notif-section -->

        <div class="card-footer">
            <div class="card-footer-info">
                <i class="fas fa-circle-info" style="color:var(--blue);"></i>
                Changes apply system-wide for all admin accounts
            </div>
            <button class="btn-save btn-blue-grad" onclick="saveNotifications()" <?= !$is_superadmin ? 'disabled' : '' ?>>
                <i class="fas fa-floppy-disk"></i> Save Preferences
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════
         DELIVERY INFO CARD
    ══════════════════════════════════ -->
    <div class="settings-card">
        <div class="card-head">
            <div class="card-head-left">
                <div class="card-head-icon ci-green"><i class="fas fa-paper-plane"></i></div>
                <div>
                    <div class="card-head-title">Delivery &amp; Access Information</div>
                    <div class="card-head-sub">Where notifications are sent and who can configure them</div>
                </div>
            </div>
        </div>
        <div class="delivery-grid">

            <div class="delivery-item">
                <div class="di-icon ci-blue" style="border-radius:var(--r-sm);"><i class="fas fa-envelope"></i></div>
                <div class="di-body">
                    <div class="di-label">Email Delivery Address</div>
                    <div class="di-value"><?= htmlspecialchars($delivery_email) ?></div>
                    <div class="di-hint">Notifications go to your registered admin email</div>
                </div>
            </div>

            <div class="delivery-item">
                <div class="di-icon ci-violet" style="border-radius:var(--r-sm);"><i class="fas fa-user-shield"></i></div>
                <div class="di-body">
                    <div class="di-label">Account</div>
                    <div class="di-value"><?= htmlspecialchars($display_name) ?></div>
                    <div class="di-hint">
                        <span class="status-pill <?= $is_superadmin ? 'sp-green' : 'sp-amber' ?>">
                            <span class="pulse-dot"></span>
                            <?= $is_superadmin ? 'Can edit settings' : 'Read-only access' ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="delivery-item">
                <div class="di-icon ci-amber" style="border-radius:var(--r-sm);"><i class="fas fa-clock"></i></div>
                <div class="di-body">
                    <div class="di-label">Report Schedule</div>
                    <div class="di-value">Every Monday</div>
                    <div class="di-hint">Weekly reports sent at the start of each week</div>
                </div>
            </div>

            <div class="delivery-item">
                <div class="di-icon ci-red" style="border-radius:var(--r-sm);"><i class="fas fa-star-half-stroke"></i></div>
                <div class="di-body">
                    <div class="di-label">Low Rating Threshold</div>
                    <div class="di-value">Below 3 Stars</div>
                    <div class="di-hint">Alert triggers when any rating is 1 or 2 stars</div>
                </div>
            </div>

        </div>
    </div>

</main>

<!-- ── FLOATING SAVE BAR ── -->
<div class="save-bar" id="saveBar">
    <span class="save-bar-text"><strong id="changesCount">0</strong> unsaved change(s)</span>
    <button class="btn-save-bar btn-discard" onclick="discardChanges()"><i class="fas fa-rotate-left"></i> Discard</button>
    <button class="btn-save-bar btn-confirm" onclick="saveNotifications()"><i class="fas fa-floppy-disk"></i> Save Now</button>
</div>

<script>
/* ── ORIGINAL STATE (for discard) ── */
const originalState = {
    notif_email:  document.getElementById('notif_email').checked,
    notif_low:    document.getElementById('notif_low').checked,
    notif_weekly: document.getElementById('notif_weekly').checked,
    notif_sys:    document.getElementById('notif_sys').checked
};
let pendingChanges = 0;

/* ── TOGGLE CARD ── */
function toggleCard(cardId, inputId) {
    const input = document.getElementById(inputId);
    input.checked = !input.checked;
    const card = document.getElementById(cardId);
    const colorClass = [...card.classList].find(c => c.startsWith('tc-') && c !== 'tc-red' && c !== 'tc-green' && c !== 'tc-blue' && c !== 'tc-violet') || '';
    onToggleChange(cardId, input.checked, card.classList[1]);
}

function onToggleChange(cardId, isOn, colorClass) {
    const card = document.getElementById(cardId);
    if (isOn) card.classList.add('is-on');
    else       card.classList.remove('is-on');
    updateStats();
    trackChanges();
}

/* ── STATS ── */
function updateStats() {
    const count = ['notif_email','notif_low','notif_weekly','notif_sys']
        .filter(id => document.getElementById(id).checked).length;
    document.getElementById('statEnabled').textContent = count;
}

/* ── UNSAVED CHANGES TRACKER ── */
function trackChanges() {
    const ids = ['notif_email','notif_low','notif_weekly','notif_sys'];
    pendingChanges = ids.filter(id => document.getElementById(id).checked !== originalState[id]).length;
    document.getElementById('changesCount').textContent = pendingChanges;
    const bar = document.getElementById('saveBar');
    if (pendingChanges > 0) bar.classList.add('visible');
    else                    bar.classList.remove('visible');
}

function discardChanges() {
    const ids = ['notif_email','notif_low','notif_weekly','notif_sys'];
    const cardMap = { notif_email: 'tc_email', notif_low: 'tc_low', notif_weekly: 'tc_weekly', notif_sys: 'tc_sys' };
    ids.forEach(id => {
        const input = document.getElementById(id);
        input.checked = originalState[id];
        const card = document.getElementById(cardMap[id]);
        if (originalState[id]) card.classList.add('is-on');
        else                   card.classList.remove('is-on');
    });
    updateStats();
    trackChanges();
}

/* ── SEND DATA ── */
function sendData(formData) {
    Swal.fire({ title: 'Saving…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('../../functions/process_settings.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Saved!', text: 'Notification preferences updated.', timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Network connection failed.', 'error'));
}

/* ── SAVE NOTIFICATIONS ── */
function saveNotifications() {
    const fd = new FormData();
    fd.append('type',                'notifications');
    fd.append('email_notifications', document.getElementById('notif_email').checked);
    fd.append('low_rating_alerts',   document.getElementById('notif_low').checked);
    fd.append('weekly_reports',      document.getElementById('notif_weekly').checked);
    fd.append('system_updates',      document.getElementById('notif_sys').checked);
    sendData(fd);
}

/* ── OTHER SETTINGS FUNCTIONS (backward compat) ── */
function saveProfile() {
    const fd = new FormData();
    fd.append('type', 'profile');
    fd.append('full_name', document.getElementById('prof_name')?.value ?? '');
    fd.append('email',     document.getElementById('prof_email')?.value ?? '');
    fd.append('new_password', document.getElementById('prof_pass')?.value ?? '');
    sendData(fd);
}
function saveSystem() {
    const fd = new FormData();
    fd.append('type',           'system');
    fd.append('system_name',    document.getElementById('sys_name')?.value    ?? '');
    fd.append('default_region', document.getElementById('sys_region')?.value  ?? '');
    fd.append('items_per_page', document.getElementById('sys_items')?.value   ?? '');
    fd.append('date_format',    document.getElementById('sys_date')?.value    ?? '');
    sendData(fd);
}
function saveSecurity() {
    const fd = new FormData();
    fd.append('type',                    'security');
    fd.append('two_factor_auth',         document.getElementById('sec_2fa')?.checked            ?? false);
    fd.append('session_timeout_enabled', document.getElementById('sec_timeout_toggle')?.checked  ?? false);
    fd.append('login_alerts',            document.getElementById('sec_alerts')?.checked          ?? false);
    fd.append('session_timeout',         document.getElementById('sec_timeout_mins')?.value      ?? '');
    sendData(fd);
}

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
