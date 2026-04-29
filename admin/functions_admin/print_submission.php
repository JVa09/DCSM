<?php
session_start();
require_once "../../functions/db_connection.php";

/* =========================
    🔐 AUTHENTICATION & RBAC
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

/* MAPS */
$cc1_opts = [5 => "1. Yes, I was already aware before my transaction with this office", 3 => "2. Yes, but I was only aware when I saw the CC of this office", 1 => "3. No, I was not aware of the CC"];
$cc2_opts = [5 => "1. Yes, the CC was easy to find", 3 => "2. Yes, but the CC was hard to find", 1 => "3. No, I did not see this office's CC"];
$cc3_opts = [5 => "1. Yes, I was able to use the CC as a guide", 1 => "2. No, I was not able to use the CC"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSM_Print_<?php echo $data['control_no']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* PRINT SETTINGS */
        @media print {
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            /* Logos will remain in color because filter: grayscale is NOT applied here */
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Arial", sans-serif; padding: 10mm 15mm; background: white; line-height: 1.2; color: black; }

        /* HEADER - LOGOS IN COLOR */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        .header-table img { height: 60px; display: block; } /* Removed grayscale filter */
        .header-center { text-align: center; }
        .header-center p { font-size: 9pt; margin-bottom: 2px; color: black; }
        .header-center h1 { font-size: 11pt; font-weight: bold; color: black; }
        
        .online-version { font-size: 9pt; margin-bottom: 10px; color: #555; }
        .form-title { text-align: center; font-weight: bold; font-size: 12pt; margin: 10px 0; border-bottom: 1.5px solid black; display: inline-block; width: 100%; padding-bottom: 5px; color: black; }
        .form-intro { font-size: 9pt; margin-bottom: 15px; text-align: left; font-weight: bold; color: black; }

        /* INPUTS */
        .input-row { margin-bottom: 8px; font-size: 10pt; display: flex; gap: 25px; color: black; }
        .underline { border-bottom: 1px solid black; min-width: 120px; display: inline-block; padding: 0 5px; font-weight: bold; }

        /* CC SECTION */
        .instr { font-size: 8.5pt; font-weight: bold; margin: 15px 0 5px 0; color: black; }
        .cc-block { margin-bottom: 10px; }
        .cc-q { font-size: 9.5pt; font-weight: bold; margin-bottom: 3px; display: flex; gap: 10px; color: black; }
        .cc-opt { margin-left: 35px; font-size: 9pt; display: flex; align-items: center; margin-bottom: 2px; color: black; }
        .box { width: 14px; height: 14px; border: 1.5px solid black; margin-right: 8px; text-align: center; line-height: 11px; font-size: 10pt; font-weight: bold; }

        /* SQD TABLE - FORMAL BLACK & WHITE */
        .sqd-main { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1.5px solid black; }
        .sqd-main th, .sqd-main td { border: 1px solid black; padding: 6px; text-align: center; vertical-align: middle; color: black; }
        .sqd-main th { font-size: 8pt; background-color: #f2f2f2 !important; }
        .text-left { text-align: left !important; padding-left: 10px !important; font-size: 8.5pt !important; }
        
        /* FACE ICONS (Font Awesome renders in Black) */
        .face-icon { font-size: 20pt; display: block; margin-bottom: 2px; color: #333 !important; }
        .face-label { font-size: 7pt; font-weight: normal; display: block; color: black; }

        /* HIGHLIGHT SELECTION */
        .circle-select { 
            border: 2px solid black; 
            border-radius: 50%; 
            padding: 2px 8px; 
            background: #dcdcdc !important; 
            font-weight: bold;
            color: black !important;
        }

        .remarks-label { font-size: 9pt; font-weight: bold; margin-top: 15px; color: black; }
        .remarks-content { margin-top: 5px; font-size: 10pt; border-bottom: 1px solid black; min-height: 40px; padding: 5px; font-style: italic; color: black; }
    </style>
</head>
<body onload="window.print()">

    <table class="header-table">
        <tr>
            <td width="15%"><img src="../../assets/img/DICTLOGO.png" alt="DICT Logo"></td>
            <td class="header-center">
                <p>REPUBLIC OF THE PHILIPPINES</p>
                <h1>DEPARTMENT OF INFORMATION AND COMMUNICATIONS TECHNOLOGY</h1>
            </td>
            <td width="15%" style="text-align: right;"><img src="../../assets/img/logo-bagong-pilipinas.png" alt="Bagong Pilipinas"></td>
        </tr>
    </table>

    <p class="online-version">(Online Version)</p>
    <h2 class="form-title">HELP US SERVE YOU BETTER!</h2>
    <p class="form-intro">This short Client Satisfaction Measurement (CSM) survey aims to track the customer experience of government offices. Your answers will enable this office to provide a better service.</p>

    <div class="input-row">
        <div>Age: <span class="underline"><?php echo $data['age']; ?></span></div>
        <div>Sex: <span class="underline"><?php echo $data['sex']; ?></span></div>
        <div>Region: <span class="underline"><?php echo $data['region_name']; ?></span></div>
    </div>
    <div class="input-row"><div>Agency visited: <span class="underline" style="min-width:450px;">DICT - <?php echo $data['region_name']; ?></span></div></div>
    <div class="input-row"><div>Service availed: <span class="underline" style="min-width:450px;"><?php echo $data['service_name']; ?></span></div></div>
    <div class="input-row"><div>Customer type: <span class="underline" style="min-width:200px;"><?php echo $data['client_type']; ?></span></div></div>

    <p class="instr">INSTRUCTIONS: Check mark (✓) your answer to the Citizen's Charter (CC) questions.</p>
    
    <?php 
    $cc_groups = ['cc1' => $cc1_opts, 'cc2' => $cc2_opts, 'cc3' => $cc3_opts];
    $cc_qs = [
        'cc1' => "Do you know about the Citizen's Charter?",
        'cc2' => "If Yes, did you see this office's Citizen's Charter?",
        'cc3' => "If Yes, did you use the Citizen's Charter as a guide?"
    ];
    foreach($cc_groups as $key => $opts): ?>
    <div class="cc-block">
        <div class="cc-q"><span><?php echo strtoupper($key); ?></span> <span><?php echo $cc_qs[$key]; ?></span></div>
        <?php foreach($opts as $v => $l): ?>
            <div class="cc-opt"><span class="box"><?php echo ($data[$key] == $v) ? '✓' : ''; ?></span> <?php echo $l; ?></div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <p class="instr">INSTRUCTIONS: For SQD 1-8, please encircle the number that corresponds to your answer:</p>
    
    <table class="sqd-main">
        <thead>
            <tr>
                <th width="50%"></th>
                <th><i class="fa-regular fa-face-frown-open face-icon"></i><span class="face-label">Strongly<br>Disagree</span></th>
                <th><i class="fa-regular fa-face-frown face-icon"></i><span class="face-label">Disagree</span></th>
                <th><i class="fa-regular fa-face-meh face-icon"></i><span class="face-label">Neither Agree<br>nor Disagree</span></th>
                <th><i class="fa-regular fa-face-smile face-icon"></i><span class="face-label">Agree</span></th>
                <th><i class="fa-regular fa-face-laugh-beam face-icon"></i><span class="face-label">Strongly<br>Agree</span></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sqds = [
                "sqd0"=>"I spent an acceptable amount of time to complete my transaction (Responsiveness)",
                "sqd1"=>"The office accurately informed and followed the transaction’s requirements and steps (Reliability)",
                "sqd2"=>"My online transaction (including steps and payment) was simple and convenient (Access and Facilities)",
                "sqd3"=>"I easily found information about my transaction from the office or its website (Communication)",
                "sqd4"=>"I paid an acceptable amount of fees for my transaction (Costs)",
                "sqd5"=>"I am confident my online transaction was secure (Integrity)",
                "sqd6"=>"The office’s online support was available, or (if asked) online support was quick to respond (Assurance)",
                "sqd7"=>"I got what I needed from the government office (Outcome)"
            ];
            foreach($sqds as $key => $label): ?>
            <tr>
                <td class="text-left"><strong><?php echo strtoupper($key); ?>.</strong> <?php echo $label; ?></td>
                <?php for($i=1; $i<=5; $i++): ?>
                    <td><span class="<?php echo ($data[$key] == $i) ? 'circle-select' : ''; ?>"><?php echo $i; ?></span></td>
                <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="remarks-label">Remarks (optional):</p>
    <div class="remarks-content"><?php echo !empty($data['suggestions']) ? htmlspecialchars($data['suggestions']) : "N/A"; ?></div>

</body>
</html>