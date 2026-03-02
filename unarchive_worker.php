<?php
// unarchive_worker.php - Background unarchive processing
session_start();
if (!isset($_SESSION['logged_in'])) {
    exit;
}

require_once 'config.php';

$zip_file = $_GET['file'] ?? '';
if (empty($zip_file)) exit;

$archives_dir = __DIR__ . '/data/archives/';
$archive_dir = __DIR__ . '/data/archive/';
$temp_dir = __DIR__ . '/data/temp/';
$zip_path = $archives_dir . $zip_file;
$user_name = pathinfo($zip_file, PATHINFO_FILENAME);
$progress_file = $temp_dir . 'unarchive_progress_' . $user_name . '.json';

if (!file_exists($zip_path)) {
    file_put_contents($progress_file, json_encode(['status' => 'error', 'message' => 'ZIP not found']));
    exit;
}

if (!file_exists($temp_dir)) mkdir($temp_dir, 0755, true);

ignore_user_abort(true);
set_time_limit(300);

// Initialize progress
file_put_contents($progress_file, json_encode([
    'status' => 'extracting',
    'progress' => 10,
    'message' => 'Opening archive...'
]));

// Extract ZIP
$zip = new ZipArchive();
if ($zip->open($zip_path) !== true) {
    file_put_contents($progress_file, json_encode(['status' => 'error', 'message' => 'Failed to open ZIP']));
    exit;
}

$total_files = $zip->numFiles;

file_put_contents($progress_file, json_encode([
    'status' => 'extracting',
    'progress' => 30,
    'message' => "Extracting $total_files files..."
]));

$zip->extractTo($archive_dir);
$zip->close();

file_put_contents($progress_file, json_encode([
    'status' => 'cleaning',
    'progress' => 80,
    'message' => 'Cleaning up...'
]));

// Delete ZIP file
unlink($zip_path);

// Clean up temp profile images
if (is_dir($temp_dir)) {
    foreach (glob($temp_dir . $user_name . '_profile.*') as $temp_file) {
        unlink($temp_file);
    }
}

// Complete
file_put_contents($progress_file, json_encode([
    'status' => 'complete',
    'progress' => 100,
    'message' => 'Unarchive complete!'
]));

sleep(3);
if (file_exists($progress_file)) unlink($progress_file);
?>