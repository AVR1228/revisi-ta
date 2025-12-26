<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

include 'db.php';

$searchAdmin = $_GET['search_admin'] ?? '';
$searchTypeA = $_GET['search_typea'] ?? '';
$searchTypeB = $_GET['search_typeb'] ?? '';
$showAllAdmin = isset($_GET['show_all_admin']);
$showAllTypeA = isset($_GET['show_all_typea']);
$showAllTypeB = isset($_GET['show_all_typeb']);

$query = "SELECT id, username, role FROM users ORDER BY username ASC";
$result = mysqli_query($conn, $query);

$admins = $typeA = $typeB = [];
while ($row = mysqli_fetch_assoc($result)) {
    switch ($row['role']) {
        case 'admin': $admins[] = $row; break;
        case 'Manajemen': $typeA[] = $row; break;
        case 'Pegawai': $typeB[] = $row; break;
    }
}

$filteredAdmins = array_filter($admins, fn($user) => stripos($user['username'], $searchAdmin) !== false);
$filteredTypeA = array_filter($typeA, fn($user) => stripos($user['username'], $searchTypeA) !== false);
$filteredTypeB = array_filter($typeB, fn($user) => stripos($user['username'], $searchTypeB) !== false);

if (!$showAllAdmin) $filteredAdmins = array_slice($filteredAdmins, 0, 10);
if (!$showAllTypeA) $filteredTypeA = array_slice($filteredTypeA, 0, 10);
if (!$showAllTypeB) $filteredTypeB = array_slice($filteredTypeB, 0, 10);

$admin_count = count($admins);
$can_delete_admin = $admin_count > 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard</title>
  <style>
    body { font-family: Arial, sans-serif; background-color: #f0f0f0; margin: 0; }
    .navbar { background-color: black; color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
    .navbar a { color: white; text-decoration: none; margin-left: 15px; font-weight: bold; }
    .content { padding: 40px; max-width: 1000px; margin: auto; background-color: white; border-radius: 10px; margin-top: 30px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); }
    h1, h2, h3 { margin-top: 0; }
    ul { list-style: none; padding: 0; }
    li { padding: 8px 0; display: flex; justify-content: space-between; align-items: center; }
    .columns { display: flex; gap: 30px; margin-top: 30px; }
    .column { flex: 1; background-color: #fafafa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; }
    .actions { display: flex; gap: 10px; align-items: center; }
    .delete-link { color: red; text-decoration: none; font-weight: bold; }
    .reset-link { color: orange; text-decoration: none; font-weight: bold; }
    a:hover { text-decoration: underline; }
    .search-form { margin-bottom: 15px; }
    .search-form input[type="text"] { width: 100%; padding: 6px; border-radius: 5px; border: 1px solid #ccc; }
    .see-all-btn { display: inline-block; margin-top: 10px; text-decoration: none; color: blue; font-weight: bold; }
  </style>
</head>
<body>
  <div class="navbar">
    <h1>Dashboard</h1>
    <div>
      <a href="#">Profil</a>
      <a href="logout.php">Logout</a>
    </div>
  </div>

  <div class="content">
    <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['username']); ?>!</h2>
    <p>Anda login sebagai <strong>Admin</strong>.</p>
    <ul><li><a href="add_user.php">➕ Tambah User Baru</a></li></ul>
    <hr>

    <div class="columns">
      <!-- Admin Column -->
      <div class="column">
        <h3>Admin</h3>
        <form class="search-form" method="GET">
          <input type="text" name="search_admin" placeholder="Cari User" value="<?= htmlspecialchars($searchAdmin); ?>">
        </form>
        <ul>
          <?php foreach ($filteredAdmins as $user): ?>
            <li>
              <?= htmlspecialchars($user['username']); ?>
              <div class="actions">
                <!-- Admin yang login dapat mereset password admin lain dan dirinya sendiri -->
                <?php if ($user['username'] === $_SESSION['username'] || $_SESSION['role'] === 'admin'): ?>
                  <a class="reset-link" href="reset_password_form.php?id=<?= $user['id']; ?>">🔁</a>
                <?php endif; ?>
                <!-- Hanya admin yang dapat menghapus admin lain -->
                <?php if ($user['username'] !== $_SESSION['username'] && $can_delete_admin): ?>
                  <a class="delete-link" href="delete_user.php?id=<?= $user['id']; ?>" onclick="return confirm('Hapus admin ini?')">🗑️</a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$showAllAdmin && count($admins) > 10): ?>
          <a class="see-all-btn" href="?show_all_admin=1&search_admin=<?= urlencode($searchAdmin); ?>">Lihat Semua Admin</a>
        <?php elseif ($showAllAdmin): ?>
          <a class="see-all-btn" href="?search_admin=<?= urlencode($searchAdmin); ?>">Sembunyikan</a>
        <?php endif; ?>
      </div>

      <!-- Manajemen Column -->
      <div class="column">
        <h3>Manajemen</h3>
        <form class="search-form" method="GET">
          <input type="text" name="search_typea" placeholder="Cari User" value="<?= htmlspecialchars($searchTypeA); ?>">
        </form>
        <ul>
          <?php foreach ($filteredTypeA as $user): ?>
            <li>
              <?= htmlspecialchars($user['username']); ?>
              <div class="actions">
                <!-- User Manajemen dapat mereset password dirinya sendiri -->
                <?php if ($user['username'] === $_SESSION['username'] || $_SESSION['role'] === 'admin'): ?>
                  <a class="reset-link" href="reset_password_form.php?id=<?= $user['id']; ?>">🔁</a>
                <?php endif; ?>
                <a class="delete-link" href="delete_user.php?id=<?= $user['id']; ?>" onclick="return confirm('Hapus user ini?')">🗑️</a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$showAllTypeA && count($typeA) > 10): ?>
          <a class="see-all-btn" href="?show_all_typea=1&search_typea=<?= urlencode($searchTypeA); ?>">Lihat Semua Manajemen</a>
        <?php elseif ($showAllTypeA): ?>
          <a class="see-all-btn" href="?search_typea=<?= urlencode($searchTypeA); ?>">Sembunyikan</a>
        <?php endif; ?>
      </div>

      <!-- Pegawai Column -->
      <div class="column">
        <h3>Pegawai</h3>
        <form class="search-form" method="GET">
          <input type="text" name="search_typeb" placeholder="Cari User" value="<?= htmlspecialchars($searchTypeB); ?>">
        </form>
        <ul>
          <?php foreach ($filteredTypeB as $user): ?>
            <li>
              <?= htmlspecialchars($user['username']); ?>
              <div class="actions">
                <!-- User Pegawai dapat mereset password dirinya sendiri -->
                <?php if ($user['username'] === $_SESSION['username'] || $_SESSION['role'] === 'admin'): ?>
                  <a class="reset-link" href="reset_password_form.php?id=<?= $user['id']; ?>">🔁</a>
                <?php endif; ?>
                <a class="delete-link" href="delete_user.php?id=<?= $user['id']; ?>" onclick="return confirm('Hapus user ini?')">🗑️</a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (!$showAllTypeB && count($typeB) > 10): ?>
          <a class="see-all-btn" href="?show_all_typeb=1&search_typeb=<?= urlencode($searchTypeB); ?>">Lihat Semua Pegawai</a>
        <?php elseif ($showAllTypeB): ?>
          <a class="see-all-btn" href="?search_typeb=<?= urlencode($searchTypeB); ?>">Sembunyikan</a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</body>
</html>