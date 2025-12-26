<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Manajemen') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['activity_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing activity_id']);
    exit();
}

$activity_id = intval($_GET['activity_id']);

$query = "SELECT u.username 
          FROM joined_activities ja
          JOIN users u ON ja.user_id = u.id
          WHERE ja.activity_id = $activity_id
          ORDER BY u.username ASC";

$result = mysqli_query($conn, $query);

$users = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = ['username' => $row['username']];
    }
}

echo json_encode(['success' => true, 'users' => $users]);
