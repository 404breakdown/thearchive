<?php
// get_users.php - Get list of archive users
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');

$archive_base = __DIR__ . '/data/archive/';
$users = [];

if (is_dir($archive_base)) {
    $dirs = array_diff(scandir($archive_base), ['.', '..']);
    foreach ($dirs as $dir) {
        if (is_dir($archive_base . $dir)) {
            $users[] = $dir;
        }
    }
}

echo json_encode(['users' => $users]);
