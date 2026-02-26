<?php
session_start();
header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");
include 'db.php'; 

// 1. Validasi Akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Pegawai') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$raw_code = $data['barcode'] ?? '';

if (empty($raw_code)) {
    echo json_encode(['success' => false, 'message' => 'QRcode scan kosong.']);
    exit();
}

// 2. Parsing URL (Ambil value 'code=' jika formatnya URL)
$final_code = $raw_code;
$parsed = parse_url($raw_code);
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $params);
    if (isset($params['code'])) {
        $final_code = $params['code'];
    }
}

// 3. Cari Kegiatan Berdasarkan Barcode (Tanpa perlu ID kegiatan dari frontend)
$stmt = $conn->prepare("SELECT * FROM activities WHERE barcode = ? LIMIT 1");
$stmt->bind_param("s", $final_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Kegiatan tidak ditemukan.']);
    exit();
}

$activity = $result->fetch_assoc();
$activity_id = $activity['id'];

// 4. Cek Waktu (Opsional: Aktifkan jika mau strict)
/*
$now = new DateTime();
$start = new DateTime($activity['activity_date'] . ' ' . $activity['start_time']);
$end = new DateTime($activity['activity_date'] . ' ' . $activity['end_time']);
if ($now > $end) {
    echo json_encode(['success' => false, 'message' => 'Absen sudah ditutup.']);
    exit();
}
*/

// 5. Cek Status User di Database
$stmt_check = $conn->prepare("SELECT status FROM joined_activities WHERE user_id = ? AND activity_id = ?");
$stmt_check->bind_param("ii", $user_id, $activity_id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();

if ($res_check->num_rows === 0) {
    // KASUS A: Belum Join -> Auto Join & Langsung Hadir (Confirmed)
    $stmt_insert = $conn->prepare("INSERT INTO joined_activities (user_id, activity_id, status, joined_at, attendance_time) VALUES (?, ?, 'confirmed', NOW(), NOW())");
    $stmt_insert->bind_param("ii", $user_id, $activity_id);
    
    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Berhasil Presensi! (' . $activity['activity_name'] . ')']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal Presensi.']);
    }

} else {
    // KASUS B: Sudah Join -> Cek Status
    $status_row = $res_check->fetch_assoc();
    
    if ($status_row['status'] === 'confirmed') {
        echo json_encode(['success' => false, 'message' => 'Anda sudah absen sebelumnya untuk kegiatan ini.']);
    } else {
        // Update jadi confirmed
        $stmt_update = $conn->prepare("UPDATE joined_activities SET status = 'confirmed', attendance_time = NOW() WHERE user_id = ? AND activity_id = ?");
        $stmt_update->bind_param("ii", $user_id, $activity_id);
        
        if ($stmt_update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Presensi Berhasil !']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
    }
}
?>
