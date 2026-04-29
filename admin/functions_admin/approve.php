<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header('Content-Type: application/json');

// 1. AUTHENTICATION: Only Superadmins can approve/reject
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

require_once "../../functions/db_connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../mail/Exception.php';
require '../../mail/PHPMailer.php';
require '../../mail/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $adminId = intval($_POST['id']);
    $action = $_POST['action']; 

    $conn->begin_transaction();

    try {
        // 3. FETCH ADMIN DETAILS
        $stmt = $conn->prepare("SELECT full_name, email, username FROM ADMIN_USERS WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            throw new Exception("Admin record could not be found.");
        }

        // 4. DATABASE ACTION & TEMPLATE SETUP
        if ($action === 'approve') {
            $update_stmt = $conn->prepare("UPDATE ADMIN_USERS SET status = 'active' WHERE id = ?");
            $emailTitle = "Account Approved";
            $headerColor = "#10b981"; 
            $statusMsg = "Congratulations! Your account request for the DICT DCSM System has been approved.";
            $instructions = "You can now log in using your registered username to access the dashboard.";
            $btnText = "Go to Dashboard";
            $btnUrl = "https://grassguard.online/login.php"; 
        } else {
            $update_stmt = $conn->prepare("DELETE FROM ADMIN_USERS WHERE id = ?");
            $emailTitle = "Account Request Update";
            $headerColor = "#ef4444"; 
            $statusMsg = "We regret to inform you that your request for a DICT DCSM Admin account has been declined/removed.";
            $instructions = "If you believe this was a mistake, please contact your regional supervisor.";
            $btnText = "Contact Support";
            $btnUrl = "mailto:support@dict.gov.ph";
        }
        
        $update_stmt->bind_param("i", $adminId);
        $update_stmt->execute();

        if ($update_stmt->affected_rows === 0) {
            throw new Exception("No changes were made to the database.");
        }

        // 5. CONSTRUCT EMAIL BODY
        $fullName = htmlspecialchars($admin['full_name']);
        $username = htmlspecialchars($admin['username']);
        
        $message = "
        <div style='font-family: Segoe UI, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
            <div style='background-color: #002147; padding: 25px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 24px; letter-spacing: 1px;'>DICT DCSM</h1>
            </div>
            <div style='padding: 35px;'>
                <h2 style='color: $headerColor; margin-top: 0; font-weight: 800;'>$emailTitle</h2>
                <p>Dear <strong>$fullName</strong>,</p>
                <p>$statusMsg</p>
                <div style='background-color: #f8fafc; border-left: 4px solid $headerColor; padding: 15px; margin: 25px 0;'>
                    <p style='margin: 0; font-size: 14px;'><strong>Username:</strong> $username</p>
                </div>
                <p style='font-size: 14px;'>$instructions</p>
                <div style='text-align: center; margin-top: 35px;'>
                    <a href='$btnUrl' style='background-color: #0038A8; color: white; padding: 14px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>$btnText</a>
                </div>
            </div>
            <div style='background-color: #f1f5f9; padding: 20px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0;'>
                <p style='margin: 0; font-weight: bold;'>Department of Information and Communications Technology</p>
                <p style='margin: 5px 0 0;'>Digital Client Satisfaction Measurement System</p>
                <p style='margin: 10px 0 0; font-style: italic;'>This is an automated system message. Please do not reply.</p>
            </div>
        </div>";

        // 6. SEND EMAIL
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'r2dictdcsm@gmail.com'; 
        $mail->Password = 'svmq prqf vojn yvbz'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Better for Gmail SMTP:
        $mail->setFrom('r2dictdcsm@gmail.com', 'DICT DCSM Admin');
        $mail->addReplyTo('no-reply@dict.gov.ph', 'DICT Support');
        
        $mail->addAddress($admin['email'], $admin['full_name']);
        $mail->isHTML(true);
        $mail->Subject = ($action === 'approve') ? "Account Approved - DICT DCSM" : "Account Update";
        $mail->Body = $message;

        $mail->send();

        // 7. FINALIZE
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Account ".ucfirst($action)."d successfully and notification sent."]);

    } catch (Exception $e) {
        $conn->rollback();
        // If PHPMailer fails, $e->getMessage() will contain the error
        echo json_encode(['success' => false, 'message' => "Process failed: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
exit();