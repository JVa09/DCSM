<?php
/**
 * Records an action to the activity_logs database table.
 * * @param mysqli $conn The database connection variable
 * @param string $action The action performed (e.g., 'Login', 'Delete', 'Create')
 * @param string $module The area of the system (e.g., 'Authentication', 'Settings')
 * @param string $details A text description of what exactly happened
 */
function log_activity($conn, $action, $module, $details = "") {
    // 1. Check if an admin is logged in, otherwise default to "System" or "Guest"
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Guest';
    
    // 2. Grab the user's IP address and Browser info safely
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // 3. Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, username, action, module, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        // "issssss" = 1 integer, followed by 6 strings
        $stmt->bind_param("issssss", $user_id, $username, $action, $module, $details, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
}
?>