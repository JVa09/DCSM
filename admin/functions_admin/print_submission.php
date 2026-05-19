<?php
session_start();
require_once "../../functions/db_connection.php";

/* =========================
    AUTHENTICATION & RBAC
========================= */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php");
    exit();
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("
    SELECT cs.*, r.region_name, s.service_name
    FROM csm_submissions cs
    LEFT JOIN regions r ON cs.region_id = r.id
    LEFT JOIN services s ON cs.service_id = s.id
    WHERE cs.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) { die("Error: Submission not found."); }

$is_physical = ($data['submission_source'] === 'Physical');
$version_label = $is_physical ? '(Physical Version)' : '(Online Version)';

if ($is_physical) {
    $cc_qs = [
        'cc1' => "Which of the following best describes your awareness of a CC?",
        'cc2' => "If aware of CC (answered 1-3 in CC1), would you say that the CC of this office was...?",
        'cc3' => "If aware of CC (answered codes 1-3 in CC1), how much did the CC help you in your transaction?"
    ];
    $cc1_opts = [
        1 => "I know what a CC is and I saw this office's CC.",
        2 => "I know what a CC is but I did NOT see this office's CC.",
        3 => "I learned of the CC only when I saw this office's CC.",
        4 => "I do not know what a CC is and I did not see one in this office. (Answer 'N/A' on CC2 and CC3)"
    ];
    $cc2_opts = [
        1 => "Easy to see",
        2 => "Somewhat easy to see",
        3 => "Difficult to see",
        4 => "Not visible at all",
        0 => "N/A"
    ];
    $cc3_opts = [
        1 => "Helped very much",
        2 => "Somewhat helped",
        3 => "Did not help",
        0 => "N/A"
    ];
    $sqds = [
        "sqd0" => "I am satisfied with the service that I availed.",
        "sqd1" => "I spent a reasonable amount of time for my transaction.",
        "sqd2" => "The office followed the transaction's requirements and steps based on the information provided.",
        "sqd3" => "The steps (including payment) I needed to do for my transaction were easy and simple.",
        "sqd4" => "I easily found information about my transaction from the office or its website.",
        "sqd5" => "I paid a reasonable amount of fees for my transaction.",
        "sqd6" => "I feel the office was fair to everyone, or \"walang palakasan\", during my transaction.",
        "sqd7" => "I was treated courteously by the staff, and (if asked for help) the staff was helpful.",
        "sqd8" => "I got what I needed from the government office, or (if denied) denial of request was sufficiently explained to me."
    ];
    $sqd_instr = "INSTRUCTIONS: For SQD 0-8, please put a check mark (✓) on the column that best corresponds to your answer.";
    $remarks_label = "Suggestions on how we can further improve our services (optional):";
} else {
    $cc_qs = [
        'cc1' => "Do you know about the Citizen's Charter (document of an agency's services and reqs.)?",
        'cc2' => "If Yes to the previous question, did you see this office's Citizen's Charter?",
        'cc3' => "If Yes to the previous question, did you use the Citizen's Charter as a guide for the service/s you availed?"
    ];
    $cc1_opts = [
        1 => "1. Yes, aware before my transaction with this office",
        2 => "2. Yes, but aware only when I saw the CC of this office",
        3 => "3. No, not aware of the CC (Skip questions CC2 and CC3)"
    ];
    $cc2_opts = [
        1 => "1. Yes, the CC was easy to find",
        2 => "2. Yes, but the CC was hard to find",
        3 => "3. No, I did not see this office's CC (Skip question CC3)"
    ];
    $cc3_opts = [
        1 => "1. Yes, I was able to use the CC",
        2 => "2. No, I was not able to use the CC because _______________________"
    ];
    $sqds = [
        "sqd1" => "I spent an acceptable amount of time to complete my transaction. (Responsiveness)",
        "sqd2" => "The office accurately informed and followed the transaction's requirements and steps. (Reliability)",
        "sqd3" => "My online transaction (including steps and payment) was simple and convenient. (Access and Facilities)",
        "sqd4" => "I easily found information about my transaction from the office or its website. (Communication)",
        "sqd5" => "I paid an acceptable amount of fees for my transaction. (Costs)",
        "sqd6" => "I am confident my online transaction was secure. (Integrity)",
        "sqd7" => "The office's online support was available, or (if asked questions) online support was quick to respond. (Assurance)",
        "sqd8" => "I got what I needed from the government office. (Outcome)"
    ];
    $sqd_instr = "INSTRUCTIONS: For SQD 1-8, please put a check mark (✓) on the column that best corresponds to your answer.";
    $remarks_label = "Remarks (optional):";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSM_Print_<?php echo $data['control_no']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @media print {
            @page { size: letter; margin: 0.4in; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Arial", sans-serif; }
        body { background: white; color: black; line-height: 1.2; font-size: 9.5pt; padding: 10px; }

        /* ── HEADER ── */
        .header-top { display: flex; align-items: center; justify-content: center; position: relative; margin-bottom: 5px; }
        .logo-left  { height: 60px; position: absolute; left: 40px; }
        .logo-right { height: 55px; position: absolute; right: 40px; }
        .header-text { text-align: center; border-bottom: 1px solid black; padding-bottom: 5px; width: 65%; margin: 0 auto; }
        .header-text p  { font-size: 8pt; margin-bottom: 1px; }
        .header-text h1 { font-size: 10pt; font-weight: bold; }

        .version-label { font-size: 8.5pt; margin: 3px 20px 0; color: #555; }
        .main-title  { text-align: center; font-weight: bold; font-size: 11pt; margin: 5px 0; }
        .intro-text  { font-size: 8.5pt; margin: 0 20px 10px 20px; text-align: justify; line-height: 1.3; }

        /* ── INPUT AREA ── */
        .input-container { margin: 0 20px 15px 20px; }
        .input-row  { display: flex; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
        .input-item { display: flex; align-items: center; margin-right: 20px; }
        .box {
            width: 13px; height: 13px;
            border: 1px solid black;
            margin: 0 5px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 9pt; font-weight: bold; flex-shrink: 0;
        }
        .box.checked { background: #dcdcdc !important; }
        .underline {
            border-bottom: 1px solid black;
            min-width: 80px; margin-left: 5px;
            padding: 0 3px; font-weight: bold; display: inline-block;
        }

        /* ── SECTION INSTRUCTIONS ── */
        .section-instr {
            font-weight: bold; font-size: 8.5pt;
            margin: 10px 20px 5px 20px;
            border-top: 1px solid black; padding-top: 5px;
        }

        /* ── CC QUESTIONS ── */
        .cc-question { margin: 8px 20px 3px 40px; font-size: 9pt; }
        .cc-options  { margin-left: 60px; margin-bottom: 5px; }
        .cc-opt      { display: flex; align-items: flex-start; margin-bottom: 2px; font-size: 8.5pt; }
        .cc-opt span { margin-left: 5px; }

        /* ── SQD TABLE ── */
        .sqd-table { width: calc(100% - 40px); border-collapse: collapse; margin: 10px auto; border: 1.2px solid black; table-layout: fixed; }
        .sqd-table th,
        .sqd-table td { border: 1px solid black; padding: 4px; text-align: center; vertical-align: middle; overflow: hidden; }
        .sqd-table th { background: #f2f2f2 !important; font-size: 7pt; font-weight: bold; height: 55px; vertical-align: top; padding: 0; }

        .header-content {
            display: flex; flex-direction: column;
            align-items: center; justify-content: flex-start;
            gap: 2px; padding-top: 5px; height: 100%;
        }
        .col-label  { width: 40%; }
        .col-rating { width: 10%; }
        .col-na     { width: 10%; }

        .sqd-text   { text-align: left !important; padding-left: 10px !important; font-size: 8pt !important; line-height: 1.1; }
        .emoji-icon { font-size: 18pt; color: #333; }
        .emoji-label { font-size: 6.5pt; font-weight: bold; display: block; line-height: 1.1; text-align: center; }

        /* Selected answer highlight */
        .circle-select {
            border: 2px solid black; border-radius: 50%;
            padding: 2px 7px;
            background: #dcdcdc !important;
            font-weight: bold; color: black !important;
        }

        /* ── SUGGESTIONS ── */
        .footer-suggest { margin: 15px 20px 5px 20px; font-size: 9pt; font-weight: bold; }
        .line-fill {
            border-bottom: 1px solid black;
            min-height: 30px; margin: 3px 20px;
            padding: 3px 5px; font-size: 9pt; font-style: italic;
        }
    </style>
</head>
<body onload="window.print()">

    <!-- ── HEADER ── -->
    <div class="header-top">
        <img src="../../assets/img/DICTLOGO.png" class="logo-left" alt="DICT Logo">
        <div class="header-text">
            <p>REPUBLIC OF THE PHILIPPINES</p>
            <h1>DEPARTMENT OF INFORMATION AND COMMUNICATIONS TECHNOLOGY</h1>
        </div>
        <img src="../../assets/img/logo-bagong-pilipinas.png" class="logo-right" alt="Bagong Pilipinas">
    </div>

    <p class="version-label"><?= $version_label ?></p>
    <div class="main-title">HELP US SERVE YOU BETTER!</div>
    <div class="intro-text">
        This Client Satisfaction Measurement (CSM) survey aims to track the customer experience of government offices. Your answers will enable this office to provide a better service. Personal information shared will be kept confidential and you always have the option to not answer this form.
    </div>

    <!-- ── INPUT SECTION ── -->
    <div class="input-container">

        <!-- Client type -->
        <div class="input-row">
            <span style="margin-right:10px;">Client type:</span>
            <?php
            $ct_val = strtolower(trim($data['client_type'] ?? ''));
            $ct_map = [
                'citizen'    => 'Citizen',
                'business'   => 'Business',
                'government' => 'Government (Employee or another agency)',
            ];
            foreach ($ct_map as $match => $label):
                $checked = (strpos($ct_val, $match) !== false);
            ?>
            <span class="input-item">
                <div class="box <?= $checked ? 'checked' : '' ?>"><?= $checked ? '✓' : '' ?></div> <?= $label ?>
            </span>
            <?php endforeach; ?>
        </div>

        <!-- Date · Sex · Age -->
        <div class="input-row">
            <span style="margin-right:5px;">Date:</span>
            <span class="underline" style="max-width:150px;">
                <?= !empty($data['created_at']) ? date('m/d/Y', strtotime($data['created_at'])) : '' ?>
            </span>
            <span style="margin-left:30px;">Sex:</span>
            <?php foreach (['Male', 'Female'] as $sex):
                $checked = (strcasecmp($data['sex'] ?? '', $sex) === 0); ?>
            <span class="input-item">
                <div class="box <?= $checked ? 'checked' : '' ?>"><?= $checked ? '✓' : '' ?></div> <?= $sex ?>
            </span>
            <?php endforeach; ?>
            <span style="margin-left:30px;">Age:</span>
            <span class="underline" style="max-width:80px;"><?= htmlspecialchars($data['age'] ?? '') ?></span>
        </div>

        <!-- Region · Service -->
        <div class="input-row">
            <span style="margin-right:5px;">Region of residence:</span>
            <span class="underline" style="max-width:220px;"><?= htmlspecialchars($data['region_name'] ?? '') ?></span>
            <span style="margin-left:20px; margin-right:5px;">Service Availed:</span>
            <span class="underline"><?= htmlspecialchars($data['service_name'] ?? '') ?></span>
        </div>

    </div><!-- /input-container -->

    <!-- ── CITIZEN'S CHARTER ── -->
    <div class="section-instr">INSTRUCTIONS: Check mark (✓) your answer to the Citizen's Charter (CC) questions.</div>

    <?php
    $cc_groups = ['cc1' => $cc1_opts, 'cc2' => $cc2_opts, 'cc3' => $cc3_opts];
    foreach ($cc_groups as $key => $opts):
    ?>
    <div class="cc-question"><strong><?= strtoupper($key) ?>.</strong> <?= $cc_qs[$key] ?></div>
    <div class="cc-options">
        <?php foreach ($opts as $v => $l):
            $sel = ($data[$key] == $v);
        ?>
        <div class="cc-opt">
            <div class="box <?= $sel ? 'checked' : '' ?>"><?= $sel ? '✓' : '' ?></div>
            <span><?= $l ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- ── SQD TABLE ── -->
    <div class="section-instr"><?= $sqd_instr ?></div>

    <table class="sqd-table">
        <thead>
            <tr>
                <th class="col-label"></th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-frown-open emoji-icon"></i>
                        <span class="emoji-label">Strongly Disagree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-frown emoji-icon"></i>
                        <span class="emoji-label">Disagree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-meh emoji-icon"></i>
                        <span class="emoji-label">Neither Agree nor Disagree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-smile emoji-icon"></i>
                        <span class="emoji-label">Agree</span>
                    </div>
                </th>
                <th class="col-rating">
                    <div class="header-content">
                        <i class="fa-regular fa-face-laugh-beam emoji-icon"></i>
                        <span class="emoji-label">Strongly Agree</span>
                    </div>
                </th>
                <?php if ($is_physical): ?>
                <th class="col-na">
                    <div class="header-content">
                        <span class="emoji-label" style="font-size:8.5pt;">N/A</span>
                    </div>
                </th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sqds as $key => $label):
                $val    = isset($data[$key]) ? (int)$data[$key] : 0;
                $is_na  = $is_physical && !in_array($val, [1, 2, 3, 4, 5]);
            ?>
            <tr>
                <td class="sqd-text"><strong><?= strtoupper($key) ?>.</strong> <?= $label ?></td>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <td><span class="<?= ($val === $i) ? 'circle-select' : '' ?>"><?= $i ?></span></td>
                <?php endfor; ?>
                <?php if ($is_physical): ?>
                <td><span class="<?= $is_na ? 'circle-select' : '' ?>">N/A</span></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ── SUGGESTIONS ── -->
    <div class="footer-suggest"><?= $remarks_label ?></div>
    <div class="line-fill">
        <?= !empty($data['suggestions']) ? htmlspecialchars($data['suggestions']) : '' ?>
    </div>

</body>
</html>
