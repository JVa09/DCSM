<?php
session_start();
require_once "../../functions/db_connection.php";
require_once "../../functions/logger.php"; // Include your logger function

/* =========================
    🔐 AUTHENTICATION CHECK
========================= */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../../pages/login.php");
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // Detect where the user came from (default to feedbacks if not set)
    $source = $_GET['source'] ?? 'feedbacks'; 

    /* =========================
        🔍 1. FETCH DETAILS BEFORE DELETE
    ========================= */
    // We fetch this first so the log entry actually has the data
    $info_query = mysqli_query($conn, "SELECT control_no, client_type FROM csm_submissions WHERE id = '$id' LIMIT 1");
    $info = mysqli_fetch_assoc($info_query);
    
    if (!$info) {
        header("Location: ../pages/feedbacks.php?msg=notfound");
        exit();
    }

    $control_no = $info['control_no'];
    $client_type = $info['client_type'];

    /* =========================
        🔒 2. RBAC REGION CHECK
    ========================= */
    if ($_SESSION['role'] !== 'superadmin' && !empty($_SESSION['region'])) {
        $admin_region = $_SESSION['region'];
        $check = mysqli_query($conn, "SELECT cs.id FROM csm_submissions cs 
                                     JOIN regions r ON cs.region_id = r.id 
                                     WHERE cs.id = '$id' AND r.region_name = '$admin_region'");
        if (mysqli_num_rows($check) === 0) {
            header("Location: ../pages/feedbacks.php?msg=unauthorized");
            exit();
        }
    }

    /* =========================
        🗑️ 3. EXECUTE DELETE
    ========================= */
    $sql = "DELETE FROM csm_submissions WHERE id = '$id'";

    if (mysqli_query($conn, $sql)) {
        
        /* =========================
            📝 4. ACTIVITY LOGGING
        ========================= */
        $log_details = "Deleted $client_type record. Control No: $control_no";
        log_activity($conn, 'delete', 'Client/Feedback Management', $log_details);

        /* =========================
            🚀 5. SMART REDIRECT
        ========================= */
        // If coming from client.php, go back to client.php. Otherwise go to feedbacks.php
        $redirect_page = ($source === 'clients') ? 'clients.php' : 'feedbacks.php';
        header("Location: ../pages/$redirect_page?msg=deleted");
        
    } else {
        header("Location: ../pages/feedbacks.php?msg=error");
    }
} else {
    header("Location: ../pages/feedbacks.php");
}
exit();