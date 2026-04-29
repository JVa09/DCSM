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

/* ==========================================
   PROFILE UPDATE
   ========================================== */
if ($type === 'profile') {
    // Only update name/email when they're explicitly posted (not password-only form)
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

    // Password change
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

    // Avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
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

        $upload_dir = dirname(__FILE__) . '/../uploads/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Remove any old avatar for this user
        $username_safe = preg_replace('/[^a-z0-9_]/', '', strtolower($username));
        foreach (glob($upload_dir . $username_safe . '.*') as $old) {
            @unlink($old);
        }

        $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $filename = $username_safe . '.' . $ext;
        $dest     = $upload_dir . $filename;

        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            echo "error: Could not save the uploaded photo.";
            exit();
        }

        // Try to store path in DB (fails silently if column doesn't exist)
        $rel_path = 'uploads/avatars/' . $filename;
        @$conn->query("UPDATE admin_users SET avatar_path = '" .
            $conn->real_escape_string($rel_path) . "' WHERE username = '" .
            $conn->real_escape_string($username) . "'");
    }

    log_activity($conn, 'Update', 'Profile', "Admin $username updated their profile.");
    echo "success";
    exit();
}

/* ==========================================
   SYSTEM RESET  (danger zone)
   ========================================== */
if ($type === 'system_reset') {
    // Only superadmin
    if (($_SESSION['role'] ?? '') !== 'superadmin') {
        echo "error: Only Superadministrators can reset system settings.";
        exit();
    }

    $password = $_POST['password'] ?? '';
    if (empty($password)) {
        echo "error: Password confirmation is required.";
        exit();
    }

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !password_verify($password, $row['password'])) {
        echo "error: Incorrect password. Reset was not performed.";
        exit();
    }

    // Truncate and re-seed defaults
    $conn->query("TRUNCATE TABLE system_settings");

    $defaults = [
        'system_name'             => 'DCSM System',
        'default_region'          => 'All Regions',
        'items_per_page'          => '10',
        'date_format'             => 'M/d/Y',
        'session_timeout'         => '30',
        'maintenance_mode'        => '0',
        'email_notifications'     => '0',
        'low_rating_alerts'       => '0',
        'weekly_reports'          => '0',
        'system_updates'          => '0',
        'two_factor_auth'         => '0',
        'session_timeout_enabled' => '0',
        'login_alerts'            => '0',
        'min_password_length'     => '8',
        'password_expiry_days'    => '90',
        'require_uppercase'       => '0',
        'require_numbers'         => '0',
        'require_special_chars'   => '0',
        'account_lockout'         => '0',
        'login_captcha'           => '0',
        'max_login_attempts'      => '5',
        'lockout_duration'        => '15',
        'remember_me_days'        => '30',
    ];

    $ins = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $key => $val) {
        $ins->bind_param("ss", $key, $val);
        $ins->execute();
    }

    log_activity($conn, 'Delete', 'Settings', "Admin $username reset all system settings to factory defaults.");
    echo "success";
    exit();
}

/* ==========================================
   GENERIC KEY→VALUE SETTINGS SAVE
   Handles: system, notifications, security,
            password_policy, login_protection
   ========================================== */
$generic_types = ['system', 'notifications', 'security', 'password_policy', 'login_protection'];

if (in_array($type, $generic_types)) {
    // Superadmin-only enforcement for system-wide settings
    if ($type !== 'notifications' && ($_SESSION['role'] ?? '') !== 'superadmin') {
        echo "error: Only Superadministrators can modify these settings.";
        exit();
    }

    foreach ($_POST as $key => $value) {
        if ($key === 'type') continue;

        // Normalize JS boolean strings to 0/1 for DB storage
        $val = ($value === 'true') ? '1' : (($value === 'false') ? '0' : $value);

        $check = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $check->bind_param("s", $key);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            $upd = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            $upd->bind_param("ss", $val, $key);
            $upd->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            $ins->bind_param("ss", $key, $val);
            $ins->execute();
        }
    }

    log_activity($conn, 'Update', 'Settings', "Admin $username updated $type settings.");
    echo "success";
    exit();
}

echo "error: Invalid request type '$type'";
?>
