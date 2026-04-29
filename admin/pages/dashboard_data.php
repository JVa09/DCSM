<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

require_once "../../functions/db_connection.php";

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$join   = '';
$where  = 'WHERE 1=1';
if ($_SESSION['role'] !== 'superadmin' && !empty($_SESSION['region'])) {
    $region = $conn->real_escape_string($_SESSION['region']);
    $join   = "LEFT JOIN regions r ON cs.region_id = r.id";
    $where  = "WHERE r.region_name = '$region'";
}

$avg_expr = "(sqd0+sqd1+sqd2+sqd3+sqd4+sqd5+sqd6+sqd7+sqd8)/9";

$total_q         = $conn->query("SELECT COUNT(*) as total FROM csm_submissions cs $join $where");
$total_feedbacks = $total_q ? (int)$total_q->fetch_assoc()['total'] : 0;

$avg_q      = $conn->query("SELECT AVG($avg_expr) as avg_r FROM csm_submissions cs $join $where");
$avg_rating = $avg_q ? round((float)$avg_q->fetch_assoc()['avg_r'], 1) : 0;

$sat_q     = $conn->query("SELECT COUNT(*) as sat FROM csm_submissions cs $join $where AND $avg_expr >= 4");
$sat_count = $sat_q ? (int)$sat_q->fetch_assoc()['sat'] : 0;
$sat_rate  = ($total_feedbacks > 0) ? round(($sat_count / $total_feedbacks) * 100) : 0;

$svc_join  = "LEFT JOIN services sv ON cs.service_id = sv.id" . ($join ? " $join" : '');
$services = $service_counts = [];
$q = $conn->query("SELECT sv.service_name as service, COUNT(*) as count FROM csm_submissions cs $svc_join $where GROUP BY cs.service_id");
if ($q) while ($row = $q->fetch_assoc()) { $services[] = $row['service'] ?? 'Unknown'; $service_counts[] = $row['count']; }

$months = $month_counts = [];
$q = $conn->query("SELECT DATE_FORMAT(submission_date, '%b %Y') as month, COUNT(*) as count FROM csm_submissions cs $join $where GROUP BY month ORDER BY MIN(submission_date) ASC LIMIT 6");
if ($q) while ($row = $q->fetch_assoc()) { $months[] = $row['month']; $month_counts[] = $row['count']; }

$dist_q = $conn->query("SELECT
    SUM(CASE WHEN $avg_expr >= 4.5 THEN 1 ELSE 0 END) as excellent,
    SUM(CASE WHEN $avg_expr >= 3.5 AND $avg_expr < 4.5 THEN 1 ELSE 0 END) as good,
    SUM(CASE WHEN $avg_expr < 3.5 THEN 1 ELSE 0 END) as average
    FROM csm_submissions cs $join $where");
$dist = $dist_q ? $dist_q->fetch_assoc() : ['excellent' => 0, 'good' => 0, 'average' => 0];

$reg_join = "LEFT JOIN regions rg ON cs.region_id = rg.id" . ($join ? " $join" : '');
$regions = $region_counts = [];
$q = $conn->query("SELECT rg.region_name as region, COUNT(*) as count FROM csm_submissions cs $reg_join $where GROUP BY cs.region_id");
if ($q) while ($row = $q->fetch_assoc()) { $regions[] = $row['region'] ?? 'Unknown'; $region_counts[] = $row['count']; }

echo json_encode([
    'summary'      => ['total' => $total_feedbacks, 'avg' => $avg_rating, 'sat' => $sat_rate],
    'service'      => ['labels' => $services, 'data' => $service_counts],
    'trend'        => ['labels' => $months, 'data' => $month_counts],
    'satisfaction' => ['excellent' => $dist['excellent'], 'good' => $dist['good'], 'average' => $dist['average']],
    'region'       => ['labels' => $regions, 'data' => $region_counts]
]);
