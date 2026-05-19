<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

/* ========================= AUTHENTICATION ========================= */
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'admin')) {
    die("Error: Unauthorized access.");
}

$isSuperAdmin    = ($_SESSION['role'] === 'superadmin');
$current_username = $_SESSION['username'];

function verifyAdminAction($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT password FROM ADMIN_USERS WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return password_verify($password, $row['password']);
    }
    return false;
}

/* ========================= AJAX HANDLER ========================= */
if (isset($_POST['ajax_action']) && $isSuperAdmin) {
    $id     = intval($_POST['id']);
    $action = $_POST['ajax_action'];
    $email  = mysqli_real_escape_string($conn, $_POST['email']);
    $response = ['success' => false, 'message' => 'Unknown error'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE ADMIN_USERS SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Account approved and email sent to ' . $email];
        }
    } elseif ($action === 'remove') {
        if (verifyAdminAction($conn, $current_username, $_POST['admin_password'])) {
            $stmt = $conn->prepare("DELETE FROM ADMIN_USERS WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Account removed and notification sent.'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Invalid admin password.'];
        }
    }
    echo json_encode($response);
    exit();
}

/* ========================= MANUAL ADD ========================= */
if (isset($_POST['add_admin'])) {
    if (!verifyAdminAction($conn, $current_username, $_POST['current_admin_password'])) {
        header("Location: manage_admins.php?error=wrong_password");
        exit();
    }

    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);
    $region    = mysqli_real_escape_string($conn, $_POST['region']);
    $province  = !empty($_POST['province']) ? mysqli_real_escape_string($conn, $_POST['province']) : NULL;

    $check_stmt = $conn->prepare("SELECT id FROM ADMIN_USERS WHERE username = ? OR email = ? LIMIT 1");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        header("Location: manage_admins.php?error=exists");
        exit();
    }

    $status = ($isSuperAdmin) ? 'active' : NULL;
    $sql    = "INSERT INTO ADMIN_USERS (full_name, username, email, password, role, region, province, status, requested_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt   = $conn->prepare($sql);
    $stmt->bind_param("sssssssss", $full_name, $username, $email, $password, $role, $region, $province, $status, $current_username);

    if ($stmt->execute()) {
        header("Location: manage_admins.php?success=" . ($isSuperAdmin ? "added" : "requested"));
    } else {
        header("Location: manage_admins.php?error=db_error");
    }
    exit();
}

/* ========================= DATA FETCHING ========================= */
$result = $conn->query("SELECT * FROM ADMIN_USERS ORDER BY (status IS NULL) DESC, created_at DESC");

$stats_q = $conn->query("
    SELECT
        SUM(CASE WHEN status IS NULL THEN 1 ELSE 0 END)             as pending_count,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END)             as admin_count,
        SUM(CASE WHEN role = 'superadmin' THEN 1 ELSE 0 END)        as superadmin_count,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)          as active_count
    FROM ADMIN_USERS
");
$stats           = $stats_q->fetch_assoc();
$pending_count   = $stats['pending_count']   ?? 0;
$admin_count     = $stats['admin_count']     ?? 0;
$superadmin_count= $stats['superadmin_count']?? 0;
$active_count    = $stats['active_count']    ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Admins | DICT Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>

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
    --amber:    #f59e0b;
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
    --g-indigo: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
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
.page-title-group { display: flex; flex-direction: column; gap: 4px; }
.page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.2;
}
.page-title-icon {
    width: 44px; height: 44px;
    border-radius: var(--r-md);
    background: var(--g-blue);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,56,168,.3);
}
.page-title-text {
    background: linear-gradient(135deg, var(--navy-mid) 0%, var(--blue) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.page-subtitle { font-size: .8rem; color: var(--muted); font-weight: 500; margin-top: 2px; }

/* ── BUTTON ── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: var(--r-md);
    font-size: .84rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: transform .18s, box-shadow .18s, opacity .18s;
    white-space: nowrap;
}
.btn:hover { transform: translateY(-2px); opacity: .92; }
.btn-primary { background: var(--g-blue); color: #fff; box-shadow: 0 4px 14px rgba(0,56,168,.35); }

/* ── STAT CARDS ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    padding: 12px 14px 10px;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
    transition: transform .25s, box-shadow .25s;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    border-radius: var(--r-xl) var(--r-xl) 0 0;
}
.stat-card .blob {
    position: absolute;
    width: 90px; height: 90px;
    border-radius: 50%;
    right: -22px; bottom: -22px;
    opacity: .06;
}
.s-indigo::before { background: var(--g-indigo); }
.s-violet::before { background: var(--g-violet); }
.s-green::before  { background: var(--g-green); }
.s-amber::before  { background: var(--g-amber); }
.s-indigo .blob { background: #6366f1; }
.s-violet .blob { background: var(--violet); }
.s-green  .blob { background: var(--success); }
.s-amber  .blob { background: var(--amber); }

.stat-icon-box {
    width: 30px; height: 30px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem;
    margin-bottom: 8px;
    color: #fff;
}
.s-indigo .stat-icon-box { background: var(--g-indigo); }
.s-violet .stat-icon-box { background: var(--g-violet); }
.s-green  .stat-icon-box { background: var(--g-green); }
.s-amber  .stat-icon-box { background: var(--g-amber); }

.stat-body { min-width: 0; }
.stat-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--navy-mid);
    line-height: 1.1;
    letter-spacing: -.5px;
}
.stat-label {
    font-size: .65rem;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-top: 3px;
}

/* ── TABLE CARD ── */
.table-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    overflow: hidden;
}
.table-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.tch-left { display: flex; align-items: center; gap: 10px; }
.th-icon {
    width: 36px; height: 36px;
    border-radius: var(--r-sm);
    background: var(--g-blue);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
}
.table-card-header h3 { font-size: .95rem; font-weight: 700; color: var(--text); }

.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
thead tr {
    background: #f7f9fc;
}
thead th {
    padding: 13px 16px;
    font-size: .72rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .8px;
    text-align: left;
    white-space: nowrap;
    border-bottom: 1px solid var(--border);
}
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #f8faff; }
td { padding: 13px 16px; font-size: .85rem; vertical-align: middle; }

/* Name chip */
.name-chip { display: inline-flex; align-items: center; gap: 10px; }
.name-avatar {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--blue), var(--navy));
    color: #fff;
    font-size: .68rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
}
.name-text .full-name { font-weight: 700; font-size: .85rem; color: var(--text); }
.name-text .email-text { font-size: .74rem; color: var(--muted); margin-top: 1px; }

/* Location */
.loc-region { font-size: .82rem; font-weight: 600; color: var(--text); }
.loc-province { font-size: .74rem; color: var(--muted); margin-top: 2px; }

/* Role badge */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.role-superadmin { background: rgba(99,102,241,.12); color: #4338ca; }
.role-admin       { background: rgba(0,56,168,.1);   color: var(--blue); }

/* Requested-by */
.req-by { font-size: .8rem; color: var(--navy-mid); font-weight: 500; }

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.status-active  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.status-pending { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.status-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-active  .status-dot { background: #22c55e; }
.status-pending .status-dot { background: #f59e0b; animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse {
    0%, 100% { opacity: 1; } 50% { opacity: .4; }
}

/* Action buttons */
.action-group { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }
.act-btn {
    width: 32px; height: 32px;
    border-radius: var(--r-sm);
    border: none;
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .82rem;
    transition: all .18s;
}
.act-approve { background: rgba(16,185,129,.1);  color: var(--success); }
.act-approve:hover { background: var(--success); color: #fff; transform: translateY(-1px); box-shadow: 0 3px 8px rgba(16,185,129,.4); }
.act-delete  { background: rgba(239,68,68,.1);  color: var(--danger); }
.act-delete:hover  { background: var(--danger);  color: #fff; transform: translateY(-1px); box-shadow: 0 3px 8px rgba(239,68,68,.4); }
.read-only-label { font-size: .75rem; color: #94a3b8; font-style: italic; }

/* ── MODAL ── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(0,21,41,.6);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.modal-box {
    background: var(--surface);
    border-radius: 24px;
    width: 100%;
    max-width: 560px;
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    scrollbar-width: none;
}
.modal-box::-webkit-scrollbar { display: none; }

.modal-header {
    padding: 24px 28px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    position: sticky;
    top: 0;
    background: var(--surface);
    z-index: 1;
    border-radius: 24px 24px 0 0;
}
.modal-header-left { display: flex; align-items: center; gap: 12px; }
.modal-header-icon {
    width: 40px; height: 40px;
    border-radius: var(--r-md);
    background: var(--g-blue);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
}
.modal-header h2 { font-size: 1.1rem; font-weight: 800; color: var(--text); }
.modal-header p  { font-size: .75rem; color: var(--muted); font-weight: 500; margin-top: 2px; }
.modal-close {
    width: 32px; height: 32px;
    border-radius: var(--r-sm);
    background: #f1f5f9;
    border: none;
    color: var(--muted);
    font-size: .9rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .18s;
    flex-shrink: 0;
}
.modal-close:hover { background: #fee2e2; color: var(--danger); }

.modal-body { padding: 24px 28px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-row.single { grid-template-columns: 1fr; }
.fg { display: flex; flex-direction: column; gap: 6px; margin-bottom: 4px; }
.fg label {
    font-size: .7rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .7px;
}
.input-wrap { position: relative; display: flex; align-items: center; }
.input-wrap i {
    position: absolute;
    left: 13px;
    color: #94a3b8;
    font-size: .82rem;
    pointer-events: none;
}
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
}
.input-wrap input:focus,
.input-wrap select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,56,168,.08);
    background: #fff;
}

.auth-block {
    background: #f8faff;
    border: 1.5px dashed rgba(0,56,168,.25);
    border-radius: var(--r-lg);
    padding: 16px;
    margin-top: 4px;
}
.auth-block .auth-label {
    font-size: .72rem;
    font-weight: 700;
    color: var(--blue);
    text-transform: uppercase;
    letter-spacing: .7px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.modal-footer {
    padding: 20px 28px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
}
.btn-modal-submit {
    flex: 2;
    padding: 12px 20px;
    border-radius: var(--r-md);
    background: var(--g-blue);
    color: #fff;
    font-size: .88rem;
    font-weight: 700;
    font-family: inherit;
    border: none;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: transform .18s, opacity .18s;
    box-shadow: 0 4px 14px rgba(0,56,168,.3);
}
.btn-modal-submit:hover { transform: translateY(-2px); opacity: .92; }
.btn-modal-cancel {
    flex: 1;
    padding: 12px 16px;
    border-radius: var(--r-md);
    background: #fff;
    color: var(--muted);
    font-size: .88rem;
    font-weight: 600;
    font-family: inherit;
    border: 1.5px solid var(--border);
    cursor: pointer;
    transition: all .18s;
}
.btn-modal-cancel:hover { border-color: var(--danger); color: var(--danger); background: rgba(239,68,68,.04); }

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
@media (max-width: 768px) {
    .main-content { margin-left: 0; width: 100%; padding: calc(var(--header-h) + 24px) 16px 32px; }
    .burger-btn { display: flex; }
    .page-header { flex-direction: column; align-items: flex-start; }
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .form-row { grid-template-columns: 1fr; }
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
            <h1 class="page-title">
                <div class="page-title-icon"><i class="fas fa-users-gear"></i></div>
                <span class="page-title-text">Admin User Management</span>
            </h1>
            <p class="page-subtitle">Manage system administrator accounts and access levels</p>
        </div>
        <button class="btn btn-primary" onclick="toggleModal()">
            <i class="fas fa-user-plus"></i> Add Administrator
        </button>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="stats-grid">
        <div class="stat-card s-indigo">
            <div class="stat-icon-box"><i class="fas fa-crown"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $superadmin_count ?>"><?= $superadmin_count ?></div>
                <div class="stat-label">Superadmins</div>
            </div>
            <div class="blob"></div>
        </div>
        <div class="stat-card s-violet">
            <div class="stat-icon-box"><i class="fas fa-user-shield"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $admin_count ?>"><?= $admin_count ?></div>
                <div class="stat-label">Regional Admins</div>
            </div>
            <div class="blob"></div>
        </div>
        <div class="stat-card s-green">
            <div class="stat-icon-box"><i class="fas fa-user-check"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $active_count ?>"><?= $active_count ?></div>
                <div class="stat-label">Active Accounts</div>
            </div>
            <div class="blob"></div>
        </div>
        <?php if ($isSuperAdmin): ?>
        <div class="stat-card s-amber">
            <div class="stat-icon-box"><i class="fas fa-user-clock"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $pending_count ?>"><?= $pending_count ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            <div class="blob"></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="tch-left">
                <div class="th-icon"><i class="fas fa-users-shield"></i></div>
                <h3>System Administrators</h3>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Administrator</th>
                        <th>Location</th>
                        <th>Role</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()):
                        $initials = strtoupper(substr($row['full_name'], 0, 2));
                        $roleClass = $row['role'] === 'superadmin' ? 'role-superadmin' : 'role-admin';
                        $roleIcon  = $row['role'] === 'superadmin' ? 'fa-crown' : 'fa-user-shield';
                        $isActive  = !is_null($row['status']);
                    ?>
                    <tr id="row-<?= $row['id'] ?>">
                        <td>
                            <div class="name-chip">
                                <div class="name-avatar"><?= htmlspecialchars($initials) ?></div>
                                <div class="name-text">
                                    <div class="full-name"><?= htmlspecialchars($row['full_name']) ?></div>
                                    <div class="email-text"><?= htmlspecialchars($row['email'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="loc-region"><?= htmlspecialchars($row['region'] ?? 'N/A') ?></div>
                            <div class="loc-province"><?= htmlspecialchars($row['province'] ?? 'All Provinces') ?></div>
                        </td>
                        <td>
                            <span class="role-badge <?= $roleClass ?>">
                                <i class="fas <?= $roleIcon ?>"></i>
                                <?= strtoupper($row['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="req-by"><?= htmlspecialchars($row['requested_by'] ?? 'System') ?></span>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="status-badge status-active"><span class="status-dot"></span> Active</span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><span class="status-dot"></span> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-group">
                                <?php if ($isSuperAdmin): ?>
                                    <?php if (!$isActive): ?>
                                        <button class="act-btn act-approve" title="Approve Account"
                                            onclick="handleAction(<?= $row['id'] ?>, '<?= $row['email'] ?>', 'approve')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="act-btn act-delete" title="Remove Account"
                                        onclick="handleAction(<?= $row['id'] ?>, '<?= $row['email'] ?>', 'remove')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="read-only-label">Read-only</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ══════════════════════════════════
     ADD ADMIN MODAL
══════════════════════════════════ -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon"><i class="fas fa-user-plus"></i></div>
                <div>
                    <h2>Add Administrator</h2>
                    <p>Create a new system admin account</p>
                </div>
            </div>
            <button class="modal-close" onclick="toggleModal()"><i class="fas fa-times"></i></button>
        </div>

        <div class="modal-body">
            <form method="POST" id="addAdminForm">

                <div class="form-row" style="margin-bottom:16px;">
                    <div class="fg">
                        <label>Full Name</label>
                        <div class="input-wrap"><i class="fas fa-id-card"></i><input type="text" name="full_name" placeholder="Juan dela Cruz" required></div>
                    </div>
                    <div class="fg">
                        <label>Username</label>
                        <div class="input-wrap"><i class="fas fa-user"></i><input type="text" name="username" placeholder="jdelacruz" required></div>
                    </div>
                </div>

                <div class="form-row single" style="margin-bottom:16px;">
                    <div class="fg">
                        <label>Email Address</label>
                        <div class="input-wrap"><i class="fas fa-envelope"></i><input type="email" name="email" placeholder="admin@dict.gov.ph" required></div>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom:16px;">
                    <div class="fg">
                        <label>Account Password</label>
                        <div class="input-wrap"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Min. 8 characters" minlength="8" required></div>
                    </div>
                    <div class="fg">
                        <label>Role</label>
                        <div class="input-wrap"><i class="fas fa-user-tag"></i>
                            <select name="role" required>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom:16px;">
                    <div class="fg">
                        <label>Region</label>
                        <div class="input-wrap"><i class="fas fa-map-marker-alt"></i>
                            <select id="modal_region" name="region" onchange="modalRegionChanged()" required>
                                <option value="">Select Region</option>
                                <option value="National Capital Region (NCR)">National Capital Region (NCR)</option>
                                <option value="Cordillera Administrative Region (CAR)">Cordillera Administrative Region (CAR)</option>
                                <option value="Region I - Ilocos Region">Region I (Ilocos Region)</option>
                                <option value="Region II - Cagayan Valley">Region II (Cagayan Valley)</option>
                                <option value="Region III - Central Luzon">Region III (Central Luzon)</option>
                                <option value="Region IV-A - CALABARZON">Region IV-A (CALABARZON)</option>
                                <option value="Region IV-B - MIMAROPA">MIMAROPA Region</option>
                                <option value="Region V - Bicol Region">Region V (Bicol Region)</option>
                                <option value="Region VI - Western Visayas">Region VI (Western Visayas)</option>
                                <option value="Region VII - Central Visayas">Region VII (Central Visayas)</option>
                                <option value="Region VIII - Eastern Visayas">Region VIII (Eastern Visayas)</option>
                                <option value="Region IX - Zamboanga Peninsula">Region IX (Zamboanga Peninsula)</option>
                                <option value="Region X - Northern Mindanao">Region X (Northern Mindanao)</option>
                                <option value="Region XI - Davao Region">Region XI (Davao Region)</option>
                                <option value="Region XII - SOCCSKSARGEN">Region XII (SOCCSKSARGEN)</option>
                                <option value="Region XIII - Caraga">Region XIII (Caraga)</option>
                                <option value="BARMM">Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)</option>
                            </select>
                        </div>
                    </div>
                    <div class="fg">
                        <label>Province</label>
                        <div class="input-wrap"><i class="fas fa-city"></i>
                            <select id="modal_province" name="province">
                                <option value="">Select Province</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="auth-block" style="margin-bottom:4px;">
                    <div class="auth-label"><i class="fas fa-shield-halved"></i> Authorize with Your Password</div>
                    <div class="input-wrap"><i class="fas fa-key"></i><input type="password" name="current_admin_password" placeholder="Your current admin password" required></div>
                </div>

            </form>
        </div>

        <div class="modal-footer">
            <button type="submit" form="addAdminForm" name="add_admin" class="btn-modal-submit">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
            <button type="button" class="btn-modal-cancel" onclick="toggleModal()">
                Cancel
            </button>
        </div>

    </div>
</div>

<script>
/* ── REGION / PROVINCE DATA ── */
const regionData = {
    "National Capital Region (NCR)":       ["Manila","Quezon City","Makati","Pasig","Taguig","Parañaque","Pasay","Mandaluyong","San Juan","Marikina","Caloocan","Malabon","Navotas","Valenzuela","Las Piñas","Muntinlupa"],
    "Cordillera Administrative Region (CAR)": ["Abra","Apayao","Benguet","Ifugao","Kalinga","Mountain Province"],
    "Region I - Ilocos Region":            ["Ilocos Norte","Ilocos Sur","La Union","Pangasinan"],
    "Region II - Cagayan Valley":          ["Cagayan","Isabela","Nueva Vizcaya","Quirino"],
    "Region III - Central Luzon":          ["Aurora","Bataan","Bulacan","Nueva Ecija","Pampanga","Tarlac","Zambales"],
    "Region IV-A - CALABARZON":            ["Batangas","Cavite","Laguna","Quezon","Rizal"],
    "Region IV-B - MIMAROPA":              ["Marinduque","Occidental Mindoro","Oriental Mindoro","Palawan","Romblon"],
    "Region V - Bicol Region":             ["Albay","Camarines Norte","Camarines Sur","Catanduanes","Masbate","Sorsogon"],
    "Region VI - Western Visayas":         ["Aklan","Antique","Capiz","Guimaras","Iloilo","Negros Occidental"],
    "Region VII - Central Visayas":        ["Bohol","Cebu","Negros Oriental","Siquijor"],
    "Region VIII - Eastern Visayas":       ["Biliran","Eastern Samar","Leyte","Northern Samar","Samar","Southern Leyte"],
    "Region IX - Zamboanga Peninsula":     ["Zamboanga del Norte","Zamboanga del Sur","Zamboanga Sibugay"],
    "Region X - Northern Mindanao":        ["Bukidnon","Camiguin","Lanao del Norte","Misamis Occidental","Misamis Oriental"],
    "Region XI - Davao Region":            ["Davao de Oro","Davao del Norte","Davao del Sur","Davao Occidental","Davao Oriental"],
    "Region XII - SOCCSKSARGEN":           ["Cotabato","Sarangani","South Cotabato","Sultan Kudarat"],
    "Region XIII - Caraga":                ["Agusan del Norte","Agusan del Sur","Dinagat Islands","Surigao del Norte","Surigao del Sur"],
    "BARMM":                               ["Basilan","Lanao del Sur","Maguindanao","Sulu","Tawi-Tawi"]
};

function modalRegionChanged() {
    const region = document.getElementById('modal_region').value;
    const prov   = document.getElementById('modal_province');
    prov.innerHTML = '<option value="">Select Province</option>';
    (regionData[region] || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p; opt.textContent = p;
        prov.appendChild(opt);
    });
}

function toggleModal() {
    const modal = document.getElementById('addModal');
    modal.style.display = (modal.style.display === 'flex') ? 'none' : 'flex';
}


document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) toggleModal();
});

/* ── APPROVE / REMOVE ── */
function handleAction(ownerId, email, action) {
    const actionColor = action === 'approve' ? '#10b981' : '#ef4444';
    Swal.fire({
        title: action === 'approve' ? 'Approve Account?' : 'Remove Account?',
        text: `Do you want to ${action} this admin and send a notification to ${email}?`,
        icon: 'warning',
        input: action === 'remove' ? 'password' : null,
        inputPlaceholder: 'Enter your admin password',
        showCancelButton: true,
        confirmButtonColor: actionColor,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Yes, ${action}!`,
        preConfirm: (pass) => {
            if (action === 'remove' && !pass) {
                Swal.showValidationMessage('Password required to remove an account');
            }
            return pass;
        }
    }).then(result => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing…', html: 'Updating record and sending notification.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            $.ajax({
                url: '../functions_admin/approve.php',
                type: 'POST',
                dataType: 'json',
                data: { id: ownerId, action: action, email: email, admin_password: result.value },
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ title: 'Done!', text: response.message, icon: 'success', timer: 2000, showConfirmButton: false })
                            .then(() => {
                                if (action === 'remove') $(`#row-${ownerId}`).fadeOut();
                                else location.reload();
                            });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() { Swal.fire('Error', 'Server communication failed.', 'error'); }
            });
        }
    });
}

/* ── ANIMATED COUNTERS ── */
document.querySelectorAll('.counter').forEach(el => {
    const target = +el.dataset.target;
    if (target === 0) return;
    let current = 0;
    const step  = Math.ceil(target / 40);
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current.toLocaleString();
        if (current >= target) clearInterval(timer);
    }, 25);
});

/* ── URL PARAM ALERTS ── */
window.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.has('success')) {
        const msg = p.get('success') === 'requested'
            ? 'Account request submitted for approval.'
            : 'Administrator account created successfully.';
        Swal.fire({ icon: 'success', title: 'Success', text: msg, timer: 2500, showConfirmButton: false });
    }
    if (p.get('error') === 'wrong_password') Swal.fire({ icon: 'error', title: 'Wrong Password', text: 'Authorization failed. Please try again.' });
    if (p.get('error') === 'exists')         Swal.fire({ icon: 'error', title: 'Already Exists', text: 'Username or email is already in use.' });
    if (p.get('error') === 'db_error')       Swal.fire({ icon: 'error', title: 'Database Error', text: 'Could not create account. Please try again.' });
});

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
