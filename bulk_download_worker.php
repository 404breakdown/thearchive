<?php
// bulk_download_worker.php - Background bulk download processing
session_start();
if (!isset($_SESSION['logged_in'])) {
    exit;
}

require_once 'config.php';

$selected_users = $_SESSION['bulk_download_users'] ?? '';
if (empty($selected_users)) exit;

$selected_users = json_decode($selected_users, true);
if (!is_array($selected_users) || empty($selected_users)) exit;

$total_users = count($selected_users);
$archive_base = __DIR__ . '/data/archive/';
$temp_dir = __DIR__ . '/data/temp/';
$progress_file = $temp_dir . 'bulk_download_progress.json';
$zip_filename = 'bulk_download_' . date('Y-m-d_H-i-s') . '.zip';
$zip_path = $temp_dir . $zip_filename;

if (!file_exists($temp_dir)) mkdir($temp_dir, 0755, true);

ignore_user_abort(true);
set_time_limit(600);

file_put_contents($progress_file, json_encode([
    'status' => 'creating',
    'progress' => 10,
    'message' => 'Creating archive...'
]));

// Create master ZIP
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    file_put_contents($progress_file, json_encode(['status' => 'error', 'message' => 'Failed to create ZIP']));
    exit;
}

$processed = 0;
foreach ($selected_users as $user) {
    $processed++;
    $percent = 10 + round(($processed / $total_users) * 80);
    
    file_put_contents($progress_file, json_encode([
        'status' => 'adding',
        'progress' => $percent,
        'message' => "Adding $user... ($processed / $total_users)"
    ]));
    
    $user_dir = $archive_base . $user;
    if (!is_dir($user_dir)) continue;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($user_dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($archive_base));
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

file_put_contents($progress_file, json_encode([
    'status' => 'complete',
    'progress' => 100,
    'message' => 'Download ready!',
    'download_url' => 'data/temp/' . $zip_filename
]));

unset($_SESSION['bulk_download_users']);
sleep(3);
?>