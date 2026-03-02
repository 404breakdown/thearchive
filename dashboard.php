<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$site_name = getSetting('site_name', 'TheArchive');

// Get stats
$archive_base = __DIR__ . '/data/archive/';
$user_count = 0;
$total_images = 0;
$total_videos = 0;
$total_size = 0;
$total_thumb_size = 0;

function get_dir_size($path) {
    $size = 0;
    if (!is_dir($path)) return 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Case-insensitive folder finder
function find_folder($base_path, $folder_name) {
    if (!is_dir($base_path)) return null;
    $dirs = scandir($base_path);
    foreach ($dirs as $dir) {
        if (strcasecmp($dir, $folder_name) === 0 && is_dir($base_path . $dir)) {
            return $dir;
        }
    }
    return null;
}

function count_files_in_folder($path, $extensions) {
    if (!is_dir($path)) return 0;
    $count = 0;
    $files = array_diff(scandir($path), ['.', '..']);
    foreach ($files as $file) {
        if (is_file($path . $file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                $count++;
            }
        }
    }
    return $count;
}

$allowed_img = ['jpg','jpeg','png','gif','webp'];
$allowed_vid = ['mp4','mov','avi','mkv','webm'];

if (is_dir($archive_base)) {
    $total_size = get_dir_size($archive_base);
    $dirs = array_diff(scandir($archive_base), ['.', '..']);
    
    foreach ($dirs as $dir) {
        $dir_path = $archive_base . $dir . '/';
        if (!is_dir($dir_path)) continue;
        
        $user_count++;
        
        // Layout 1: User/Images and User/Videos
        $images_folder = find_folder($dir_path, 'images');
        if ($images_folder) {
            $total_images += count_files_in_folder($dir_path . $images_folder . '/', $allowed_img);
        }
        
        $videos_folder = find_folder($dir_path, 'videos');
        if ($videos_folder) {
            $total_videos += count_files_in_folder($dir_path . $videos_folder . '/', $allowed_vid);
        }
        
        // Layout 2: User/posts/Images and User/posts/Videos
        $posts_folder = find_folder($dir_path, 'posts');
        if ($posts_folder) {
            $posts_path = $dir_path . $posts_folder . '/';
            
            $posts_img_folder = find_folder($posts_path, 'images');
            if ($posts_img_folder) {
                $total_images += count_files_in_folder($posts_path . $posts_img_folder . '/', $allowed_img);
            }
            
            $posts_vid_folder = find_folder($posts_path, 'videos');
            if ($posts_vid_folder) {
                $total_videos += count_files_in_folder($posts_path . $posts_vid_folder . '/', $allowed_vid);
            }
        }
        
        // Count thumbnail sizes
        $thumbs_folder = find_folder($dir_path, 'thumbs');
        if ($thumbs_folder) {
            $total_thumb_size += get_dir_size($dir_path . $thumbs_folder . '/');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'mobile-styles.php'; ?>
</head>
<body>
    <?php 
    $currentPage = 'dashboard';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav">
            <h3 class="mb-0">Dashboard</h3>
        </div>
        
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-people" style="font-size: 3rem; color: #60a5fa;"></i>
                            <h2 class="mt-2 mb-0"><?php echo $user_count; ?></h2>
                            <p class="text-muted mb-0">Archive Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-images" style="font-size: 3rem; color: #34d399;"></i>
                            <h2 class="mt-2 mb-0"><?php echo number_format($total_images); ?></h2>
                            <p class="text-muted mb-0">Total Images</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-play-circle" style="font-size: 3rem; color: #f59e0b;"></i>
                            <h2 class="mt-2 mb-0"><?php echo number_format($total_videos); ?></h2>
                            <p class="text-muted mb-0">Total Videos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-hdd" style="font-size: 3rem; color: #a78bfa;"></i>
                            <h2 class="mt-2 mb-0"><?php echo format_bytes($total_size); ?></h2>
                            <p class="text-muted mb-0">Total Storage</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-link-45deg"></i> Quick Links
                        </div>
                        <div class="card-body">
                            <a href="archive.php" class="btn btn-primary mb-2 w-100">
                                <i class="bi bi-folder2-open"></i> Browse Archive
                            </a>
                            <a href="archive.php" class="btn btn-outline-light w-100">
                                <i class="bi bi-upload"></i> Import ZIP
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-info-circle"></i> About
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-1">Archive Location:</p>
                            <code>data/archive/</code>
                            <p class="text-muted small mt-3 mb-1">Supported Structures:</p>
                            <code class="small">
                                User/Images/<br>
                                User/Videos/<br>
                                User/posts/Images/<br>
                                User/posts/Videos/<br>
                                User/profile/Images/
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
