<?php
// update_qrcode.php
include 'koneksi.php';

// Terima data JSON POST
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['code']) || !isset($data['activity_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Missing parameters"]);
    exit;
}

$code = mysqli_real_escape_string($conn, $data['code']);
$activity_id = intval($data['activity_id']);

// Update barcode di tabel activities
$sql = "UPDATE activities SET barcode = '$code', updated_at = NOW() WHERE id = $activity_id";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["message" => "Barcode updated successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Failed to update barcode"]);
}

mysqli_close($conn);
?>
