<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

// Check for progress request
if (isset($_GET['check_progress'])) {
    $progress_file = __DIR__ . '/data/temp/bulk_download_progress.json';
    if (file_exists($progress_file)) {
        echo file_get_contents($progress_file);
    } else {
        echo json_encode(['status' => 'idle']);
    }
    exit;
}

// Get selected users from session
$selected_users = $_SESSION['bulk_download_users'] ?? [];
if (empty($selected_users)) {
    header('Location: gallery.php?error=' . urlencode('No users selected'));
    exit;
}

$selected_users = json_decode($selected_users, true);
$total_users = count($selected_users);

$archive_base = __DIR__ . '/data/archive/';
$temp_dir = __DIR__ . '/data/temp/';
$progress_file = $temp_dir . 'bulk_download_progress.json';
$zip_filename = 'bulk_download_' . date('Y-m-d_H-i-s') . '.zip';
$zip_path = $temp_dir . $zip_filename;

if (!file_exists($temp_dir)) mkdir($temp_dir, 0755, true);

file_put_contents($progress_file, json_encode([
    'status' => 'creating',
    'progress' => 10,
    'message' => 'Creating archive...'
]));

// Create master ZIP
$zip = new ZipArchive();
if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Failed to create ZIP file');
}

$processed = 0;
foreach ($selected_users as $user) {
    $processed++;
    $percent = 10 + round(($processed / $total_users) * 80); // 10% to 90%
    
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

// Clean up session
unset($_SESSION['bulk_download_users']);

// Redirect to download page
header('Location: bulk_download_ready.php?file=' . urlencode($zip_filename));
exit;
?>