<?php
session_start();

require_once "db_connection.php";
require_once "logger.php";

/* =========================
    AUTHENTICATION CHECK
========================= */
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo "error: Unauthorized access";
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin') {
    echo "error: Unauthorized role";
    exit();
}
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = "Admin";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "error: Invalid request method.";
    exit();
}
$type     = $_POST['type'] ?? '';
$username = $_SESSION['username'];
$user_id  = $_SESSION['user_id'];

/* ==========================================
    PROFILE UPDATE (Includes Avatar)
    ========================================== */
if ($type === 'profile') {
    // 1. Update name/email
    if (isset($_POST['full_name'])) {
        $full_name = strip_tags(trim($_POST['full_name']));
        $email     = strip_tags(trim($_POST['email'] ?? ''));

        if (empty($full_name)) {
            echo "error: Full name cannot be empty.";
            exit();
        }

        $stmt = $conn->prepare("UPDATE admin_users SET full_name = ?, email = ? WHERE username = ?");
        $stmt->bind_param("sss", $full_name, $email, $username);
        $stmt->execute();
    }

    // 2. Password change
    $new_password = $_POST['new_password'] ?? '';
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            echo "error: Password must be at least 8 characters.";
            exit();
        }
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_pass = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
        $stmt_pass->bind_param("ss", $hashed, $username);
        $stmt_pass->execute();
        log_activity($conn, 'Update', 'Security', "Admin $username changed their password.");
    }

    // 3. Avatar upload (Using the new profile_picture column)
    if (isset($_FILES['avatar'])) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            if ($_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                echo "error: Upload failed (PHP error code " . $_FILES['avatar']['error'] . "). Check upload_max_filesize in php.ini.";
                exit();
            }
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_types)) {
                echo "error: Invalid image type. Use JPG, PNG, GIF, or WEBP.";
                exit();
            }
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                echo "error: Image must be under 2MB.";
                exit();
            }

            $base_dir = dirname(__FILE__) . '/../uploads/avatars';
            if (!is_dir($base_dir)) {
                mkdir($base_dir, 0755, true);
            }
            $upload_dir = realpath($base_dir) . DIRECTORY_SEPARATOR;

            // Fetch current profile picture to delete old file
            $get_old = $conn->prepare("SELECT profile_picture FROM admin_users WHERE username = ?");
            $get_old->bind_param("s", $username);
            $get_old->execute();
            $old_res = $get_old->get_result()->fetch_assoc();
            $get_old->close();

            if (!empty($old_res['profile_picture'])) {
                $old_file = $upload_dir . $old_res['profile_picture'];
                if (file_exists($old_file)) { @unlink($old_file); }
            }

            // Generate unique filename to bypass browser cache
            $username_safe = preg_replace('/[^a-z0-9_]/', '', strtolower($username));
            $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $filename = "profile_" . $username_safe . "_" . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                $upd_stmt = $conn->prepare("UPDATE admin_users SET profile_picture = ? WHERE username = ?");
                if (!$upd_stmt) {
                    echo "error: DB prepare failed: " . $conn->error;
                    exit();
                }
                $upd_stmt->bind_param("ss", $filename, $username);
                if (!$upd_stmt->execute()) {
                    echo "error: DB update failed: " . $upd_stmt->error;
                    exit();
                }
                $upd_stmt->close();
            } else {
                echo "error: Failed to move uploaded file to: " . $dest;
                exit();
            }
        }
    }

    log_activity($conn, 'Update', 'Profile', "Admin $username updated their profile.");
    echo "success";
    exit();
}

/* ==========================================
    SYSTEM RESET (danger zone)
    ========================================== */
if ($type === 'system_reset') {
    if (($_SESSION['role'] ?? '') !== 'superadmin') {
        echo "error: Only Superadministrators can reset system settings.";
        exit();
    }

    $password = $_POST['password'] ?? '';
    if (empty($password)) {
        echo "error: Password confirmation is required.";
        exit();
    }

    $stmt = $conn->prepare("SELECT password FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($password, $row['password'])) {
        echo "error: Incorrect password.";
        exit();
    }

    $conn->query("TRUNCATE TABLE system_settings");

    $defaults = [
        'system_name' => 'DCSM System',
        'items_per_page' => '10',
        'email_notifications' => '0',
    ];

    $ins = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $key => $val) {
        $ins->bind_param("ss", $key, $val);
        $ins->execute();
    }

    log_activity($conn, 'Delete', 'Settings', "Admin $username reset system settings.");
    echo "success";
    exit();
}

/* ==========================================
    GENERIC SETTINGS SAVE
    ========================================== */
$generic_types = ['system', 'notifications', 'security', 'password_policy', 'login_protection'];
if (in_array($type, $generic_types)) {
    if ($type !== 'notifications' && ($_SESSION['role'] ?? '') !== 'superadmin') {
        echo "error: Unauthorized.";
        exit();
    }

    foreach ($_POST as $key => $value) {
        if ($key === 'type') continue;
        $val = ($value === 'true') ? '1' : (($value === 'false') ? '0' : $value);

        $upd = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $upd->bind_param("sss", $key, $val, $val);
        $upd->execute();
    }

    log_activity($conn, 'Update', 'Settings', "Admin $username updated $type settings.");
    echo "success";
    exit();
}

echo "error: Invalid request type '$type'";
?>