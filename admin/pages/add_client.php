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

$submission_source = 'Physical';
$admin_region_name = $_SESSION['region'] ?? '';
$is_superadmin = ($_SESSION['role'] === 'superadmin');

$regions_result  = mysqli_query($conn, "SELECT id, region_name FROM regions WHERE is_active = 1 ORDER BY id ASC");
$services_result = mysqli_query($conn, "SELECT id, service_name FROM services WHERE is_active = 1 ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Entry | DICT Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --navy: #002147;
            --blue: #0038A8;
            --gold: #D4AF37;
            --light: #f0f4f8;
            --border: #e2e8f0;
            --muted: #64748b;
            --success: #10b981;
            --danger: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }

        body {
            background: var(--light);
            min-height: 100vh;
            padding-top: 70px;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 24px;
            transition: margin 0.3s;
        }

        /* ── PAGE TITLE BAR ── */
        .page-title-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .title-icon {
            width: 46px;
            height: 46px;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 18px;
            flex-shrink: 0;
        }

        .title-text h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--navy);
        }

        .title-text p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            background: white;
            color: var(--navy);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            border: 1.5px solid var(--border);
            transition: all 0.2s;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }

        .back-btn:hover {
            background: var(--navy);
            color: white;
            border-color: var(--navy);
            transform: translateY(-1px);
        }

        /* ── SURVEY CARD ── */
        .survey-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 28px rgba(0,33,71,.08);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
        }

        /* ── CARD HEADER / STEP NAV ── */
        .card-header {
            background: linear-gradient(135deg, #001233, var(--navy) 55%, #003580);
            padding: 24px 32px 28px;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -60%;
            left: -40%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,.04) 0%, transparent 60%);
            animation: spin 25s linear infinite;
            pointer-events: none;
        }

        .card-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), rgba(255,255,255,.35), var(--gold));
        }

        @keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } }

        .step-nav {
            display: flex;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .step-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255,255,255,.1);
            border: 2px solid rgba(255,255,255,.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            color: rgba(255,255,255,.45);
            transition: all .35s;
            flex-shrink: 0;
        }

        .step-item.active .step-circle {
            background: var(--gold);
            border-color: var(--gold);
            color: var(--navy);
            box-shadow: 0 0 0 5px rgba(212,175,55,.22);
        }

        .step-item.done .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step-meta .step-num {
            display: block;
            font-size: 10px;
            color: rgba(255,255,255,.4);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .step-meta .step-name {
            display: block;
            font-size: 13px;
            color: rgba(255,255,255,.4);
            font-weight: 700;
        }

        .step-item.active .step-meta .step-num,
        .step-item.active .step-meta .step-name { color: white; }

        .step-item.done .step-meta .step-num,
        .step-item.done .step-meta .step-name { color: rgba(255,255,255,.7); }

        .step-connector {
            flex: 1;
            height: 2px;
            background: rgba(255,255,255,.15);
            margin: 0 12px;
            transition: background .4s;
        }

        .step-connector.done { background: var(--gold); }

        /* ── CARD BODY ── */
        .card-body {
            padding: 32px 36px;
        }

        .step-section {
            display: none;
            animation: stepIn .4s cubic-bezier(.22,1,.36,1);
        }

        .step-section.active { display: block; }

        @keyframes stepIn {
            from { opacity: 0; transform: translateX(18px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* ── SECTION TITLE ── */
        .section-title {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 26px;
            padding-bottom: 18px;
            border-bottom: 2px solid var(--border);
        }

        .section-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 16px;
            flex-shrink: 0;
        }

        .section-title h2 {
            font-size: 17px;
            font-weight: 800;
            color: var(--navy);
        }

        .section-title p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* ── FIELDS ── */
        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .field-grid .full {
            grid-column: 1 / -1;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field > label {
            font-size: 11px;
            font-weight: 700;
            color: var(--navy);
            text-transform: uppercase;
            letter-spacing: .5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .req { color: var(--danger); }

        .field input,
        .field select {
            padding: 11px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: #f8fafc;
            font-size: 14px;
            color: var(--navy);
            outline: none;
            transition: all .25s;
            width: 100%;
        }

        .field input:focus,
        .field select:focus {
            border-color: var(--blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(0,56,168,.08);
        }

        .field input.err,
        .field select.err {
            border-color: var(--danger);
            background: #fef2f2;
            animation: shake .4s;
        }

        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }

        select.locked {
            background: #e5e7eb !important;
            cursor: not-allowed;
            opacity: .75;
            pointer-events: none;
        }

        .locked-note {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--muted);
        }

        /* ── CC QUESTIONS ── */
        .cc-block {
            margin-bottom: 16px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 14px;
            border: 1.5px solid var(--border);
            transition: border-color .2s;
        }

        .cc-block:hover { border-color: #cbd5e1; }

        .cc-block .q-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .cc-opts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .cc-opt input[type="radio"] { display: none; }

        .cc-opt label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border-radius: 8px;
            border: 1.5px solid var(--border);
            background: white;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
            user-select: none;
        }

        .cc-opt.skip-opt label {
            background: #f1f5f9;
            color: var(--muted);
            border-style: dashed;
        }

        .cc-opt input:checked + label {
            border-color: var(--blue);
            background: #eff6ff;
            color: var(--blue);
        }

        .cc-opt.skip-opt input:checked + label {
            border-color: #94a3b8;
            background: #e2e8f0;
            color: var(--muted);
        }

        .cc-opt label:hover {
            border-color: var(--blue);
            background: #f0f7ff;
        }

        /* ── SQD QUESTIONS ── */
        .sqd-block {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            background: #f8fafc;
            border-radius: 14px;
            border: 1.5px solid var(--border);
            margin-bottom: 12px;
            transition: border-color .25s, background .25s;
        }

        .sqd-block:hover { border-color: #cbd5e1; }

        .sqd-block.answered {
            border-color: var(--blue);
            background: #f0f7ff;
        }

        .sqd-q-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
            line-height: 1.5;
        }

        .sqd-rating {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        .sqd-opt input[type="radio"] { display: none; }

        .sqd-opt label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            width: 54px;
            padding: 8px 4px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: white;
            cursor: pointer;
            transition: all .2s;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            user-select: none;
        }

        .sqd-opt label .em {
            font-size: 22px;
            filter: grayscale(55%);
            transition: all .2s;
            line-height: 1;
        }

        .sqd-opt.skip-opt label {
            background: #f1f5f9;
            border-style: dashed;
        }

        .sqd-opt input:checked + label {
            border-color: var(--blue);
            background: #eff6ff;
            color: var(--blue);
        }

        .sqd-opt input:checked + label .em {
            filter: grayscale(0%);
            transform: scale(1.18);
        }

        .sqd-opt.skip-opt input:checked + label {
            border-color: #94a3b8;
            background: #e2e8f0;
            color: var(--muted);
        }

        .sqd-opt label:hover {
            border-color: var(--blue);
            background: #f0f7ff;
        }

        /* ── SUGGESTIONS ── */
        .suggestions-wrap {
            margin-top: 6px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 14px;
            border: 1.5px solid var(--border);
        }

        .suggestions-wrap .q-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .suggestions-wrap textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            background: white;
            font-size: 14px;
            color: var(--navy);
            resize: vertical;
            outline: none;
            transition: all .25s;
            font-family: inherit;
            min-height: 80px;
        }

        .suggestions-wrap textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(0,56,168,.08);
        }

        /* ── CARD FOOTER ── */
        .card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 36px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all .25s;
        }

        .btn-prev {
            background: white;
            color: var(--muted);
            border: 1.5px solid var(--border);
        }

        .btn-prev:hover {
            background: var(--border);
            color: var(--navy);
        }

        .btn-next {
            background: linear-gradient(135deg, var(--blue), var(--navy));
            color: white;
            box-shadow: 0 4px 16px rgba(0,56,168,.28);
        }

        .btn-next:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,56,168,.38);
        }

        .btn-submit {
            background: linear-gradient(135deg, #059669, var(--success));
            color: white;
            box-shadow: 0 4px 16px rgba(16,185,129,.28);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16,185,129,.38);
        }

        .btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none !important;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 70px; width: calc(100% - 70px); padding: 14px; }
            .field-grid { grid-template-columns: 1fr; }
            .sqd-block { grid-template-columns: 1fr; }
            .sqd-rating { flex-wrap: wrap; justify-content: flex-start; }
            .card-body { padding: 20px; }
            .card-footer { padding: 14px 20px; }
            .card-header { padding: 20px; }
            .step-meta { display: none; }
        }
    </style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>
<?php include '../component_admin/top_header.php'; ?>

<main class="main-content">

    <div class="page-title-bar">
        <div class="page-title">
            <div class="title-icon"><i class="fas fa-clipboard-pen"></i></div>
            <div class="title-text">
                <h1>Manual Survey Entry</h1>
                <p>Encode physical CSM survey forms into the system</p>
            </div>
        </div>
        <a href="feedbacks.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Cancel &amp; Return
        </a>
    </div>

    <div class="survey-card">

        <!-- Dark Header / Step Nav -->
        <div class="card-header">
            <div class="step-nav">

                <div class="step-item active" id="stepItem1">
                    <div class="step-circle" id="sc1">1</div>
                    <div class="step-meta">
                        <span class="step-num">Step 1</span>
                        <span class="step-name">Client Info</span>
                    </div>
                </div>

                <div class="step-connector" id="conn1"></div>

                <div class="step-item" id="stepItem2">
                    <div class="step-circle" id="sc2">2</div>
                    <div class="step-meta">
                        <span class="step-num">Step 2</span>
                        <span class="step-name">Citizen's Charter</span>
                    </div>
                </div>

                <div class="step-connector" id="conn2"></div>

                <div class="step-item" id="stepItem3">
                    <div class="step-circle" id="sc3">3</div>
                    <div class="step-meta">
                        <span class="step-num">Step 3</span>
                        <span class="step-name">Service Quality</span>
                    </div>
                </div>

            </div>
        </div>

        <form id="surveyForm" novalidate>
            <input type="hidden" name="submission_source" value="<?php echo $submission_source; ?>">

            <!-- ─── STEP 1: Client Information ─── -->
            <div class="card-body step-section active" id="step1">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-user"></i></div>
                    <div>
                        <h2>Client Information</h2>
                        <p>Fill in available information — blank fields are allowed for physical forms</p>
                    </div>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label><i class="fas fa-hashtag"></i> Age</label>
                        <input type="number" name="age" id="fAge" min="1" max="120" placeholder="e.g. 34">
                    </div>

                    <div class="field">
                        <label><i class="fas fa-venus-mars"></i> Sex</label>
                        <select name="sex" id="fSex">
                            <option value="">Select sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <div class="field">
                        <label><i class="fas fa-map-marker-alt"></i> Region of Residence</label>
                        <select name="region_id" id="regionSelect" <?php echo (!$is_superadmin) ? 'class="locked" tabindex="-1"' : ''; ?>>
                            <option value="">Select region</option>
                            <?php
                            if (mysqli_num_rows($regions_result) > 0) {
                                while ($row = mysqli_fetch_assoc($regions_result)) {
                                    $selected = (!$is_superadmin && $row['region_name'] === $admin_region_name) ? 'selected' : '';
                                    echo "<option value='" . $row['id'] . "' $selected>" . htmlspecialchars($row['region_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <?php if (!$is_superadmin): ?>
                        <div class="locked-note"><i class="fas fa-lock" style="font-size:10px"></i> Locked to your assigned region</div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label><i class="fas fa-id-badge"></i> Client Type</label>
                        <select name="client_type" id="fClientType">
                            <option value="">Select client type</option>
                            <option value="Citizen">Citizen</option>
                            <option value="Business">Business</option>
                            <option value="Government">Government</option>
                        </select>
                    </div>

                    <div class="field full">
                        <label><i class="fas fa-concierge-bell"></i> Service Availed</label>
                        <select name="service_id" id="fService">
                            <option value="">Select service</option>
                            <?php
                            if (mysqli_num_rows($services_result) > 0) {
                                while ($row = mysqli_fetch_assoc($services_result)) {
                                    echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['service_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ─── STEP 2: Citizen's Charter ─── -->
            <div class="card-body step-section" id="step2">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-file-shield"></i></div>
                    <div>
                        <h2>Citizen's Charter</h2>
                        <p>Optional — select <strong>Skipped</strong> if the client left a question blank</p>
                    </div>
                </div>

                <div class="cc-block">
                    <div class="q-title">CC1. Which of the following best describes their awareness of a Citizen's Charter?</div>
                    <div class="cc-opts">
                        <div class="cc-opt skip-opt">
                            <input type="radio" name="cc1" id="cc1_s" value="">
                            <label for="cc1_s"><i class="fas fa-forward"></i> Skipped</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc1" id="cc1_1" value="1">
                            <label for="cc1_1">I do not know / Did not see one</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc1" id="cc1_2" value="2">
                            <label for="cc1_2">Learned only when I saw it</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc1" id="cc1_3" value="3">
                            <label for="cc1_3">I know what it is but did NOT see it</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc1" id="cc1_4" value="4">
                            <label for="cc1_4">I know what it is and saw it</label>
                        </div>
                    </div>
                </div>

                <div class="cc-block">
                    <div class="q-title">CC2. Would they say that the CC of this office was...?</div>
                    <div class="cc-opts">
                        <div class="cc-opt skip-opt">
                            <input type="radio" name="cc2" id="cc2_s" value="">
                            <label for="cc2_s"><i class="fas fa-forward"></i> Skipped</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc2" id="cc2_5" value="5">
                            <label for="cc2_5">N/A</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc2" id="cc2_4" value="4">
                            <label for="cc2_4">Not visible</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc2" id="cc2_3" value="3">
                            <label for="cc2_3">Difficult</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc2" id="cc2_2" value="2">
                            <label for="cc2_2">Somewhat easy</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc2" id="cc2_1" value="1">
                            <label for="cc2_1">Easy to see</label>
                        </div>
                    </div>
                </div>

                <div class="cc-block">
                    <div class="q-title">CC3. How much did the CC help them in their transaction?</div>
                    <div class="cc-opts">
                        <div class="cc-opt skip-opt">
                            <input type="radio" name="cc3" id="cc3_s" value="">
                            <label for="cc3_s"><i class="fas fa-forward"></i> Skipped</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc3" id="cc3_4" value="4">
                            <label for="cc3_4">N/A</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc3" id="cc3_3" value="3">
                            <label for="cc3_3">Did not help</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc3" id="cc3_2" value="2">
                            <label for="cc3_2">Somewhat helpful</label>
                        </div>
                        <div class="cc-opt">
                            <input type="radio" name="cc3" id="cc3_1" value="1">
                            <label for="cc3_1">Helped very much</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── STEP 3: SQD ─── -->
            <div class="card-body step-section" id="step3">
                <div class="section-title">
                    <div class="section-icon"><i class="fas fa-star-half-stroke"></i></div>
                    <div>
                        <h2>Service Quality Dimensions</h2>
                        <p>Optional — select <strong>Skip</strong> if the client left a question blank</p>
                    </div>
                </div>

                <?php
                $sqd_questions = [
                    "SQD0. I am satisfied with the service that I availed.",
                    "SQD1. I spent a reasonable amount of time for my transaction.",
                    "SQD2. The office followed the transaction's requirements and steps.",
                    "SQD3. The steps (including process and payment) were easy and simple.",
                    "SQD4. I easily found information about my transaction from the office or its website.",
                    "SQD5. I paid a reasonable amount of fees for my transaction.",
                    "SQD6. The office was fair to everyone, or 'walang palakasan'.",
                    "SQD7. I was treated courteously by the staff, and (if asked) I was attended to promptly.",
                    "SQD8. I got what I needed from the government office, or (if denied) denial of request was clearly explained to me."
                ];
                $emojis = ['😢','🙁','😐','😊','🤩'];
                foreach ($sqd_questions as $idx => $q):
                ?>
                <div class="sqd-block" id="sqdRow<?php echo $idx; ?>">
                    <div class="sqd-q-text"><?php echo htmlspecialchars($q); ?></div>
                    <div class="sqd-rating">
                        <div class="sqd-opt skip-opt">
                            <input type="radio" name="sqd<?php echo $idx; ?>" id="sqd<?php echo $idx; ?>_s" value="" onchange="markSqd(<?php echo $idx; ?>,false)">
                            <label for="sqd<?php echo $idx; ?>_s">
                                <span class="em">➖</span>Skip
                            </label>
                        </div>
                        <?php foreach ($emojis as $ei => $emoji): ?>
                        <div class="sqd-opt">
                            <input type="radio" name="sqd<?php echo $idx; ?>" id="sqd<?php echo $idx; ?>_<?php echo ($ei+1); ?>" value="<?php echo ($ei+1); ?>" onchange="markSqd(<?php echo $idx; ?>,true)">
                            <label for="sqd<?php echo $idx; ?>_<?php echo ($ei+1); ?>">
                                <span class="em"><?php echo $emoji; ?></span><?php echo ($ei+1); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="suggestions-wrap">
                    <div class="q-title">
                        <i class="fas fa-comment-dots" style="color:var(--blue)"></i>
                        Written Suggestions / Comments
                    </div>
                    <textarea name="suggestions" rows="3" placeholder="Transcribe any written comments or suggestions here..."></textarea>
                </div>
            </div>

            <!-- ─── FOOTER BUTTONS ─── -->
            <div class="card-footer">
                <button type="button" class="btn btn-prev" id="btnPrev" onclick="prevStep()" style="display:none;">
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <div></div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <button type="button" class="btn btn-next" id="btnNext" onclick="nextStep()">
                        Next Step <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" class="btn btn-submit" id="btnSubmit" style="display:none;">
                        <i class="fas fa-check-circle"></i> Submit Record
                    </button>
                </div>
            </div>
        </form>
    </div>

</main>

<script>
let currentStep = 0;
const steps     = document.querySelectorAll('.step-section');
const stepItems = [
    document.getElementById('stepItem1'),
    document.getElementById('stepItem2'),
    document.getElementById('stepItem3')
];
const circles   = [
    document.getElementById('sc1'),
    document.getElementById('sc2'),
    document.getElementById('sc3')
];
const connectors = [
    document.getElementById('conn1'),
    document.getElementById('conn2')
];
const btnPrev   = document.getElementById('btnPrev');
const btnNext   = document.getElementById('btnNext');
const btnSubmit = document.getElementById('btnSubmit');

function showStep(index) {
    steps.forEach((s, i) => s.classList.toggle('active', i === index));

    stepItems.forEach((si, i) => {
        si.classList.remove('active', 'done');
        if (i < index) {
            si.classList.add('done');
            circles[i].innerHTML = '<i class="fas fa-check"></i>';
        } else {
            circles[i].textContent = i + 1;
            if (i === index) si.classList.add('active');
        }
    });

    connectors.forEach((c, i) => c.classList.toggle('done', i < index));

    btnPrev.style.display  = index > 0 ? 'inline-flex' : 'none';
    btnNext.style.display  = index < steps.length - 1 ? 'inline-flex' : 'none';
    btnSubmit.style.display = index === steps.length - 1 ? 'inline-flex' : 'none';

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep() {
    if (currentStep === 0) {
        const fields = [
            document.getElementById('fAge'),
            document.getElementById('fSex'),
            document.getElementById('regionSelect'),
            document.getElementById('fClientType'),
            document.getElementById('fService')
        ];
        const empty = fields.filter(f => f && !f.value.trim());
        if (empty.length === 0) {
            currentStep++;
            showStep(currentStep);
        } else {
            Swal.fire({
                icon: 'question',
                title: 'Some fields are blank',
                html: `<p style="color:#64748b;font-size:14px">${empty.length} field(s) are empty.<br>For physical forms this is allowed — blank fields will be recorded as not filled.<br><br>Proceed anyway?</p>`,
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Go back and fill in',
                showCancelButton: true,
                confirmButtonColor: '#002147',
                cancelButtonColor: '#64748b'
            }).then(result => {
                if (result.isConfirmed) {
                    currentStep++;
                    showStep(currentStep);
                }
            });
        }
        return;
    }
    currentStep++;
    showStep(currentStep);
}

function prevStep() {
    currentStep--;
    showStep(currentStep);
}

function markSqd(index, answered) {
    document.getElementById('sqdRow' + index).classList.toggle('answered', answered);
}

// Clear error styling on change
document.querySelectorAll('#step1 input, #step1 select').forEach(el => {
    el.addEventListener('change', () => el.classList.remove('err'));
    el.addEventListener('input',  () => el.classList.remove('err'));
});

// ── FORM SUBMISSION ──
document.getElementById('surveyForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    // Ensure locked (non-interactive) select value is included
    const regionSelect = document.getElementById('regionSelect');
    if (regionSelect.classList.contains('locked')) {
        formData.set('region_id', regionSelect.value);
    }

    Swal.fire({
        title: 'Saving Record…',
        text: 'Please wait while the record is being saved.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('../functions_admin/submit_feedback_physical.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if (data.trim() === 'success') {
            confetti({
                particleCount: 200,
                spread: 80,
                origin: { y: 0.55 },
                colors: ['#002147', '#0038A8', '#D4AF37', '#ffffff']
            });

            Swal.fire({
                icon: 'success',
                title: 'Record Encoded!',
                html: '<p style="color:#64748b;font-size:14px">Physical survey saved successfully. Ready for the next entry.</p>',
                confirmButtonColor: '#002147',
                confirmButtonText: '<i class="fas fa-plus"></i> Encode Another',
                timer: 4000,
                timerProgressBar: true,
                showConfirmButton: true
            }).then(() => {
                document.getElementById('surveyForm').reset();
                currentStep = 0;
                showStep(0);
                // Clear sqd answered states
                document.querySelectorAll('.sqd-block').forEach(b => b.classList.remove('answered'));
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Save Failed',
                text: data || 'An unexpected error occurred. Please try again.',
                confirmButtonColor: '#002147'
            });
        }
    })
    .catch(() => Swal.fire({
        icon: 'error',
        title: 'Network Error',
        text: 'Connection failed. Please check your network and try again.',
        confirmButtonColor: '#002147'
    }));
});

// Init
showStep(0);
</script>
</body>
</html>
