<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;
$success = '';
$error = '';

$db = getDB();
$site_name = getSetting('site_name', 'TrueNAS Dashboard');

$archive_base = __DIR__ . '/data/archive/';

// Archive base should be a Docker volume mount
if (!is_dir($archive_base)) {
    die('Archive directory is not mounted. Check Docker volume configuration.');
}

// Archive base is mounted via Docker volume - don't create it
// if (!file_exists($archive_base)) {
//     mkdir($archive_base, 0755, true);
// }

// Create archive_users table
try {
    $db->exec('CREATE TABLE IF NOT EXISTS archive_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        folder_name TEXT NOT NULL UNIQUE,
        display_name TEXT,
        notes TEXT,
        profile_image TEXT,
        url_1 TEXT,
        url_2 TEXT,
        url_3 TEXT,
        location TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
} catch (PDOException $e) {
    $error = 'Database error';
}

// Helper function to find folder case-insensitive
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

// Handle ZIP import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archive_zip'])) {
    try {
        if ($_FILES['archive_zip']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error');
        }
        
        $zip_path = $_FILES['archive_zip']['tmp_name'];
        $zip = new ZipArchive();
        
        if ($zip->open($zip_path) === true) {
            $imported_users = [];
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (basename($filename)[0] === '.' || substr($filename, -1) === '/') continue;
                
                $parts = explode('/', $filename);
                if (count($parts) < 3) continue;
                
                $folder_username = $parts[0];
                
                // Detect layout: Dave/images or Dave/posts/images
                $layout_type = '';
                $target_folder = '';
                
                if (count($parts) === 3) {
                    // Layout 1: Dave/images/file.jpg or Dave/videos/file.mp4
                    $subfolder = strtolower($parts[1]);
                    $file = $parts[2];
                    
                    if ($subfolder === 'images' || $subfolder === 'videos') {
                        $layout_type = 'direct';
                        $target_folder = $subfolder;
                    } elseif ($subfolder === 'profile') {
                        // Profile image
                        $layout_type = 'profile';
                        $target_folder = 'profile/images';
                    }
                } elseif (count($parts) === 4) {
                    // Layout 2: Dave/posts/images/file.jpg or Dave/profile/images/file.jpg
                    $middle = strtolower($parts[1]);
                    $subfolder = strtolower($parts[2]);
                    $file = $parts[3];
                    
                    if ($middle === 'posts' && ($subfolder === 'images' || $subfolder === 'videos')) {
                        $layout_type = 'posts';
                        $target_folder = 'posts/' . $subfolder;
                    } elseif ($middle === 'profile' && $subfolder === 'images') {
                        $layout_type = 'profile';
                        $target_folder = 'profile/images';
                    }
                }
                
                if (empty($layout_type)) continue;
                
                // Create user folder structure
                $user_dir = $archive_base . $folder_username . '/';
                if (!file_exists($user_dir)) mkdir($user_dir, 0755);
                
                // Create target directory
                $full_target = $user_dir . $target_folder . '/';
                if (!file_exists($full_target)) mkdir($full_target, 0755, true);
                
                // Extract file
                $destination = $full_target . basename($filename);
                $file_content = $zip->getFromIndex($i);
                file_put_contents($destination, $file_content);
                
                $imported_users[$folder_username] = true;
            }
            
            $zip->close();
            
            // Add users to database
            foreach (array_keys($imported_users) as $folder) {
                try {
                    $stmt = $db->prepare('INSERT OR IGNORE INTO archive_users (folder_name, display_name) VALUES (:folder, :display)');
                    $stmt->execute(['folder' => $folder, 'display' => $folder]);
                } catch (PDOException $e) {}
            }
            
            $success = 'Imported ' . count($imported_users) . ' user(s)!';
        } else {
            $error = 'Failed to open ZIP';
        }
    } catch (Exception $e) {
        $error = 'Import error: ' . $e->getMessage();
    }
}

// Scan for username folders
$user_folders = [];

if (is_dir($archive_base)) {
    $dirs = array_diff(scandir($archive_base), ['.', '..']);
    
    foreach ($dirs as $dir) {
        $dir_path = $archive_base . $dir . '/';
        if (!is_dir($dir_path)) continue;
        
        $image_count = 0;
        $video_count = 0;
        $profile_image = null;
        
        // Check for profile image (Dave/profile/images/)
        $profile_folder = find_folder($dir_path, 'profile');
        if ($profile_folder) {
            $profile_images_folder = find_folder($dir_path . $profile_folder . '/', 'images');
            if ($profile_images_folder) {
                $profile_path = $dir_path . $profile_folder . '/' . $profile_images_folder . '/';
                $profile_files = array_diff(scandir($profile_path), ['.', '..']);
                foreach ($profile_files as $pf) {
                    if (is_file($profile_path . $pf)) {
                        $ext = strtolower(pathinfo($pf, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $profile_image = 'data/archive/' . $dir . '/' . $profile_folder . '/' . $profile_images_folder . '/' . $pf;
                            break;
                        }
                    }
                }
            }
        }
        
        // Layout 1: Dave/images and Dave/videos
        $images_folder = find_folder($dir_path, 'images');
        $videos_folder = find_folder($dir_path, 'videos');
        
        if ($images_folder) {
            $images = array_diff(scandir($dir_path . $images_folder), ['.', '..']);
            $image_count += count(array_filter($images, function($f) use ($dir_path, $images_folder) {
                return is_file($dir_path . $images_folder . '/' . $f);
            }));
        }
        
        if ($videos_folder) {
            $videos = array_diff(scandir($dir_path . $videos_folder), ['.', '..']);
            $video_count += count(array_filter($videos, function($f) use ($dir_path, $videos_folder) {
                return is_file($dir_path . $videos_folder . '/' . $f);
            }));
        }
        
        // Layout 2: Dave/posts/images and Dave/posts/videos
        $posts_folder = find_folder($dir_path, 'posts');
        if ($posts_folder) {
            $posts_path = $dir_path . $posts_folder . '/';
            
            $posts_images_folder = find_folder($posts_path, 'images');
            if ($posts_images_folder) {
                $images = array_diff(scandir($posts_path . $posts_images_folder), ['.', '..']);
                $image_count += count(array_filter($images, function($f) use ($posts_path, $posts_images_folder) {
                    return is_file($posts_path . $posts_images_folder . '/' . $f);
                }));
            }
            
            $posts_videos_folder = find_folder($posts_path, 'videos');
            if ($posts_videos_folder) {
                $videos = array_diff(scandir($posts_path . $posts_videos_folder), ['.', '..']);
                $video_count += count(array_filter($videos, function($f) use ($posts_path, $posts_videos_folder) {
                    return is_file($posts_path . $posts_videos_folder . '/' . $f);
                }));
            }
        }
        
        // Only add if has content
        if ($image_count > 0 || $video_count > 0) {
            // Get/create user info
            try {
                $stmt = $db->prepare('SELECT * FROM archive_users WHERE folder_name = :folder');
                $stmt->execute(['folder' => $dir]);
                $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user_info) {
                    $stmt = $db->prepare('INSERT INTO archive_users (folder_name, display_name, profile_image) VALUES (:folder, :display, :profile)');
                    $stmt->execute(['folder' => $dir, 'display' => $dir, 'profile' => $profile_image]);
                    $user_info = ['folder_name' => $dir, 'display_name' => $dir, 'notes' => '', 'profile_image' => $profile_image];
                } elseif ($profile_image && !$user_info['profile_image']) {
                    // Update profile image if found
                    $stmt = $db->prepare('UPDATE archive_users SET profile_image = :profile WHERE folder_name = :folder');
                    $stmt->execute(['profile' => $profile_image, 'folder' => $dir]);
                    $user_info['profile_image'] = $profile_image;
                }
            } catch (PDOException $e) {
                $user_info = ['folder_name' => $dir, 'display_name' => $dir, 'notes' => '', 'profile_image' => $profile_image];
            }
            
            $user_folders[] = [
                'folder' => $dir,
                'display_name' => $user_info['display_name'] ?? $dir,
                'notes' => $user_info['notes'] ?? '',
                'profile_image' => $user_info['profile_image'] ?? $profile_image,
                'image_count' => $image_count,
                'video_count' => $video_count,
                'total' => $image_count + $video_count
            ];
        }
    }
}

usort($user_folders, function($a, $b) {
    return strcmp($a['folder'], $b['folder']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Gallery - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'mobile-styles.php'; ?>
    <style>
        .user-card { cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; background: #374151; }
    </style>
    <style>
        .user-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            background: #374151;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'gallery';
    include 'sidebar.php'; 
    ?>
    
    <!-- Main Content -->
    <div class="content-wrapper">
        <div class="top-nav d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="mb-0"><i class="bi bi-images"></i> Gallery</h3>
            <div class="text-muted"><?php echo count($user_folders); ?> users</div>
        </div>
        
        <div class="container-fluid">
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> <strong>Supported Structures:</strong><br>
                <small>
                • <code>Dave/Images/</code> and <code>Dave/Videos/</code><br>
                • <code>Dave/posts/Images/</code> and <code>Dave/posts/Videos/</code><br>
                • <code>Dave/profile/Images/</code> (used as avatar)<br>
                (Case-insensitive)
                </small>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3 mobile-hide-form">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-upload"></i> Import ZIP</div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <input type="file" class="form-control" name="archive_zip" accept=".zip" required>
                                </div>
                                <div class="alert alert-warning small">
                                    <strong>Supported structures:</strong><br>
                                    <code>
                                    Dave/Images/photo.jpg<br>
                                    Dave/Videos/vid.mp4<br>
                                    </code>
                                    OR<br>
                                    <code>
                                    Dave/posts/Images/photo.jpg<br>
                                    Dave/posts/Videos/vid.mp4<br>
                                    Dave/profile/Images/avatar.jpg
                                    </code>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-upload"></i> Import
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <?php if (empty($user_folders)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 4rem; color: #4b5563;"></i>
                            <p class="text-muted mt-3">No archive users found</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($user_folders as $user): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card user-card" onclick="window.location.href='gallery_view.php?user=<?php echo urlencode($user['folder']); ?>'">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <?php if ($user['profile_image']): ?>
                                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="user-avatar me-3">
                                                <?php else: ?>
                                                    <div class="user-avatar me-3 d-flex align-items-center justify-content-center">
                                                        <i class="bi bi-person-circle" style="font-size: 2rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($user['display_name']); ?></h5>
                                                    <small class="text-muted"><?php echo htmlspecialchars($user['folder']); ?></small>
                                                </div>
                                            </div>
                                            <?php if ($user['notes']): ?>
                                                <p class="small mb-2"><?php echo htmlspecialchars(substr($user['notes'], 0, 80)); ?><?php echo strlen($user['notes']) > 80 ? '...' : ''; ?></p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between mt-2">
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-images"></i> <?php echo $user['image_count']; ?>
                                                </span>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-play-circle"></i> <?php echo $user['video_count']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <button class="fab" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-plus-lg"></i></button>
    
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload"></i> Import ZIP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="file" class="form-control" name="archive_zip" accept=".zip" required>
                        <div class="alert alert-info small mt-3">
                            Both structures supported!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
