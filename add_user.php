<?php
session_start();
include 'db.php';

// Cek hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Tambah user manual
if (isset($_POST['add'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (!in_array($role, ['admin', 'Manajemen', 'Pegawai'])) {
        echo "<script>alert('Role tidak valid!');</script>";
        exit();
    }

    if ($password !== $confirm_password) {
        echo "<script>alert('Konfirmasi password tidak cocok!');</script>";
        exit();
    }

    $password_md5 = md5($password);

    $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        echo "<script>alert('Username sudah digunakan!');</script>";
    } else {
        $query = "INSERT INTO users (username, password, role) VALUES ('$username', '$password_md5', '$role')";
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('User berhasil ditambahkan!'); window.location='dashboard_admin.php';</script>";
        } else {
            echo "<script>alert('Gagal menambahkan user!');</script>";
        }
    }
}

// Tambah user dari CSV
if (isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
        $file_tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file_tmp, 'r');

        fgetcsv($handle); // lewati header

        $inserted = 0;
        $skipped = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $username = mysqli_real_escape_string($conn, $data[0]);
            $password = md5($data[1]);
            $role = $data[2];

            if (!in_array($role, ['admin', 'Manajemen', 'Pegawai'])) {
                $skipped++;
                continue;
            }

            $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
            if (mysqli_num_rows($check) > 0) {
                $skipped++;
                continue;
            }

            $query = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
            if (mysqli_query($conn, $query)) {
                $inserted++;
            } else {
                $skipped++;
            }
        }

        fclose($handle);
        echo "<script>alert('Upload selesai! Berhasil: $inserted, Gagal/Duplikat: $skipped'); window.location='dashboard_admin.php';</script>";
    } else {
        echo "<script>alert('Gagal mengunggah file.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            padding: 40px;
        }

        .form-container {
            background-color: white;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 10px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
        }

        input, select, button {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        button {
            background-color: black;
            color: white;
            font-weight: bold;
            border: none;
        }

        button:hover {
            background-color: #333;
        }

        a {
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            text-align: center;
            width: 100%;
            color: #007BFF;
        }

        a:hover {
            text-decoration: underline;
        }

        .separator {
            text-align: center;
            margin: 30px 0;
            font-weight: bold;
        }

        .note {
            font-size: 12px;
            color: #555;
            margin-top: -5px;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Tambah User Manual</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username baru" required>
        <input type="password" name="password" placeholder="Password baru" required>
        <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required>
        <select name="role" required>
            <option value="">Pilih Role</option>
            <option value="admin">Admin</option>
            <option value="Manajemen">Manajemen</option>
            <option value="Pegawai">Pegawai</option>
        </select>
        <button type="submit" name="add">Tambah</button>
    </form>
</div>

<div class="separator">ATAU</div>

<div class="form-container">
    <h2>Upload CSV User</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required>
        <p class="note">* File harus memiliki kolom: username, password, role</p>
        <button type="submit" name="upload_csv">Upload CSV</button>
    </form>
    <a href="template_users.csv" download>📥 Unduh Contoh Format CSV</a>
</div>

<a href="dashboard_admin.php">← Kembali ke Dashboard</a>

</body>
</html>