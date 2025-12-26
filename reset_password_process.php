<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Validasi dan ambil data dari form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Cek apakah password dan konfirmasi password cocok
    if ($new_password !== $confirm_password) {
        header("Location: reset_password_form.php?id=$id&error=password_mismatch");
        exit();
    }

    // Hash password baru dengan MD5
    $hashed_password = md5($new_password);

    // Query untuk update password
    $update_query = "UPDATE users SET password = '$hashed_password' WHERE id = $id";
    if (mysqli_query($conn, $update_query)) {
        // Redirect ke halaman sukses atau ke halaman dashboard
        header("Location: dashboard_admin.php?success=password_reset");
    } else {
        // Jika gagal mereset password
        header("Location: reset_password_form.php?id=$id&error=update_failed");
    }
}
?>
