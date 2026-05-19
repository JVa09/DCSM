<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

/* === AUTHENTICATION CHECK === */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php");
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../unauthorized.php");
    exit();
}
$_SESSION['username'] = $_SESSION['username'] ?? "Admin";

/* === DATA SCOPING (RBAC) === */
$filter_alias = "1=1";
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'superadmin' && !empty($_SESSION['region'])) {
    $admin_region = $conn->real_escape_string($_SESSION['region']);
    $reg_check = $conn->query("SELECT id FROM regions WHERE region_name = '$admin_region' LIMIT 1");
    if ($reg_check && $reg_check->num_rows > 0) {
        $reg_id = $reg_check->fetch_assoc()['id'];
        $filter_alias = "cs.region_id = $reg_id";
    } else {
        $filter_alias = "0=1";
    }
}

/* === FILTERING === */
$search    = isset($_GET['search'])    ? mysqli_real_escape_string($conn, $_GET['search'])    : '';
$type_f    = isset($_GET['type'])      ? mysqli_real_escape_string($conn, $_GET['type'])      : '';
$service_f = isset($_GET['service_id'])? mysqli_real_escape_string($conn, $_GET['service_id']): '';
$region_f  = isset($_GET['region_id']) ? mysqli_real_escape_string($conn, $_GET['region_id']) : '';
$source_f  = isset($_GET['source'])    ? mysqli_real_escape_string($conn, $_GET['source'])    : '';
$start_d   = isset($_GET['start_date'])? mysqli_real_escape_string($conn, $_GET['start_date']): '';
$end_d     = isset($_GET['end_date'])  ? mysqli_real_escape_string($conn, $_GET['end_date'])  : '';

$conditions = [$filter_alias];
if ($search    != '') $conditions[] = "cs.control_no LIKE '%$search%'";
if ($type_f    != '') $conditions[] = "cs.client_type = '$type_f'";
if ($service_f != '') $conditions[] = "cs.service_id = '$service_f'";
if ($region_f  != '') $conditions[] = "cs.region_id = '$region_f'";
if ($source_f  != '') $conditions[] = "cs.submission_source = '$source_f'";
if ($start_d   != '') $conditions[] = "DATE(cs.created_at) >= '$start_d'";
if ($end_d     != '') $conditions[] = "DATE(cs.created_at) <= '$end_d'";
$where_clause = implode(" AND ", $conditions);

/* === SUMMARY STATS === */
$total_feedbacks  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM csm_submissions cs WHERE $where_clause"))['total'] ?? 0;
$avg_rating_raw   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG((IFNULL(sqd0,0)+IFNULL(sqd1,0)+IFNULL(sqd2,0)+IFNULL(sqd3,0)+IFNULL(sqd4,0)+IFNULL(sqd5,0)+IFNULL(sqd6,0)+IFNULL(sqd7,0)+IFNULL(sqd8,0))/9.0) AS avg_rating FROM csm_submissions cs WHERE $where_clause"))['avg_rating'] ?? 0;
$satisfaction_rate = ($total_feedbacks > 0) ? round(($avg_rating_raw / 5) * 100, 1) : 0;
$week_feedbacks   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as w FROM csm_submissions cs WHERE YEARWEEK(cs.created_at,1)=YEARWEEK(CURDATE(),1) AND $where_clause"))['w'] ?? 0;
$with_comments    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM csm_submissions cs WHERE suggestions IS NOT NULL AND TRIM(suggestions)!='' AND $where_clause"))['c'] ?? 0;

/* === CHART DATA === */
$monthly_result = mysqli_query($conn, "SELECT MONTH(created_at) as month, COUNT(*) as total FROM csm_submissions cs WHERE YEAR(created_at)=YEAR(CURDATE()) AND $where_clause GROUP BY MONTH(created_at)");
$monthly_data = array_fill(1, 12, 0);
while ($row = mysqli_fetch_assoc($monthly_result)) { $monthly_data[$row['month']] = (int)$row['total']; }

$sat_dist = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(CASE WHEN ((IFNULL(sqd0,0)+IFNULL(sqd1,0)+IFNULL(sqd2,0)+IFNULL(sqd3,0)+IFNULL(sqd4,0)+IFNULL(sqd5,0)+IFNULL(sqd6,0)+IFNULL(sqd7,0)+IFNULL(sqd8,0))/9.0)>=4 THEN 1 END) AS satisfied,
        COUNT(CASE WHEN ((IFNULL(sqd0,0)+IFNULL(sqd1,0)+IFNULL(sqd2,0)+IFNULL(sqd3,0)+IFNULL(sqd4,0)+IFNULL(sqd5,0)+IFNULL(sqd6,0)+IFNULL(sqd7,0)+IFNULL(sqd8,0))/9.0)< 4 THEN 1 END) AS unsatisfied
    FROM csm_submissions cs WHERE $where_clause
")) ?? ['satisfied' => 0, 'unsatisfied' => 0];

/* === PAGINATION === */
$limit       = (int)(($conn->query("SELECT setting_value FROM system_settings WHERE setting_key='items_per_page'")->fetch_assoc()['setting_value']) ?? 10);
$page        = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($page - 1) * $limit;
$total_pages = max(1, ceil($total_feedbacks / $limit));

$result = mysqli_query($conn, "
    SELECT cs.*, r.region_name, s.service_name
    FROM csm_submissions cs
    LEFT JOIN regions r ON cs.region_id = r.id
    LEFT JOIN services s ON cs.service_id = s.id
    WHERE $where_clause
    ORDER BY cs.created_at DESC
    LIMIT $limit OFFSET $offset
");

$regions_list      = $conn->query("SELECT id, region_name FROM regions ORDER BY region_name ASC");
$services_dropdown = $conn->query("SELECT id, service_name FROM services WHERE is_active = 1");


$qp = $_GET;
unset($qp['page']);
$qs = http_build_query($qp);
$qs = $qs ? '&' . $qs : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedbacks | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
       
        :root {
            --navy:     #002147;
            --navy-mid: #003366;
            --blue:     #0038A8;
            --gold:     #FCD116;
            --bg:       #eef1f8;
            --surface:  #ffffff;
            --border:   #e2e8f4;
            --text:     #0f172a;
            --muted:    #64748b;
            --faint:    #94a3b8;
            --danger:   #ef4444;
            --success:  #10b981;

            --g-blue:  linear-gradient(135deg,#3b82f6,#6366f1);
            --g-amber: linear-gradient(135deg,#f59e0b,#ef4444);
            --g-teal:  linear-gradient(135deg,#10b981,#06b6d4);
            --g-violet:linear-gradient(135deg,#8b5cf6,#ec4899);

            --r-sm: 10px; --r-md: 14px; --r-lg: 20px;
            --sh-xs: 0 1px 3px rgba(0,0,0,.06);
            --sh-sm: 0 2px 8px rgba(0,0,0,.07);
            --sh-md: 0 8px 24px rgba(0,0,0,.09);
            --sh-lg: 0 16px 40px rgba(0,0,0,.13);

            --sidebar-w: 260px;
            --header-h:  80px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { scrollbar-width:none; -ms-overflow-style:none; }
        ::-webkit-scrollbar { display:none; }

        body {
            font-family: 'Inter','Segoe UI',system-ui,sans-serif;
            background: var(--bg);
            color: var(--text);
            padding-top: var(--header-h);
        }

        /* ── LAYOUT ── */
        .main-content {
            margin-left: var(--sidebar-w);
            min-height: calc(100vh - var(--header-h));
            padding: 32px 36px 52px;
            transition: margin .3s;
        }

        /* ── BURGER ── */
        .burger-btn {
            display: none;
            position: fixed; top:20px; left:18px; z-index:1300;
            background: transparent; border: none;
            color: #fff; font-size: 1.35rem; cursor: pointer; padding: 4px 8px;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header-left h1 {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -.5px;
        }
        .page-header-left p {
            font-size: .875rem;
            color: var(--muted);
            margin-top: 3px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--blue), var(--navy));
            color: #fff;
            padding: 10px 20px;
            border-radius: var(--r-sm);
            font-size: .85rem;
            font-weight: 700;
            font-family: inherit;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 12px rgba(0,56,168,.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,56,168,.4); }

        /* ── SECTION LABEL ── */
        .section-label {
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--muted);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-label::after { content:''; flex:1; height:1px; background:var(--border); }

        /* ── STAT CARDS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4,1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border-radius: var(--r-lg);
            padding: 12px 14px 10px;
            border: 1px solid var(--border);
            box-shadow: var(--sh-sm);
            position: relative;
            overflow: hidden;
            transition: transform .25s, box-shadow .25s;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--sh-lg); }

        .stat-card::before {
            content:'';
            position:absolute; top:0; left:0; right:0; height:4px;
            border-radius: var(--r-lg) var(--r-lg) 0 0;
        }
        .stat-card.s-blue::before   { background: var(--g-blue); }
        .stat-card.s-amber::before  { background: var(--g-amber); }
        .stat-card.s-teal::before   { background: var(--g-teal); }
        .stat-card.s-violet::before { background: var(--g-violet); }

        .stat-card .blob {
            position:absolute; width:100px; height:100px; border-radius:50%;
            right:-26px; bottom:-26px; opacity:.07;
        }
        .s-blue  .blob { background: var(--g-blue); }
        .s-amber .blob { background: var(--g-amber); }
        .s-teal  .blob { background: var(--g-teal); }
        .s-violet .blob { background: var(--g-violet); }

        .stat-icon-wrap {
            width: 30px; height: 30px; border-radius: var(--r-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; color: #fff; margin-bottom: 8px;
        }
        .s-blue   .stat-icon-wrap { background: var(--g-blue); }
        .s-amber  .stat-icon-wrap { background: var(--g-amber); }
        .s-teal   .stat-icon-wrap { background: var(--g-teal); }
        .s-violet .stat-icon-wrap { background: var(--g-violet); }

        .stat-value { font-size: 1.5rem; font-weight: 900; color: var(--navy); line-height:1; letter-spacing:-.5px; }
        .stat-label { font-size: .65rem; font-weight:700; color: var(--muted); text-transform:uppercase; letter-spacing:.7px; margin-top:3px; }

        /* ── FILTER CARD ── */
        .filter-card {
            background: var(--surface);
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            box-shadow: var(--sh-sm);
            margin-bottom: 28px;
            overflow: hidden;
        }
        .filter-card-head {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-card-head .fch-icon {
            width: 32px; height: 32px; border-radius: var(--r-sm);
            background: var(--g-blue); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; flex-shrink: 0;
        }
        .filter-card-head h3 { font-size: .9rem; font-weight: 700; color: var(--navy); }
        .filter-card-body { padding: 20px 22px; }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            align-items: flex-end;
        }

        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label {
            font-size: .68rem; font-weight: 700; color: var(--muted);
            text-transform: uppercase; letter-spacing: .7px;
        }
        .filter-group select,
        .filter-group input[type="date"],
        .filter-group input[type="text"] {
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            font-size: .85rem;
            font-family: inherit;
            color: var(--text);
            background: #f8fafc;
            transition: border .15s, box-shadow .15s;
            outline: none;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
            background: #fff;
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: var(--r-sm);
            border: 1px solid var(--border);
            background: #f1f5f9;
            color: var(--muted);
            font-size: .83rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: all .15s;
        }
        .btn-reset:hover { background: var(--border); color: var(--text); }

        /* ── CHART CARDS ── */
        .charts-grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:28px; }

        .card {
            background: var(--surface);
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            box-shadow: var(--sh-sm);
            overflow: hidden;
            display: flex; flex-direction: column;
        }
        .card-head {
            padding: 16px 22px 14px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
        }
        .card-head-icon {
            width: 34px; height: 34px; border-radius: var(--r-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem; color: #fff; flex-shrink: 0;
        }
        .card-head h3 { font-size: .93rem; font-weight: 700; color: var(--navy); }
        .card-body { padding: 18px 22px; flex:1; }
        .chart-wrapper { height: 300px; position: relative; }
        .chart-wrapper canvas { width:100%!important; height:100%!important; }

        /* ── TABLE CARD ── */
        .table-card {
            background: var(--surface);
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            box-shadow: var(--sh-sm);
            overflow: hidden;
        }
        .table-card .card-head { justify-content: space-between; }
        .badge-pill {
            font-size: .7rem; font-weight:700; padding: 3px 11px;
            border-radius: 20px; background: var(--navy); color:#fff; margin-left:auto;
        }

        .table-scroll { overflow-x: auto; }
        table { width:100%; border-collapse:collapse; min-width:700px; }
        thead tr { background: #f7f9fc; }
        th {
            padding: 12px 16px;
            font-size: .68rem; font-weight:700; color: var(--muted);
            text-transform:uppercase; letter-spacing:.9px;
            border-bottom: 1px solid var(--border); white-space:nowrap;
        }
        td { padding:13px 16px; font-size:.875rem; color:var(--text); border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr { transition: background .15s; }
        tbody tr:hover { background:#f8fafd; }

        .ctrl-no {
            font-weight:700; font-family:'Courier New',monospace; font-size:.8rem;
            color: var(--navy); background:#eef2ff; padding:4px 9px; border-radius:6px;
            display:inline-block;
        }
        .source-chip {
            display:inline-flex; align-items:center; gap:5px;
            font-size:.78rem; font-weight:600; padding:4px 10px;
            border-radius:20px;
        }
        .source-online   { background:#dbeafe; color:#1d4ed8; }
        .source-physical { background:#f1f5f9; color:#475569; }

        .rating-badge {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 11px; border-radius:20px; font-size:.78rem; font-weight:700;
        }
        .excellent { background:#dcfce7; color:#15803d; }
        .good      { background:#dbeafe; color:#1d4ed8; }
        .average   { background:#fef9c3; color:#a16207; }

        /* Action buttons */
        .action-btns { display:flex; gap:7px; }
        .btn-icon {
            width:32px; height:32px; border-radius:8px; border:none;
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; color:#fff; font-size:.8rem;
            transition: transform .15s, opacity .15s;
            text-decoration:none;
        }
        .btn-icon:hover { transform:scale(1.1); opacity:.88; }
        .btn-icon-view   { background: var(--g-blue); }
        .btn-icon-edit   { background: var(--g-amber); }
        .btn-icon-delete { background: linear-gradient(135deg,#ef4444,#dc2626); }

        /* ── PAGINATION ── */
        .pag-bar {
            display:flex; align-items:center; justify-content:space-between;
            padding:14px 20px; border-top:1px solid var(--border); background:#f7f9fc;
        }
        .pag-info { font-size:.8rem; color:var(--muted); }
        .pag-btns { display:flex; gap:5px; }
        .pag-btn {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:34px; height:34px; padding:0 8px;
            border-radius:var(--r-sm); border:1px solid var(--border);
            background:var(--surface); color:var(--text);
            font-size:.8rem; font-weight:600; font-family:inherit;
            text-decoration:none; transition:all .15s;
        }
        .pag-btn:hover  { background:var(--navy); color:#fff; border-color:var(--navy); }
        .pag-btn.active { background:var(--navy); color:#fff; border-color:var(--navy); pointer-events:none; }
        .pag-btn.off    { opacity:.3; pointer-events:none; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:52px 20px; }
        .empty-state .ei { width:68px; height:68px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:1.7rem; color:var(--faint); }
        .empty-state p { font-size:.9rem; color:var(--muted); }

        /* ── MODAL ── */
        .modal-overlay {
            display:none;
            position:fixed; inset:0; z-index:10001;
            background:rgba(15,23,42,.55);
            backdrop-filter:blur(4px);
            align-items:center; justify-content:center;
            padding:20px;
        }
        .modal-overlay.open { display:flex; }

        .modal-box {
            background: #0f172a;
            border-radius: 20px;
            width:100%; max-width:720px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0,0,0,.5);
            animation: modalIn .25s ease;
        }
        @keyframes modalIn { from { transform:translateY(-20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

        .modal-head {
            background: linear-gradient(135deg, var(--navy), var(--blue));
            padding: 22px 26px;
            border-radius: 20px 20px 0 0;
            display:flex; align-items:center; justify-content:space-between;
        }
        .modal-head-left h3 { font-size:1rem; font-weight:800; color:#fff; }
        .modal-head-left .modal-ctrl { font-size:.8rem; color:rgba(255,255,255,.65); margin-top:3px; font-family:'Courier New',monospace; }
        .modal-close-btn {
            width:34px; height:34px; border-radius:50%;
            background:rgba(255,255,255,.15); border:none;
            color:#fff; font-size:.95rem; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            transition: background .15s;
        }
        .modal-close-btn:hover { background:rgba(255,255,255,.28); }

        .modal-body { padding:24px 26px; }

        .modal-info-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;
        }
        .modal-info-item label {
            font-size:.65rem; font-weight:800; text-transform:uppercase;
            letter-spacing:.8px; color:#94a3b8; display:block; margin-bottom:4px;
        }
        .modal-info-item span {
            font-size:.875rem; font-weight:600; color:#e2e8f0;
            display:block; background:#1e293b; padding:8px 12px; border-radius:var(--r-sm);
            border:1px solid #334155;
        }

        .modal-section-title {
            font-size:.7rem; font-weight:800; text-transform:uppercase;
            letter-spacing:.9px; color:#64748b; margin-bottom:10px;
            padding-bottom:6px; border-bottom:1px solid #1e293b;
        }

        .cc-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:18px; }
        .cc-box {
            text-align:center; padding:12px 8px; border-radius:var(--r-sm);
            border:1px solid #334155; background:#1e293b;
        }
        .cc-box .cc-key { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#64748b; margin-bottom:4px; }
        .cc-box .cc-val { font-size:.95rem; font-weight:800; }
        .cc-box .cc-val.yes { color: #34d399; }
        .cc-box .cc-val.no  { color: #f87171; }

        .cc-reason-box {
            background:#1c1a09; border:1px solid #44380a; border-radius:var(--r-sm);
            padding:10px 14px; font-size:.82rem; color:#fbbf24; margin-bottom:18px;
            display:none;
        }

        .sqd-grid { display:flex; flex-direction:column; gap:8px; margin-bottom:18px; }
        .sqd-row {
            display:flex; align-items:center; justify-content:space-between;
            padding:9px 14px; border-radius:var(--r-sm);
            background:#1e293b; border:1px solid #334155;
            font-size:.82rem;
        }
        .sqd-row .sqd-name { color:#cbd5e1; font-weight:500; }
        .sqd-row .sqd-score { font-weight:800; color:#e2e8f0; display:flex; align-items:center; gap:4px; }
        .sqd-row .sqd-score .stars { color:#f59e0b; font-size:.65rem; }

        .suggestions-box {
            background:#1e293b; border:1px solid #334155;
            border-radius:var(--r-sm); padding:12px 14px;
            font-size:.875rem; color:#94a3b8; line-height:1.6;
            font-style:italic; margin-bottom:20px;
        }

        .modal-footer {
            display:flex; justify-content:flex-end; gap:10px;
            padding-top:16px; border-top:1px solid #1e293b;
        }
        .btn-secondary {
            display:inline-flex; align-items:center; gap:6px;
            padding:9px 20px; border-radius:var(--r-sm); border:1px solid #334155;
            background:#1e293b; color:#94a3b8; font-size:.83rem;
            font-weight:600; font-family:inherit; cursor:pointer; transition:all .15s;
        }
        .btn-secondary:hover { background:#334155; color:#e2e8f0; }
        .btn-danger {
            display:inline-flex; align-items:center; gap:6px;
            padding:9px 20px; border-radius:var(--r-sm); border:none;
            background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff;
            font-size:.83rem; font-weight:700; font-family:inherit; cursor:pointer;
            transition:transform .15s, box-shadow .15s;
        }
        .btn-danger:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(239,68,68,.35); }

        @media (max-width:1200px) { .stats-grid { grid-template-columns:repeat(2,1fr); } .charts-grid { grid-template-columns:1fr; } }
        @media (max-width:768px)  {
            .burger-btn { display:block; }
            .main-content { margin-left:0; padding:20px 16px 48px; }
            .stats-grid { grid-template-columns:1fr; gap:14px; }
            .filter-grid { grid-template-columns:1fr 1fr; }
            .modal-info-grid { grid-template-columns:1fr; }
            .cc-grid { grid-template-columns:1fr; }
            .pag-info { display:none; }
        }
        @media (max-width:480px) {
            .stat-value { font-size:1.75rem; }
            .filter-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<button class="burger-btn" id="menuToggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<main class="main-content">

    <!-- ══ PAGE HEADER ══ -->
    <div class="page-header">
        <div class="page-header-left">
            <h1><i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;"></i>Feedback Management</h1>
            <p>Review, filter, and manage all client feedback submissions.</p>
        </div>
        <a href="add_client.php" class="btn-primary"><i class="fas fa-plus"></i> Add Entry</a>
    </div>

    <!-- ══ STAT CARDS ══ -->
    <p class="section-label"><i class="fas fa-chart-bar"></i> Summary</p>
    <div class="stats-grid">
        <div class="stat-card s-blue">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-poll-h"></i></div>
            <div class="stat-value"><?= number_format($total_feedbacks) ?></div>
            <div class="stat-label">Total Feedbacks</div>
        </div>
        <div class="stat-card s-teal">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-smile-beam"></i></div>
            <div class="stat-value"><?= number_format($satisfaction_rate, 1) ?>%</div>
            <div class="stat-label">Satisfaction Rate</div>
        </div>
        <div class="stat-card s-violet">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-comment-alt"></i></div>
            <div class="stat-value"><?= number_format($with_comments) ?></div>
            <div class="stat-label">With Comments</div>
        </div>
        <div class="stat-card s-amber">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-value"><?= number_format($week_feedbacks) ?></div>
            <div class="stat-label">This Week</div>
        </div>
    </div>

    <!-- ══ FILTER CARD ══ -->
    <p class="section-label"><i class="fas fa-filter"></i> Filters</p>
    <div class="filter-card">
        <div class="filter-card-head">
            <div class="fch-icon"><i class="fas fa-sliders-h"></i></div>
            <h3>Filter Submissions</h3>
        </div>
        <div class="filter-card-body">
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label>Control No.</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search…" onchange="this.form.submit()">
                </div>
                <div class="filter-group">
                    <label>Region</label>
                    <select name="region_id" onchange="this.form.submit()">
                        <option value="">All Regions</option>
                        <?php while ($reg = $regions_list->fetch_assoc()): ?>
                            <option value="<?= $reg['id'] ?>" <?= $region_f == $reg['id'] ? 'selected' : '' ?>><?= htmlspecialchars($reg['region_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Service</label>
                    <select name="service_id" onchange="this.form.submit()">
                        <option value="">All Services</option>
                        <?php $services_dropdown->data_seek(0); while ($s = $services_dropdown->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" <?= $service_f == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['service_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Source</label>
                    <select name="source" onchange="this.form.submit()">
                        <option value="">All Sources</option>
                        <option value="Online"   <?= $source_f == 'Online'   ? 'selected' : '' ?>>Online</option>
                        <option value="Physical" <?= $source_f == 'Physical' ? 'selected' : '' ?>>Physical</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= $start_d ?>" onchange="this.form.submit()">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?= $end_d ?>" onchange="this.form.submit()">
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <a href="feedbacks.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ CHARTS ══ -->
    <p class="section-label"><i class="fas fa-chart-pie"></i> Analytics</p>
    <div class="charts-grid">
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:var(--g-blue);"><i class="fas fa-chart-area"></i></div>
                <h3>Monthly Feedback Trend (<?= date('Y') ?>)</h3>
            </div>
            <div class="card-body">
                <div class="chart-wrapper"><canvas id="monthlyTrend"></canvas></div>
            </div>
        </div>
        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:var(--g-teal);"><i class="fas fa-chart-pie"></i></div>
                <h3>Overall Satisfaction</h3>
            </div>
            <div class="card-body">
                <div class="chart-wrapper"><canvas id="satisfactionDist"></canvas></div>
            </div>
        </div>
    </div>

    <!-- ══ TABLE ══ -->
    <p class="section-label"><i class="fas fa-table"></i> Records</p>
    <div class="table-card">
        <div class="card-head">
            <div class="card-head-icon" style="background:linear-gradient(135deg,var(--blue),var(--navy));"><i class="fas fa-list-ul"></i></div>
            <h3>Feedback Records</h3>
            <span class="badge-pill"><?= number_format($total_feedbacks) ?> total</span>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Control No.</th>
                        <th>Date</th>
                        <th>Client Type</th>
                        <th>Region</th>
                        <th>Avg Rating</th>
                        <th>Source</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                        $row_avg = ((int)$row['sqd0']+(int)$row['sqd1']+(int)$row['sqd2']+(int)$row['sqd3']+(int)$row['sqd4']+(int)$row['sqd5']+(int)$row['sqd6']+(int)$row['sqd7']+(int)$row['sqd8'])/9.0;
                        $r = round($row_avg, 1);
                        $cls = ($r >= 4.5) ? 'excellent' : (($r >= 3.5) ? 'good' : 'average');
                        $jsonData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td><span class="ctrl-no"><?= htmlspecialchars($row['control_no']) ?></span></td>
                    <td style="color:var(--muted);font-size:.82rem;"><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                    <td><?= htmlspecialchars($row['client_type']) ?></td>
                    <td><?= htmlspecialchars($row['region_name'] ?? 'N/A') ?></td>
                    <td>
                        <span class="rating-badge <?= $cls ?>">
                            <i class="fas fa-star" style="font-size:.65rem;"></i>
                            <?= number_format($r, 1) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['submission_source'] === 'Online'): ?>
                            <span class="source-chip source-online"><i class="fas fa-globe"></i> Online</span>
                        <?php else: ?>
                            <span class="source-chip source-physical"><i class="fas fa-file-alt"></i> Physical</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-icon btn-icon-view"   title="View"   onclick="openModal(<?= $jsonData ?>)"><i class="fas fa-eye"></i></button>
                            <a      class="btn-icon btn-icon-edit"   title="Edit"   href="../functions_admin/edit_feedback.php?id=<?= $row['id'] ?>"><i class="fas fa-edit"></i></a>
                            <button class="btn-icon btn-icon-delete" title="Delete" onclick="deleteEntry(<?= $row['id'] ?>)"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="7">
                    <div class="empty-state">
                        <div class="ei"><i class="fas fa-inbox"></i></div>
                        <p>No feedback records match your current filters.</p>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_feedbacks > 0): ?>
        <div class="pag-bar">
            <span class="pag-info">
                Showing <?= min($offset + 1, $total_feedbacks) ?>–<?= min($offset + $limit, $total_feedbacks) ?> of <?= number_format($total_feedbacks) ?> entries
            </span>
            <div class="pag-btns">
                <a class="pag-btn <?= $page <= 1 ? 'off' : '' ?>" href="?page=<?= $page-1 ?><?= $qs ?>"><i class="fas fa-chevron-left"></i></a>
                <?php
                $range_start = max(1, $page - 2);
                $range_end   = min($total_pages, $page + 2);
                if ($range_start > 1) { echo '<a class="pag-btn" href="?page=1'.$qs.'">1</a>'; if ($range_start > 2) echo '<span class="pag-btn off">…</span>'; }
                for ($i = $range_start; $i <= $range_end; $i++):
                ?>
                    <a class="pag-btn <?= $i === $page ? 'active' : '' ?>" href="?page=<?= $i ?><?= $qs ?>"><?= $i ?></a>
                <?php endfor;
                if ($range_end < $total_pages) { if ($range_end < $total_pages - 1) echo '<span class="pag-btn off">…</span>'; echo '<a class="pag-btn" href="?page='.$total_pages.$qs.'">'.$total_pages.'</a>'; }
                ?>
                <a class="pag-btn <?= $page >= $total_pages ? 'off' : '' ?>" href="?page=<?= $page+1 ?><?= $qs ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>


<div id="viewModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-head-left">
                <h3>Feedback Details</h3>
                <div class="modal-ctrl" id="v_control"></div>
            </div>
            <button class="modal-close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">

            <p class="modal-section-title">Client Information</p>
            <div class="modal-info-grid">
                <div class="modal-info-item"><label>Demographics</label><span id="v_demo"></span></div>
                <div class="modal-info-item"><label>Service Availed</label><span id="v_service"></span></div>
                <div class="modal-info-item"><label>Submission Date</label><span id="v_date"></span></div>
                <div class="modal-info-item"><label>Source</label><span id="v_source"></span></div>
            </div>

            <p class="modal-section-title">Citizen's Charter</p>
            <div class="cc-grid">
                <div class="cc-box"><div class="cc-key">CC1</div><div class="cc-val" id="v_cc1"></div></div>
                <div class="cc-box"><div class="cc-key">CC2</div><div class="cc-val" id="v_cc2"></div></div>
                <div class="cc-box"><div class="cc-key">CC3</div><div class="cc-val" id="v_cc3"></div></div>
            </div>
            <div class="cc-reason-box" id="v_cc_reason"></div>

            <p class="modal-section-title">Quality Dimensions (SQD 0–8)</p>
            <div class="sqd-grid" id="v_sqd_list"></div>

            <p class="modal-section-title">Suggestions / Comments</p>
            <div class="suggestions-box" id="v_suggestions"></div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal()"><i class="fas fa-times"></i> Close</button>
                <button class="btn-danger" id="v_delete_btn" onclick=""><i class="fas fa-trash-alt"></i> Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0-alpha1/js/bootstrap.bundle.min.js"></script>
<script>
/* ── TOASTS ── */
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('msg') === 'deleted') {
    Swal.fire({ icon:'success', title:'Deleted!', text:'Record removed successfully.', timer:2000, showConfirmButton:false });
} else if (urlParams.get('msg') === 'updated') {
    Swal.fire({ icon:'success', title:'Updated!', text:'Changes saved successfully.', timer:2000, showConfirmButton:false });
}

/* ── DELETE ── */
function deleteEntry(id) {
    Swal.fire({
        title: 'Delete this record?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!'
    }).then(r => { if (r.isConfirmed) window.location.href = `../functions_admin/delete_feedback.php?id=${id}`; });
}

/* ── MODAL ── */
const sqdNames = ['Responsiveness','Reliability','Access & Facilities','Communication','Costs','Integrity','Assurance','Outcome','Overall'];

function starStr(score) {
    const s = parseInt(score) || 0;
    return '★'.repeat(s) + '☆'.repeat(Math.max(0, 5 - s));
}

function openModal(data) {
    document.getElementById('viewModal').classList.add('open');
    document.getElementById('v_control').textContent  = data.control_no;
    document.getElementById('v_demo').textContent     = `${data.client_type || 'N/A'}  ·  ${data.sex || 'N/A'}  ·  ${data.age || 'N/A'} yrs`;
    document.getElementById('v_service').textContent  = data.service_name || 'N/A';
    document.getElementById('v_date').textContent     = data.created_at;
    document.getElementById('v_source').textContent   = data.submission_source || 'N/A';

    const setCC = (id, val) => {
        const el = document.getElementById(id);
        const yes = val == 1;
        el.textContent = yes ? 'Yes' : 'No';
        el.className = 'cc-val ' + (yes ? 'yes' : 'no');
    };
    setCC('v_cc1', data.cc1);
    setCC('v_cc2', data.cc2);
    setCC('v_cc3', data.cc3);

    const reasonBox = document.getElementById('v_cc_reason');
    if (data.cc3_reason) {
        reasonBox.textContent = '📌 ' + data.cc3_reason;
        reasonBox.style.display = 'block';
    } else {
        reasonBox.style.display = 'none';
    }

    let sqdHtml = '';
    for (let i = 0; i <= 8; i++) {
        const score = data['sqd' + i];
        sqdHtml += `<div class="sqd-row">
            <span class="sqd-name">SQD${i} — ${sqdNames[i]}</span>
            <span class="sqd-score"><span class="stars">${starStr(score)}</span> ${score}</span>
        </div>`;
    }
    document.getElementById('v_sqd_list').innerHTML = sqdHtml;
    document.getElementById('v_suggestions').textContent = data.suggestions || 'No suggestions provided.';
    document.getElementById('v_delete_btn').onclick = () => { closeModal(); deleteEntry(data.id); };
}

function closeModal() {
    document.getElementById('viewModal').classList.remove('open');
}
document.getElementById('viewModal').addEventListener('click', e => {
    if (e.target === document.getElementById('viewModal')) closeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ── CHARTS ── */
Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.color = '#64748b';
const anim = { duration:1100, easing:'easeOutQuart' };

const tCtx = document.getElementById('monthlyTrend').getContext('2d');
const tGrad = tCtx.createLinearGradient(0,0,0,300);
tGrad.addColorStop(0,'rgba(99,102,241,.22)');
tGrad.addColorStop(1,'rgba(99,102,241,0)');

new Chart(tCtx, {
    type:'line',
    data:{
        labels:['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        datasets:[{
            label:'Submissions',
            data:<?= json_encode(array_values($monthly_data)) ?>,
            borderColor:'#6366f1',
            backgroundColor:tGrad,
            borderWidth:2.5,
            pointBackgroundColor:'#fff',
            pointBorderColor:'#6366f1',
            pointBorderWidth:2.5,
            pointRadius:4, pointHoverRadius:6,
            fill:true, tension:0.45
        }]
    },
    options:{
        animation:anim, responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#0f172a',titleColor:'#fff',bodyColor:'rgba(255,255,255,.75)',padding:10,cornerRadius:8} },
        scales:{
            y:{ beginAtZero:true, grid:{color:'#f1f5f9',drawBorder:false}, ticks:{font:{size:11}} },
            x:{ grid:{display:false}, ticks:{font:{size:11}} }
        }
    }
});

new Chart(document.getElementById('satisfactionDist'), {
    type:'doughnut',
    data:{
        labels:['Satisfied','Unsatisfied'],
        datasets:[{
            data:[<?= (int)$sat_dist['satisfied'] ?>,<?= (int)$sat_dist['unsatisfied'] ?>],
            backgroundColor:['#10b981','#ef4444'],
            borderWidth:3, borderColor:'#fff', hoverOffset:8
        }]
    },
    options:{
        animation:anim, responsive:true, maintainAspectRatio:false,
        cutout:'68%',
        plugins:{
            legend:{position:'bottom',labels:{padding:16,usePointStyle:true,pointStyleWidth:10,font:{size:12}}},
            tooltip:{backgroundColor:'#0f172a',titleColor:'#fff',bodyColor:'rgba(255,255,255,.75)',padding:10,cornerRadius:8}
        }
    }
});

/* ── CLOCK ── */
function updateClock() {
    const now = new Date();
    const t = document.getElementById('pst-time');
    const d = document.getElementById('pst-date');
    if (t) t.textContent = now.toLocaleTimeString('en-US',{hour12:true,hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateClock, 1000);
updateClock();


document.getElementById('menuToggle').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('active');
});
document.querySelectorAll('.sidebar-menu a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 768)
            document.querySelector('.sidebar').classList.remove('active');
    });
});
</script>
</body>
</html>
