<?php
session_start();

// 1. Autentikasi dan Otorisasi
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Manajemen') {
    header("Location: login.php"); // Ganti ke halaman login Anda
    exit();
}

include 'db.php'; // Koneksi database $conn
date_default_timezone_set("Asia/Jakarta"); // Set timezone

$user_id_manajemen = $_SESSION['user_id']; // User ID manajemen yang sedang login
$feedback_message = ''; // Untuk pesan sukses atau error

// --- AWAL: LOGIKA PHP UNTUK TAMBAH KEGIATAN (POST Standar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity_submit'])) {
    $activity_name = trim($_POST['activity_name']);
    $activity_date = trim($_POST['activity_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    
    if (empty($activity_name) || empty($activity_date) || empty($start_time) || empty($end_time)) {
        $_SESSION['temp_feedback'] = "<p style='color:red;'>Semua field wajib diisi.</p>";
    } else {
        // 1. Generate Kode Unik (Untuk Join)
        $unique_code_generated = false;
        $try_count = 0;
        $unique_code = '';
        while(!$unique_code_generated && $try_count < 5) {
            $try_count++;
            $unique_code = strtoupper(bin2hex(random_bytes(6)));
            $stmt_check = $conn->prepare("SELECT id FROM activities WHERE unique_code = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("s", $unique_code);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows == 0) $unique_code_generated = true;
                $stmt_check->close();
            } else { break; }
        }

        // 2. Generate Barcode (Untuk QR Code) SECARA OTOMATIS
        // --- BAGIAN INI YANG HILANG DI KODE KAMU ---
        $barcode_generated = false;
        $try_count_bc = 0;
        $final_barcode = '';
        while(!$barcode_generated && $try_count_bc < 5) {
            $try_count_bc++;
            $final_barcode = strtoupper(bin2hex(random_bytes(4))); // 8 char hex
            $stmt_check_bc = $conn->prepare("SELECT id FROM activities WHERE barcode = ?");
            if ($stmt_check_bc) {
                $stmt_check_bc->bind_param("s", $final_barcode);
                $stmt_check_bc->execute();
                if ($stmt_check_bc->get_result()->num_rows == 0) $barcode_generated = true;
                $stmt_check_bc->close();
            }
        }

        if (!$unique_code_generated || !$barcode_generated) {
             $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal generate kode. Coba lagi.</p>";
        } else {
            // 3. Masukkan ke Database (Perhatikan ada kolom 'barcode' sekarang)
            // --- QUERY INI JUGA PERLU DIUPDATE ---
            $stmt_add = $conn->prepare("INSERT INTO activities (activity_name, activity_date, start_time, end_time, unique_code, barcode, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt_add) {
                // Parameter: ssssssi (7 parameter)
                $stmt_add->bind_param("ssssssi", $activity_name, $activity_date, $start_time, $end_time, $unique_code, $final_barcode, $user_id_manajemen);
                
                if ($stmt_add->execute()) {
                    $_SESSION['temp_feedback'] = "<p style='color:green;'>Kegiatan berhasil ditambahkan & QR siap.</p>";
                } else {
                    error_log("Insert Error: " . $stmt_add->error);
                    $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal simpan ke database.</p>";
                }
                $stmt_add->close();
            } else {
                $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal prepare statement.</p>";
            }
        }
    }
    header("Location: dashboard_manajemen.php");
    exit();
}
// --- AKHIR: LOGIKA PHP UNTUK TAMBAH KEGIATAN ---

// --- AWAL: LOGIKA PHP UNTUK HAPUS KEGIATAN (POST Standar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity_btn']) && isset($_POST['delete_activity_id'])) {
    $activity_id_to_delete = intval($_POST['delete_activity_id']);

    // Dengan ON DELETE CASCADE pada FK di `joined_activities` yang merujuk ke `activities.id`,
    // record terkait di `joined_activities` akan otomatis terhapus.

    $stmt_delete = $conn->prepare("DELETE FROM activities WHERE id = ? AND user_id = ?");
    if ($stmt_delete) {
        $stmt_delete->bind_param("ii", $activity_id_to_delete, $user_id_manajemen);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $_SESSION['temp_feedback'] = "<p style='color:green;'>Kegiatan berhasil dihapus.</p>";
            } else {
                $_SESSION['temp_feedback'] = "<p style='color:orange;'>Kegiatan tidak ditemukan atau Anda tidak berhak menghapusnya.</p>";
            }
        } else {
            error_log("Manajemen - Error deleting activity: " . $stmt_delete->error);
            $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal menghapus kegiatan: Terjadi kesalahan database.</p>";
        }
        $stmt_delete->close();
    } else {
        error_log("Manajemen - Error preparing delete activity statement: " . $conn->error);
        $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal mempersiapkan penghapusan kegiatan.</p>";
    }
    header("Location: dashboard_manajemen.php");
    exit();
}
// --- AKHIR: LOGIKA PHP UNTUK HAPUS KEGIATAN ---

// --- AWAL: LOGIKA PHP UNTUK AJAX (QR CODE MANAGEMENT) ---
function generateAndUpdateQRCode($activity_id, $conn) { 
    $activity_id = intval($activity_id);
    $new_barcode = strtoupper(bin2hex(random_bytes(4))); // 8 karakter hex
    $stmt_check_barcode = $conn->prepare("SELECT id FROM activities WHERE barcode = ? AND id != ?");
    $is_barcode_unique = false;
    $barcode_gen_tries = 0;
    while(!$is_barcode_unique && $barcode_gen_tries < 5){ // Coba max 5 kali jika ada collision (sangat jarang)
        $barcode_gen_tries++;
        $new_barcode = strtoupper(bin2hex(random_bytes(4)));
        if($stmt_check_barcode){
            $stmt_check_barcode->bind_param("si", $new_barcode, $activity_id);
            $stmt_check_barcode->execute();
            $result_check_barcode = $stmt_check_barcode->get_result();
            if($result_check_barcode->num_rows == 0){
                $is_barcode_unique = true;
            }
        } else { break; } // Gagal prepare, keluar
    }
    if($stmt_check_barcode) $stmt_check_barcode->close();

    if(!$is_barcode_unique){
        error_log("Manajemen - Gagal generate barcode unik setelah beberapa percobaan.");
        return false;
    }
    
    $stmt_update = mysqli_prepare($conn, "UPDATE activities SET barcode = ? WHERE id = ?");
    if ($stmt_update) {
        mysqli_stmt_bind_param($stmt_update, "si", $new_barcode, $activity_id);
        if (mysqli_stmt_execute($stmt_update)) {
            mysqli_stmt_close($stmt_update);
            return $new_barcode;
        } else { error_log("Manajemen - Error executing barcode update: " . mysqli_stmt_error($stmt_update)); mysqli_stmt_close($stmt_update); return false; }
    } else { error_log("Manajemen - Error preparing barcode update statement: " . mysqli_error($conn)); return false; }
}

if (isset($_POST['action'])) { 
    header('Content-Type: application/json');
    if ($_POST['action'] === 'generate_qr' && isset($_POST['activity_id'])) {
        $activity_id_for_qr = intval($_POST['activity_id']); // Renamed variable
        $generated_barcode = generateAndUpdateQRCode($activity_id_for_qr, $conn);
        if ($generated_barcode !== false) { echo json_encode(['success' => true, 'barcode' => $generated_barcode]); } 
        else { echo json_encode(['success' => false, 'message' => 'Gagal generate QR code unik. Coba lagi.']); }
        exit();
    }
    if ($_POST['action'] === 'delete_qr' && isset($_POST['activity_id'])) {
        $activity_id_to_delete_qr = intval($_POST['activity_id']);
        $stmt_del_qr = mysqli_prepare($conn, "UPDATE activities SET barcode = NULL WHERE id = ?"); // Renamed variable
        if ($stmt_del_qr) {
            mysqli_stmt_bind_param($stmt_del_qr, "i", $activity_id_to_delete_qr);
            if (mysqli_stmt_execute($stmt_del_qr)) { mysqli_stmt_close($stmt_del_qr); echo json_encode(['success' => true, 'message' => 'QR Code berhasil dihapus.']); } 
            else { error_log("Manajemen - Error executing barcode NULL update: " . mysqli_stmt_error($stmt_del_qr)); mysqli_stmt_close($stmt_del_qr); echo json_encode(['success' => false, 'message' => 'Gagal menghapus QR Code.']); }
        } else { error_log("Manajemen - Error preparing barcode NULL update: " . mysqli_error($conn)); echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan penghapusan QR.']); }
        exit();
    }
}
// --- AKHIR: LOGIKA PHP UNTUK AJAX ---

if (isset($_SESSION['temp_feedback'])) {
    $feedback_message = $_SESSION['temp_feedback'];
    unset($_SESSION['temp_feedback']);
}

$stmt_fetch_activities = $conn->prepare("SELECT id, activity_name, activity_date, start_time, end_time, unique_code, barcode FROM activities WHERE user_id = ? ORDER BY activity_date DESC, start_time ASC");
$result_activities_for_display = null;
if ($stmt_fetch_activities) {
    $stmt_fetch_activities->bind_param("i", $user_id_manajemen);
    $stmt_fetch_activities->execute();
    $result_activities_for_display = $stmt_fetch_activities->get_result();
} else {
    error_log("Manajemen - Gagal mempersiapkan statement ambil kegiatan: " . $conn->error);
    $feedback_message .= "<p style='color:red;'>Gagal memuat daftar kegiatan.</p>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard Manajemen</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
<style>
        /* --- GAYA UMUM & NAVBAR (Diambil dari Pegawai) --- */
        body { 
            font-family: Arial, sans-serif; 
            padding: 0; 
            margin: 0; /* Reset margin agar navbar full width */
            background-color: #f4f7f6; 
            color: #333; 
            line-height: 1.6; 
        }
        .navbar { 
            background-color: #333; 
            color: white; 
            padding: 15px 25px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar h2 { margin: 0; font-size: 1.4em; }
        .navbar .user-info { display: flex; align-items: center; }
        .navbar .user-info span { margin-right: 15px; }
        .navbar a { color: white; text-decoration: none; font-weight: normal; }
        .navbar a:hover { text-decoration: underline; }

        /* --- GAYA KONTEN MANAJEMEN --- */
        .container { 
            background-color: #fff; 
            padding: 20px; 
            margin: 20px auto; /* Tengah dengan margin atas bawah */
            max-width: 1200px; /* Batasi lebar agar rapi */
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        h1 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0; }
        
        /* Tabel Styles */
        table { border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 0.9em; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; font-weight: bold; }
        td ul { margin: 5px 0 0 0; padding-left: 20px; list-style-type: decimal; font-size: 0.9em; }
        td ul li { margin-bottom: 3px; }
        
        /* Button Styles */
        button, input[type="submit"] { cursor: pointer; padding: 8px 15px; border-radius: 4px; border: 1px solid transparent; margin: 3px; font-size: 0.9em; }
        button[name="add_activity_submit"] { background-color: #5cb85c; color: white; border-color: #4cae4c; }
        button[name="delete_activity_btn"] { background-color: #d9534f; color: white; border-color: #d43f3a; }
        .qr-actions button { background-color: #5bc0de; color: white; border-color: #46b8da; }
        .qr-actions button:hover, button[name="add_activity_submit"]:hover, button[name="delete_activity_btn"]:hover { opacity: 0.85; }
        
        /* Modal & Canvas */
        .qr-actions canvas { margin-right: 10px; vertical-align: middle; border: 1px solid #eee; }
        #qrModal { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 1000;
        }
        #qrModal canvas { background: white; padding: 20px; border-radius: 5px; }
        
        /* Form Styles */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="date"], .form-group input[type="time"] {
            width: 100%; max-width: 400px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
        }
        
        /* Feedback Messages */
        .feedback-message { padding: 12px; margin-bottom: 20px; border-radius: 4px; text-align: center; border: 1px solid transparent; }
        .feedback-message[style*="color:red"] { background-color: #f2dede; border-color: #ebccd1; color: #a94442; }
        .feedback-message[style*="color:green"] { background-color: #dff0d8; border-color: #d6e9c6; color: #3c763d; }
        .feedback-message[style*="color:orange"] { background-color: #fcf8e3; border-color: #faebcc; color: #8a6d3b; }
    </style>
</head>
<body>
<div class="navbar">
        <h2>Dashboard Manajemen</h2>
        <div class="user-info">
            <span>Halo, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>!</span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1>Kelola Kegiatan</h1>
        
        <?php if (!empty($feedback_message)) echo "<div class='feedback-message'>$feedback_message</div>"; ?>

        <h2>Tambah Kegiatan Baru</h2>
        <form method="post" action="dashboard_manajemen.php">
            <div class="form-group"><label for="activity_name">Nama Kegiatan:</label><input type="text" id="activity_name" name="activity_name" required></div>
            <div class="form-group"><label for="activity_date">Tanggal Kegiatan:</label><input type="date" id="activity_date" name="activity_date" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label for="start_time">Waktu Mulai (WIB):</label><input type="time" id="start_time" name="start_time" required></div>
            <div class="form-group"><label for="end_time">Waktu Berakhir (WIB):</label><input type="time" id="end_time" name="end_time" required></div>
            <button type="submit" name="add_activity_submit">Tambah Kegiatan</button>
        </form>
        <hr style="margin: 30px 0;">

        <h2>Daftar Kegiatan Anda</h2>
<table>
    <thead>
        <tr>
            <th>Nama Kegiatan</th>
            <th>Tanggal & Waktu</th>
            <th>Kode Unik</th>
            <th>QR Code Presensi</th>
            <th>Daftar Hadir (Status: 'confirmed')</th>
            <th>Aksi Kegiatan</th>
        </tr>
    </thead>
    <tbody>
        <?php
        // 1. SIAPKAN ARRAY PENAMPUNG UNTUK JS
        $js_qr_list = []; 

        if ($result_activities_for_display && $result_activities_for_display->num_rows > 0) {
            while ($row_activity = $result_activities_for_display->fetch_assoc()) {
                // 2. SIMPAN DATA KE ARRAY JIKA BARCODE ADA
                if (!empty($row_activity['barcode'])) {
                    $js_qr_list[] = [
                        'id' => $row_activity['id'],
                        'barcode' => $row_activity['barcode']
                    ];
                }

                echo "<tr>";
                echo "<td>" . htmlspecialchars($row_activity['activity_name']) . "</td>";
                echo "<td>" . htmlspecialchars(date("d M Y", strtotime($row_activity['activity_date']))) . 
                     "<br><small>" . htmlspecialchars(date("H:i", strtotime($row_activity['start_time']))) . 
                     " - " . htmlspecialchars(date("H:i", strtotime($row_activity['end_time']))) . "</small></td>";
                echo "<td><strong>" . htmlspecialchars($row_activity['unique_code']) . "</strong></td>";
                
                // KOLOM QR CODE
                echo "<td class='qr-actions' id='qr-cell-{$row_activity['id']}'>";
                if (!empty($row_activity['barcode'])) {
                    // Canvas kosong yang akan diisi oleh JS nanti
                    echo "<canvas id='qr-canvas-{$row_activity['id']}' style='width:70px; height:70px;'></canvas><br>";
                    echo "<button onclick=\"enlargeQRCode('{$row_activity['id']}', '{$row_activity['barcode']}')\">Perbesar</button>";
                    echo "<button onclick=\"deleteQRCode({$row_activity['id']})\">Hapus QR</button>";
                    echo "<button onclick=\"generateNewQRCode({$row_activity['id']})\">Generate Ulang</button>";
                } else {
                    echo "<span id='no-qr-message-{$row_activity['id']}'>Belum ada QR Code.</span><br>";
                    echo "<button onclick=\"generateNewQRCode({$row_activity['id']})\">Generate QR</button>";
                }
                echo "</td>";

                // ... (Kode kolom Daftar Hadir dan Aksi biarkan seperti semula) ...
                echo "<td>"; 
                // ... (Logika daftar hadir Anda yang panjang itu) ...
                echo "</td>";
                
                echo "<td>
                        <form method='post' action='dashboard_manajemen.php' onsubmit='return confirm(\"Yakin hapus?\");'>
                            <input type='hidden' name='delete_activity_id' value='{$row_activity['id']}'>
                            <button type='submit' name='delete_activity_btn'>Hapus</button>
                        </form>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='6' style='text-align:center;'>Belum ada kegiatan.</td></tr>";
        }
        
        // JANGAN TUTUP KONEKSI DISINI DULU, KITA PAKAI DATA NYA DI BAWAH
        ?>
    </tbody>
</table>
        <div id="qrModal" onclick="if(event.target === this) this.style.display='none'"><canvas id="qrModalCanvas"></canvas></div>
    </div>
<script> 
    const attendancePageUrl = 'https://your-domain.com/attendance-page.php'; // Ganti URL ini sesuai kebutuhan
    
    function getQrUrl(barcodeValue) { 
        return `${attendancePageUrl}?code=${encodeURIComponent(barcodeValue)}`; 
    }
    
    function displayQRCode(activityId, barcodeValue) {
        const canvasId = 'qr-canvas-' + activityId;
        const canvas = document.getElementById(canvasId);
        
        if (canvas) { 
            const qrDataForEncoding = getQrUrl(barcodeValue); 
            QRCode.toCanvas(canvas, qrDataForEncoding, { width: 70, margin: 1, errorCorrectionLevel: 'L' }, function (error) { 
                if (error) console.error(`Error QR (activity ${activityId}):`, error); 
            });
        }
    }

    // --- Fungsi Helper Lainnya Tetap Sama ---
    function enlargeQRCode(activityId, barcodeValue) {
        const modal = document.getElementById('qrModal'); 
        const canvas = document.getElementById('qrModalCanvas'); 
        const qrDataForEncoding = getQrUrl(barcodeValue);
        QRCode.toCanvas(canvas, qrDataForEncoding, { width: 280, margin: 2, errorCorrectionLevel: 'M' }, function (error) { 
            if (error) alert('Gagal perbesar QR.'); else modal.style.display = 'flex'; 
        });
    }

    function generateNewQRCode(activityId) {
       // ... (Isi fungsi ini sama seperti kode lama Anda, tidak perlu diubah) ...
       if (!confirm('Generate QR baru?')) return;
       fetch('dashboard_manajemen.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'generate_qr', activity_id: activityId })})
       .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
    }

    function deleteQRCode(activityId) {
        // ... (Isi fungsi ini sama seperti kode lama Anda) ...
       if (!confirm('Hapus QR?')) return;
       fetch('dashboard_manajemen.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'delete_qr', activity_id: activityId })})
       .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
    }

    // --- BAGIAN INI YANG DIREVISI AGAR OTOMATIS MUNCUL ---
    document.addEventListener('DOMContentLoaded', () => {
        // Ambil data dari PHP Array yang kita buat di Langkah 1
        const qrList = <?php echo json_encode($js_qr_list); ?>;
        
        console.log("Data QR yang akan dirender:", qrList); // Cek Console browser jika masih error

        if (qrList && qrList.length > 0) {
            qrList.forEach(item => {
                try {
                    displayQRCode(item.id, item.barcode);
                } catch (e) {
                    console.error("Gagal render QR untuk ID " + item.id, e);
                }
            });
        }
    });
</script>
</body>
</html>
