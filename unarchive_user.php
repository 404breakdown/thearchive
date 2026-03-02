<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$zip_file = $_GET['file'] ?? '';
if (empty($zip_file)) {
    die('No file specified');
}

$archives_dir = __DIR__ . '/data/archives/';
$archive_dir = __DIR__ . '/data/archive/';
$zip_path = $archives_dir . $zip_file;

// Check if ZIP exists
if (!file_exists($zip_path)) {
    die('Archive file not found');
}

// Extract ZIP
$zip = new ZipArchive();
if ($zip->open($zip_path) !== true) {
    die('Failed to open ZIP file');
}

// Extract to archive directory
$zip->extractTo($archive_dir);
$zip->close();

// Delete ZIP file
unlink($zip_path);

// Clean up temp profile images
$user_name = pathinfo($zip_file, PATHINFO_FILENAME);
$temp_dir = __DIR__ . '/data/temp/';
if (is_dir($temp_dir)) {
    foreach (glob($temp_dir . $user_name . '_profile.*') as $temp_file) {
        unlink($temp_file);
    }
}

header('Location: archived.php?success=' . urlencode('User unarchived successfully'));
exit;
