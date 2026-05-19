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
$profile_query = $conn->prepare("SELECT full_name, email, role, region, province, created_at, profile_picture FROM admin_users WHERE username = ?");
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

$display_name   = $profile_data['full_name']  ?? $username;
$display_email  = $profile_data['email']       ?? '—';
$display_role   = $profile_data['role']        ?? 'admin';
$display_region = $profile_data['region']      ?? 'N/A';
$display_prov   = $profile_data['province']    ?? null;
$member_since   = !empty($profile_data['created_at'])
    ? date('F Y', strtotime($profile_data['created_at']))
    : 'N/A';
$initials = strtoupper(substr($display_name, 0, 2));

/* Check for a locally-uploaded avatar first */
$username_safe   = preg_replace('/[^a-z0-9_]/', '', strtolower($username));
$local_avatars   = glob(__DIR__ . "/../../uploads/avatars/{$username_safe}.*");

$db_image = $profile_data['profile_picture'] ?? '';

if (!empty($db_image)) {
    $profile_avatar_url = "../../uploads/avatars/" . $db_image . "?t=" . time();
} else {
    $profile_avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($display_name) . "&background=FCD116&color=002147&bold=true&size=200";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile | DICT Admin</title>
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
    --shadow-lg: 0 10px 40px rgba(0,0,0,.14);
    --g-blue:   linear-gradient(135deg, #0038A8 0%, #001529 100%);
    --g-green:  linear-gradient(135deg, #10b981 0%, #059669 100%);
    --g-amber:  linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    --g-violet: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
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
   PROFILE HERO BANNER
══════════════════════════════════════ */
.profile-hero {
    background: linear-gradient(135deg, var(--navy) 0%, #00285e 40%, var(--blue) 100%);
    border-radius: var(--r-xl);
    padding: 36px 40px;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 32px;
    box-shadow: 0 8px 32px rgba(0,21,41,.3);
}
/* Decorative circles */
.profile-hero::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    pointer-events: none;
}
.profile-hero::after {
    content: '';
    position: absolute;
    bottom: -100px; right: 120px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,.03);
    pointer-events: none;
}
.hero-deco-3 {
    position: absolute;
    top: 50%; left: 38%;
    transform: translateY(-50%);
    width: 1px; height: 60%;
    background: rgba(255,255,255,.08);
}

/* Avatar */
.avatar-wrap {
    position: relative;
    flex-shrink: 0;
    cursor: pointer;
    z-index: 1;
}
.hero-avatar {
    width: 100px; height: 100px;
    border-radius: 50%;
    border: 3px solid rgba(252,209,22,.6);
    object-fit: cover;
    display: block;
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
    transition: transform .2s;
}
.avatar-wrap:hover .hero-avatar { transform: scale(1.04); }
.avatar-edit-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(0,0,0,.45);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 4px;
    opacity: 0;
    transition: opacity .2s;
    color: #fff;
    font-size: .78rem;
    font-weight: 600;
    text-align: center;
}
.avatar-edit-overlay i { font-size: 1.1rem; }
.avatar-wrap:hover .avatar-edit-overlay { opacity: 1; }
.avatar-upload-badge {
    position: absolute;
    bottom: 2px; right: 2px;
    width: 26px; height: 26px;
    border-radius: 50%;
    background: var(--gold);
    color: var(--navy-mid);
    display: flex; align-items: center; justify-content: center;
    font-size: .68rem;
    border: 2px solid var(--navy-mid);
    box-shadow: 0 2px 6px rgba(0,0,0,.3);
    pointer-events: none;
}

/* Hero info */
.hero-info { flex: 1; min-width: 0; z-index: 1; }
.hero-name {
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hero-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    background: rgba(252,209,22,.15);
    border: 1px solid rgba(252,209,22,.35);
    color: var(--gold);
    font-size: .74rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    margin-bottom: 16px;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.hero-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.8);
    font-size: .76rem;
    font-weight: 500;
    white-space: nowrap;
}
.hero-meta-chip i { color: rgba(252,209,22,.7); font-size: .72rem; }

/* Hero right side */
.hero-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    z-index: 1;
    flex-shrink: 0;
}
.hero-stat {
    text-align: right;
}
.hero-stat-value {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--gold);
    line-height: 1;
}
.hero-stat-label {
    font-size: .66rem;
    font-weight: 600;
    color: rgba(255,255,255,.5);
    text-transform: uppercase;
    letter-spacing: .7px;
    margin-top: 2px;
}
.hero-divider {
    width: 1px;
    height: 40px;
    background: rgba(255,255,255,.1);
    margin: 0 4px;
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
    transition: box-shadow .25s, transform .25s;
}
.settings-card:hover { box-shadow: var(--shadow-lg); }

.card-head {
    padding: 20px 24px 18px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
}
.card-head-icon {
    width: 38px; height: 38px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
}
.card-head-icon.ci-blue   { background: rgba(0,56,168,.1);    color: var(--blue); }
.card-head-icon.ci-violet { background: rgba(139,92,246,.1);  color: var(--violet); }
.card-head-icon.ci-green  { background: rgba(16,185,129,.1);  color: var(--success); }
.card-head-icon.ci-amber  { background: rgba(245,158,11,.1);  color: var(--warning); }

.card-head-title { font-size: .95rem; font-weight: 700; color: var(--text); }
.card-head-sub   { font-size: .74rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

.card-body { padding: 24px; }

/* ── FORM FIELDS ── */
.fg { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
.fg:last-of-type { margin-bottom: 0; }
.fg label {
    font-size: .7rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .7px;
}
.fg .input-wrap { position: relative; display: flex; align-items: center; }
.fg .input-wrap i {
    position: absolute; left: 13px;
    color: #94a3b8; font-size: .82rem;
    pointer-events: none;
}
.fg .input-wrap input {
    width: 100%;
    padding: 11px 13px 11px 38px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-md);
    font-size: .85rem;
    font-family: inherit;
    color: var(--text);
    background: #fafbfc;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.fg .input-wrap input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,56,168,.08);
    background: #fff;
}
.fg .input-wrap input:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
    border-color: #e2e8f0;
}
.fg .input-wrap input::placeholder { color: #c0ccda; }

.section-divider {
    border: none;
    border-top: 1.5px dashed #e2e8f0;
    margin: 20px 0;
}

/* ── PASSWORD STRENGTH ── */
.strength-wrap { margin-bottom: 18px; }
.strength-bar-track {
    height: 5px;
    background: #f1f5f9;
    border-radius: 99px;
    overflow: hidden;
    margin-top: 8px;
}
.strength-bar-fill {
    height: 100%;
    width: 0%;
    border-radius: 99px;
    background: var(--danger);
    transition: width .3s ease, background .3s ease;
}
.strength-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 5px;
}
.strength-hint { font-size: .72rem; color: var(--muted); }
.strength-label { font-size: .72rem; font-weight: 700; color: var(--muted); }

.requirements {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
    margin-top: 14px;
}
.req-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .74rem;
    color: #94a3b8;
    font-weight: 500;
    transition: color .2s;
}
.req-item::before {
    content: '';
    width: 14px; height: 14px;
    border-radius: 50%;
    background: #e2e8f0;
    flex-shrink: 0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M3 6l2 2 4-4' stroke='%2394a3b8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-size: 10px;
    background-position: center;
    background-repeat: no-repeat;
    transition: background .2s;
}
.req-item.met { color: var(--success); }
.req-item.met::before {
    background-color: rgba(16,185,129,.15);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M3 6l2 2 4-4' stroke='%2310b981' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
}

/* ── BUTTONS ── */
.btn-save {
    width: 100%;
    padding: 12px 20px;
    border-radius: var(--r-md);
    border: none;
    font-size: .88rem;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: transform .18s, opacity .18s;
    margin-top: 20px;
}
.btn-save:hover { transform: translateY(-2px); opacity: .92; }
.btn-blue   { background: var(--g-blue);   color: #fff; box-shadow: 0 4px 14px rgba(0,56,168,.3); }
.btn-violet { background: var(--g-violet); color: #fff; box-shadow: 0 4px 14px rgba(139,92,246,.3); }

/* ── ACCOUNT INFO CARD (full width) ── */
.account-info-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    overflow: hidden;
}
.info-chips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    padding: 24px;
}
.info-chip {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border-radius: var(--r-lg);
    background: #f8fafc;
    border: 1px solid var(--border);
    transition: background .2s;
}
.info-chip:hover { background: #f1f5f9; }
.info-chip-icon {
    width: 40px; height: 40px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
}
.info-chip-icon.ic-blue   { background: rgba(0,56,168,.08);   color: var(--blue); }
.info-chip-icon.ic-indigo { background: rgba(99,102,241,.1);  color: #6366f1; }
.info-chip-icon.ic-green  { background: rgba(16,185,129,.08); color: var(--success); }
.info-chip-icon.ic-amber  { background: rgba(245,158,11,.1);  color: var(--warning); }
.info-chip-icon.ic-violet { background: rgba(139,92,246,.1);  color: var(--violet); }
.info-chip-icon.ic-teal   { background: rgba(20,184,166,.1);  color: #14b8a6; }

.info-chip-body { min-width: 0; }
.info-chip-label { font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; }
.info-chip-value { font-size: .88rem; font-weight: 600; color: var(--text); margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.info-chip-value.active-tag {
    display: inline-flex; align-items: center; gap: 5px;
    color: var(--success);
}
.active-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--success); display: inline-block; animation: pulse 1.8s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.3; } }

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
    color: #fff;
    font-size: 1.1rem;
    cursor: pointer;
    align-items: center; justify-content: center;
    transition: background .2s;
}
.burger-btn:hover { background: rgba(255,255,255,.18); }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
    .settings-grid { grid-template-columns: 1fr; }
    .profile-hero { flex-wrap: wrap; gap: 20px; padding: 24px; }
    .hero-right { flex-direction: row; align-items: center; justify-content: flex-start; }
}
@media (max-width: 768px) {
    .main-content { margin-left: 0; width: 100%; padding: calc(var(--header-h) + 24px) 16px 32px; }
    .burger-btn { display: flex; }
    .hero-name { font-size: 1.2rem; }
    .info-chips-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .info-chips-grid { grid-template-columns: 1fr; }
    .requirements { grid-template-columns: 1fr; }
    .hero-right { display: none; }
}
</style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<button class="burger-btn" id="menuToggle"><i class="fas fa-bars"></i></button>

<input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">

<main class="main-content">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="page-title-icon"><i class="fas fa-circle-user"></i></div>
        <div>
            <div class="page-title-text">My Profile</div>
            <div class="page-subtitle">Manage your account information and security settings</div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         PROFILE HERO BANNER
    ══════════════════════════════════ -->
    <div class="profile-hero">
        <div class="hero-deco-3"></div>

        <!-- Avatar -->
        <div class="avatar-wrap" onclick="document.getElementById('avatarInput').click()" title="Click to change photo">
            <img id="heroAvatar" src="<?= $profile_avatar_url ?>" class="hero-avatar" alt="Profile Photo">
            <div class="avatar-edit-overlay">
                <i class="fas fa-camera"></i>
                <span>Change</span>
            </div>
            <div class="avatar-upload-badge"><i class="fas fa-camera"></i></div>
        </div>

        <!-- Info -->
        <div class="hero-info">
            <div class="hero-name"><?= htmlspecialchars($display_name) ?></div>
            <div class="hero-role-badge">
                <i class="fas <?= $is_superadmin ? 'fa-crown' : 'fa-user-shield' ?>"></i>
                <?= $is_superadmin ? 'Superadministrator' : 'Regional Administrator' ?>
            </div>
            <div class="hero-meta">
                <span class="hero-meta-chip"><i class="fas fa-envelope"></i><?= htmlspecialchars($display_email) ?></span>
                <span class="hero-meta-chip"><i class="fas fa-user"></i>@<?= htmlspecialchars($username) ?></span>
                <?php if ($display_region !== 'N/A'): ?>
                <span class="hero-meta-chip"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($display_region) ?></span>
                <?php endif; ?>
                <span class="hero-meta-chip"><i class="fas fa-calendar-check"></i>Since <?= $member_since ?></span>
            </div>
        </div>

        <!-- Right side stats -->
        <div class="hero-right">
            <div class="hero-stat">
                <div class="hero-stat-value"><?= strtoupper(substr($display_role, 0, 1)) . strtolower(substr($display_role, 1)) ?></div>
                <div class="hero-stat-label">Account Role</div>
            </div>
            <div class="hero-divider"></div>
            <div class="hero-stat">
                <div class="hero-stat-value" style="color:#4ade80;">Active</div>
                <div class="hero-stat-label">Status</div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════
         EDIT PROFILE + CHANGE PASSWORD
    ══════════════════════════════════ -->
    <div class="settings-grid">

        <!-- ── EDIT PROFILE ── -->
        <div class="settings-card">
            <div class="card-head">
                <div class="card-head-icon ci-blue"><i class="fas fa-user-pen"></i></div>
                <div>
                    <div class="card-head-title">Profile Information</div>
                    <div class="card-head-sub">Update your name, email, and photo</div>
                </div>
            </div>
            <div class="card-body">

                <!-- Avatar change (inline shortcut) -->
                <div style="display:flex; align-items:center; gap:14px; padding:14px 16px; background:#f8fafc; border-radius:var(--r-lg); border:1px solid var(--border); margin-bottom:22px; cursor:pointer;"
                     onclick="document.getElementById('avatarInput').click()">
                    <img id="cardAvatar" src="<?= $profile_avatar_url ?>" style="width:48px;height:48px;border-radius:12px;object-fit:cover;border:2px solid rgba(0,56,168,.2);" alt="avatar">
                    <div style="flex:1;">
                        <div style="font-size:.84rem;font-weight:600;color:var(--text);">Profile Photo</div>
                        <div style="font-size:.74rem;color:var(--muted);margin-top:2px;">Click to upload · JPG, PNG, GIF up to 2MB</div>
                    </div>
                    <div style="width:32px;height:32px;border-radius:var(--r-sm);background:rgba(0,56,168,.08);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:.8rem;">
                        <i class="fas fa-upload"></i>
                    </div>
                </div>

                <div class="fg">
                    <label>Full Name</label>
                    <div class="input-wrap"><i class="fas fa-id-card"></i>
                        <input type="text" id="prof_name" value="<?= htmlspecialchars($profile_data['full_name'] ?? '') ?>" placeholder="Your full name">
                    </div>
                </div>

                <div class="fg">
                    <label>Email Address</label>
                    <div class="input-wrap"><i class="fas fa-envelope"></i>
                        <input type="email" id="prof_email" value="<?= htmlspecialchars($profile_data['email'] ?? '') ?>" placeholder="your@email.com">
                    </div>
                </div>

                <div class="fg">
                    <label>Username <span style="font-size:.65rem;color:#94a3b8;font-weight:500;text-transform:none;letter-spacing:0;">— cannot be changed</span></label>
                    <div class="input-wrap"><i class="fas fa-at"></i>
                        <input type="text" value="<?= htmlspecialchars($username) ?>" disabled>
                    </div>
                </div>

                <button class="btn-save btn-blue" onclick="saveProfileInfo()">
                    <i class="fas fa-floppy-disk"></i> Save Profile Changes
                </button>
            </div>
        </div>

        <!-- ── CHANGE PASSWORD ── -->
        <div class="settings-card">
            <div class="card-head">
                <div class="card-head-icon ci-violet"><i class="fas fa-lock"></i></div>
                <div>
                    <div class="card-head-title">Change Password</div>
                    <div class="card-head-sub">Keep your account secure with a strong password</div>
                </div>
            </div>
            <div class="card-body">

                <div class="fg">
                    <label>New Password</label>
                    <div class="input-wrap"><i class="fas fa-key"></i>
                        <input type="password" id="prof_pass" placeholder="Enter new password" oninput="updateStrength(this.value)">
                    </div>
                </div>

                <!-- Strength meter -->
                <div class="strength-wrap">
                    <div class="strength-bar-track">
                        <div class="strength-bar-fill" id="strengthBar"></div>
                    </div>
                    <div class="strength-row">
                        <span class="strength-hint">Password strength</span>
                        <span class="strength-label" id="strengthLabel"></span>
                    </div>
                </div>

                <!-- Requirements checklist -->
                <div class="requirements">
                    <div class="req-item" id="req_length">At least 8 characters</div>
                    <div class="req-item" id="req_upper">One uppercase letter</div>
                    <div class="req-item" id="req_number">One number (0–9)</div>
                    <div class="req-item" id="req_special">One special character</div>
                </div>

                <hr class="section-divider" style="margin-top:20px;">

                <div class="fg" style="margin-top:0;">
                    <label>Confirm New Password</label>
                    <div class="input-wrap"><i class="fas fa-lock-open"></i>
                        <input type="password" id="prof_pass_confirm" placeholder="Repeat new password" oninput="checkMatch()">
                    </div>
                    <div id="matchHint" style="font-size:.73rem;margin-top:4px;display:none;"></div>
                </div>

                <button class="btn-save btn-violet" onclick="savePassword()">
                    <i class="fas fa-shield-halved"></i> Update Password
                </button>
            </div>
        </div>

    </div><!-- /settings-grid -->

    <!-- ══════════════════════════════════
         ACCOUNT INFORMATION
    ══════════════════════════════════ -->
    <div class="account-info-card">
        <div class="card-head">
            <div class="card-head-icon ci-green"><i class="fas fa-circle-info"></i></div>
            <div>
                <div class="card-head-title">Account Details</div>
                <div class="card-head-sub">Read-only information about your account</div>
            </div>
        </div>
        <div class="info-chips-grid">

            <div class="info-chip">
                <div class="info-chip-icon ic-blue"><i class="fas fa-user"></i></div>
                <div class="info-chip-body">
                    <div class="info-chip-label">Username</div>
                    <div class="info-chip-value">@<?= htmlspecialchars($username) ?></div>
                </div>
            </div>

            <div class="info-chip">
                <div class="info-chip-icon ic-indigo"><i class="fas fa-crown"></i></div>
                <div class="info-chip-body">
                    <div class="info-chip-label">Role</div>
                    <div class="info-chip-value"><?= $is_superadmin ? 'Superadministrator' : 'Regional Admin' ?></div>
                </div>
            </div>

            <div class="info-chip">
                <div class="info-chip-icon ic-green"><i class="fas fa-circle-check"></i></div>
                <div class="info-chip-body">
                    <div class="info-chip-label">Account Status</div>
                    <div class="info-chip-value active-tag"><span class="active-dot"></span>Active</div>
                </div>
            </div>

            <div class="info-chip">
                <div class="info-chip-icon ic-amber"><i class="fas fa-map-marker-alt"></i></div>
                <div class="info-chip-body">
                    <div class="info-chip-label">Assigned Region</div>
                    <div class="info-chip-value"><?= htmlspecialchars($display_region) ?></div>
                </div>
            </div>

            <?php if ($display_prov): ?>
            <div class="info-chip">
                <div class="info-chip-icon ic-teal"><i class="fas fa-city"></i></div>
                <div class="info-chip-body">
                    <div class="info-chip-label">Province</div>
                    <div class="info-chip-value"><?= htmlspecialchars($display_prov) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-chip">
                <div class="info-chip-icon ic-violet"><i class="fas fa-calendar-plus"></i></div>
                <div class="info-chip-body">
                    <div class="info-chip-label">Member Since</div>
                    <div class="info-chip-value"><?= $member_since ?></div>
                </div>
            </div>

        </div>
    </div>

</main>

<script>
/* ── AVATAR PREVIEW ── */
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'File Too Large', text: 'Please choose an image under 2MB.', confirmButtonColor: '#0038A8' });
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('heroAvatar').src = e.target.result;
            document.getElementById('cardAvatar').src = e.target.result;
        };
        reader.readAsDataURL(file);
        Swal.fire({
            icon: 'info',
            title: 'Photo Selected',
            text: 'Click "Save Profile Changes" to upload your new profile photo.',
            timer: 2500,
            showConfirmButton: false,
            confirmButtonColor: '#0038A8'
        });
    }
}

/* ── PASSWORD STRENGTH METER ── */
function updateStrength(pwd) {
    const len  = pwd.length >= 8;
    const upp  = /[A-Z]/.test(pwd);
    const num  = /[0-9]/.test(pwd);
    const spc  = /[^A-Za-z0-9]/.test(pwd);
    const score = [len, upp, num, spc].filter(Boolean).length;

    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');

    const widths = ['0%', '25%', '50%', '75%', '100%'];
    const colors = ['#e2e8f0', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
    const labels = ['',       'Weak',     'Fair',    'Good',    'Strong'];

    bar.style.width      = pwd.length ? widths[score] : '0%';
    bar.style.background = colors[score];
    label.textContent    = pwd.length ? labels[score] : '';
    label.style.color    = colors[score];

    document.getElementById('req_length').className  = 'req-item' + (len ? ' met' : '');
    document.getElementById('req_upper').className   = 'req-item' + (upp ? ' met' : '');
    document.getElementById('req_number').className  = 'req-item' + (num ? ' met' : '');
    document.getElementById('req_special').className = 'req-item' + (spc ? ' met' : '');

    if (document.getElementById('prof_pass_confirm').value) checkMatch();
}

/* ── PASSWORD MATCH INDICATOR ── */
function checkMatch() {
    const p1   = document.getElementById('prof_pass').value;
    const p2   = document.getElementById('prof_pass_confirm').value;
    const hint = document.getElementById('matchHint');
    if (!p2) { hint.style.display = 'none'; return; }
    hint.style.display = 'block';
    if (p1 === p2) {
        hint.innerHTML = '<i class="fas fa-check" style="color:#10b981;margin-right:4px;"></i><span style="color:#10b981;">Passwords match</span>';
    } else {
        hint.innerHTML = '<i class="fas fa-xmark" style="color:#ef4444;margin-right:4px;"></i><span style="color:#ef4444;">Passwords do not match</span>';
    }
}

/* ── SEND DATA HELPER ── */
function sendData(formData) {
    Swal.fire({ title: 'Saving…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    fetch('../../functions/process_settings.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(data => {
            if (data.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Saved!', text: 'Your changes have been updated.', timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data, 'error');
            }
        })
        .catch(() => Swal.fire('Error', 'Network connection failed.', 'error'));
}

/* ── SAVE PROFILE INFO ── */
function saveProfileInfo() {
    const name = document.getElementById('prof_name').value.trim();
    if (!name) { Swal.fire('Required', 'Full name cannot be empty.', 'warning'); return; }

    const fd = new FormData();
    fd.append('type', 'profile');
    fd.append('full_name', name);
    fd.append('email', document.getElementById('prof_email').value);

    const avatarFile = document.getElementById('avatarInput').files[0];
    if (avatarFile) fd.append('avatar', avatarFile);

    sendData(fd);
}

/* ── SAVE PASSWORD ── */
function savePassword() {
    const pass = document.getElementById('prof_pass').value;
    const conf = document.getElementById('prof_pass_confirm').value;

    if (!pass) { Swal.fire('Required', 'Please enter a new password.', 'warning'); return; }
    if (pass.length < 8) { Swal.fire('Too Short', 'Password must be at least 8 characters.', 'warning'); return; }
    if (pass !== conf) { Swal.fire('Mismatch', 'The passwords you entered do not match.', 'warning'); return; }

    const fd = new FormData();
    fd.append('type', 'profile');
    fd.append('new_password', pass);
    sendData(fd);
}

/* ── BACKWARD COMPAT ── */
function saveProfile() { saveProfileInfo(); }

function saveNotifications() {
    const fd = new FormData();
    fd.append('type', 'notifications');
    fd.append('email_notifications', document.getElementById('notif_email')?.checked ?? false);
    fd.append('low_rating_alerts',   document.getElementById('notif_low')?.checked   ?? false);
    fd.append('weekly_reports',      document.getElementById('notif_weekly')?.checked ?? false);
    fd.append('system_updates',      document.getElementById('notif_sys')?.checked   ?? false);
    sendData(fd);
}

function saveSystem() {
    const fd = new FormData();
    fd.append('type', 'system');
    fd.append('system_name',    document.getElementById('sys_name')?.value   ?? '');
    fd.append('default_region', document.getElementById('sys_region')?.value ?? '');
    fd.append('items_per_page', document.getElementById('sys_items')?.value  ?? '');
    fd.append('date_format',    document.getElementById('sys_date')?.value   ?? '');
    sendData(fd);
}

function saveSecurity() {
    const fd = new FormData();
    fd.append('type', 'security');
    fd.append('two_factor_auth',         document.getElementById('sec_2fa')?.checked           ?? false);
    fd.append('session_timeout_enabled', document.getElementById('sec_timeout_toggle')?.checked ?? false);
    fd.append('login_alerts',            document.getElementById('sec_alerts')?.checked         ?? false);
    fd.append('session_timeout',         document.getElementById('sec_timeout_mins')?.value     ?? '');
    sendData(fd);
}

/* ── SIDEBAR TOGGLE ── */
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
