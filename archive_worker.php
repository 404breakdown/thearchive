<?php
// archive_worker.php - Background archive processing
session_start();
if (!isset($_SESSION['logged_in'])) {
    exit;
}

require_once 'config.php';

$user = $_GET['user'] ?? '';
if (empty($user)) exit;

$user_dir = __DIR__ . '/data/archive/' . $user;
$archives_dir = __DIR__ . '/data/archives';
$temp_dir = __DIR__ . '/data/temp';
$zip_file = $archives_dir . '/' . $user . '.zip';
$progress_file = $temp_dir . '/archive_progress_' . $user . '.json';

if (!file_exists($archives_dir)) mkdir($archives_dir, 0755, true);
if (!file_exists($temp_dir)) mkdir($temp_dir, 0755, true);

if (!is_dir($user_dir)) {
    file_put_contents($progress_file, json_encode(['status' => 'error', 'message' => 'User not found']));
    exit;
}

// Ignore user abort so we complete even if browser closes
ignore_user_abort(true);
set_time_limit(300);

// Initialize progress
file_put_contents($progress_file, json_encode([
    'status' => 'zipping',
    'progress' => 5,
    'message' => 'Starting archive...'
]));

// Count total files
$total_files = 0;
$files_to_zip = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($user_dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files_to_zip as $file) {
    if ($file->isFile()) $total_files++;
}

file_put_contents($progress_file, json_encode([
    'status' => 'zipping',
    'progress' => 10,
    'message' => "Zipping $total_files files..."
]));

// Create ZIP
$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    file_put_contents($progress_file, json_encode(['status' => 'error', 'message' => 'Failed to create ZIP']));
    exit;
}

$processed = 0;
$files_iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($user_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files_iterator as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($user_dir) + 1);
        
        $zip->addFile($filePath, $user . '/' . $relativePath);
        
        $processed++;
        if ($processed % 5 == 0 || $processed == $total_files) {
            $percent = 10 + round(($processed / $total_files) * 70);
            file_put_contents($progress_file, json_encode([
                'status' => 'zipping',
                'progress' => $percent,
                'message' => "Zipped $processed / $total_files files"
            ]));
        }
    }
}

$zip->close();

// Update progress - deleting
file_put_contents($progress_file, json_encode([
    'status' => 'deleting',
    'progress' => 85,
    'message' => 'Removing original folder...'
]));

// Delete original folder
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

deleteDirectory($user_dir);

// Remove from database
$db = getDB();
$stmt = $db->prepare('DELETE FROM archive_users WHERE folder_name = ?');
$stmt->execute([$user]);

// Complete
file_put_contents($progress_file, json_encode([
    'status' => 'complete',
    'progress' => 100,
    'message' => 'Archive complete!'
]));

// Clean up after delay
sleep(3);
if (file_exists($progress_file)) unlink($progress_file);
?>