<?php
session_start();
require_once "../../functions/db_connection.php";
require_once "../../functions/logger.php"; // Ensure this path is correct

/* =========================
    🔐 AUTHENTICATION CHECK
========================= */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize Basic Information
    $id           = mysqli_real_escape_string($conn, $_POST['id']);
    $age          = mysqli_real_escape_string($conn, $_POST['age']);
    $sex          = mysqli_real_escape_string($conn, $_POST['sex']);
    $client_type  = mysqli_real_escape_string($conn, $_POST['client_type']);
    $service_id   = mysqli_real_escape_string($conn, $_POST['service_id']);
    $suggestions  = mysqli_real_escape_string($conn, $_POST['suggestions']);

    // Fetch Control Number for better log details
    $fetch_info = mysqli_query($conn, "SELECT control_no FROM csm_submissions WHERE id = '$id'");
    $info = mysqli_fetch_assoc($fetch_info);
    $control_no = $info['control_no'] ?? 'Unknown';

    // 2. Prepare CC (Citizen's Charter) values
    $cc_updates = [];
    for ($i = 1; $i <= 3; $i++) {
        $val = isset($_POST["cc$i"]) ? (int)$_POST["cc$i"] : 0;
        $cc_updates[] = "cc$i = '$val'";
    }

    // 3. Prepare SQD (Service Quality Dimensions) values
    $sqd_updates = [];
    for ($i = 0; $i <= 8; $i++) {
        $val = isset($_POST["sqd$i"]) ? (int)$_POST["sqd$i"] : 0;
        $sqd_updates[] = "sqd$i = '$val'";
    }

    $cc_sql_part = implode(", ", $cc_updates);
    $sqd_sql_part = implode(", ", $sqd_updates);

    // 4. Build and execute the query
    $update_query = "UPDATE csm_submissions SET 
        age = '$age', 
        sex = '$sex', 
        client_type = '$client_type', 
        service_id = '$service_id', 
        suggestions = '$suggestions',
        $cc_sql_part,
        $sqd_sql_part
        WHERE id = '$id'";

    if (mysqli_query($conn, $update_query)) {
        
        /* =========================
            📝 ACTIVITY LOGGING
        ========================= */
        $log_details = "Admin updated feedback record for Control No: $control_no (ID: $id)";
        log_activity($conn, 'update', 'Feedback Management', $log_details);
        
        echo "success";
    } else {
        echo "Database Error: " . mysqli_error($conn);
    }
} else {
    echo "Invalid request method.";
}
?>