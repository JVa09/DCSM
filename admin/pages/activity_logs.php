<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    header('Content-Type: application/json');

    if ($_SESSION['role'] !== 'superadmin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized role.']);
        exit();
    }

    $password = $_POST['password'] ?? '';
    $username = $_SESSION['username'];

    $stmt = $conn->prepare("SELECT password FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $conn->query("TRUNCATE TABLE activity_logs");
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (username, action, module, details, ip_address) VALUES (?, 'delete', 'System', 'Superadmin permanently wiped all historical activity logs.', ?)");
            $log_stmt->bind_param("ss", $username, $ip);
            $log_stmt->execute();
            echo json_encode(['status' => 'success']);
            exit();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
            exit();
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
    exit();
}

/* FILTERS*/
$where_clauses = ["1=1"];
$f_date_from = $_GET['date_from'] ?? '';
$f_date_to   = $_GET['date_to']   ?? '';
$f_action    = $_GET['action']    ?? 'All';
$f_user      = $_GET['user']      ?? '';

if (!empty($f_date_from)) {
    $clean_from = $conn->real_escape_string($f_date_from);
    $where_clauses[] = "DATE(created_at) >= '$clean_from'";
}
if (!empty($f_date_to)) {
    $clean_to = $conn->real_escape_string($f_date_to);
    $where_clauses[] = "DATE(created_at) <= '$clean_to'";
}
if ($f_action !== 'All' && !empty($f_action)) {
    $clean_act = $conn->real_escape_string($f_action);
    $where_clauses[] = "action LIKE '%$clean_act%'";
}
if (!empty($f_user)) {
    $clean_user = $conn->real_escape_string($f_user);
    $where_clauses[] = "username LIKE '%$clean_user%'";
}
$where_sql = implode(" AND ", $where_clauses);

/* CSV EXPORT */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=System_Logs_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['DICT SYSTEM ACTIVITY LOGS']);
    fputcsv($output, ['Generated On:', date('F d, Y - h:i A')]);
    fputcsv($output, ['Exported By:', $_SESSION['username']]);
    fputcsv($output, []);
    fputcsv($output, ['Timestamp', 'User', 'Action', 'Module', 'Details']);
    $export_query = $conn->query("SELECT * FROM activity_logs WHERE $where_sql ORDER BY created_at DESC");
    while ($row = $export_query->fetch_assoc()) {
        fputcsv($output, [
            date('Y-m-d H:i:s', strtotime($row['created_at'])),
            $row['username'] ?? 'System',
            $row['action'],
            $row['module'],
            $row['details']
        ]);
    }
    fclose($output);
    exit();
}

/* STATS */
$stats_total  = $conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE $where_sql")->fetch_assoc()['c'] ?? 0;
$stats_today  = $conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$stats_failed = $conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE action LIKE '%fail%' AND $where_sql")->fetch_assoc()['c'] ?? 0;
$stats_mods   = $conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE action IN ('create', 'update', 'delete', 'insert') AND $where_sql")->fetch_assoc()['c'] ?? 0;


$limit  = (int)(($conn->query("SELECT setting_value FROM system_settings WHERE setting_key='items_per_page'")->fetch_assoc()['setting_value']) ?? 10);
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$count_query   = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE $where_sql");
$total_records = $count_query->fetch_assoc()['total'] ?? 0;
$total_pages   = ceil($total_records / $limit);

$logs_result = $conn->query("SELECT * FROM activity_logs WHERE $where_sql ORDER BY created_at DESC LIMIT $offset, $limit");

$qs_parts = [];
if (!empty($f_date_from))    $qs_parts[] = 'date_from=' . urlencode($f_date_from);
if (!empty($f_date_to))      $qs_parts[] = 'date_to='   . urlencode($f_date_to);
if ($f_action !== 'All')     $qs_parts[] = 'action='    . urlencode($f_action);
if (!empty($f_user))         $qs_parts[] = 'user='      . urlencode($f_user);
$qs_base = implode('&', $qs_parts);

function buildUrl($newPage, $qs_base) {
    $q = $qs_base ? $qs_base . '&page=' . $newPage : 'page=' . $newPage;
    return '?' . $q;
}

$export_link = '?' . ($qs_base ? $qs_base . '&' : '') . 'export=csv';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs | DICT Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>

:root {
    --navy:      #001529;
    --navy-mid:  #002147;
    --blue:      #0038A8;
    --gold:      #FCD116;
    --red-ph:    #CE1126;
    --bg:        #f0f4f8;
    --surface:   #ffffff;
    --border:    #e2e8f0;
    --text:      #1e293b;
    --muted:     #64748b;
    --success:   #10b981;
    --warning:   #f59e0b;
    --danger:    #ef4444;
    --violet:    #8b5cf6;
    --r-sm:  8px;
    --r-md:  12px;
    --r-lg:  16px;
    --r-xl:  20px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.08);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 10px 40px rgba(0,0,0,.12);
    --g-blue:   linear-gradient(135deg, #0038A8 0%, #001529 100%);
    --g-green:  linear-gradient(135deg, #10b981 0%, #059669 100%);
    --g-red:    linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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


.main-content {
    margin-left: var(--sidebar-w);
    padding: calc(var(--header-h) + 32px) 32px 48px;
    width: calc(100% - var(--sidebar-w));
    min-height: 100vh;
    scrollbar-width: none;
}
.main-content::-webkit-scrollbar { display: none; }


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
    gap: 10px;
    font-size: 1.65rem;
    font-weight: 800;
    line-height: 1.2;
}
.page-title-text {
    background: linear-gradient(135deg, var(--navy-mid) 0%, var(--blue) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
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
.page-subtitle { font-size: .8rem; color: var(--muted); font-weight: 500; margin-top: 2px; }
.page-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* ── BUTTONS ── */
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
.btn-green { background: var(--g-green); color: #fff; box-shadow: 0 4px 14px rgba(16,185,129,.35); }
.btn-red   { background: var(--g-red);   color: #fff; box-shadow: 0 4px 14px rgba(239,68,68,.35); }

/* ── STAT CARDS ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.stat-card {
    background: var(--surface);
    border-radius: var(--r-lg);
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
    border-radius: var(--r-lg) var(--r-lg) 0 0;
}
.stat-card .blob {
    position: absolute;
    width: 88px; height: 88px;
    border-radius: 50%;
    right: -22px; bottom: -22px;
    opacity: .07;
}
.s-blue::before   { background: var(--g-blue); }
.s-green::before  { background: var(--g-green); }
.s-red::before    { background: var(--g-red); }
.s-violet::before { background: var(--g-violet); }
.s-blue   .blob { background: var(--g-blue); }
.s-green  .blob { background: var(--g-green); }
.s-red    .blob { background: var(--g-red); }
.s-violet .blob { background: var(--g-violet); }

.stat-icon-box {
    width: 30px; height: 30px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem;
    color: #fff;
    margin-bottom: 8px;
}
.s-blue   .stat-icon-box { background: var(--g-blue); }
.s-green  .stat-icon-box { background: var(--g-green); }
.s-red    .stat-icon-box { background: var(--g-red); }
.s-violet .stat-icon-box { background: var(--g-violet); }

.stat-body { }
.stat-value {
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--navy-mid);
    line-height: 1;
    letter-spacing: -.5px;
}
.stat-label {
    font-size: .65rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .8px;
    margin-top: 3px;
}

/* ── FILTER CARD ── */
.filter-card {
    background: var(--surface);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border);
    margin-bottom: 24px;
    overflow: hidden;
}
.filter-card-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}
.fh-icon {
    width: 32px; height: 32px;
    border-radius: var(--r-sm);
    background: rgba(0,56,168,.08);
    color: var(--blue);
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
}
.filter-card-header h3 { font-size: .92rem; font-weight: 700; color: var(--text); }
.filter-card-body { padding: 20px 24px; }

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    align-items: end;
}
.fg { display: flex; flex-direction: column; gap: 6px; }
.fg label {
    font-size: .7rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .7px;
}
.fg input, .fg select {
    padding: 10px 13px;
    border-radius: var(--r-md);
    border: 1.5px solid var(--border);
    font-size: .85rem;
    font-family: inherit;
    color: var(--text);
    background: #fafbfc;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.fg input:focus, .fg select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,56,168,.08);
    background: #fff;
}
.btn-reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 16px;
    border-radius: var(--r-md);
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--muted);
    font-size: .84rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
    width: 100%;
    font-family: inherit;
}
.btn-reset:hover { border-color: var(--blue); color: var(--blue); background: rgba(0,56,168,.04); }

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
.tch-count { font-size: .8rem; color: var(--muted); font-weight: 500; }

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

/* Timestamp */
.ts-cell { white-space: nowrap; }
.ts-date { font-size: .82rem; font-weight: 600; color: var(--text); }
.ts-time { font-size: .74rem; color: var(--muted); margin-top: 2px; font-family: 'Courier New', monospace; }

/* Username chip */
.user-chip { display: inline-flex; align-items: center; gap: 8px; }
.user-avatar {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--blue), var(--navy));
    color: #fff;
    font-size: .66rem;
    font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
}
.user-name { font-weight: 600; font-size: .84rem; color: var(--text); }

/* Action badges */
.action-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: .71rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}
.ab-login   { background: #d1fae5; color: #065f46; }
.ab-logout  { background: #fee2e2; color: #991b1b; }
.ab-create  { background: #dbeafe; color: #1e40af; }
.ab-update  { background: #fef3c7; color: #92400e; }
.ab-delete  { background: #fee2e2; color: #7f1d1d; }
.ab-export  { background: #ede9fe; color: #4c1d95; }
.ab-view    { background: #ecfdf5; color: #064e3b; }
.ab-default { background: #f1f5f9; color: #475569; }

/* Module pill */
.module-pill {
    display: inline-block;
    padding: 3px 9px;
    background: rgba(0,56,168,.07);
    color: var(--blue);
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 600;
}

/* IP chip */
.ip-chip {
    font-family: 'Courier New', monospace;
    font-size: .76rem;
    color: var(--muted);
    background: #f1f5f9;
    padding: 3px 8px;
    border-radius: 5px;
    white-space: nowrap;
}

/* Details */
.details-text {
    font-size: .82rem;
    color: #4b5563;
    line-height: 1.45;
    max-width: 280px;
}

/* Empty state */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state i { font-size: 2.5rem; color: #cbd5e1; margin-bottom: 14px; display: block; }
.empty-state p { color: var(--muted); font-size: .9rem; font-weight: 500; }

/* ── PAGINATION ── */
.pagination-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 12px;
}
.pag-info { font-size: .82rem; color: var(--muted); font-weight: 500; }
.pag-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.pag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px; height: 36px;
    padding: 0 10px;
    border-radius: var(--r-sm);
    border: 1.5px solid var(--border);
    background: #fff;
    color: var(--text);
    font-size: .82rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .18s;
}
.pag-btn:hover { border-color: var(--blue); color: var(--blue); background: rgba(0,56,168,.04); }
.pag-btn.active { background: var(--g-blue); border-color: transparent; color: #fff; box-shadow: 0 2px 8px rgba(0,56,168,.3); }
.pag-btn.disabled { opacity: .38; pointer-events: none; }
.pag-ellipsis {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px;
    color: var(--muted); font-size: .82rem;
}

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
    .filter-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .page-actions { width: 100%; }
    .btn { flex: 1; justify-content: center; }
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
                <div class="page-title-icon"><i class="fas fa-history"></i></div>
                <span class="page-title-text">System Activity Logs</span>
            </h1>
            <p class="page-subtitle">Full audit trail of all user and system actions</p>
        </div>
        <div class="page-actions">
            <button class="btn btn-green" onclick="exportLogsUI()">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
            <button class="btn btn-red" onclick="clearLogs('<?php echo $_SESSION['role']; ?>')">
                <i class="fas fa-eraser"></i> Clear Logs
            </button>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="stats-grid">
        <div class="stat-card s-blue">
            <div class="stat-icon-box"><i class="fas fa-list-ul"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $stats_total ?>">0</div>
                <div class="stat-label">Filtered Entries</div>
            </div>
            <div class="blob"></div>
        </div>
        <div class="stat-card s-green">
            <div class="stat-icon-box"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $stats_today ?>">0</div>
                <div class="stat-label">Today's Activities</div>
            </div>
            <div class="blob"></div>
        </div>
        <div class="stat-card s-red">
            <div class="stat-icon-box"><i class="fas fa-user-shield"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $stats_failed ?>">0</div>
                <div class="stat-label">Failed Logins</div>
            </div>
            <div class="blob"></div>
        </div>
        <div class="stat-card s-violet">
            <div class="stat-icon-box"><i class="fas fa-database"></i></div>
            <div class="stat-body">
                <div class="stat-value counter" data-target="<?= $stats_mods ?>">0</div>
                <div class="stat-label">System Modifications</div>
            </div>
            <div class="blob"></div>
        </div>
    </div>

    <!-- ── FILTER CARD ── -->
    <div class="filter-card">
        <div class="filter-card-header">
            <div class="fh-icon"><i class="fas fa-filter"></i></div>
            <h3>Filter Logs</h3>
        </div>
        <div class="filter-card-body">
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="fg">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($f_date_from) ?>" onchange="this.form.submit()">
                    </div>
                    <div class="fg">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($f_date_to) ?>" onchange="this.form.submit()">
                    </div>
                    <div class="fg">
                        <label>Action Category</label>
                        <select name="action" onchange="this.form.submit()">
                            <option value="All"    <?= $f_action === 'All'    ? 'selected' : '' ?>>All Actions</option>
                            <option value="login"  <?= $f_action === 'login'  ? 'selected' : '' ?>>Logins</option>
                            <option value="logout" <?= $f_action === 'logout' ? 'selected' : '' ?>>Logouts</option>
                            <option value="create" <?= $f_action === 'create' ? 'selected' : '' ?>>Data Creation</option>
                            <option value="update" <?= $f_action === 'update' ? 'selected' : '' ?>>Data Updates</option>
                            <option value="delete" <?= $f_action === 'delete' ? 'selected' : '' ?>>Deletions</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Search Username</label>
                        <input type="text" name="user" id="searchInput" placeholder="Type username…" value="<?= htmlspecialchars($f_user) ?>">
                    </div>
                    <div class="fg">
                        <label>&nbsp;</label>
                        <a href="activity_logs.php" class="btn-reset"><i class="fas fa-rotate-left"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ── TABLE CARD ── -->
    <div class="table-card">
        <div class="table-card-header">
            <div class="tch-left">
                <div class="th-icon"><i class="fas fa-table-list"></i></div>
                <h3>Detailed Activity Record</h3>
            </div>
            <span class="tch-count"><?= number_format($total_records) ?> total entries</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                        <?php while ($row = $logs_result->fetch_assoc()):
                            $al = strtolower($row['action']);
                            $bc = 'ab-default'; $bi = 'fa-circle-dot';
                            if      (strpos($al, 'login')  !== false) { $bc = 'ab-login';  $bi = 'fa-right-to-bracket'; }
                            elseif  (strpos($al, 'logout') !== false) { $bc = 'ab-logout'; $bi = 'fa-right-from-bracket'; }
                            elseif  (strpos($al, 'create') !== false) { $bc = 'ab-create'; $bi = 'fa-plus'; }
                            elseif  (strpos($al, 'update') !== false) { $bc = 'ab-update'; $bi = 'fa-pen'; }
                            elseif  (strpos($al, 'delete') !== false) { $bc = 'ab-delete'; $bi = 'fa-trash'; }
                            elseif  (strpos($al, 'export') !== false) { $bc = 'ab-export'; $bi = 'fa-file-export'; }
                            elseif  (strpos($al, 'view')   !== false) { $bc = 'ab-view';   $bi = 'fa-eye'; }
                            $uname    = $row['username'] ?? 'System';
                            $initials = strtoupper(substr($uname, 0, 2));
                        ?>
                        <tr>
                            <td class="ts-cell">
                                <div class="ts-date"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                <div class="ts-time"><?= date('h:i:s A', strtotime($row['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="user-chip">
                                    <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                                    <span class="user-name"><?= htmlspecialchars($uname) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="action-badge <?= $bc ?>">
                                    <i class="fas <?= $bi ?>"></i>
                                    <?= htmlspecialchars($row['action']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="module-pill"><?= htmlspecialchars($row['module'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                                <div class="details-text"><?= htmlspecialchars($row['details'] ?? '') ?></div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <p>No activity logs found matching your filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- ── PAGINATION ── -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <span class="pag-info">
                Showing <?= $offset + 1 ?>–<?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> entries
            </span>
            <div class="pag-btns">

                <?php if ($page > 1): ?>
                    <a href="<?= buildUrl($page - 1, $qs_base) ?>" class="pag-btn"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="pag-btn disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                $sp = max(1, $page - 2);
                $ep = min($total_pages, $page + 2);
                if ($sp > 1) {
                    echo '<a href="' . buildUrl(1, $qs_base) . '" class="pag-btn">1</a>';
                    if ($sp > 2) echo '<span class="pag-ellipsis">…</span>';
                }
                for ($i = $sp; $i <= $ep; $i++):
                ?>
                    <a href="<?= buildUrl($i, $qs_base) ?>" class="pag-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php
                if ($ep < $total_pages) {
                    if ($ep < $total_pages - 1) echo '<span class="pag-ellipsis">…</span>';
                    echo '<a href="' . buildUrl($total_pages, $qs_base) . '" class="pag-btn">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= buildUrl($page + 1, $qs_base) ?>" class="pag-btn"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="pag-btn disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div><!-- /table-card -->

</main>

<script>
/* ── SIDEBAR TOGGLE ── */
document.getElementById('menuToggle').onclick = function () {
    document.querySelector('.sidebar').classList.toggle('active');
};
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768) document.querySelector('.sidebar').classList.remove('active');
    });
});

/* ── ANIMATED COUNTERS ── */
document.querySelectorAll('.counter').forEach(el => {
    const target = +el.dataset.target;
    if (target === 0) { el.textContent = '0'; return; }
    let current = 0;
    const step = Math.ceil(target / 40);
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current.toLocaleString();
        if (current >= target) clearInterval(timer);
    }, 25);
});

/* ── DEBOUNCED SEARCH ── */
const searchInput = document.getElementById('searchInput');
let debounce;
searchInput.addEventListener('keyup', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => document.getElementById('filterForm').submit(), 500);
});
searchInput.addEventListener('keydown', () => clearTimeout(debounce));
if (searchInput.value) {
    searchInput.focus();
    const v = searchInput.value; searchInput.value = ''; searchInput.value = v;
}

/* ── EXPORT CSV ── */
function exportLogsUI() {
    const total = <?= $total_records ?>;
    if (total === 0) {
        Swal.fire({ icon: 'info', title: 'No Data', text: 'No logs match the current filters.', confirmButtonColor: '#0038A8' });
        return;
    }
    Swal.fire({
        title: 'Export Activity Logs?',
        html: `Downloading <strong>${total.toLocaleString()}</strong> filtered records as CSV.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-download"></i>&nbsp;Download CSV',
        cancelButtonText: 'Cancel'
    }).then(r => {
        if (r.isConfirmed) {
            Swal.fire({
                title: 'Generating…',
                html: 'Preparing your export file.',
                timer: 1400,
                timerProgressBar: true,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            }).then(() => { window.location.href = '<?= $export_link ?>'; });
        }
    });
}

/* ── CLEAR LOGS (SUPERADMIN ONLY) ── */
function clearLogs(role) {
    if (role !== 'superadmin') {
        Swal.fire({ icon: 'error', title: 'Access Denied', text: 'Only Superadministrators can clear system logs.', confirmButtonColor: '#0038A8' });
        return;
    }
    Swal.fire({
        title: 'Critical Action',
        text: 'You are about to permanently wipe ALL system activity logs. Enter your password to confirm.',
        input: 'password',
        inputPlaceholder: 'Enter your Superadmin password',
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off' },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Verify & Wipe Logs',
        showLoaderOnConfirm: true,
        preConfirm: pwd => {
            if (!pwd) { Swal.showValidationMessage('Please enter your password'); return false; }
            const fd = new FormData();
            fd.append('action', 'clear_logs');
            fd.append('password', pwd);
            return fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (d.status !== 'success') throw new Error(d.message || 'Verification failed'); return d; })
                .catch(e => Swal.showValidationMessage(e.message));
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then(r => {
        if (r.isConfirmed) {
            Swal.fire({ icon: 'success', title: 'Logs Cleared', text: 'All historical activity logs have been securely wiped.' })
                .then(() => location.reload());
        }
    });
}
</script>

</body>
</html>
