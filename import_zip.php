<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['zip_file'])) {
    header('Location: gallery.php?error=' . urlencode('No file uploaded'));
    exit;
}

$zip_file = $_FILES['zip_file'];
$archive_base = __DIR__ . '/data/archive/';

// Validate file
if ($zip_file['error'] !== UPLOAD_ERR_OK) {
    header('Location: gallery.php?error=' . urlencode('Upload failed'));
    exit;
}

if (pathinfo($zip_file['name'], PATHINFO_EXTENSION) !== 'zip') {
    header('Location: gallery.php?error=' . urlencode('File must be a ZIP'));
    exit;
}

// Extract ZIP
$zip = new ZipArchive();
if ($zip->open($zip_file['tmp_name']) !== true) {
    header('Location: gallery.php?error=' . urlencode('Failed to open ZIP file'));
    exit;
}

// Extract to archive directory
$zip->extractTo($archive_base);
$num_files = $zip->numFiles;
$zip->close();

// Clean up uploaded file
unlink($zip_file['tmp_name']);

header('Location: gallery.php?success=' . urlencode("Successfully imported ZIP with $num_files files"));
exit;
?>