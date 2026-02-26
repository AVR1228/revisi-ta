<?php
session_start();
include 'db.php';
date_default_timezone_set("Asia/Jakarta");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Pegawai') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username_pegawai = $_SESSION['username'] ?? 'Pegawai';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pegawai Dashboard</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .navbar { background-color: #000000; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .navbar h2 { margin: 0; font-size: 1.3rem; }
        .btn-logout { text-decoration: none; background-color: #dc3545; color: white; padding: 8px 15px; border-radius: 4px; font-size: 0.9rem; }
        
        .container { max-width: 600px; margin: 20px auto; padding: 0 15px; }
        .card { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e1e4e8; }
        
        /* Tombol Scan Utama */
        .btn-main-scan { 
            background: #007bff; color: white; border: none; border-radius: 12px; width: 100%; 
            padding: 15px; font-size: 1.1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 4px 6px rgba(0,123,255,0.3); transition: transform 0.1s;
        }
        .btn-main-scan:active { transform: scale(0.98); }

        /* Scanner */
        #scan-container { display: none; text-align: center; margin-bottom: 20px; background: #000; padding: 10px; border-radius: 12px; }
        #reader { width: 100%; border-radius: 8px; overflow: hidden; }
        #reader video { object-fit: cover; }
        
        .close-scan { background: #dc3545; color: white; border: none; padding: 10px; width: 100%; border-radius: 8px; margin-top: 10px; font-size: 1rem; cursor: pointer; }

        /* List Status */
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .status-hadir { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>

    <div class="navbar">
        <h2>Dashboard Pegawai</h2>
        <div style="display:flex; gap:15px; align-items:center;">
            <span style="font-size:0.9rem;"><?= htmlspecialchars($username_pegawai) ?></span>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        
        <button class="btn-main-scan" onclick="bukaKamera()">
            📷 SCAN QRCODE
        </button>
        <br><br>

        <div id="scan-container">
            <div id="reader"></div>
            <p id="scan-status" style="color:white; margin:10px 0;">Arahkan kamera ke QR Code...</p>
            <button type="button" onclick="stopScan()" class="close-scan">Tutup Kamera</button>
        </div>

        <h3 style="color:#555; border-bottom:2px solid #eee; padding-bottom:10px;">Riwayat Kegiatan Saya</h3>
        <?php
        $q = "SELECT a.*, ja.status as user_status 
              FROM joined_activities ja 
              JOIN activities a ON ja.activity_id = a.id 
              WHERE ja.user_id = ? ORDER BY ja.joined_at DESC";
        $stmt = $conn->prepare($q);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if($res->num_rows == 0) echo "<p style='text-align:center; color:#999; margin-top:30px;'>Belum ada kegiatan yang diikuti.<br></p>";

        while($row = $res->fetch_assoc()):
            $is_confirmed = ($row['user_status'] == 'confirmed');
        ?>
            <div class="card" style="padding: 15px;">
                <h4 style="margin:0 0 5px 0; font-size:1.1rem;"><?= htmlspecialchars($row['activity_name']) ?></h4>
                <div style="font-size: 0.9rem; color: #666; margin-bottom: 10px;">
                    <?= date('d M Y', strtotime($row['activity_date'])) ?> | 
                    <?= date('H:i', strtotime($row['start_time'])) ?> - <?= date('H:i', strtotime($row['end_time'])) ?>
                </div>
                
                <div>
                    <?php if($is_confirmed): ?>
                        <span class="status-badge status-hadir">SUDAH HADIR ✅</span>
                    <?php else: ?>
                        <span class="status-badge status-pending">TERDAFTAR (BELUM ABSEN) ⏳</span>
                        <div style="margin-top:5px; font-size:0.8rem; color:#856404;">Scan lagi untuk konfirmasi kehadiran.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <script>
        let html5QrcodeScanner = null;

        function bukaKamera() {
            document.getElementById('scan-container').style.display = 'block';
            document.getElementById('scan-status').innerText = "Arahkan kamera ke QR Code...";
            
            // Auto scroll ke kamera
            document.getElementById('scan-container').scrollIntoView({ behavior: 'smooth' });

            if(html5QrcodeScanner) { try { html5QrcodeScanner.clear(); } catch(e) {} }

            html5QrcodeScanner = new Html5Qrcode("reader");
            
            const qrboxFunction = function(viewfinderWidth, viewfinderHeight) {
                let minEdgePercentage = 0.70; 
                let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
                return { width: qrboxSize, height: qrboxSize };
            }

            html5QrcodeScanner.start({ facingMode: "environment" }, { fps: 10, qrbox: qrboxFunction, aspectRatio: 1.0 }, onScanSuccess)
            .catch(err => {
                alert("Gagal buka kamera: " + err);
                stopScan();
            });
        }

        function onScanSuccess(decodedText, decodedResult) {
            html5QrcodeScanner.stop().then(() => {
                document.getElementById('scan-status').innerHTML = "<b style='color:#007bff'>Memproses... Tunggu sebentar.</b>";
                
                // Kirim Code ke Backend (Tanpa activity_id spesifik)
                fetch('scan_qrcode.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ barcode: decodedText })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        alert("✅ BERHASIL: " + data.message);
                        location.reload();
                    } else {
                        alert("❌ GAGAL: " + data.message);
                        document.getElementById('scan-status').innerHTML = "<b style='color:red'>" + data.message + "</b>";
                    }
                })
                .catch(err => { alert("Error jaringan: " + err); stopScan(); });
            });
        }

        function stopScan() {
            document.getElementById('scan-container').style.display = 'none';
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    try { html5QrcodeScanner.clear(); } catch(e) {}
                }).catch(err => {
                    try { html5QrcodeScanner.clear(); } catch(e) {}
                }).finally(() => { html5QrcodeScanner = null; });
            }
        }
    </script>
</body>
</html>
