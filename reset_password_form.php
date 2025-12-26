<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

if (!isset($_GET['id'])) {
    echo "ID tidak valid.";
    exit();
}

$id = intval($_GET['id']);

// Ambil data user berdasarkan ID
$query = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "User tidak ditemukan.";
    exit();
}

// Cegah admin mereset password admin lain
if ($user['role'] === 'admin' && $user['username'] !== $_SESSION['username']) {
    echo "Anda tidak diizinkan untuk mereset password admin lain.";
    exit();
}

// Menampilkan pesan error jika ada
$error_message = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'password_mismatch') {
        $error_message = 'Password dan konfirmasi password tidak cocok.';
    } elseif ($_GET['error'] == 'update_failed') {
        $error_message = 'Gagal mereset password.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
        }
        .reset-container {
            background-color: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }
        .reset-container h2 {
            margin-bottom: 20px;
        }
        .reset-container input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .reset-container button {
            width: 100%;
            padding: 10px;
            background-color: black;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .reset-container button:hover {
            background-color: #333;
        }
        .error-message {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="reset-container">
    <h2>Reset Password untuk: <?= htmlspecialchars($user['username']) ?></h2>
    
    <?php if ($error_message): ?>
        <div class="error-message"><?= $error_message ?></div>
    <?php endif; ?>

    <form action="reset_password_process.php" method="post">
        <input type="hidden" name="id" value="<?= $user['id']; ?>">
        <input type="password" name="new_password" placeholder="Password Baru" required>
        <input type="password" name="confirm_password" placeholder="Konfirmasi Password" required>
        <button type="submit">Reset Password</button>
    </form>
</div>

</body>
</html>