<?php
session_start();
require_once "../../functions/db_connection.php";
require_once "../../functions/logger.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit();
}
if (!in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']); exit();
}

$actor  = $_SESSION['username'] ?? 'Admin';
$role   = $_SESSION['role'] ?? 'admin';
$action = $_POST['action'] ?? '';

function gen_code($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = 'DICT-';
        for ($i = 0; $i < 6; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $r = $conn->query("SELECT id FROM vouchers WHERE code='" . $conn->real_escape_string($code) . "' LIMIT 1");
    } while ($r && $r->num_rows > 0);
    return $code;
}


if ($action === 'generate') {
    $qty    = min(50, max(1, (int)($_POST['qty'] ?? 1)));
    $expiry = !empty($_POST['expires_at']) ? trim($_POST['expires_at']) : null;
    if ($expiry && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) $expiry = null;
    if ($expiry && strtotime($expiry) < strtotime(date('Y-m-d'))) {
        echo json_encode(['success' => false, 'message' => 'Expiry date must be today or in the future.']); exit();
    }

    $stmt = $conn->prepare("INSERT INTO vouchers (code, created_by, expires_at) VALUES (?, ?, ?)");
    $generated = [];
    for ($i = 0; $i < $qty; $i++) {
        $code = gen_code($conn);
        $stmt->bind_param("sss", $code, $actor, $expiry);
        if ($stmt->execute()) $generated[] = $code;
    }
    $stmt->close();

    log_activity($conn, 'Create', 'Vouchers', "$actor generated " . count($generated) . " voucher(s).");
    echo json_encode(['success' => true, 'codes' => $generated, 'count' => count($generated)]);
    exit();
}


if ($action === 'revoke') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

    $stmt = $conn->prepare("UPDATE vouchers SET status='revoked' WHERE id=? AND status='active'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        log_activity($conn, 'Update', 'Vouchers', "$actor revoked voucher ID $id.");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Voucher not found or already used/revoked.']);
    }
    exit();
}

if ($action === 'delete') {
    if ($role !== 'superadmin') {
        echo json_encode(['success' => false, 'message' => 'Only superadmins can delete vouchers.']); exit();
    }
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit(); }

    $stmt = $conn->prepare("DELETE FROM vouchers WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        log_activity($conn, 'Delete', 'Vouchers', "$actor permanently deleted voucher ID $id.");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Voucher not found.']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
