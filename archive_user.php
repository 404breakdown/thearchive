<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$user = $_GET['user'] ?? '';
if (empty($user)) {
    die('No user specified');
}

$user_dir = __DIR__ . '/data/archive/' . $user;
$archives_dir = __DIR__ . '/data/archives';
$zip_file = $archives_dir . '/' . $user . '.zip';

// Create archives directory if it doesn't exist
if (!file_exists($archives_dir)) {
    mkdir($archives_dir, 0755, true);
}

// Check if user folder exists
if (!is_dir($user_dir)) {
    die('User folder not found');
}

// Create ZIP archive
$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Failed to create ZIP file');
}

// Add all files recursively
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($user_dir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($user_dir) + 1);
        
        $zip->addFile($filePath, $user . '/' . $relativePath);
    }
}

$zip->close();

// Delete original folder
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

deleteDirectory($user_dir);

// Remove from database
$db = getDB();
$stmt = $db->prepare('DELETE FROM archive_users WHERE folder_name = ?');
$stmt->execute([$user]);

header('Location: gallery.php?success=' . urlencode('User archived successfully'));
exit;
