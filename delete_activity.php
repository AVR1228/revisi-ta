<?php
// delete_activity.php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['activity_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID kegiatan tidak ditemukan']);
    exit;
}

$activity_id = intval($data['activity_id']);

include 'koneksi.php'; // sesuaikan koneksi DB

// Hapus data dari tabel activities
$stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
$stmt->bind_param("i", $activity_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus data']);
}
$stmt->close();
$conn->close();
?>