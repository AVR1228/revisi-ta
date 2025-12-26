<?php
session_start();
// Disable caching
header("Expires: 0");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

// Cek autentikasi dan peran
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manajemen') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include 'db.php';

// Ambil data JSON dari request
$data = json_decode(file_get_contents("php://input"), true);

// Validasi data
if (!isset($data['activity_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing activity_id']);
    exit;
}

$activity_id = intval($data['activity_id']);

// Update barcode menjadi kosong tanpa updated_at
$sql = "UPDATE activities SET barcode = '' WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $activity_id);

// Eksekusi dan berikan respons
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete QR code']);
}

$stmt->close();
$conn->close();
?>