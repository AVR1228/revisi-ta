<?php
// db.php - Koneksi ke database
$servername = "localhost"; // Ganti dengan host database Anda jika diperlukan
$username = "root"; // Ganti dengan username database Anda
$password = ""; // Ganti dengan password database Anda
$dbname = "login_system"; // Nama database yang Anda gunakan

// Membuat koneksi
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Memeriksa koneksi
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
