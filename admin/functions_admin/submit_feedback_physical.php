<?php
include "../../functions/db_connection.php";
require_once "../../functions/logger.php"; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $current_year = date('Y');
    $count_query = "SELECT COUNT(id) as total FROM csm_submissions WHERE control_no LIKE '$current_year-%'";
    $count_result = mysqli_query($conn, $count_query);
    $count_row = mysqli_fetch_assoc($count_result);
    $next_number = $count_row['total'] + 1;
    $control_no = $current_year . '-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
    $submission_date = date('Y-m-d');
    
    // 2. Capture Demographics
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $region_id = !empty($_POST['region_id']) ? (int)$_POST['region_id'] : null;
    $service_id = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
    $sex = !empty($_POST['sex']) ? strip_tags(trim($_POST['sex'])) : null;
    $client_type = !empty($_POST['client_type']) ? strip_tags(trim($_POST['client_type'])) : null;

    // 3. Capture Citizen's Charter (CC)
    $cc1 = isset($_POST['cc1']) ? (int)$_POST['cc1'] : null;
    $cc2 = isset($_POST['cc2']) ? (int)$_POST['cc2'] : 0; // Default to 0 if skipped
    $cc3 = isset($_POST['cc3']) ? (int)$_POST['cc3'] : 0; // Default to 0 if skipped

    // AUTOMATIC N/A: If reason is empty or not provided, set to 'N/A'
    $cc3_reason = !empty($_POST['cc3_reason']) ? strip_tags(trim($_POST['cc3_reason'])) : "N/A";

    // 4. Capture SQDs (sqd0 to sqd8)
    $sqd0 = isset($_POST['sqd0']) ? (int)$_POST['sqd0'] : null;
    $sqd1 = isset($_POST['sqd1']) ? (int)$_POST['sqd1'] : null;
    $sqd2 = isset($_POST['sqd2']) ? (int)$_POST['sqd2'] : null;
    $sqd3 = isset($_POST['sqd3']) ? (int)$_POST['sqd3'] : null;
    $sqd4 = isset($_POST['sqd4']) ? (int)$_POST['sqd4'] : null;
    $sqd5 = isset($_POST['sqd5']) ? (int)$_POST['sqd5'] : null;
    $sqd6 = isset($_POST['sqd6']) ? (int)$_POST['sqd6'] : null;
    $sqd7 = isset($_POST['sqd7']) ? (int)$_POST['sqd7'] : null;
    $sqd8 = isset($_POST['sqd8']) ? (int)$_POST['sqd8'] : null;

    // 5. Suggestions and Source
    $suggestions = !empty($_POST['suggestions']) ? strip_tags(trim($_POST['suggestions'])) : null;
    $submission_source = 'Physical';
    
    // 6. Prepared Statement
    $stmt = $conn->prepare("INSERT INTO csm_submissions 
        (control_no, submission_date, client_type, sex, age, region_id, service_id, cc1, cc2, cc3, cc3_reason, sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, suggestions, submission_source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssiiiiiisiiiiiiiiiss",
        $control_no, $submission_date, $client_type, $sex, $age, $region_id, $service_id,
        $cc1, $cc2, $cc3, $cc3_reason,
        $sqd0, $sqd1, $sqd2, $sqd3, $sqd4, $sqd5, $sqd6, $sqd7, $sqd8,
        $suggestions, $submission_source
    );

    // 7. Execute and Log
    if ($stmt->execute()) {
        if(session_status() !== PHP_SESSION_ACTIVE) session_start();
        $admin_name = $_SESSION['username'] ?? 'Admin';
        log_activity($conn, 'Create', 'Surveys', "A physical survey was encoded manually by $admin_name (Control No: $control_no)");
        echo "success";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Error: Invalid request method.";
}
?>