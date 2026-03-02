<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    exit;
}

$filename = $_GET['file'] ?? '';
if (empty($filename)) exit;

$file_path = __DIR__ . '/data/temp/' . basename($filename);

if (file_exists($file_path)) {
    unlink($file_path);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>