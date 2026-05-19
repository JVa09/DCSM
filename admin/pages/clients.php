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
if (!isset($_SESSION['username'])) $_SESSION['username'] = "Admin";

/* === DATA SCOPING (RBAC) === */
$filter_simple = "1=1";
$filter_alias  = "1=1";

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'superadmin' && !empty($_SESSION['region'])) {
    $admin_region = $conn->real_escape_string($_SESSION['region']);
    $reg_check = $conn->query("SELECT id FROM regions WHERE region_name = '$admin_region' LIMIT 1");
    if ($reg_check && $reg_check->num_rows > 0) {
        $reg_id = $reg_check->fetch_assoc()['id'];
        $filter_simple = "region_id = $reg_id";
        $filter_alias  = "cs.region_id = $reg_id";
    } else {
        $filter_simple = "0=1";
        $filter_alias  = "0=1";
    }
}

/* === FILTERS === */
$search    = isset($_GET['search'])    ? mysqli_real_escape_string($conn, $_GET['search'])    : '';
$type_f    = isset($_GET['type'])      ? mysqli_real_escape_string($conn, $_GET['type'])      : '';
$sex_f     = isset($_GET['sex'])       ? mysqli_real_escape_string($conn, $_GET['sex'])       : '';
$service_f = isset($_GET['service_id'])? mysqli_real_escape_string($conn, $_GET['service_id']): '';
$age_f     = isset($_GET['age_group']) ? mysqli_real_escape_string($conn, $_GET['age_group']) : '';
$region_f  = isset($_GET['region_id']) ? mysqli_real_escape_string($conn, $_GET['region_id']) : '';
$start_d   = isset($_GET['start_date'])? mysqli_real_escape_string($conn, $_GET['start_date']): '';
$end_d     = isset($_GET['end_date'])  ? mysqli_real_escape_string($conn, $_GET['end_date'])  : '';

$conditions = [$filter_alias];
if ($search    != '')               $conditions[] = "cs.control_no LIKE '%$search%'";
if ($type_f    != '')               $conditions[] = "cs.client_type = '$type_f'";
if ($sex_f     != '')               $conditions[] = "cs.sex = '$sex_f'";
if ($service_f != '')               $conditions[] = "cs.service_id = '$service_f'";
if ($region_f  != '')               $conditions[] = "cs.region_id = '$region_f'";
if ($age_f === '18-24')             $conditions[] = "cs.age BETWEEN 18 AND 24";
elseif ($age_f === '25-34')         $conditions[] = "cs.age BETWEEN 25 AND 34";
elseif ($age_f === '35-44')         $conditions[] = "cs.age BETWEEN 35 AND 44";
elseif ($age_f === '45+')           $conditions[] = "cs.age >= 45";
if ($start_d != '')                 $conditions[] = "DATE(cs.created_at) >= '$start_d'";
if ($end_d   != '')                 $conditions[] = "DATE(cs.created_at) <= '$end_d'";
$where_clause = implode(" AND ", $conditions);

/* === STATS === */
$stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN client_type='Citizen'    THEN 1 ELSE 0 END) as citizens,
    SUM(CASE WHEN client_type='Business'   THEN 1 ELSE 0 END) as businesses,
    SUM(CASE WHEN client_type='Government' THEN 1 ELSE 0 END) as government
    FROM csm_submissions cs WHERE $where_clause"));

/* === PAGINATION === */
$limit       = (int)(($conn->query("SELECT setting_value FROM system_settings WHERE setting_key='items_per_page'")->fetch_assoc()['setting_value']) ?? 10);
$page        = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($page - 1) * $limit;
$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM csm_submissions cs WHERE $where_clause"))['total'] ?? 0;
$total_pages = max(1, ceil($total_rows / $limit));

$result = mysqli_query($conn, "
    SELECT cs.*, r.region_name, s.service_name
    FROM csm_submissions cs
    LEFT JOIN regions r ON cs.region_id = r.id
    LEFT JOIN services s ON cs.service_id = s.id
    WHERE $where_clause
    ORDER BY cs.created_at DESC
    LIMIT $limit OFFSET $offset
");

$regions_list  = mysqli_query($conn, "SELECT id, region_name FROM regions ORDER BY region_name ASC");
$services_list = mysqli_query($conn, "SELECT id, service_name FROM services ORDER BY service_name ASC");

$qp = $_GET; unset($qp['page']);
$qs = ($q = http_build_query($qp)) ? '&' . $q : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        
        :root {
            --navy:      #002147;
            --navy-mid:  #003366;
            --blue:      #0038A8;
            --gold:      #FCD116;
            --bg:        #eef1f8;
            --surface:   #ffffff;
            --border:    #e2e8f4;
            --text:      #0f172a;
            --muted:     #64748b;
            --faint:     #94a3b8;
            --success:   #10b981;
            --danger:    #ef4444;

            --g-blue:   linear-gradient(135deg,#3b82f6,#6366f1);
            --g-amber:  linear-gradient(135deg,#f59e0b,#ef4444);
            --g-teal:   linear-gradient(135deg,#10b981,#06b6d4);
            --g-violet: linear-gradient(135deg,#8b5cf6,#ec4899);

            --r-sm: 10px; --r-md: 14px; --r-lg: 20px;
            --sh-sm: 0 2px 8px rgba(0,0,0,.07);
            --sh-lg: 0 16px 40px rgba(0,0,0,.13);

            --sidebar-w: 260px;
            --header-h:  80px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { scrollbar-width:none; -ms-overflow-style:none; }
        ::-webkit-scrollbar { display:none; }

        body {
            font-family:'Inter','Segoe UI',system-ui,sans-serif;
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
            display:none; position:fixed; top:20px; left:18px; z-index:1300;
            background:transparent; border:none; color:#fff;
            font-size:1.35rem; cursor:pointer; padding:4px 8px;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:28px; gap:16px; flex-wrap:wrap;
        }
        .page-header h1 {
            font-size:1.55rem; font-weight:800; color:var(--navy); letter-spacing:-.5px;
        }
        .page-header p { font-size:.875rem; color:var(--muted); margin-top:3px; }

        /* ── SECTION LABEL ── */
        .section-label {
            font-size:.7rem; font-weight:800; text-transform:uppercase;
            letter-spacing:1.2px; color:var(--muted); margin-bottom:14px;
            display:flex; align-items:center; gap:8px;
        }
        .section-label::after { content:''; flex:1; height:1px; background:var(--border); }

        /* ── STAT CARDS ── */
        .stats-grid {
            display:grid; grid-template-columns:repeat(4,1fr);
            gap:18px; margin-bottom:28px;
        }
        .stat-card {
            background:var(--surface); border-radius:var(--r-lg);
            padding:12px 14px 10px; border:1px solid var(--border);
            box-shadow:var(--sh-sm); position:relative; overflow:hidden;
            transition:transform .25s, box-shadow .25s;
        }
        .stat-card:hover { transform:translateY(-4px); box-shadow:var(--sh-lg); }
        .stat-card::before {
            content:''; position:absolute; top:0; left:0; right:0; height:4px;
            border-radius:var(--r-lg) var(--r-lg) 0 0;
        }
        .stat-card.s-blue::before   { background:var(--g-blue); }
        .stat-card.s-teal::before   { background:var(--g-teal); }
        .stat-card.s-amber::before  { background:var(--g-amber); }
        .stat-card.s-violet::before { background:var(--g-violet); }

        .stat-card .blob {
            position:absolute; width:100px; height:100px; border-radius:50%;
            right:-26px; bottom:-26px; opacity:.07;
        }
        .s-blue  .blob { background:var(--g-blue); }
        .s-teal  .blob { background:var(--g-teal); }
        .s-amber .blob { background:var(--g-amber); }
        .s-violet .blob { background:var(--g-violet); }

        .stat-icon-wrap {
            width:30px; height:30px; border-radius:var(--r-sm);
            display:flex; align-items:center; justify-content:center;
            font-size:0.8rem; color:#fff; margin-bottom:8px;
        }
        .s-blue   .stat-icon-wrap { background:var(--g-blue); }
        .s-teal   .stat-icon-wrap { background:var(--g-teal); }
        .s-amber  .stat-icon-wrap { background:var(--g-amber); }
        .s-violet .stat-icon-wrap { background:var(--g-violet); }

        .stat-value { font-size:1.5rem; font-weight:900; color:var(--navy); line-height:1; letter-spacing:-.5px; }
        .stat-label { font-size:.65rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; margin-top:3px; }

        /* ── FILTER CARD ── */
        .filter-card {
            background:var(--surface); border-radius:var(--r-lg);
            border:1px solid var(--border); box-shadow:var(--sh-sm);
            margin-bottom:28px; overflow:hidden;
        }
        .filter-card-head {
            padding:16px 22px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:10px;
        }
        .fch-icon {
            width:32px; height:32px; border-radius:var(--r-sm);
            background:var(--g-blue); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:.85rem; flex-shrink:0;
        }
        .filter-card-head h3 { font-size:.9rem; font-weight:700; color:var(--navy); }
        .filter-card-body { padding:20px 22px; }

        .filter-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
            gap:14px; align-items:flex-end;
        }
        .filter-group { display:flex; flex-direction:column; gap:5px; }
        .filter-group label {
            font-size:.67rem; font-weight:700; color:var(--muted);
            text-transform:uppercase; letter-spacing:.7px;
        }
        .filter-group select,
        .filter-group input[type="text"],
        .filter-group input[type="date"] {
            padding:9px 12px; border:1px solid var(--border);
            border-radius:var(--r-sm); font-size:.85rem;
            font-family:inherit; color:var(--text);
            background:#f8fafc; outline:none;
            transition:border .15s, box-shadow .15s;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            border-color:#6366f1;
            box-shadow:0 0 0 3px rgba(99,102,241,.12);
            background:#fff;
        }
        .btn-reset {
            display:inline-flex; align-items:center; justify-content:center; gap:6px;
            padding:9px 16px; border-radius:var(--r-sm); border:1px solid var(--border);
            background:#f1f5f9; color:var(--muted); font-size:.83rem; font-weight:600;
            font-family:inherit; cursor:pointer; text-decoration:none; transition:all .15s;
        }
        .btn-reset:hover { background:var(--border); color:var(--text); }

        /* ── TABLE CARD ── */
        .table-card {
            background:var(--surface); border-radius:var(--r-lg);
            border:1px solid var(--border); box-shadow:var(--sh-sm); overflow:hidden;
        }
        .table-card-head {
            padding:16px 22px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:12px;
        }
        .tch-icon {
            width:34px; height:34px; border-radius:var(--r-sm); color:#fff;
            display:flex; align-items:center; justify-content:center;
            font-size:.9rem; flex-shrink:0;
            background:linear-gradient(135deg,var(--blue),var(--navy));
        }
        .table-card-head h3 { font-size:.93rem; font-weight:700; color:var(--navy); flex:1; }
        .badge-pill {
            font-size:.7rem; font-weight:700; padding:3px 11px;
            border-radius:20px; background:var(--navy); color:#fff;
        }

        .table-scroll { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:720px; }
        thead tr { background:#f7f9fc; }
        th {
            padding:12px 16px; font-size:.68rem; font-weight:700;
            color:var(--muted); text-transform:uppercase; letter-spacing:.9px;
            border-bottom:1px solid var(--border); white-space:nowrap;
        }
        td {
            padding:13px 16px; font-size:.875rem; color:var(--text);
            border-bottom:1px solid #f1f5f9; vertical-align:middle;
        }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr { transition:background .15s; }
        tbody tr:hover { background:#f8fafd; }

        /* Badges */
        .type-badge {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 11px; border-radius:20px; font-size:.75rem; font-weight:700;
        }
        .citizen    { background:#dbeafe; color:#1d4ed8; }
        .business   { background:#dcfce7; color:#15803d; }
        .government { background:#fef9c3; color:#a16207; }

        .sex-chip {
            display:inline-flex; align-items:center; gap:4px;
            font-size:.8rem; font-weight:600; color:var(--muted);
        }

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
            text-decoration:none; transition:transform .15s, opacity .15s;
        }
        .btn-icon:hover { transform:scale(1.1); opacity:.88; }
        .btn-icon-view   { background:var(--g-blue); }
        .btn-icon-edit   { background:var(--g-amber); }
        .btn-icon-delete { background:linear-gradient(135deg,#ef4444,#dc2626); }

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
        .empty-state .ei {
            width:68px; height:68px; border-radius:50%; background:#f1f5f9;
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 14px; font-size:1.7rem; color:var(--faint);
        }
        .empty-state p { font-size:.9rem; color:var(--muted); }

        
        @media (max-width:1200px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:768px) {
            .burger-btn   { display:block; }
            .main-content { margin-left:0; padding:20px 16px 48px; }
            .stats-grid   { grid-template-columns:1fr; gap:14px; }
            .filter-grid  { grid-template-columns:1fr 1fr; }
            .pag-info     { display:none; }
        }
        @media (max-width:480px) {
            .stat-value  { font-size:1.75rem; }
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
        <div>
            <h1><i class="fas fa-users" style="color:var(--blue);margin-right:8px;"></i>Client Management</h1>
            <p>Browse, filter, and manage all client submissions.</p>
        </div>
    </div>

    <!-- ══ STAT CARDS ══ -->
    <p class="section-label"><i class="fas fa-chart-bar"></i> Client Breakdown</p>
    <div class="stats-grid">
        <div class="stat-card s-blue">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-chart-bar"></i></div>
            <div class="stat-value counter" data-target="<?= $stats['total'] ?? 0 ?>">0</div>
            <div class="stat-label">Total Submissions</div>
        </div>
        <div class="stat-card s-teal">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-user-friends"></i></div>
            <div class="stat-value counter" data-target="<?= $stats['citizens'] ?? 0 ?>">0</div>
            <div class="stat-label">Citizens</div>
        </div>
        <div class="stat-card s-amber">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-building"></i></div>
            <div class="stat-value counter" data-target="<?= $stats['businesses'] ?? 0 ?>">0</div>
            <div class="stat-label">Business</div>
        </div>
        <div class="stat-card s-violet">
            <div class="blob"></div>
            <div class="stat-icon-wrap"><i class="fas fa-university"></i></div>
            <div class="stat-value counter" data-target="<?= $stats['government'] ?? 0 ?>">0</div>
            <div class="stat-label">Government</div>
        </div>
    </div>

    <!-- ══ FILTER CARD ══ -->
    <p class="section-label"><i class="fas fa-filter"></i> Filters</p>
    <div class="filter-card">
        <div class="filter-card-head">
            <div class="fch-icon"><i class="fas fa-sliders-h"></i></div>
            <h3>Filter Clients</h3>
        </div>
        <div class="filter-card-body">
            <form method="GET" id="filterForm" class="filter-grid">
                <div class="filter-group">
                    <label>Control No.</label>
                    <input type="text" id="searchInput" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search…">
                </div>
                <div class="filter-group">
                    <label>Region</label>
                    <select name="region_id" onchange="this.form.submit()">
                        <option value="">All Regions</option>
                        <?php while ($reg = mysqli_fetch_assoc($regions_list)): ?>
                            <option value="<?= $reg['id'] ?>" <?= $region_f == $reg['id'] ? 'selected':'' ?>><?= htmlspecialchars($reg['region_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Client Type</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="Citizen"    <?= $type_f==='Citizen'    ?'selected':'' ?>>Citizen</option>
                        <option value="Business"   <?= $type_f==='Business'   ?'selected':'' ?>>Business</option>
                        <option value="Government" <?= $type_f==='Government' ?'selected':'' ?>>Government</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Service</label>
                    <select name="service_id" onchange="this.form.submit()">
                        <option value="">All Services</option>
                        <?php mysqli_data_seek($services_list, 0); while ($s = mysqli_fetch_assoc($services_list)): ?>
                            <option value="<?= $s['id'] ?>" <?= $service_f==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['service_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Sex</label>
                    <select name="sex" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="Male"   <?= $sex_f==='Male'   ?'selected':'' ?>>Male</option>
                        <option value="Female" <?= $sex_f==='Female' ?'selected':'' ?>>Female</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Age Group</label>
                    <select name="age_group" onchange="this.form.submit()">
                        <option value="">All Ages</option>
                        <option value="18-24" <?= $age_f==='18-24'?'selected':'' ?>>18–24</option>
                        <option value="25-34" <?= $age_f==='25-34'?'selected':'' ?>>25–34</option>
                        <option value="35-44" <?= $age_f==='35-44'?'selected':'' ?>>35–44</option>
                        <option value="45+"   <?= $age_f==='45+'  ?'selected':'' ?>>45+</option>
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
                    <a href="clients.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- ══ TABLE ══ -->
    <p class="section-label"><i class="fas fa-table"></i> Records</p>
    <div class="table-card">
        <div class="table-card-head">
            <div class="tch-icon"><i class="fas fa-users"></i></div>
            <h3>Client Records</h3>
            <span class="badge-pill"><?= number_format($total_rows) ?> total</span>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Client Type</th>
                        <th>Sex</th>
                        <th>Age</th>
                        <th>Region</th>
                        <th>Service</th>
                        <th>Source</th>
                        <th>Avg Rating</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                        $is_phys = (($row['submission_source'] ?? 'Online') === 'Physical');
                        $sqd_sum = floatval($row['sqd1'])+floatval($row['sqd2'])+floatval($row['sqd3'])+
                                   floatval($row['sqd4'])+floatval($row['sqd5'])+floatval($row['sqd6'])+
                                   floatval($row['sqd7'])+floatval($row['sqd8']);
                        if ($is_phys) { $sqd_sum += floatval($row['sqd0']); }
                        $row_avg = round($sqd_sum / ($is_phys ? 9.0 : 8.0), 1);
                        $cls = ($row_avg >= 4.5) ? 'excellent' : (($row_avg >= 3.5) ? 'good' : 'average');
                        $type_cls = strtolower($row['client_type']);
                ?>
                <tr>
                    <td>
                        <span class="type-badge <?= $type_cls ?>">
                            <?php
                                $type_icon = ['citizen'=>'fa-user','business'=>'fa-building','government'=>'fa-university'];
                                echo '<i class="fas '.($type_icon[$type_cls] ?? 'fa-user').'"></i> ';
                                echo htmlspecialchars($row['client_type']);
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="sex-chip">
                            <i class="fas <?= $row['sex']==='Male' ? 'fa-mars' : 'fa-venus' ?>" style="color:<?= $row['sex']==='Male' ? '#3b82f6':'#ec4899'; ?>"></i>
                            <?= htmlspecialchars($row['sex']) ?>
                        </span>
                    </td>
                    <td style="font-size:.82rem;color:var(--muted);font-weight:600;"><?= htmlspecialchars($row['age']) ?></td>
                    <td style="font-size:.85rem;"><?= htmlspecialchars($row['region_name'] ?? 'N/A') ?></td>
                    <td style="font-size:.85rem;"><?= htmlspecialchars($row['service_name'] ?? 'N/A') ?></td>
                    <td>
                        <?php $src = $row['submission_source'] ?? 'Online'; ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.73rem;font-weight:700;
                            <?= $src === 'Physical' ? 'background:#fef9c3;color:#a16207;' : 'background:#dbeafe;color:#1d4ed8;' ?>">
                            <i class="fas <?= $src === 'Physical' ? 'fa-file-alt' : 'fa-globe' ?>" style="font-size:.65rem;"></i>
                            <?= htmlspecialchars($src) ?>
                        </span>
                    </td>
                    <td>
                        <span class="rating-badge <?= $cls ?>">
                            <i class="fas fa-star" style="font-size:.65rem;"></i>
                            <?= number_format($row_avg, 1) ?>
                        </span>
                    </td>
                    <td style="font-size:.82rem;color:var(--muted);"><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-icon btn-icon-view"   title="View"   onclick="viewDetails(<?= $row['id'] ?>)"><i class="fas fa-eye"></i></button>
                            <a      class="btn-icon btn-icon-edit"   title="Edit"   href="../functions_admin/edit_feedback.php?id=<?= $row['id'] ?>"><i class="fas fa-pen"></i></a>
                            <button class="btn-icon btn-icon-delete" title="Delete" onclick="deleteClient(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9">
                    <div class="empty-state">
                        <div class="ei"><i class="fas fa-users"></i></div>
                        <p>No client records match your current filters.</p>
                    </div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_rows > 0): ?>
        <div class="pag-bar">
            <span class="pag-info">
                Showing <?= min($offset + 1, $total_rows) ?>–<?= min($offset + $limit, $total_rows) ?> of <?= number_format($total_rows) ?> entries
            </span>
            <div class="pag-btns">
                <a class="pag-btn <?= $page<=1?'off':'' ?>" href="?page=<?= $page-1 ?><?= $qs ?>"><i class="fas fa-chevron-left"></i></a>
                <?php
                $rs = max(1, $page-2); $re = min($total_pages, $page+2);
                if ($rs > 1) { echo '<a class="pag-btn" href="?page=1'.$qs.'">1</a>'; if ($rs>2) echo '<span class="pag-btn off">…</span>'; }
                for ($i=$rs; $i<=$re; $i++) echo '<a class="pag-btn '.($i===$page?'active':'').'" href="?page='.$i.$qs.'">'.$i.'</a>';
                if ($re < $total_pages) { if ($re<$total_pages-1) echo '<span class="pag-btn off">…</span>'; echo '<a class="pag-btn" href="?page='.$total_pages.$qs.'">'.$total_pages.'</a>'; }
                ?>
                <a class="pag-btn <?= $page>=$total_pages?'off':'' ?>" href="?page=<?= $page+1 ?><?= $qs ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
/* ── COUNTER ANIMATION ── */
document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.dataset.target) || 0;
    if (!target) { el.textContent = '0'; return; }
    const steps = 40, inc = target / steps;
    let cur = 0;
    const tick = () => {
        cur = Math.min(cur + inc, target);
        el.textContent = Math.ceil(cur).toLocaleString();
        if (cur < target) setTimeout(tick, 22);
    };
    tick();
});

/* ── TOASTS ── */
document.addEventListener('DOMContentLoaded', () => {
    const p = new URLSearchParams(window.location.search);
    if (p.get('msg') === 'deleted') {
        Swal.fire({ icon:'success', title:'Deleted!', text:'Record removed successfully.', confirmButtonColor:'#002147', timer:3000, timerProgressBar:true })
            .then(() => window.history.replaceState({}, '', window.location.pathname));
    } else if (p.get('msg') === 'updated') {
        Swal.fire({ icon:'success', title:'Updated!', text:'Changes saved successfully.', confirmButtonColor:'#002147', timer:3000 })
            .then(() => window.history.replaceState({}, '', window.location.pathname));
    }
});

/* ── DELETE ── */
function deleteClient(id) {
    Swal.fire({
        title: 'Delete this client?',
        text: 'This will also remove their associated feedback.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it!'
    }).then(r => { if (r.isConfirmed) window.location.href = '../functions_admin/delete_feedback.php?id=' + id + '&source=clients'; });
}

/* ── VIEW ── */
function viewDetails(id) { window.location.href = 'view_client.php?id=' + id; }

/* ── SEARCH DEBOUNCE ── */
const searchInput = document.getElementById('searchInput');
let debounce;
searchInput.addEventListener('keyup', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => document.getElementById('filterForm').submit(), 500);
});
searchInput.addEventListener('keydown', () => clearTimeout(debounce));
if (searchInput.value) { searchInput.focus(); const v = searchInput.value; searchInput.value=''; searchInput.value=v; }

/* ── SIDEBAR TOGGLE ── */
document.getElementById('menuToggle').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('active');
});
document.querySelectorAll('.sidebar-menu a').forEach(l => {
    l.addEventListener('click', () => {
        if (window.innerWidth <= 768) document.querySelector('.sidebar').classList.remove('active');
    });
});
</script>
</body>
</html>
