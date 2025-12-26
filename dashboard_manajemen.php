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
        $_SESSION['temp_feedback'] = "<p style='color:red;'>Semua field wajib diisi untuk menambah kegiatan.</p>";
    } else {
        $unique_code_generated = false;
        $max_tries = 5; 
        $try_count = 0;
        $unique_code = '';
        while(!$unique_code_generated && $try_count < $max_tries) {
            $try_count++;
            $unique_code = strtoupper(bin2hex(random_bytes(6)));
            $stmt_check_unique = $conn->prepare("SELECT id FROM activities WHERE unique_code = ?");
            if ($stmt_check_unique) {
                $stmt_check_unique->bind_param("s", $unique_code);
                $stmt_check_unique->execute();
                $result_check_unique = $stmt_check_unique->get_result();
                if ($result_check_unique->num_rows == 0) {
                    $unique_code_generated = true;
                }
                $stmt_check_unique->close();
            } else {
                error_log("Manajemen - Error preparing check unique code: " . $conn->error);
                break; 
            }
        }

        if (!$unique_code_generated) {
             $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal generate kode unik untuk kegiatan. Silakan coba lagi.</p>";
        } else {
            $stmt_add = $conn->prepare("INSERT INTO activities (activity_name, activity_date, start_time, end_time, unique_code, user_id) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt_add) {
                $stmt_add->bind_param("sssssi", $activity_name, $activity_date, $start_time, $end_time, $unique_code, $user_id_manajemen);
                if ($stmt_add->execute()) {
                    $_SESSION['temp_feedback'] = "<p style='color:green;'>Kegiatan '" . htmlspecialchars($activity_name) . "' berhasil ditambahkan.</p>";
                } else {
                    error_log("Manajemen - Error inserting activity: " . $stmt_add->error . " (Unique Code: " . $unique_code . ")");
                     // Cek jika error karena unique_code sudah ada (meskipun sudah dicek, sebagai fallback)
                    if ($conn->errno == 1062 && strpos($stmt_add->error, 'unique_code_UNIQUE') !== false) {
                         $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal menambahkan kegiatan: Kode Unik '" . htmlspecialchars($unique_code) . "' sudah digunakan. Coba lagi.</p>";
                    } else {
                        $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal menambahkan kegiatan: Terjadi kesalahan database (ErrCode: ".$conn->errno.").</p>";
                    }
                }
                $stmt_add->close();
            } else {
                error_log("Manajemen - Error preparing insert activity statement: " . $conn->error);
                $_SESSION['temp_feedback'] = "<p style='color:red;'>Gagal mempersiapkan penambahan kegiatan.</p>";
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
                if ($result_activities_for_display && $result_activities_for_display->num_rows > 0) {
                    while ($row_activity = $result_activities_for_display->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row_activity['activity_name']) . "</td>";
                        echo "<td>" . htmlspecialchars(date("d M Y", strtotime($row_activity['activity_date']))) . 
                             "<br><small>" . htmlspecialchars(date("H:i", strtotime($row_activity['start_time']))) . 
                             " - " . htmlspecialchars(date("H:i", strtotime($row_activity['end_time']))) . "</small></td>";
                        echo "<td><strong>" . htmlspecialchars($row_activity['unique_code']) . "</strong></td>";
                        echo "<td class='qr-actions' id='qr-cell-{$row_activity['id']}'>";
                        if (!empty($row_activity['barcode'])) {
                            echo "<canvas id='qr-canvas-{$row_activity['id']}' style='width:70px; height:70px;'></canvas><br>";
                            echo "<button onclick=\"enlargeQRCode('{$row_activity['id']}', '{$row_activity['barcode']}')\">Perbesar</button>";
                            echo "<button onclick=\"deleteQRCode({$row_activity['id']})\">Hapus QR</button>";
                            echo "<button onclick=\"generateNewQRCode({$row_activity['id']})\">Generate Ulang</button>";
                        } else {
                            echo "<span id='no-qr-message-{$row_activity['id']}'>Belum ada QR Code.</span><br>";
                            echo "<button onclick=\"generateNewQRCode({$row_activity['id']})\">Generate QR</button>";
                        }
                        echo "</td>";

                        echo "<td>"; // Daftar Hadir
                        $stmt_attendees = $conn->prepare("
                            SELECT u.username, ja.attendance_time 
                            FROM joined_activities ja
                            JOIN users u ON ja.user_id = u.id
                            WHERE ja.activity_id = ? AND ja.status = 'confirmed'
                            ORDER BY ja.attendance_time ASC, u.username ASC 
                        "); 
                        if ($stmt_attendees) {
                            $current_activity_id_for_attendees = $row_activity['id'];
                            $stmt_attendees->bind_param("i", $current_activity_id_for_attendees);
                            $stmt_attendees->execute();
                            $result_attendees = $stmt_attendees->get_result();
                            
                            if ($result_attendees->num_rows > 0) {
                                echo "<small>Total Hadir: ".$result_attendees->num_rows."</small>";
                                echo "<ul>";
                                while ($attendee = $result_attendees->fetch_assoc()) {
                                    echo "<li>" . htmlspecialchars($attendee['username']);
                                    if (!empty($attendee['attendance_time'])) {
                                        echo " <small>(Pukul: " . htmlspecialchars(date("H:i", strtotime($attendee['attendance_time']))) . ")</small>";
                                    }
                                    echo "</li>";
                                }
                                echo "</ul>";
                            } else {
                                echo "<small>Belum ada yang hadir (status 'confirmed').</small>";
                            }
                            $stmt_attendees->close();
                        } else {
                            echo "<small style='color:red;'>Gagal memuat daftar hadir.</small>";
                            error_log("Manajemen - Gagal mempersiapkan statement ambil daftar hadir: " . $conn->error);
                        }
                        echo "</td>";
                        
                        echo "<td>
                                <form method='post' action='dashboard_manajemen.php' onsubmit='return confirm(\"Yakin ingin menghapus kegiatan: " . htmlspecialchars(addslashes($row_activity['activity_name'])) . "? Ini juga akan menghapus data presensi terkait (jika ON DELETE CASCADE aktif).\");'>
                                    <input type='hidden' name='delete_activity_id' value='{$row_activity['id']}'>
                                    <button type='submit' name='delete_activity_btn'>Hapus</button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align:center;'>Belum ada kegiatan yang Anda kelola.</td></tr>";
                }
                if ($stmt_fetch_activities) $stmt_fetch_activities->close();
                ?>
            </tbody>
        </table>
        <div id="qrModal" onclick="if(event.target === this) this.style.display='none'"><canvas id="qrModalCanvas"></canvas></div>
    </div>
<script> 
    const attendancePageUrl = 'https://your-domain.com/attendance-page.php'; // PENTING: Ganti dengan URL sebenarnya
    function getQrUrl(barcodeValue) { return `${attendancePageUrl}?code=${encodeURIComponent(barcodeValue)}`; }
    
    function displayQRCode(activityId, barcodeValue) {
        const canvasId = 'qr-canvas-' + activityId;
        let canvas = document.getElementById(canvasId);
        const qrCell = document.getElementById('qr-cell-' + activityId);
        
        if (!qrCell) return; // Guard clause if cell not found

        // Clear previous content and rebuild for consistency
        qrCell.innerHTML = ''; 

        canvas = document.createElement('canvas'); 
        canvas.id = canvasId;
        canvas.style.width = '70px'; // Apply style here for dynamically created
        canvas.style.height = '70px';
        qrCell.appendChild(canvas);
        qrCell.appendChild(document.createElement('br'));


        const enlargeButton = document.createElement('button'); 
        enlargeButton.textContent = 'Perbesar'; 
        enlargeButton.onclick = (event) => { event.stopPropagation(); enlargeQRCode(activityId, barcodeValue); };
        qrCell.appendChild(enlargeButton);

        const deleteButton = document.createElement('button'); 
        deleteButton.textContent = 'Hapus QR'; 
        deleteButton.onclick = (event) => { event.stopPropagation(); deleteQRCode(activityId); };
        qrCell.appendChild(deleteButton);
    
        const regenerateButton = document.createElement('button'); 
        regenerateButton.textContent = 'Generate Ulang'; 
        regenerateButton.onclick = (event) => {event.stopPropagation(); generateNewQRCode(activityId); };
        qrCell.appendChild(regenerateButton);
        
        if (canvas) { 
            const qrDataForEncoding = getQrUrl(barcodeValue); 
            QRCode.toCanvas(canvas, qrDataForEncoding, { width: 70, margin: 1, errorCorrectionLevel: 'L' }, function (error) { 
                if (error) console.error(`Error QR (activity ${activityId}):`, error); 
            });
        }
    }

    function enlargeQRCode(activityId, barcodeValue) {
        const modal = document.getElementById('qrModal'); 
        const canvas = document.getElementById('qrModalCanvas'); 
        const qrDataForEncoding = getQrUrl(barcodeValue);
        QRCode.toCanvas(canvas, qrDataForEncoding, { width: 280, margin: 2, errorCorrectionLevel: 'M' }, function (error) { 
            if (error) { 
                console.error('Enlarge QR Error:', error); 
                alert('Gagal perbesar QR.'); 
            } else { 
                modal.style.display = 'flex'; 
            }
        });
    }

    function generateNewQRCode(activityId) {
        const currentButton = event.target;
        if (!confirm('Generate QR baru akan menggantikan yang lama. Lanjutkan?')) return;
        currentButton.disabled = true;
        fetch('dashboard_manajemen.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'generate_qr', activity_id: activityId })})
        .then(response => response.json())
        .then(data => { 
            if (data.success && data.barcode) { 
                alert('QR Code baru berhasil di-generate!'); 
                // Ensure displayQRCode correctly rebuilds elements or updates existing one
                const qrCell = document.getElementById('qr-cell-' + activityId);
                if (qrCell.querySelector('#no-qr-message-' + activityId)) {
                    qrCell.querySelector('#no-qr-message-' + activityId).remove();
                    const genButton = qrCell.querySelector('button[onclick*="generateNewQRCode"]');
                    if(genButton && genButton.textContent.includes("Generate QR") && !genButton.textContent.includes("Ulang")) genButton.remove();

                }
                displayQRCode(activityId, data.barcode); 
            } else { 
                alert(data.message || 'Gagal generate QR baru.'); 
            } 
        })
        .catch(error => { console.error('Error:', error); alert('Kesalahan jaringan generate QR.'); })
        .finally(() => { currentButton.disabled = false; });
    }

    function deleteQRCode(activityId) {
        const currentButton = event.target;
        if (!confirm('Yakin hapus QR Code ini?')) return;
        currentButton.disabled = true;
        fetch('dashboard_manajemen.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action: 'delete_qr', activity_id: activityId })})
        .then(response => response.json())
        .then(data => { 
            if (data.success) { 
                alert(data.message || 'QR Code dihapus.'); 
                const qrCell = document.getElementById('qr-cell-' + activityId); 
                qrCell.innerHTML = `<span id='no-qr-message-${activityId}'>Belum ada QR Code.</span><br><button onclick="generateNewQRCode(${activityId})">Generate QR</button>`; 
            } else { 
                alert(data.message || 'Gagal hapus QR.'); 
            } 
        })
        .catch(error => { console.error('Error:', error); alert('Kesalahan jaringan hapus QR.'); })
        .finally(() => { currentButton.disabled = false; });
    }

    document.addEventListener('DOMContentLoaded', () => {
        <?php
        if ($result_activities_for_display && $result_activities_for_display->num_rows > 0) {
             if (method_exists($result_activities_for_display, 'data_seek')) {
                $result_activities_for_display->data_seek(0); 
                while ($row_js_init = $result_activities_for_display->fetch_assoc()) {
                    if (!empty($row_js_init['barcode'])) {
                        $js_barcode_init = addslashes($row_js_init['barcode']); // Renamed variable
                        echo "try { displayQRCode({$row_js_init['id']}, '{$js_barcode_init}'); } catch(e){ console.error('Error init QR', e); }\n";
                    }
                }
            }
        }
        ?>
    });
</script>
</body>
</html>