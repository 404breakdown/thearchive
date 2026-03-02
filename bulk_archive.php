<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

// Check for progress request
if (isset($_GET['check_progress'])) {
    $progress_file = __DIR__ . '/data/temp/bulk_archive_progress.json';
    if (file_exists($progress_file)) {
        echo file_get_contents($progress_file);
    } else {
        echo json_encode(['status' => 'idle']);
    }
    exit;
}

// Get selected users from session
$selected_users = $_SESSION['bulk_archive_users'] ?? [];
if (empty($selected_users)) {
    header('Location: gallery.php?error=' . urlencode('No users selected'));
    exit;
}

$selected_users = json_decode($selected_users, true);
$total_users = count($selected_users);

$archive_base = __DIR__ . '/data/archive/';
$archives_dir = __DIR__ . '/data/archives/';
$temp_dir = __DIR__ . '/data/temp/';
$progress_file = $temp_dir . 'bulk_archive_progress.json';

if (!file_exists($archives_dir)) mkdir($archives_dir, 0755, true);
if (!file_exists($temp_dir)) mkdir($temp_dir, 0755, true);

$db = getDB();

// Process each user
$processed = 0;
foreach ($selected_users as $user) {
    $processed++;
    $percent = round(($processed / $total_users) * 100);
    
    file_put_contents($progress_file, json_encode([
        'status' => 'processing',
        'progress' => $percent,
        'message' => "Archiving $user... ($processed / $total_users)",
        'current' => $processed,
        'total' => $total_users
    ]));
    
    $user_dir = $archive_base . $user;
    $zip_file = $archives_dir . $user . '.zip';
    
    if (!is_dir($user_dir)) continue;
    
    // Create ZIP
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
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
        $stmt = $db->prepare('DELETE FROM archive_users WHERE folder_name = ?');
        $stmt->execute([$user]);
    }
}

// Complete
file_put_contents($progress_file, json_encode([
    'status' => 'complete',
    'progress' => 100,
    'message' => "Successfully archived $total_users users!",
    'current' => $total_users,
    'total' => $total_users
]));

// Clean up
unset($_SESSION['bulk_archive_users']);
sleep(2);
unlink($progress_file);

header('Location: gallery.php?success=' . urlencode("Successfully archived $total_users users"));
exit;
?>