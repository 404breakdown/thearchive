<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$success = $_GET['success'] ?? '';

$db = getDB();
$site_name = getSetting('site_name', 'TheArchive');

$archives_dir = __DIR__ . '/data/archives/';

// Get all archived users (ZIP files)
$archived_users = [];
if (is_dir($archives_dir)) {
    foreach (scandir($archives_dir) as $file) {
        if ($file === '.' || $file === '..') continue;
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'zip') continue;
        
        $zip_path = $archives_dir . $file;
        $user_name = pathinfo($file, PATHINFO_FILENAME);
        
        // Try to get profile image from ZIP
        $profile_image = null;
        $zip = new ZipArchive();
        if ($zip->open($zip_path) === true) {
            // Look for profile image
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (stripos($filename, 'profile') !== false && stripos($filename, 'Images') !== false) {
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        // Extract first profile image to temp
                        $temp_dir = __DIR__ . '/data/temp/';
                        if (!file_exists($temp_dir)) mkdir($temp_dir, 0755, true);
                        
                        $profile_image = 'data/temp/' . $user_name . '_profile.' . $ext;
                        file_put_contents(__DIR__ . '/' . $profile_image, $zip->getFromName($filename));
                        break;
                    }
                }
            }
            $zip->close();
        }
        
        $archived_users[] = [
            'name' => $user_name,
            'zip_file' => $file,
            'size' => filesize($zip_path),
            'modified' => filemtime($zip_path),
            'profile_image' => $profile_image
        ];
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    else return $bytes . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'mobile-styles.php'; ?>
    <style>
        .user-card { cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .user-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: #374151; }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'archive';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="mb-0"><i class="bi bi-archive"></i> Archived Users</h3>
            <div class="text-muted"><?php echo count($archived_users); ?> archived</div>
        </div>
        
        <div class="container-fluid">
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($archived_users)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-archive" style="font-size: 4rem; color: #4b5563;"></i>
                    <p class="text-muted mt-3">No archived users</p>
                    <p class="text-muted small">Archived users will appear here</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($archived_users as $user): ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                            <div class="card user-card h-100">
                                <div class="card-body text-center">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" class="user-avatar mb-3" alt="Profile">
                                    <?php else: ?>
                                        <div class="user-avatar mx-auto mb-3 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-person-fill" style="font-size: 2rem; color: #9ca3af;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h6 class="mb-2"><?php echo htmlspecialchars($user['name']); ?></h6>
                                    
                                    <div class="text-muted small mb-3">
                                        <div><i class="bi bi-hdd"></i> <?php echo formatBytes($user['size']); ?></div>
                                        <div><i class="bi bi-calendar"></i> <?php echo date('M d, Y', $user['modified']); ?></div>
                                    </div>
                                    
                                    <a href="data/archives/<?php echo urlencode($user['zip_file']); ?>" class="btn btn-primary btn-sm w-100 mb-2" download>
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                    
                                    <button class="btn btn-success btn-sm w-100" onclick="if(confirm('Unarchive <?php echo htmlspecialchars($user['name']); ?>? This will extract and restore to gallery.')) { window.location.href='unarchive_user.php?file=<?php echo urlencode($user['zip_file']); ?>'; }">
                                        <i class="bi bi-arrow-counterclockwise"></i> Unarchive
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
