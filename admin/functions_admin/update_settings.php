<?php
session_start();

require_once '../../functions/db_connection.php';
require_once '../../functions/logger.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../../pages/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $keys_to_update = ['system_name', 'default_region', 'items_per_page', 'date_format'];

    foreach ($keys_to_update as $key) {
        if (isset($_POST[$key])) {
            $value = mysqli_real_escape_string($conn, $_POST[$key]);
            $sql = "UPDATE system_settings SET setting_value = '$value' WHERE setting_key = '$key'";
            mysqli_query($conn, $sql);
        }
    }

    log_activity($conn, 'Update', 'Settings', "System settings were modified by " . $_SESSION['username'] . ".");
    header("Location: ../pages/settings.php?status=success");
    exit();
}
?>
