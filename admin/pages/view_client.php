<?php
session_start();
require_once "../../functions/db_connection.php";

/* --- AUTH & RBAC --- */
if (!isset($_SESSION['user_id'])) { header("Location: ../../pages/login.php"); exit(); }
$filter_alias = "1=1"; 
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'superadmin' && !empty($_SESSION['region'])) {
    $admin_region = $conn->real_escape_string($_SESSION['region']);
    $reg_check = $conn->query("SELECT id FROM regions WHERE region_name = '$admin_region' LIMIT 1");
    if ($reg_check && $reg_check->num_rows > 0) {
        $reg_id = $reg_check->fetch_assoc()['id'];
        $filter_alias = "cs.region_id = $reg_id";
    } else { $filter_alias = "0=1"; }
}

if (!isset($_GET['id'])) { die("Error: ID missing."); }
$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT cs.*, r.region_name, s.service_name FROM csm_submissions cs LEFT JOIN regions r ON cs.region_id = r.id LEFT JOIN services s ON cs.service_id = s.id WHERE cs.id = ? AND $filter_alias");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if (!$data) { die("Error: Submission not found."); }

/* --- CC MAPPINGS --- */
$cc1_map = [
    1 => "Yes, aware before my transaction with this office",
    2 => "Yes, but aware only when I saw the CC of this office",
    3 => "No, not aware of the CC"
];
$cc2_map = [
    1 => "Yes, the CC was easy to find",
    2 => "Yes, but the CC was hard to find",
    3 => "No, I did not see this office's CC"
];
$cc3_map = [
    1 => "Yes, I was able to use the CC",
    2 => "No, I was not able to use the CC"
];

/* --- MATH --- */
$is_physical = (($data['submission_source'] ?? 'Online') === 'Physical');
$sqd_keys = $is_physical
    ? ['sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8']
    : ['sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8'];
$sqd_sum = 0;
foreach($sqd_keys as $k) { $sqd_sum += (int)$data[$k]; }
$sqd_avg = round($sqd_sum / count($sqd_keys), 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>View Submission | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #002147; --secondary: #0038A8; --accent: #FFD700; --light-bg: #f4f6f9; --card-bg: #ffffff; --text-dark: #1f2937; --text-light: #6b7280; --border: #e5e7eb; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }
        body { background: var(--light-bg); display: flex; min-height: 100vh; padding-top: 70px; }
        .main-content { margin-left: 260px; width: calc(100% - 260px); padding: 40px; }
        
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .badge { background: var(--accent); color: var(--primary); padding: 4px 12px; border-radius: 50px; font-weight: bold; font-size: 0.8rem; }
        
        .grid-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .card { background: white; padding: 25px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 25px; }
        .card h3 { color: var(--primary); margin-bottom: 20px; font-size: 1.1rem; border-left: 4px solid var(--secondary); padding-left: 15px; }
        
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f9f9f9; }
        .info-row span:first-child { color: var(--text-light); font-weight: 500; font-size: 0.95rem; }
        .info-row span:last-child { color: var(--text-dark); font-weight: 600; text-align: right; margin-left: 20px; }
        
        .sqd-list { list-style: none; }
        .sqd-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-bottom: 8px; background: #f8fafc; border-radius: 8px; border: 1px solid #edf2f7; }
        .score-pill { background: var(--primary); color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.9rem; }
        
        .rating-box { text-align: center; background: var(--primary); color: white; padding: 30px; border-radius: 12px; }
        .rating-box h2 { font-size: 3rem; color: var(--accent); margin: 10px 0; }
        
        .btn { padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; transition: 0.3s; }
        .btn-print { background: white; color: var(--primary); border: 1px solid var(--primary); }
        .btn-print:hover { background: #f0f7ff; }
        .btn-back { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <?php include '../component_admin/sidebar.php'; ?>
    <?php include '../component_admin/top_header.php'; ?>

    <main class="main-content">
        <div class="header-flex">
            <div>
                <h1 style="color: var(--primary);">Client Submission</h1>
                <p>Control Number: <strong><?= $data['control_no'] ?></strong></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="../functions_admin/print_submission.php?id=<?= $id ?>" target="_blank" class="btn btn-print"><i class="fa fa-print"></i> Generate ARTA Form</a>
                <a href="clients.php" class="btn btn-back">Return to List</a>
            </div>
        </div>

        <div class="grid-layout">
            <div class="left-col">
                <div class="card">
                    <h3><i class="fa fa-user-circle"></i> Basic Information</h3>
                    <div class="info-row"><span>Submission Date</span><span><?= date("F d, Y", strtotime($data['submission_date'])) ?></span></div>
                    <div class="info-row"><span>Service Availed</span><span class="badge"><?= $data['service_name'] ?></span></div>
                    <div class="info-row"><span>Client Type</span><span><?= $data['client_type'] ?></span></div>
                    <div class="info-row"><span>Region</span><span><?= $data['region_name'] ?></span></div>
                    <div class="info-row"><span>Age / Sex</span><span><?= $data['age'] ?> yrs old / <?= $data['sex'] ?></span></div>
                </div>

                <div class="card">
                    <h3><i class="fa fa-book"></i> Citizen's Charter Responses</h3>
                    <div class="info-row">
                        <span>CC1: Awareness</span>
                        <span><?= $cc1_map[$data['cc1']] ?? 'N/A' ?></span>
                    </div>
                    <div class="info-row">
                        <span>CC2: Visibility</span>
                        <span><?= $cc2_map[$data['cc2']] ?? 'N/A' ?></span>
                    </div>
                    <div class="info-row">
                        <span>CC3: Helpfulness</span>
                        <span><?= $cc3_map[$data['cc3']] ?? 'N/A' ?></span>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fa fa-star"></i> Detailed SQD Scores</h3>
                    <div class="sqd-list">
                        <?php
                        $labels = $is_physical
                            ? ["sqd0"=>"Satisfaction", "sqd1"=>"Responsiveness", "sqd2"=>"Reliability", "sqd3"=>"Access/Facilities", "sqd4"=>"Communication", "sqd5"=>"Costs", "sqd6"=>"Integrity", "sqd7"=>"Assurance", "sqd8"=>"Outcome"]
                            : ["sqd1"=>"Responsiveness", "sqd2"=>"Reliability", "sqd3"=>"Access/Facilities", "sqd4"=>"Communication", "sqd5"=>"Costs", "sqd6"=>"Integrity", "sqd7"=>"Assurance", "sqd8"=>"Outcome"];
                        foreach($labels as $k => $v): ?>
                            <div class="sqd-item">
                                <span><?= $v ?></span>
                                <span class="score-pill"><?= $data[$k] ?> / 5</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="right-col">
                <div class="rating-box">
                    <p>CSM SCORE</p>
                    <h2><?= $sqd_avg ?></h2>
                    <p><?= ($sqd_avg >= 4) ? "Excellent Performance" : "Needs Review" ?></p>
                </div>

                <div class="card" style="margin-top: 25px;">
                    <h3><i class="fa fa-comment"></i> Client Feedback</h3>
                    <p style="font-style: italic; color: #4a5568; line-height: 1.6;">
                        "<?= !empty($data['suggestions']) ? htmlspecialchars($data['suggestions']) : 'No suggestions provided.' ?>"
                    </p>
                </div>
            </div>
        </div>
    </main>
    <script>
    /**
     * Philippine Standard Time (PST) Clock
     */
    function updatePST() {
        const now = new Date();

        const dateStr = now.toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'long', 
            day: 'numeric', 
            year: 'numeric' 
        });
        
        const timeEl = document.getElementById('pst-time');
        const dateEl = document.getElementById('pst-date');
        
        if(timeEl) timeEl.textContent = timeStr;
        if(dateEl) dateEl.textContent = dateStr;
    }
    
    setInterval(updatePST, 1000);
    updatePST();
</script>
</body>
</html>