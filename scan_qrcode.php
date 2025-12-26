<?php
// scan_qrcode.php - Logika presensi dengan URL Parser

session_start();
header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");

// 1. Inisialisasi & Koneksi DB
include 'db.php'; 

// Fungsi untuk mengirim respons error dan menghentikan script
function send_error($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// 2. Validasi Sesi & Data Input
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Pegawai') {
    http_response_code(403); // Forbidden
    send_error("Akses ditolak. Anda bukan Pegawai yang valid.");
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['barcode']) || !isset($data['activity_id_context'])) {
    http_response_code(400); // Bad Request
    send_error("Data yang dikirim tidak lengkap.");
}

$user_id = $_SESSION['user_id'];
$barcode_scanned_url = $data['barcode']; // Berisi URL lengkap dari QR
$activity_id = intval($data['activity_id_context']);


// --- BAGIAN PENTING YANG DIPERBAIKI ---
// Ekstrak 'code' dari parameter URL yang di-scan
$parsed_url = parse_url($barcode_scanned_url);
$actual_code = '';

if (isset($parsed_url['query'])) {
    parse_str($parsed_url['query'], $query_params);
    if (isset($query_params['code'])) {
        $actual_code = $query_params['code']; // Ambil nilainya
    }
}

// Fallback jika QR tidak berisi URL, tapi hanya kode mentah
if (empty($actual_code)) {
    $actual_code = $barcode_scanned_url; 
}
// --- AKHIR DARI PERBAIKAN ---


// 3. Logika Inti presensi (Sekarang menggunakan $actual_code)
$conn->begin_transaction(); 

try {
    // A. Cek apakah kegiatan ada & barcode-nya cocok (menggunakan $actual_code)
    $stmt_activity = $conn->prepare(
        "SELECT id, activity_date, start_time, end_time FROM activities WHERE id = ? AND barcode = ? LIMIT 1"
    );
    if (!$stmt_activity) throw new Exception("Gagal mempersiapkan query kegiatan.");
    
    // Gunakan $actual_code yang sudah bersih untuk perbandingan
    $stmt_activity->bind_param("is", $activity_id, $actual_code);
    $stmt_activity->execute();
    $result_activity = $stmt_activity->get_result();

    if ($result_activity->num_rows === 0) {
        // Jika masih error di sini, berarti ID kegiatan atau kode-nya memang salah
        send_error("QR Code tidak valid atau tidak cocok untuk kegiatan ini.");
    }
    $activity = $result_activity->fetch_assoc();
    $stmt_activity->close();

    // B. Cek apakah sesi presensi sedang berlangsung
    $current_time = new DateTime();
    $start_time = new DateTime($activity['activity_date'] . ' ' . $activity['start_time']);
    $end_time = new DateTime($activity['activity_date'] . ' ' . $activity['end_time']);

    if ($current_time < $start_time) {
        send_error("Sesi presensi untuk kegiatan ini belum dimulai.");
    }
    if ($current_time > $end_time) {
        send_error("Sesi presensi untuk kegiatan ini telah berakhir.");
    }
    
    // C. Cek status partisipasi pegawai
    $stmt_join = $conn->prepare(
        "SELECT status FROM joined_activities WHERE user_id = ? AND activity_id = ? LIMIT 1"
    );
    if (!$stmt_join) throw new Exception("Gagal mempersiapkan query partisipasi.");

    $stmt_join->bind_param("ii", $user_id, $activity_id);
    $stmt_join->execute();
    $result_join = $stmt_join->get_result();
    
    if ($result_join->num_rows === 0) {
        send_error("Anda belum bergabung dengan kegiatan ini. Silakan gabung menggunakan Kode Unik terlebih dahulu.");
    }
    $join_data = $result_join->fetch_assoc();
    $stmt_join->close();

    if ($join_data['status'] === 'confirmed') {
        send_error("Anda sudah terkonfirmasi hadir di kegiatan ini.");
    }
    if ($join_data['status'] !== 'pending') {
        send_error("Status partisipasi Anda tidak valid untuk presensi (Status: " . htmlspecialchars($join_data['status']) . ").");
    }

    // D. Jika semua validasi lolos, update status menjadi 'confirmed'
    $stmt_update = $conn->prepare(
        "UPDATE joined_activities SET status = 'confirmed', attendance_time = NOW() WHERE user_id = ? AND activity_id = ?"
    );
    if (!$stmt_update) throw new Exception("Gagal mempersiapkan query update presensi.");
    
    $stmt_update->bind_param("ii", $user_id, $activity_id);
    $stmt_update->execute();

    if ($stmt_update->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Presensi berhasil! Kehadiran Anda telah dicatat.']);
    } else {
        throw new Exception("Gagal mengupdate status kehadiran Anda. Tidak ada baris yang diubah.");
    }
    $stmt_update->close();

} catch (Exception $e) {
    $conn->rollback(); 
    http_response_code(500); 
    error_log("Scan Error: " . $e->getMessage()); 
    send_error("Terjadi kesalahan pada server. Silakan coba lagi.");
}

$conn->close();
?>