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

/* === SUMMARY STATS === */
$total_q         = $conn->query("SELECT COUNT(*) as total FROM csm_submissions WHERE $filter_simple");
$total_feedbacks = $total_q->fetch_assoc()['total'] ?? 0;

$avg_result  = $conn->query("SELECT AVG((IFNULL(sqd0,0)+IFNULL(sqd1,0)+IFNULL(sqd2,0)+IFNULL(sqd3,0)+IFNULL(sqd4,0)+IFNULL(sqd5,0)+IFNULL(sqd6,0)+IFNULL(sqd7,0)+IFNULL(sqd8,0))/9.0) as avg_r FROM csm_submissions WHERE $filter_simple")->fetch_assoc();
$avg_rating  = $avg_result['avg_r'] ? round($avg_result['avg_r'], 1) : 0.0;
$sat_rate    = ($avg_rating > 0) ? round(($avg_rating / 5) * 100, 1) : 0;

/* === CHART DATA === */
$service_data_q = $conn->query("SELECT s.service_name as service, COUNT(cs.id) as count FROM csm_submissions cs LEFT JOIN services s ON cs.service_id = s.id WHERE $filter_alias GROUP BY s.service_name");
$services = []; $service_counts = [];
while ($row = $service_data_q->fetch_assoc()) {
    $services[]       = $row['service'] ?? 'Unknown';
    $service_counts[] = $row['count'];
}

$trend_q = $conn->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count FROM csm_submissions WHERE $filter_simple GROUP BY month ORDER BY MIN(created_at) ASC LIMIT 6");
$months = []; $month_counts = [];
while ($row = $trend_q->fetch_assoc()) {
    $months[]       = $row['month'];
    $month_counts[] = $row['count'];
}

$dist_q = $conn->query("SELECT
    SUM(CASE WHEN ((sqd0+sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/9.0) >= 4.5 THEN 1 ELSE 0 END) as excellent,
    SUM(CASE WHEN ((sqd0+sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/9.0) >= 3.5 AND ((sqd0+sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/9.0) < 4.5 THEN 1 ELSE 0 END) as good,
    SUM(CASE WHEN ((sqd0+sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/9.0) < 3.5 THEN 1 ELSE 0 END) as poor
    FROM csm_submissions WHERE $filter_simple");
$dist = $dist_q->fetch_assoc();

$region_q = $conn->query("SELECT r.region_name as region, COUNT(cs.id) as count FROM csm_submissions cs LEFT JOIN regions r ON cs.region_id = r.id WHERE $filter_alias GROUP BY r.region_name");
$regions = []; $region_counts = [];
while ($row = $region_q->fetch_assoc()) {
    $regions[]       = $row['region'] ?? 'Unknown';
    $region_counts[] = $row['count'];
}

/* === PAGINATION === */
$limit       = 5;
$page        = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset      = ($page - 1) * $limit;
$total_today = $conn->query("SELECT COUNT(*) as total FROM csm_submissions cs WHERE DATE(cs.created_at) = CURDATE() AND $filter_alias")->fetch_assoc()['total'] ?? 0;
$total_pages = max(1, ceil($total_today / $limit));

$table_q = $conn->query("
    SELECT cs.*, s.service_name as service,
    ((IFNULL(cs.sqd0,0)+IFNULL(cs.sqd1,0)+IFNULL(cs.sqd2,0)+IFNULL(cs.sqd3,0)+IFNULL(cs.sqd4,0)+IFNULL(cs.sqd5,0)+IFNULL(cs.sqd6,0)+IFNULL(cs.sqd7,0)+IFNULL(cs.sqd8,0))/9.0) as rating
    FROM csm_submissions cs
    LEFT JOIN services s ON cs.service_id = s.id
    WHERE DATE(cs.created_at) = CURDATE() AND $filter_alias
    ORDER BY cs.created_at DESC
    LIMIT $limit OFFSET $offset
");

$first_name = htmlspecialchars(explode(' ', trim($_SESSION['username']))[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DICT DCSM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
      
        :root {
            --navy:       #002147;
            --navy-mid:   #003366;
            --blue:       #0038A8;
            --gold:       #FCD116;
            --bg:         #eef1f8;
            --surface:    #ffffff;
            --border:     #e2e8f4;
            --text:       #0f172a;
            --muted:      #64748b;
            --faint:      #94a3b8;

         
            --g-blue:   linear-gradient(135deg,#3b82f6,#6366f1);
            --g-amber:  linear-gradient(135deg,#f59e0b,#ef4444);
            --g-teal:   linear-gradient(135deg,#10b981,#06b6d4);

            --r-sm: 10px;
            --r-md: 14px;
            --r-lg: 20px;
            --r-xl: 24px;

            --sh-xs: 0 1px 3px rgba(0,0,0,.06);
            --sh-sm: 0 2px 8px rgba(0,0,0,.07);
            --sh-md: 0 8px 24px rgba(0,0,0,.09);
            --sh-lg: 0 16px 40px rgba(0,0,0,.12);

            --sidebar-w: 260px;
            --header-h:  80px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        html, body {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        ::-webkit-scrollbar { display:none; }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding-top: var(--header-h);
        }

      
        .main-content {
            margin-left: var(--sidebar-w);
            min-height: calc(100vh - var(--header-h));
            padding: 32px 36px 48px;
            transition: margin .3s ease;
        }

        
        .burger-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 18px;
            z-index: 1300;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.35rem;
            cursor: pointer;
            padding: 4px 8px;
            line-height: 1;
        }

        
        .welcome-banner {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 50%, var(--blue) 100%);
            border-radius: var(--r-xl);
            padding: 32px 36px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--sh-lg);
        }

        .welcome-banner::before,
        .welcome-banner::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
            pointer-events: none;
        }
        .welcome-banner::before { width: 280px; height: 280px; top: -80px; right: 120px; }
        .welcome-banner::after  { width: 180px; height: 180px; bottom: -60px; right: 40px; }

        .welcome-text { position: relative; z-index: 1; }
        .welcome-text .greeting {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        .welcome-text .greeting span { color: var(--gold); }
        .welcome-text .sub-greeting {
            margin-top: 6px;
            font-size: 0.9rem;
            color: rgba(255,255,255,.65);
            font-weight: 400;
        }

        .welcome-badge {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,.1);
            border: 1px solid rgba(255,255,255,.15);
            border-radius: var(--r-md);
            padding: 12px 18px;
            backdrop-filter: blur(8px);
            flex-shrink: 0;
        }
        .welcome-badge i { font-size: 1.4rem; color: var(--gold); }
        .welcome-badge-text { text-align: left; }
        .welcome-badge-text .badge-label { font-size: 0.7rem; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .8px; font-weight: 600; }
        .welcome-badge-text .badge-val   { font-size: 1.3rem; font-weight: 800; color: #fff; line-height: 1.2; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
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
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--sh-lg);
        }


        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            border-radius: var(--r-lg) var(--r-lg) 0 0;
        }
        .stat-card.s-blue::before  { background: var(--g-blue); }
        .stat-card.s-amber::before { background: var(--g-amber); }
        .stat-card.s-teal::before  { background: var(--g-teal); }

   
        .stat-card .blob {
            position: absolute;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            right: -22px;
            bottom: -22px;
            opacity: .07;
        }
        .stat-card.s-blue  .blob { background: var(--g-blue); }
        .stat-card.s-amber .blob { background: var(--g-amber); }
        .stat-card.s-teal  .blob { background: var(--g-teal); }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .stat-icon-wrap {
            width: 30px;
            height: 30px;
            border-radius: var(--r-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #fff;
            flex-shrink: 0;
        }
        .stat-card.s-blue  .stat-icon-wrap { background: var(--g-blue); }
        .stat-card.s-amber .stat-icon-wrap { background: var(--g-amber); }
        .stat-card.s-teal  .stat-icon-wrap { background: var(--g-teal); }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--navy);
            line-height: 1;
            letter-spacing: -.5px;
        }
        .stat-label {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .7px;
            margin-top: 3px;
        }

        .section-label {
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--muted);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* CHART CARDS */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .card {
            background: var(--surface);
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            box-shadow: var(--sh-sm);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-head {
            padding: 18px 22px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-head-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--r-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            color: #fff;
        }
        .card-head h3 {
            font-size: 0.93rem;
            font-weight: 700;
            color: var(--navy);
            flex: 1;
        }
        .card-head .card-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .card-body { padding: 20px 22px; flex: 1; }

        .chart-wrapper {
            height: 268px;
            position: relative;
        }
        .chart-wrapper canvas { width: 100% !important; height: 100% !important; }


        .table-card {
            background: var(--surface);
            border-radius: var(--r-lg);
            border: 1px solid var(--border);
            box-shadow: var(--sh-sm);
            overflow: hidden;
        }

        .badge-pill {
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            background: var(--navy);
            color: #fff;
            margin-left: auto;
        }

        .table-scroll { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; min-width: 580px; }

        thead tr { background: #f7f9fc; }

        th {
            padding: 13px 18px;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .9px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        td {
            padding: 14px 18px;
            font-size: 0.875rem;
            color: var(--text);
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tbody tr:last-child td { border-bottom: none; }

        tbody tr {
            transition: background .15s;
        }
        tbody tr:hover { background: #f8fafd; }

        .ctrl-no {
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: var(--navy);
            background: #eef2ff;
            padding: 4px 9px;
            border-radius: 6px;
            display: inline-block;
        }

        .time-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.82rem;
            color: var(--muted);
        }
        .time-chip i { font-size: .7rem; }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .excellent { background: #dcfce7; color: #15803d; }
        .good      { background: #dbeafe; color: #1d4ed8; }
        .average   { background: #fef9c3; color: #a16207; }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--navy);
            color: #fff;
            border: none;
            padding: 7px 15px;
            border-radius: var(--r-sm);
            font-size: 0.78rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            transition: background .2s, transform .2s, box-shadow .2s;
        }
        .btn-view:hover {
            background: var(--blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,56,168,.3);
        }

        /* ────────────────────────────────────────
           PAGINATION
        ──────────────────────────────────────── */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: #f7f9fc;
        }
        .pag-info { font-size: 0.8rem; color: var(--muted); }
        .pag-btns { display: flex; gap: 5px; }
        .pag-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: var(--r-sm);
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all .15s;
            font-family: inherit;
        }
        .pag-btn:hover  { background: var(--navy); color: #fff; border-color: var(--navy); }
        .pag-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); pointer-events: none; }
        .pag-btn.off    { opacity: .3; pointer-events: none; }


        .empty-state {
            text-align: center;
            padding: 52px 20px;
        }
        .empty-state .empty-icon {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.8rem;
            color: var(--faint);
        }
        .empty-state p { font-size: 0.9rem; color: var(--muted); }


        @media (max-width: 1100px) {
            .stats-grid  { grid-template-columns: repeat(2,1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .burger-btn  { display: block; }
            .main-content { margin-left: 0; padding: 20px 16px 40px; }
            .stats-grid  { grid-template-columns: 1fr; gap: 14px; }
            .welcome-banner { padding: 24px 22px; }
            .welcome-text .greeting { font-size: 1.35rem; }
            .welcome-badge { display: none; }
        }

        @media (max-width: 480px) {
            .stat-value { font-size: 1.4rem; }
            .pag-info   { display: none; }
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

    <!-- ══ WELCOME BANNER ══ -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <p class="greeting">Good <?php
                $h = (int)date('H');
                echo $h < 12 ? 'Morning' : ($h < 17 ? 'Afternoon' : 'Evening');
            ?>, <span><?php echo $first_name; ?></span> 👋</p>
            <p class="sub-greeting">Here's what's happening with your feedback system today.</p>
        </div>
        <div class="welcome-badge">
            <i class="fas fa-calendar-check"></i>
            <div class="welcome-badge-text">
                <span class="badge-label">Today's Entries</span>
                <span class="badge-val"><?php echo $total_today; ?></span>
            </div>
        </div>
    </div>

    <!-- ══ STAT CARDS ══ -->
    <p class="section-label"><i class="fas fa-chart-bar"></i> Key Metrics</p>
    <div class="stats-grid">
        <div class="stat-card s-blue">
            <div class="blob"></div>
            <div class="stat-top">
                <div class="stat-icon-wrap"><i class="fas fa-inbox"></i></div>
            </div>
            <div class="stat-value counter" data-target="<?php echo $total_feedbacks; ?>">0</div>
            <div class="stat-label">Total Feedbacks</div>
        </div>

        <div class="stat-card s-amber">
            <div class="blob"></div>
            <div class="stat-top">
                <div class="stat-icon-wrap"><i class="fas fa-star"></i></div>
            </div>
            <div class="stat-value counter" data-type="float" data-target="<?php echo $avg_rating; ?>">0.0</div>
            <div class="stat-label">Average Rating <span style="color:var(--faint);font-weight:500;">(out of 5)</span></div>
        </div>

        <div class="stat-card s-teal">
            <div class="blob"></div>
            <div class="stat-top">
                <div class="stat-icon-wrap"><i class="fas fa-smile-beam"></i></div>
            </div>
            <div class="stat-value counter" data-type="float" data-target="<?php echo $sat_rate; ?>">0</div>
            <div class="stat-label">Satisfaction Rate <span style="color:var(--faint);font-weight:500;">(%)</span></div>
        </div>
    </div>

    <!-- ══ CHARTS ══ -->
    <p class="section-label"><i class="fas fa-chart-pie"></i> Analytics Overview</p>
    <div class="charts-grid">

        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:linear-gradient(135deg,#3b82f6,#6366f1);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Monthly Feedback Trend</h3>
            </div>
            <div class="card-body">
                <div class="chart-wrapper"><canvas id="trendChart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:linear-gradient(135deg,#10b981,#06b6d4);">
                    <i class="fas fa-chart-donut" style="font-size:.8rem;"></i>
                    <i class="fas fa-circle-notch" style="font-size:.8rem;"></i>
                </div>
                <h3>Satisfaction Distribution</h3>
            </div>
            <div class="card-body">
                <div class="chart-wrapper"><canvas id="satChart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:linear-gradient(135deg,#f59e0b,#ef4444);">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h3>Feedback by Service</h3>
            </div>
            <div class="card-body">
                <div class="chart-wrapper"><canvas id="serviceChart"></canvas></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <div class="card-head-icon" style="background:linear-gradient(135deg,#8b5cf6,#ec4899);">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <h3>Feedback by Region</h3>
            </div>
            <div class="card-body">
                <div class="chart-wrapper"><canvas id="regionChart"></canvas></div>
            </div>
        </div>

    </div>


    <p class="section-label"><i class="fas fa-clock"></i> Today's Activity</p>
    <div class="table-card">
        <div class="card-head" style="border-radius:0;">
            <div class="card-head-icon" style="background:linear-gradient(135deg,#0038A8,#002147);">
                <i class="fas fa-list-ul"></i>
            </div>
            <h3>Today's Submissions</h3>
            <span class="badge-pill"><?php echo $total_today; ?> entries</span>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Control No.</th>
                        <th>Service</th>
                        <th>Avg Rating</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($table_q && $table_q->num_rows > 0): ?>
                    <?php while ($row = $table_q->fetch_assoc()):
                        $r   = round($row['rating'], 1);
                        $cls = ($r >= 4.5) ? 'excellent' : (($r >= 3.5) ? 'good' : 'average');
                    ?>
                    <tr>
                        <td>
                            <span class="time-chip">
                                <i class="far fa-clock"></i>
                                <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                            </span>
                        </td>
                        <td><span class="ctrl-no"><?php echo htmlspecialchars($row['control_no']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['service'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="rating-badge <?php echo $cls; ?>">
                                <i class="fas fa-star" style="font-size:.65rem;"></i>
                                <?php echo number_format($r, 1); ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn-view" href="feedbacks.php?id=<?php echo $row['id']; ?>">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                            <p>No submissions recorded for today yet.</p>
                        </div>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_today > 0): ?>
        <div class="pagination-bar">
            <span class="pag-info">
                Showing <?php echo min($offset + 1, $total_today); ?>–<?php echo min($offset + $limit, $total_today); ?> of <?php echo $total_today; ?> entries
            </span>
            <div class="pag-btns">
                <a class="pag-btn <?php echo $page <= 1 ? 'off' : ''; ?>" href="?page=<?php echo $page - 1; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a class="pag-btn <?php echo $i === $page ? 'active' : ''; ?>" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a class="pag-btn <?php echo $page >= $total_pages ? 'off' : ''; ?>" href="?page=<?php echo $page + 1; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0-alpha1/js/bootstrap.bundle.min.js"></script>
<script>
/* ── COUNTERS ── */
document.querySelectorAll('.counter').forEach(el => {
    const target  = parseFloat(el.dataset.target) || 0;
    const isFloat = el.dataset.type === 'float';
    if (!target) { el.textContent = isFloat ? '0.0' : '0'; return; }
    const steps = 45, inc = target / steps;
    let cur = 0;
    const tick = () => {
        cur = Math.min(cur + inc, target);
        el.textContent = isFloat ? cur.toFixed(1) : Math.ceil(cur);
        if (cur < target) setTimeout(tick, 22);
    };
    tick();
});

/* ── CHARTS ── */
Chart.defaults.font.family = "'Inter', 'Segoe UI', system-ui, sans-serif";
Chart.defaults.color = '#64748b';
const anim = { duration: 1100, easing: 'easeOutQuart' };

/* Trend */
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendGrad = trendCtx.createLinearGradient(0, 0, 0, 268);
trendGrad.addColorStop(0,   'rgba(99,102,241,.25)');
trendGrad.addColorStop(1,   'rgba(99,102,241,0)');

new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Submissions',
            data: <?php echo json_encode($month_counts); ?>,
            borderColor: '#6366f1',
            backgroundColor: trendGrad,
            borderWidth: 2.5,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#6366f1',
            pointBorderWidth: 2.5,
            pointRadius: 5,
            pointHoverRadius: 7,
            fill: true,
            tension: 0.45
        }]
    },
    options: {
        animation: anim, responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: 'rgba(255,255,255,.75)', padding: 10, cornerRadius: 8 } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

/* Satisfaction Doughnut */
new Chart(document.getElementById('satChart'), {
    type: 'doughnut',
    data: {
        labels: ['Excellent (≥4.5)', 'Good (3.5–4.4)', 'Needs Improvement (<3.5)'],
        datasets: [{
            data: [<?php echo (int)($dist['excellent']??0); ?>, <?php echo (int)($dist['good']??0); ?>, <?php echo (int)($dist['poor']??0); ?>],
            backgroundColor: ['#10b981','#3b82f6','#f59e0b'],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 10
        }]
    },
    options: {
        animation: anim, responsive: true, maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10, font: { size: 12 } } },
            tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: 'rgba(255,255,255,.75)', padding: 10, cornerRadius: 8 }
        }
    }
});

/* Service Polar */
new Chart(document.getElementById('serviceChart'), {
    type: 'polarArea',
    data: {
        labels: <?php echo json_encode($services); ?>,
        datasets: [{
            data: <?php echo json_encode($service_counts); ?>,
            backgroundColor: ['rgba(59,130,246,.75)','rgba(16,185,129,.75)','rgba(245,158,11,.75)','rgba(139,92,246,.75)','rgba(236,72,153,.75)'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        animation: anim, responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right', labels: { usePointStyle: true, pointStyleWidth: 10, font: { size: 11 }, padding: 14 } },
            tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: 'rgba(255,255,255,.75)', padding: 10, cornerRadius: 8 }
        }
    }
});

/* Region Bar */
const regCtx = document.getElementById('regionChart').getContext('2d');
const regGrad = regCtx.createLinearGradient(0, 0, 0, 268);
regGrad.addColorStop(0, '#0038A8');
regGrad.addColorStop(1, '#002147');

new Chart(regCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($regions); ?>,
        datasets: [{
            label: 'Submissions',
            data: <?php echo json_encode($region_counts); ?>,
            backgroundColor: regGrad,
            hoverBackgroundColor: '#6366f1',
            borderRadius: 7,
            borderSkipped: false,
            barPercentage: 0.55
        }]
    },
    options: {
        animation: anim, responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: 'rgba(255,255,255,.75)', padding: 10, cornerRadius: 8 }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

/* ── CLOCK ── */
function updateClock() {
    const now = new Date();
    const t = document.getElementById('pst-time');
    const d = document.getElementById('pst-date');
    if (t) t.textContent = now.toLocaleTimeString('en-US', { hour12:true, hour:'2-digit', minute:'2-digit', second:'2-digit' });

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

<?php if (!empty($alertScript)) echo $alertScript; ?>
</body>
</html>
