<?php
require_once "db_connection.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['valid' => false, 'message' => 'Invalid request.']); exit();
}

$code = strtoupper(trim($_POST['code'] ?? ''));

if (empty($code)) {
    echo json_encode(['valid' => false, 'message' => 'Please enter your voucher code.']); exit();
}

if (!preg_match('/^DICT-[A-Z2-9]{6}$/', $code)) {
    echo json_encode(['valid' => false, 'message' => 'Invalid format. Codes look like DICT-XXXXXX.']); exit();
}

$stmt = $conn->prepare("SELECT id, status, expires_at FROM vouchers WHERE code = ? LIMIT 1");
$stmt->bind_param("s", $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['valid' => false, 'message' => 'This voucher code does not exist.']); exit();
}

if ($row['status'] === 'used') {
    echo json_encode(['valid' => false, 'message' => 'This voucher has already been used.']); exit();
}

if ($row['status'] === 'revoked') {
    echo json_encode(['valid' => false, 'message' => 'This voucher has been revoked.']); exit();
}

if ($row['expires_at'] && strtotime($row['expires_at']) < strtotime(date('Y-m-d'))) {
    echo json_encode(['valid' => false, 'message' => 'This voucher has expired.']); exit();
}

echo json_encode(['valid' => true, 'message' => 'Voucher accepted! You may now proceed.']);
