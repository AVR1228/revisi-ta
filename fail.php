<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Manajemen') {
    header("Location: login.php");
    exit();
}

include 'db.php';
date_default_timezone_set("Asia/Jakarta");

// Fungsi generate dan update QR Code
function generateAndUpdateQRCode($activity_id, $conn) {
    $activity_id = intval($activity_id);
    $query = "SELECT unique_code FROM activities WHERE id = $activity_id";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $base_code = $row['unique_code'];

        // Generate random 8 karakter hex uppercase (pengganti timestamp)
        $random_code = strtoupper(bin2hex(random_bytes(4)));

        $barcode = $base_code . '-' . $random_code;

        // Update barcode di database
        $update_query = "UPDATE activities SET barcode = '$barcode' WHERE id = $activity_id";
        if (mysqli_query($conn, $update_query)) {
            return $barcode;
        } else {
            error_log("Error updating barcode: " . mysqli_error($conn));
            return false;
        }
    } else {
        error_log("Error retrieving unique code: " . mysqli_error($conn));
        return false;
    }
}

// Proses AJAX generate QR
if (isset($_POST['action']) && $_POST['action'] === 'generate_qr' && isset($_POST['activity_id'])) {
    $activity_id = intval($_POST['activity_id']);
    $barcode = generateAndUpdateQRCode($activity_id, $conn);

    if ($barcode !== false) {
        echo json_encode(['success' => true, 'barcode' => $barcode]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Proses AJAX hapus QR
if (isset($_POST['action']) && $_POST['action'] === 'delete_qr' && isset($_POST['activity_id'])) {
    $activity_id = intval($_POST['activity_id']);
    $update_query = "UPDATE activities SET barcode = NULL WHERE id = $activity_id";
    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Proses hapus kegiatan
if (isset($_POST['delete_btn']) && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $user_id = $_SESSION['user_id'];
    $delete_query = "DELETE FROM activities WHERE id = $delete_id AND user_id = $user_id";
    mysqli_query($conn, $delete_query);
    header("Location: dashboard_Manajemen.php");
    exit();
}

// Proses tambah kegiatan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_name']) && !isset($_POST['action'])) {
    $activity_name = mysqli_real_escape_string($conn, $_POST['activity_name']);
    $activity_date = mysqli_real_escape_string($conn, $_POST['activity_date']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    $unique_code = strtoupper(bin2hex(random_bytes(6)));
    $user_id = $_SESSION['user_id'];

    $query = "INSERT INTO activities (activity_name, activity_date, start_time, end_time, unique_code, user_id) 
              VALUES ('$activity_name', '$activity_date', '$start_time', '$end_time', '$unique_code', '$user_id')";
    if (mysqli_query($conn, $query)) {
        header("Location: dashboard_Manajemen.php");
        exit();
    } else {
        error_log("Error inserting activity: " . mysqli_error($conn));
    }
}

$user_id = $_SESSION['user_id'];
$result = mysqli_query($conn, "SELECT * FROM activities WHERE user_id = '$user_id' ORDER BY activity_date DESC, start_time ASC");
$current_time = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Dashboard Manajemen</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <style>
        table { border-collapse: collapse; width: 100%; max-width: 900px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        button { cursor: pointer; }
        #qrModal, #attendanceModal {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.75);
            display: none;
            align-items: center; justify-content: center;
            z-index: 9999;
            overflow: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        #qrModal canvas {
            background: white; padding: 20px; border-radius: 8px;
        }
        #attendanceModal .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 400px;
            width: 100%;
            max-height: 70vh;
            overflow-y: auto;
            padding: 20px;
            color: #333;
            text-align: left;
        }
        #attendanceModal .close-btn {
            cursor: pointer;
            float: right;
            font-size: 20px;
            margin-bottom: 10px;
        }
        #attendanceModal ul {
            padding-left: 20px;
            margin-top: 0;
        }
    </style>
</head>
<body>

<h1>Dashboard Manajemen</h1>
<p>Selamat datang, <?= htmlspecialchars($_SESSION['username']) ?> | <a href="logout.php">Logout</a></p>

<h2>Tambah Kegiatan</h2>
<form method="post" style="max-width: 400px;">
    <input type="text" name="activity_name" placeholder="Nama Kegiatan" required><br><br>
    <label>Tanggal:</label><br>
    <input type="date" name="activity_date" required><br><br>
    <label>Waktu Mulai (WIB):</label><br>
    <input type="time" name="start_time" required><br><br>
    <label>Waktu Berakhir (WIB):</label><br>
    <input type="time" name="end_time" required><br><br>
    <button type="submit">Tambah Kegiatan</button>
</form>

<h2>Daftar Kegiatan Anda</h2>
<table>
    <thead>
        <tr>
            <th>Aksi</th>
            <th>Nama Kegiatan</th>
            <th>Tanggal</th>
            <th>Waktu Mulai</th>
            <th>Waktu Berakhir</th>
            <th>Kode Unik</th>
            <th>QR Code</th>
            <th>Presensi</th>
        </tr>
    </thead>
    <tbody>
    <?php
    if (mysqli_num_rows($result) == 0) {
        echo "<tr><td colspan='8'>Belum ada kegiatan</td></tr>";
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";

            // Hapus kegiatan
            echo "<td>
                    <form method='post' onsubmit='return confirm(\"Yakin ingin menghapus kegiatan ini?\")'>
                        <input type='hidden' name='delete_id' value='" . $row['id'] . "'>
                        <button type='submit' name='delete_btn'>Hapus</button>
                    </form>
                  </td>";

            echo "<td>" . htmlspecialchars($row['activity_name']) . "</td>";
            echo "<td>" . htmlspecialchars(date("d-m-Y", strtotime($row['activity_date']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['start_time']) . "</td>";
            echo "<td>" . htmlspecialchars($row['end_time']) . "</td>";
            echo "<td>" . htmlspecialchars($row['unique_code']) . "</td>";
            echo "<td>";

              // Tampilkan tombol generate QR code jika waktu mulai sudah lewat
            $start_datetime = $row['activity_date'] . ' ' . $row['start_time'];
            if ($start_datetime <= $current_time) {
                echo "<button class='show-qr-btn' data-activity-id='{$row['id']}'>Tampilkan QR Code</button><br>";
                echo "<div id='qrcode-{$row['id']}'></div>";
            } else {
                echo "Belum Dimulai";
            }

            echo "</td>";

            // Presensi (jumlah yang hadir)
            $activity_id = intval($row['id']);
            $check_attendance = mysqli_query($conn, "SELECT COUNT(*) as total FROM joined_activities WHERE activity_id = $activity_id");
            $attendance = mysqli_fetch_assoc($check_attendance);
            $jumlah_hadir = intval($attendance['total']);
            echo "<td>";
            echo $jumlah_hadir . " hadir";
            if ($jumlah_hadir > 0) {
                echo " <button class='lihat-peserta-btn' data-activity-id='{$row['id']}'>Lihat Peserta</button>";
            }
            echo "</td>";
            echo "</tr>";
        }
    }
    ?>
    </tbody>
</table>

<!-- Modal QR Code -->
<div id="qrModal" onclick="this.style.display='none'"></div>

<!-- Modal Attendance -->
<div id="attendanceModal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('attendanceModal').style.display='none'">&times;</span>
        <h3>Daftar Peserta Hadir</h3>
        <ul id="attendanceList"></ul>
    </div>
</div>

<script>
// Event tombol tampilkan QR
document.querySelectorAll('.show-qr-btn').forEach(button => {
    button.addEventListener('click', () => {
        const activityId = button.getAttribute('data-activity-id');
        const qrContainer = document.getElementById('qrcode-' + activityId);
        button.style.display = 'none';
        qrContainer.textContent = 'Loading QR Code...';

        fetch('dashboard_Manajemen.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'generate_qr',
                activity_id: activityId
            })
        })
        .then(res => res.json())
.then(data => {
    if (data.success) {
        qrContainer.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.style.display = 'flex';
        wrapper.style.alignItems = 'center';
        wrapper.style.gap = '10px';

        // Tombol hapus QR Code
        const deleteBtn = document.createElement('button');
        deleteBtn.textContent = 'Hapus QR Code';
        deleteBtn.onclick = () => {
            if (confirm('Yakin ingin menghapus QR Code ini dari database?')) {
                fetch('dashboard_Manajemen.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'delete_qr',
                        activity_id: activityId
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        qrContainer.innerHTML = '';
                        button.textContent = 'Tampilkan QR Code';
                        button.style.display = '';
                        alert('QR Code berhasil dihapus dari database.');
                    } else {
                        alert('Gagal menghapus QR Code.');
                    }
                })
                .catch(() => alert('Terjadi kesalahan saat menghapus QR Code.'));
            }
        };

        // Generate QR Code ke canvas
        const canvas = document.createElement('canvas');
        const qrUrl = 'https://yourdomain.com/attendance.php?code=' + encodeURIComponent(data.barcode);
        QRCode.toCanvas(canvas, qrUrl, { width: 100 }, error => {
            if (error) {
                qrContainer.textContent = 'Gagal membuat QR Code';
                button.style.display = '';
                return;
            }

                    // Tombol untuk memperbesar QR Code di modal
                    const enlargeBtn = document.createElement('button');
                    enlargeBtn.textContent = 'Perbesar QR Code';
                    enlargeBtn.onclick = () => openModalWithQRCode(qrUrl);

                    wrapper.appendChild(deleteBtn);
                    wrapper.appendChild(canvas);
                    wrapper.appendChild(enlargeBtn);
                    qrContainer.appendChild(wrapper);
                });
    } else {
        qrContainer.textContent = 'Gagal memuat QR Code';
        button.style.display = '';
    }
})
        .catch(err => {
            qrContainer.textContent = 'Terjadi kesalahan jaringan.';
            button.style.display = '';
            console.error(err);
        });
    });
});

// Event tombol lihat peserta
document.querySelectorAll('.lihat-peserta-btn').forEach(button => {
    button.addEventListener('click', () => {
        const activityId = button.getAttribute('data-activity-id');
        const modal = document.getElementById('attendanceModal');
        const list = document.getElementById('attendanceList');
        list.innerHTML = '<li>Loading...</li>';
        modal.style.display = 'flex';

        fetch('get_attendance.php?activity_id=' + encodeURIComponent(activityId))
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.users)) {
                if (data.users.length === 0) {
                    list.innerHTML = '<li>Belum ada peserta yang hadir.</li>';
                } else {
                    list.innerHTML = '';
                    data.users.forEach(user => {
                        const li = document.createElement('li');
                        li.textContent = user.username;
                        list.appendChild(li);
                    });
                }
            } else {
                list.innerHTML = '<li>Gagal memuat data peserta.</li>';
            }
        })
        .catch(() => {
            list.innerHTML = '<li>Terjadi kesalahan jaringan saat memuat data peserta.</li>';
        });
    });
});
</script>

</body>
</html>