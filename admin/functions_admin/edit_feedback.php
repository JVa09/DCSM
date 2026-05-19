<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once "../../functions/db_connection.php";

/* AUTHENTICATION CHECK */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../../pages/login.php"); 
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    header("Location: ../../unauthorized.php"); 
    exit();
}

// 1. GET THE RECORD ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../pages/feedbacks.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$is_superadmin = ($_SESSION['role'] === 'superadmin');

// 2. FETCH EXISTING DATA
$fetch_query = "SELECT * FROM csm_submissions WHERE id = '$id' LIMIT 1";
$result = mysqli_query($conn, $fetch_query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("Error: Record not found.");
}


if (!$is_superadmin && !empty($_SESSION['region'])) {
    $admin_region_query = mysqli_query($conn, "SELECT id FROM regions WHERE region_name = '" . mysqli_real_escape_string($conn, $_SESSION['region']) . "' LIMIT 1");
    $admin_reg = mysqli_fetch_assoc($admin_region_query);
    if ($data['region_id'] != $admin_reg['id']) {
        header("Location: ../../unauthorized.php");
        exit();
    }
}

$regions_result = mysqli_query($conn, "SELECT id, region_name FROM regions WHERE is_active = 1 ORDER BY id ASC");
$services_result = mysqli_query($conn, "SELECT id, service_name FROM services WHERE is_active = 1 ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Feedback | DICT Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        
        :root { --primary: #002147; --secondary: #0038A8; --accent: #FFD700; --light-bg: #f4f6f9; --success: #10b981; --danger: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { background: var(--light-bg); min-height: 100vh; padding-top: 70px; }
        .main-content { margin-left: 260px; padding: 20px; transition: 0.3s; }
        .top-header { position: fixed; top: 0; left: 0; width: 100%; background: #002147; color: white; padding: 10px 20px; z-index: 1000; display: flex; justify-content: space-between; align-items: center; }
        
        .survey-container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        
        .progress-container { margin-bottom: 35px; }
        .progress-bar { display: flex; justify-content: space-between; position: relative; margin-bottom: 15px; padding: 0 20px; }
        .progress-bar::before { content: ''; position: absolute; top: 50%; left: 40px; right: 40px; height: 4px; background: #e5e7eb; transform: translateY(-50%); z-index: 0; }
        .progress-bar::after { content: ''; position: absolute; top: 50%; left: 40px; height: 4px; background: linear-gradient(90deg, var(--success), var(--secondary)); transform: translateY(-50%); z-index: 0; transition: width 0.5s; width: 0; }
        .progress-container.step-1 .progress-bar::after { width: 0; }
        .progress-container.step-2 .progress-bar::after { width: calc(50% - 20px); }
        .progress-container.step-3 .progress-bar::after { width: calc(100% - 40px); }
        .progress-step { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #6b7280; position: relative; z-index: 1; border: 3px solid white; }
        .progress-step.active { background: var(--secondary); color: white; border-color: var(--accent); }
        .progress-step.completed { background: var(--success); color: white; }

        .step { display: none; animation: fadeIn 0.4s; }
        .step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .step h2 { color: var(--primary); font-size: 24px; margin-bottom: 25px; border-bottom: 3px solid var(--accent); display: inline-block; }
        .input-group { margin-bottom: 20px; }
        label { font-weight: 600; font-size: 14px; margin-bottom: 8px; display: block; }
        input, select, textarea { width: 100%; padding: 12px; border-radius: 8px; border: 2px solid #e5e7eb; font-size: 14px; }
        input.locked { background: #eee; cursor: not-allowed; }

        .question { margin-bottom: 25px; padding: 15px; background: #f9fafb; border-radius: 12px; border-left: 4px solid var(--secondary); }
        .rating { display: flex; justify-content: space-between; gap: 10px; }
        .rating label { flex: 1; text-align: center; cursor: pointer; padding: 10px; border-radius: 12px; background: white; border: 2px solid #e5e7eb; font-size: 12px; }
        .rating input { display: none; }
        .rating label:has(input:checked) { border-color: var(--secondary); background: #eff6ff; font-weight: bold; }
        
        .btn-group { display: flex; justify-content: space-between; margin-top: 30px; gap: 15px; }
        button.action-btn { padding: 14px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; flex: 1; }
        .next-btn { background: var(--secondary); color: white; }
        .prev-btn { background: #e5e7eb; color: #374151; }
        .submit-btn { background: var(--success); color: white; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; }
        .back-btn { background: var(--secondary); color: white; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; }
    </style>
</head>
<body>

<?php include '../component_admin/sidebar.php'; ?>

<main class="main-content">
    <div class="top-header">
        <h1><i class="fas fa-edit"></i> CSM | Edit Feedback Entry</h1>
        <div style="display: flex; align-items: center; gap: 8px;">
            Welcome, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong>
        </div> 
    </div>
    
    <div class="page-header">
        <h1 style="background: linear-gradient(90deg, #002147, #0038A8); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
            Editing: <?php echo $data['control_no']; ?>
        </h1> 
        <a href="../pages/feedbacks.php" class="back-btn">← Cancel Edit</a>
    </div>

    <div class="survey-container">
        <form id="editForm">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <div class="progress-container step-1" id="progressContainer">
                <div class="progress-bar">
                    <div class="progress-step active" data-step="1">1</div>
                    <div class="progress-step" data-step="2">2</div>
                    <div class="progress-step" data-step="3">3</div>
                </div>
            </div>

            <div class="step active" data-step="1">
                <h2>👤 Client Information</h2>
                <div class="input-group">
                    <label>🎂 Age</label>
                    <input type="number" name="age" value="<?php echo $data['age']; ?>" required>
                </div>
                <div class="input-group">
                    <label>⚤ Sex</label>
                    <select name="sex" required>
                        <option value="Male" <?php if($data['sex'] == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if($data['sex'] == 'Female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>👔 Client Type</label>
                    <select name="client_type" required>
                        <option value="Citizen" <?php if($data['client_type'] == 'Citizen') echo 'selected'; ?>>Citizen</option>
                        <option value="Business" <?php if($data['client_type'] == 'Business') echo 'selected'; ?>>Business</option>
                        <option value="Government" <?php if($data['client_type'] == 'Government') echo 'selected'; ?>>Government</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>📱 Service Availed</label>
                    <select name="service_id" required>
                        <?php 
                        mysqli_data_seek($services_result, 0);
                        while($row = mysqli_fetch_assoc($services_result)) {
                            $sel = ($data['service_id'] == $row['id']) ? 'selected' : '';
                            echo "<option value='{$row['id']}' $sel>{$row['service_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="btn-group">
                    <span></span>
                    <button type="button" class="action-btn next-btn" onclick="nextStep()">Next Step →</button>
                </div>
            </div>

            <div class="step" data-step="2">
                <h2>Citizen's Charter</h2>

                <?php
                $cc_configs = [
                    1 => [
                        'label'   => 'CC1: Do you know about the Citizen\'s Charter?',
                        'options' => [
                            5 => 'Yes, I was already aware before my transaction with this office',
                            3 => 'Yes, but I was only aware when I saw the CC of this office',
                            1 => 'No, I was not aware of the CC',
                        ]
                    ],
                    2 => [
                        'label'   => 'CC2: Did you see this office\'s Citizen\'s Charter?',
                        'options' => [
                            5 => 'Yes, the CC was easy to find',
                            3 => 'Yes, but the CC was hard to find',
                            1 => 'No, I did not see this office\'s CC',
                            0 => 'N/A (CC1 answered "Not aware")',
                        ]
                    ],
                    3 => [
                        'label'   => 'CC3: Did you use the Citizen\'s Charter as a guide?',
                        'options' => [
                            5 => 'Yes, I was able to use the CC as a guide',
                            1 => 'No, I was not able to use the CC',
                            0 => 'N/A (Did not see CC)',
                        ]
                    ],
                ];
                foreach ($cc_configs as $i => $cfg):
                    $curr = (int)$data['cc'.$i];
                ?>
                <div class="question">
                    <label><strong><?php echo htmlspecialchars($cfg['label']); ?></strong></label>
                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:10px;">
                        <?php foreach ($cfg['options'] as $val => $text): ?>
                        <label style="display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; border:2px solid #e5e7eb; background:#f9fafb; cursor:pointer; font-size:14px; <?php echo ($curr === $val) ? 'border-color:#0038A8; background:#eff6ff;' : ''; ?>">
                            <input type="radio" name="cc<?php echo $i; ?>" value="<?php echo $val; ?>" <?php echo ($curr === $val) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($text); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="btn-group">
                    <button type="button" class="action-btn prev-btn" onclick="prevStep()">← Previous</button>
                    <button type="button" class="action-btn next-btn" onclick="nextStep()">Next Step →</button>
                </div>
            </div>

            <div class="step" data-step="3">
                <h2>⭐ Service Quality Dimensions</h2>
                <?php for($i=0; $i<=8; $i++): 
                    $curr_sqd = "sqd".$i;
                ?>
                <div class="question">
                    <p>SQD<?php echo $i; ?> Dimension Rating</p>
                    <div class="rating">
                        <?php for($val=1; $val<=5; $val++): ?>
                        <label>
                            <input type="radio" name="sqd<?php echo $i; ?>" value="<?php echo $val; ?>" <?php if($data[$curr_sqd] == $val) echo 'checked'; ?>>
                            <?php echo $val; ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endfor; ?>

                <div class="input-group">
                    <label>Suggestions/Comments</label>
                    <textarea name="suggestions"><?php echo htmlspecialchars($data['suggestions']); ?></textarea>
                </div>

                <div class="btn-group">
                    <button type="button" class="action-btn prev-btn" onclick="prevStep()">← Previous</button>
                    <button type="submit" class="action-btn submit-btn">Update Record</button>
                </div>
            </div>
        </form>
    </div>
</main>

<script>
let currentStep = 0;
const steps = document.querySelectorAll(".step");
const progressSteps = document.querySelectorAll(".progress-step");
const progressContainer = document.getElementById("progressContainer");

function showStep(index) {
    steps.forEach((step, i) => step.classList.toggle("active", i === index));
    progressContainer.className = 'progress-container step-' + (index + 1);
    progressSteps.forEach((step, i) => {
        step.classList.toggle("active", i === index);
        step.classList.toggle("completed", i < index);
    });
    window.scrollTo(0, 0);
}

function nextStep() { currentStep++; showStep(currentStep); }
function prevStep() { currentStep--; showStep(currentStep); }

document.getElementById("editForm").addEventListener("submit", function(e) {
    e.preventDefault();
    let formData = new FormData(this);

    Swal.fire({ title: 'Updating...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

   
    fetch("../functions_admin/update_feedback.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.text())
    .then(data => {
        if (data.trim() === "success") {
            Swal.fire({ icon: 'success', title: 'Updated!', text: 'Changes saved successfully.' }).then(() => {
                window.location.href = "../pages/feedbacks.php";
            });
        } else {
            Swal.fire('Error', data, 'error');
        }
    });
});
</script>
</body>
</html>