<?php
session_start();

// 1. Cek Login & Role
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Manajemen') {
    header("Location: login.php");
    exit();
}

include 'db.php'; 
date_default_timezone_set("Asia/Jakarta");

$user_id_manajemen = $_SESSION['user_id'];
$username_manajemen = $_SESSION['username'] ?? 'Manajemen';
$feedback_message = '';

// --- A. LOGIKA AJAX (GENERATE/DELETE QR) ---
if (isset($_POST['action'])) { 
    header('Content-Type: application/json');
    
    // Generate QR Baru (Sekarang Code = Barcode)
    if ($_POST['action'] === 'generate_qr' && isset($_POST['activity_id'])) {
        $act_id = intval($_POST['activity_id']);
        $new_code = strtoupper(bin2hex(random_bytes(4))); // 8 Karakter Hex
        
        // Update barcode DAN unique_code agar sinkron
        $stmt = $conn->prepare("UPDATE activities SET barcode = ?, unique_code = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_code, $new_code, $act_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'barcode' => $new_code]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update DB']);
        }
        exit();
    }

    // Hapus QR
    if ($_POST['action'] === 'delete_qr' && isset($_POST['activity_id'])) {
        $act_id = intval($_POST['activity_id']);
        $stmt = $conn->prepare("UPDATE activities SET barcode = NULL WHERE id = ?");
        $stmt->bind_param("i", $act_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'QR dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal hapus QR']);
        }
        exit();
    }
}

// --- B. TAMBAH KEGIATAN (SATU KODE UNTUK SEMUA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity_submit'])) {
    $name = trim($_POST['activity_name']);
    $date = $_POST['activity_date'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    // SATU KODE untuk Unique Code & Barcode
    $single_code = strtoupper(bin2hex(random_bytes(4))); // 8 Karakter

    $stmt = $conn->prepare("INSERT INTO activities (activity_name, activity_date, start_time, end_time, unique_code, barcode, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssi", $name, $date, $start, $end, $single_code, $single_code, $user_id_manajemen);
        if ($stmt->execute()) {
            $_SESSION['temp_feedback'] = "Kegiatan berhasil dibuat.";
        } else {
            $_SESSION['temp_feedback'] = "Gagal menyimpan: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: dashboard_manajemen.php");
    exit();
}

// --- C. HAPUS KEGIATAN ---
if (isset($_POST['delete_activity_btn'])) {
    $id_del = intval($_POST['delete_activity_id']);
    $conn->query("DELETE FROM activities WHERE id = $id_del");
    header("Location: dashboard_manajemen.php");
    exit();
}

// --- D. AMBIL DATA ---
if (isset($_SESSION['temp_feedback'])) {
    $feedback_message = $_SESSION['temp_feedback'];
    unset($_SESSION['temp_feedback']);
}

$query = "SELECT * FROM activities WHERE user_id = ? ORDER BY activity_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id_manajemen);
$stmt->execute();
$result_activities = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Manajemen</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <style>
        body { margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .navbar { background-color: #000000; color: white; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .navbar h2 { margin: 0; font-size: 1.5rem; }
        .btn-logout { text-decoration: none; background-color: #dc3545; color: white; padding: 8px 15px; border-radius: 4px; font-size: 0.9rem; }
        .container { max-width: 1200px; margin: 30px auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .form-inline { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; }
        input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; flex: 1; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #e1e1e1; padding: 12px; text-align: left; vertical-align: top; }
        th { background-color: #f8f9fa; color: #495057; }
        
        .btn { padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.85em; }
        .btn-green { background-color: #28a745; }
        .btn-blue { background-color: #007bff; }
        .btn-red { background-color: #dc3545; }
        .alert { padding: 15px; background-color: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px; }

        /* Modal */
        #qrModal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 9999; }
        #qrModalContent { background: white; padding: 30px; border-radius: 8px; text-align: center; position: relative; }
        .close-modal-btn { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; color: #aaa; }
    </style>
</head>
<body>

    <div class="navbar">
        <h2>Dashboard Manajemen</h2>
        <div style="display:flex; gap:15px; align-items:center;">
            <span>Halo, <b><?= htmlspecialchars($username_manajemen) ?></b></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if($feedback_message) echo "<div class='alert'>$feedback_message</div>"; ?>

        <h3>Tambah Kegiatan Baru</h3>
        <form method="post" class="form-inline">
            <input type="text" name="activity_name" placeholder="Nama Kegiatan" required style="flex: 2;">
            <input type="date" name="activity_date" value="<?= date('Y-m-d') ?>" required>
            <div style="display:flex; align-items:center; gap:5px;">
                <input type="time" name="start_time" required> <span>s/d</span> <input type="time" name="end_time" required>
            </div>
            <button type="submit" name="add_activity_submit" class="btn btn-green" style="flex:0; padding: 10px 15px;">Simpan</button>
        </form>

        <h3>Daftar Kegiatan</h3>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Detail Kegiatan</th>
                    <th style="width: 20%;">QR Code</th>
                    <th style="width: 40%;">Peserta Hadir</th>
                    <th style="width: 10%;">Hapus</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $js_qr_data = []; 
                if ($result_activities->num_rows > 0):
                    while($row = $result_activities->fetch_assoc()): 
                        if(!empty($row['barcode'])) {
                            $js_qr_data[] = ['id' => $row['id'], 'code' => $row['barcode']];
                        }
                ?>
                <tr>
                    <td>
                        <b style="font-size:1.1em;"><?= htmlspecialchars($row['activity_name']) ?></b><br>
                        <span style="color:#666;">
                            <?= date('d M Y', strtotime($row['activity_date'])) ?><br>
                            <?= date('H:i', strtotime($row['start_time'])) ?> - <?= date('H:i', strtotime($row['end_time'])) ?> WIB
                        </span>
                    </td>
                    
                    <td id="qr-cell-<?= $row['id'] ?>" style="text-align: center;">
                        <?php if($row['barcode']): ?>
                            <canvas id="canvas-<?= $row['id'] ?>" style="margin-bottom:5px;"></canvas><br>
                            <button onclick="perbesarQR('<?= htmlspecialchars(addslashes($row['activity_name'])) ?>', '<?= $row['barcode'] ?>')" class="btn btn-green" style="width:100%; margin-bottom:4px;">🔍 Perbesar</button>
                            <div style="display:flex; gap:5px;">
                                <button onclick="hapusQR(<?= $row['id'] ?>)" class="btn btn-red" style="flex:1;">Hapus</button>
                                <button onclick="buatQR(<?= $row['id'] ?>)" class="btn btn-blue" style="flex:1;">Ubah</button>
                            </div>
                        <?php else: ?>
                            <span style="color: #999; display:block; margin-bottom:5px;"></span>
                            <button onclick="buatQR(<?= $row['id'] ?>)" class="btn btn-green" style="width:100%;">Buat QR</button>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php
                        $q_hadir = $conn->query("SELECT u.username, ja.attendance_time FROM joined_activities ja JOIN users u ON ja.user_id = u.id WHERE ja.activity_id = {$row['id']} AND ja.status = 'confirmed' ORDER BY ja.attendance_time ASC");
                        if($q_hadir->num_rows > 0) {
                            echo "<div style='max-height:100px; overflow-y:auto;'><ul style='margin:0; padding-left:20px;'>";
                            while($p = $q_hadir->fetch_assoc()) {
                                echo "<li><b>{$p['username']}</b> <small style='color:#28a745;'>(" . date('H:i', strtotime($p['attendance_time'])) . ")</small></li>";
                            }
                            echo "</ul></div>";
                        } else {
                            echo "<small style='color:#888;'>Belum ada data presensi.</small>";
                        }
                        ?>
                    </td>

                    <td>
                        <form method="post" onsubmit="return confirm('Hapus kegiatan ini?');">
                            <input type="hidden" name="delete_activity_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="delete_activity_btn" class="btn btn-red" style="width:100%;">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center; padding: 30px;">Belum ada kegiatan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="qrModal" onclick="tutupModal()">
        <div id="qrModalContent" onclick="event.stopPropagation()">
            <span class="close-modal-btn" onclick="tutupModal()">&times;</span>
            <h4 id="modalTitle">QR Code</h4>
            <canvas id="qrModalCanvas"></canvas>
            <p><small></small></p>
        </div>
    </div>

    <script>
        const baseUrl = "https://websiteanda.com/presensi?code=";
        const qrList = <?= json_encode($js_qr_data) ?>;
        
        document.addEventListener("DOMContentLoaded", function() {
            qrList.forEach(item => {
                const canvas = document.getElementById('canvas-' + item.id);
                if(canvas) QRCode.toCanvas(canvas, baseUrl + item.code, { width: 80, margin: 1 });
            });
        });

        function perbesarQR(nama, code) {
            document.getElementById('modalTitle').innerText = nama;
            QRCode.toCanvas(document.getElementById('qrModalCanvas'), baseUrl + code, { width: 300 });
            document.getElementById('qrModal').style.display = 'flex';
        }
        function tutupModal() { document.getElementById('qrModal').style.display = 'none'; }
        
        function buatQR(id) {
            if(!confirm("Generate QR?")) return;
            fetch('', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=generate_qr&activity_id=' + id })
            .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
        }
        function hapusQR(id) {
            if(!confirm("Hapus QR?")) return;
            fetch('', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=delete_qr&activity_id=' + id })
            .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.message); });
        }
    </script>
</body>
</html>
