<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Jangan hapus diri sendiri
    $check = mysqli_query($conn, "SELECT * FROM users WHERE id = $id");
    $user = mysqli_fetch_assoc($check);

    if ($user['username'] == $_SESSION['username']) {
        echo "<script>alert('Anda tidak bisa menghapus akun Anda sendiri!'); window.location='dashboard_admin.php';</script>";
        exit();
    }

    mysqli_query($conn, "DELETE FROM users WHERE id = $id");
}

header("Location: dashboard_admin.php");
exit();