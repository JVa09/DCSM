<?php
session_start();
require_once '../functions/db_connection.php';
require_once '../functions/logger.php';

// Log the logout action before destroying the session
if (isset($_SESSION['username'])) {
    log_activity($conn, 'Logout', 'Authentication', "User {$_SESSION['username']} logged out safely.");
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect back to the public homepage (which is safely in the same folder as this script)
header("Location: ../index.php");
exit();
?>