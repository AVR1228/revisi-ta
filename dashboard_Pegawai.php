<?php
session_start();
include 'db.php'; // $conn
date_default_timezone_set("Asia/Jakarta");

// --- Validasi Sesi Pengguna yang Lebih Ketat ---
if (isset($_SESSION['user_id'])) {
    $current_session_user_id = $_SESSION['user_id'];
    $stmt_validate_session = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    if ($stmt_validate_session) {
        $stmt_validate_session->bind_param("i", $current_session_user_id);
        $stmt_validate_session->execute();
        $result_validate_session = $stmt_validate_session->get_result();
        if ($user_session_data = $result_validate_session->fetch_assoc()) {
            if ($user_session_data['role'] !== 'Pegawai') {
                session_unset(); session_destroy();
                header("Location: login.php?error=role_mismatch_pegawai");
                exit();
            }
            $_SESSION['user_id'] = $user_session_data['id'];
            $_SESSION['username'] = $user_session_data['username'];
            $_SESSION['role'] = $user_session_data['role'];
            $user_id = $_SESSION['user_id'];
            $username = $_SESSION['username'];
        } else {
            session_unset(); session_destroy();
            header("Location: login.php?error=invalid_user_session_pegawai");
            exit();
        }
        $stmt_validate_session->close();
    } else {
        error_log("Pegawai - Gagal prepare validasi sesi: " . $conn->error);
        session_unset(); session_destroy();
        header("Location: login.php?error=session_db_error_pegawai");
        exit();
    }
} else {
    header("Location: login.php?error=not_logged_in_pegawai");
    exit();
}
// --- Akhir Validasi Sesi ---

$success_message = null;
$error_message = null;

// --- GABUNG KEGIATAN MANUAL (INPUT KODE UNIK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_activity_submit'])) {
    $unique_code_input = trim($_POST['kode_kegiatan_join']);

    if (empty($unique_code_input)) {
        $error_message = "Kode kegiatan tidak boleh kosong.";
    } else {
        $stmt_find_activity = $conn->prepare("SELECT id FROM activities WHERE unique_code = ? LIMIT 1");
        if ($stmt_find_activity) {
            $stmt_find_activity->bind_param("s", $unique_code_input);
            $stmt_find_activity->execute();
            $result_activity = $stmt_find_activity->get_result();

            if ($activity_row = $result_activity->fetch_assoc()) {
                $activity_id = $activity_row['id'];
                $stmt_check_join = $conn->prepare("SELECT id, status FROM joined_activities WHERE user_id = ? AND activity_id = ? LIMIT 1");
                if ($stmt_check_join) {
                    $stmt_check_join->bind_param("ii", $user_id, $activity_id);
                    $stmt_check_join->execute();
                    $result_check = $stmt_check_join->get_result();

                    if ($existing_join = $result_check->fetch_assoc()) {
                        if ($existing_join['status'] == 'pending') {
                            $error_message = "⚠️ Anda sudah bergabung (status 'pending'). Silakan scan QR untuk konfirmasi.";
                        } elseif ($existing_join['status'] == 'confirmed' || $existing_join['status'] == 'completed') {
                            $error_message = "✅ Anda sudah terkonfirmasi hadir/menyelesaikan kegiatan ini.";
                        } else {
                            $error_message = "⚠️ Catatan Anda untuk kegiatan ini: status " . htmlspecialchars($existing_join['status']);
                        }
                    } else {
                        $stmt_insert_join = $conn->prepare(
                            "INSERT INTO joined_activities (user_id, activity_id, status, joined_at, attendance_time) 
                             VALUES (?, ?, 'pending', NOW(), NULL)"
                        );
                        if ($stmt_insert_join) {
                            $stmt_insert_join->bind_param("ii", $user_id, $activity_id);
                            if ($stmt_insert_join->execute()) {
                                $success_message = "Berhasil bergabung dengan kegiatan. Status: 'pending'.";
                            } else {
                                error_log("Pegawai - Gagal insert joined_activities: " . $stmt_insert_join->error);
                                if ($conn->errno == 1062) {
                                    $error_message = "❌ Anda sudah terdaftar di kegiatan ini.";
                                } else {
                                    $error_message = "❌ Gagal menyimpan partisipasi (ErrCode: ".$conn->errno.").";
                                }
                            }
                            $stmt_insert_join->close();
                        } else {
                             $error_message = "❌ Gagal mempersiapkan statement gabung: " . $conn->error;
                        }
                    }
                    $stmt_check_join->close();
                } else {
                     $error_message = "❌ Gagal mempersiapkan pengecekan partisipasi: " . $conn->error;
                }
            } else {
                $error_message = "❌ Kode kegiatan tidak ditemukan.";
            }
            $stmt_find_activity->close();
        } else {
            $error_message = "❌ Gagal mempersiapkan pencarian kegiatan: " . $conn->error;
        }
    }
}

// --- AMBIL DAFTAR KEGIATAN YANG DIIKUTI ---
$joined_activities_list = [];
$stmt_joined_list = $conn->prepare("
    SELECT a.id, a.activity_name, a.activity_date, a.start_time, a.end_time, a.barcode, ja.status as user_status
    FROM activities a
    JOIN joined_activities ja ON a.id = ja.activity_id
    WHERE ja.user_id = ?
    ORDER BY a.activity_date DESC, a.start_time DESC
");
if ($stmt_joined_list) {
    $stmt_joined_list->bind_param("i", $user_id);
    $stmt_joined_list->execute();
    $result_joined = $stmt_joined_list->get_result();
    $joined_activities_list = $result_joined->fetch_all(MYSQLI_ASSOC);
    $stmt_joined_list->close();
} else {
    error_log("Pegawai - Gagal ambil daftar kegiatan diikuti: " . $conn->error);
    if(empty($error_message) && empty($success_message)) $error_message = "Gagal memuat daftar kegiatan yang diikuti.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Pegawai - <?= htmlspecialchars($username) ?></title>
    <style> 
        body { font-family: Arial, sans-serif; padding: 0; margin:0; background-color: #f4f7f6; color: #333; line-height: 1.6; }
        .navbar { background-color: #333; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .navbar h2 { margin:0; font-size: 1.4em; }
        .navbar .user-info { display: flex; align-items: center; }
        .navbar .user-info span { margin-right: 15px; }
        .navbar a { color: white; text-decoration: none; font-weight: normal; }
        .navbar a:hover { text-decoration: underline; }
        .container { padding: 25px; max-width: 900px; margin: 20px auto; }
        .form-container { background-color: #fff; padding: 25px; margin: 0 auto 30px auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 500px;}
        .form-container h4 { margin-top:0; color: #2c3e50; border-bottom:1px solid #eee; padding-bottom:10px; }
        input[type="text"]#kode_kegiatan_join_id, button[type="submit"] {
            width: 100%; padding: 12px; margin-top: 10px; font-size: 16px; 
            border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; 
        }
        input[type="text"]#kode_kegiatan_join_id { margin-bottom:10px;}
        button { cursor: pointer; background-color: #007bff; color: white; font-weight: bold; }
        button:hover { background-color: #0056b3; }
        button.scan-btn { background-color: #17a2b8; padding: 8px 12px; font-size:0.9em;}
        button.scan-btn:hover { background-color: #138496; }
        .message { text-align: center; margin-top: 15px; font-weight: bold; padding: 10px; border-radius: 5px; }
        .message-success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb;}
        .message-error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb;}
        table { width: 100%; border-collapse: collapse; background-color: white; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-radius:8px; overflow:hidden;}
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold;}
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f9f9f9; }
        #camera-container { text-align: center; margin-top:25px; padding:20px; border:1px solid #ddd; background-color:#fff; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        #preview { width: 100%; max-width: 300px; height: auto; aspect-ratio: 1 / 1; margin: 20px auto; display: none; border: 2px dashed #007bff; background-color: #e9ecef; }
        #preview video { max-width:100%; max-height:100%; }
        #scan-result { min-height:20px; margin-top:10px; }
        .no-activities { text-align: center; padding: 30px; color: #7f8c8d; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        small.status-info { font-size: 0.85em; color: #555; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Dashboard Pegawai</h2>
    <div class="user-info">
        <span>Halo, <?= htmlspecialchars($username) ?>!</span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <?php if (!empty($success_message)) { ?>
        <p class="message message-success"><?= htmlspecialchars($success_message) ?></p>
    <?php } elseif (!empty($error_message)) { ?>
        <p class="message message-error"><?= htmlspecialchars($error_message) ?></p>
    <?php } ?>

    <div class="form-container">
        <h4>Gabung Kegiatan dengan Kode Unik</h4>
        <form method="post" autocomplete="off" action="dashboard_pegawai.php">
            <input type="text" id="kode_kegiatan_join_id" name="kode_kegiatan_join" placeholder="Masukkan Kode Unik Kegiatan" required>
            <button type="submit" name="join_activity_submit">Gabung</button>
        </form>
    </div>

    <h4 style="margin-top: 40px; color: #2c3e50;">Kegiatan yang Anda Ikuti</h4>
    <?php if (empty($joined_activities_list)) { ?>
        <p class="no-activities">Anda belum mengikuti kegiatan apa pun.</p>
    <?php } else { ?>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Kegiatan</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Status Anda</th>
                    <th>QR Presensi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                foreach ($joined_activities_list as $activity) {
                    $activity_start_datetime_str = $activity['activity_date'] . ' ' . $activity['start_time'];
                    $activity_end_datetime_str = $activity['activity_date'] . ' ' . $activity['end_time'];
                    
                    $current_dt_loop = new DateTime("now", new DateTimeZone('Asia/Jakarta'));
                    $activity_start_dt_loop = new DateTime($activity_start_datetime_str, new DateTimeZone('Asia/Jakarta'));
                    $activity_end_dt_loop = new DateTime($activity_end_datetime_str, new DateTimeZone('Asia/Jakarta'));
                    
                    $can_scan_qr = ($activity['user_status'] === 'pending' && 
                                 !empty($activity['barcode']) && 
                                 $current_dt_loop >= $activity_start_dt_loop && 
                                 $current_dt_loop <= $activity_end_dt_loop);
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($activity['activity_name']) ?></td>
                    <td><?= htmlspecialchars(date("d M Y", strtotime($activity['activity_date']))) ?></td>
                    <td><?= htmlspecialchars(date("H:i", strtotime($activity['start_time']))) ?> - <?= htmlspecialchars(date("H:i", strtotime($activity['end_time']))) ?></td>
                    <td>
                        <?php 
                        if ($activity['user_status'] === 'pending') {
                            echo "<span style='color:#e67e22;'>Menunggu Presensi</span>";
                        } elseif ($activity['user_status'] === 'confirmed') {
                            echo "<strong style='color:green;'>Hadir Terkonfirmasi</strong>";
                        } elseif ($activity['user_status'] === 'completed') {
                            echo "<strong style='color:blue;'>Selesai</strong>";
                        } else {
                            echo htmlspecialchars(ucfirst($activity['user_status']));
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($can_scan_qr) { ?>
                            <button type="button" class="scan-btn" onclick="startScanner(<?= $activity['id'] ?>)">Scan QR</button>
                        <?php } elseif ($activity['user_status'] === 'confirmed' || $activity['user_status'] === 'completed') { ?>
                            <span style="color:green; font-weight:bold;">Sudah Presensi</span>
                        <?php } elseif (empty($activity['barcode'])) { ?>
                            <small class="status-info">QR Belum Tersedia</small>
                        <?php } elseif ($current_dt_loop < $activity_start_dt_loop) { ?>
                            <small class="status-info">Sesi Belum Dimulai</small>
                        <?php } elseif ($current_dt_loop > $activity_end_dt_loop) { ?>
                            <small class="status-info" style="color:#c0392b;">Sesi Telah Berakhir</small>
                        <?php } else { ?>
                            <small class="status-info">Tidak Bisa Presensi</small>
                        <?php } ?>
                    </td>
                </tr>
                <?php 
                } // Akhir dari foreach
                ?>
            </tbody>
        </table>
    <?php } // Akhir dari else untuk if (empty($joined_activities_list)) ?>

    <div id="camera-container">
        <div id="preview"></div>
        <p id="scan-result" class="message"></p>
         <button type="button" id="stop-scanner-btn" style="display:none; margin-top:10px; background-color: #e74c3c;" onclick="stopScannerManually()">Tutup Kamera</button>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let scanner = null;
let isScanning = false;
let lastScanned = "";

const previewElement = document.getElementById("preview");
const resultElement = document.getElementById("scan-result");
const stopScannerBtn = document.getElementById("stop-scanner-btn");

function startScanner(activityId) {
    // 1. CEK KEAMANAN BROWSER (HTTPS)
    // Chrome memblokir kamera di HTTP biasa (kecuali localhost).
    if (location.protocol !== "https:" && 
        location.hostname !== "localhost" && 
        location.hostname !== "127.0.0.1") {
        
        alert("⚠️ PERINGATAN KEAMANAN CHROME:\n\n" +
              "Google Chrome memblokir akses kamera pada website HTTP.\n" +
              "Agar kamera bisa terbuka di HP/Jaringan lain, Anda WAJIB menggunakan HTTPS (SSL).\n\n" +
              "Solusi Cepat: Gunakan 'Ngrok' untuk mendapatkan link HTTPS gratis.");
        return; 
    }

    resultElement.innerText = "";
    previewElement.style.display = "block";
    stopScannerBtn.style.display = "inline-block";

    if (isScanning && scanner) {
        scanner.stop().then(() => {
            isScanning = false;
            initiateScan(activityId, previewElement, resultElement);
        }).catch(err => {
            isScanning = false;
            initiateScan(activityId, previewElement, resultElement);
        });
    } else {
        initiateScan(activityId, previewElement, resultElement);
    }
}

function initiateScan(activityId, preview, resultBox) {
    scanner = new Html5Qrcode(preview.id);
    isScanning = true;
    lastScanned = "";

    const config = { fps: 10, qrbox: 250 };
    
    // Gunakan kamera belakang (environment)
    const cameraConfig = { facingMode: "environment" };

    scanner.start(
        cameraConfig,
        config,
        (qrCodeMessage) => {
            if (qrCodeMessage === lastScanned) return;
            lastScanned = qrCodeMessage;
            
            // Efek suara/getar (opsional, agar terasa seperti scan beneran)
            if (navigator.vibrate) navigator.vibrate(200);

            resultBox.innerHTML = `<span style="color:#3498db;">Memproses...</span>`;

            fetch("scan_qrcode.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ barcode: qrCodeMessage, activity_id_context: activityId })
            })
            .then(res => res.json())
            .then(data => {
                resultBox.style.color = data.success ? "green" : "red";
                resultBox.innerText = (data.success ? "✅ " : "❌ ") + data.message;
                if (data.success) {
                    setTimeout(() => window.location.reload(), 2000);
                    scanner.stop();
                }
            })
            .catch(err => {
                resultBox.innerText = "❌ Error: " + err.message;
            });
        },
        (errorMessage) => {
            // Biarkan kosong agar tidak spam log
        }
    ).catch(err => {
        console.error("Kamera Error:", err);
        let msg = "Gagal membuka kamera.";
        if (err?.message?.includes("Permission")) {
            msg = "Izin kamera ditolak. Mohon izinkan akses kamera di pengaturan browser (ikon gembok di URL bar).";
        } else if (err?.message?.includes("Secure Context")) {
            msg = "Kamera diblokir karena tidak menggunakan HTTPS.";
        }
        alert(msg);
        stopScannerManually();
    });
}

function stopScannerManually() {
    if (scanner) {
        scanner.stop().then(() => {
            isScanning = false;
            previewElement.style.display = "none";
            stopScannerBtn.style.display = "none";
            resultElement.innerText = "";
        }).catch(err => console.error(err));
    }
}
</script>
</body>
</html>