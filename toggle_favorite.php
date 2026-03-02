<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once 'config.php';

$user = $_GET['user'] ?? '';
if (empty($user)) {
    echo json_encode(['success' => false, 'error' => 'No user specified']);
    exit;
}

$db = getDB();

// Get or create user record
$stmt = $db->prepare('SELECT * FROM archive_users WHERE folder_name = ?');
$stmt->execute([$user]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    // Create user record if it doesn't exist
    $stmt = $db->prepare('INSERT INTO archive_users (folder_name, display_name, is_favorite) VALUES (?, ?, 1)');
    $stmt->execute([$user, $user, 1]);
    echo json_encode(['success' => true, 'is_favorite' => 1]);
    exit;
}

// Toggle favorite
$new_favorite = $user_data['is_favorite'] ? 0 : 1;
$stmt = $db->prepare('UPDATE archive_users SET is_favorite = ? WHERE id = ?');
$stmt->execute([$new_favorite, $user_data['id']]);

echo json_encode(['success' => true, 'is_favorite' => $new_favorite]);
?>