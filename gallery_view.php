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

$archive_user = $_GET['user'] ?? '';
if (empty($archive_user)) {
    header('Location: gallery.php');
    exit;
}

$archive_base = __DIR__ . '/data/archive/';
$user_dir = $archive_base . $archive_user . '/';

if (!is_dir($user_dir)) {
    header('Location: gallery.php');
    exit;
}

// Thumbs directory
$thumbs_dir = $user_dir . 'thumbs/';
if (!file_exists($thumbs_dir)) mkdir($thumbs_dir, 0755, true);

// Helper function
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

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $display_name = trim($_POST['display_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $url_1 = trim($_POST['url_1'] ?? '');
    $url_2 = trim($_POST['url_2'] ?? '');
    $url_3 = trim($_POST['url_3'] ?? '');
    $location = trim($_POST['location'] ?? '');
    
    try {
        $stmt = $db->prepare('UPDATE archive_users SET 
            display_name = :display, 
            notes = :notes, 
            url_1 = :url_1,
            url_2 = :url_2,
            url_3 = :url_3,
            location = :location
            WHERE folder_name = :folder');
        $stmt->execute([
            'display' => $display_name, 
            'notes' => $notes, 
            'url_1' => $url_1,
            'url_2' => $url_2,
            'url_3' => $url_3,
            'location' => $location,
            'folder' => $archive_user
        ]);
        $success = 'Info updated!';
    } catch (PDOException $e) {}
}

// Get user info
try {
    $stmt = $db->prepare('SELECT * FROM archive_users WHERE folder_name = :folder');
    $stmt->execute(['folder' => $archive_user]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_info) {
        $user_info = ['folder_name' => $archive_user, 'display_name' => $archive_user, 'notes' => '', 'url_1' => '', 'url_2' => '', 'url_3' => '', 'location' => '', 'profile_image' => null];
    }
} catch (PDOException $e) {
    $user_info = ['folder_name' => $archive_user, 'display_name' => $archive_user, 'notes' => '', 'url_1' => '', 'url_2' => '', 'url_3' => '', 'location' => '', 'profile_image' => null];
}

// Get profile image if not in database yet
if (!$user_info['profile_image']) {
    $profile_folder = find_folder($user_dir, 'profile');
    if ($profile_folder) {
        $profile_images_folder = find_folder($user_dir . $profile_folder . '/', 'images');
        if ($profile_images_folder) {
            $profile_path = $user_dir . $profile_folder . '/' . $profile_images_folder . '/';
            $profile_files = array_diff(scandir($profile_path), ['.', '..']);
            foreach ($profile_files as $pf) {
                if (is_file($profile_path . $pf)) {
                    $ext = strtolower(pathinfo($pf, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $user_info['profile_image'] = 'data/archive/' . $archive_user . '/' . $profile_folder . '/' . $profile_images_folder . '/' . $pf;
                        break;
                    }
                }
            }
        }
    }
}

// Thumbnail functions
function create_thumb($source, $dest, $max = 200) {
    if (file_exists($dest)) return true;
    if (!function_exists('imagecreatetruecolor')) return false; // GD not installed
    $info = @getimagesize($source);
    if (!$info) return false;
    list($w, $h, $type) = $info;
    $ratio = min($max/$w, $max/$h);
    $nw = round($w*$ratio); $nh = round($h*$ratio);
    $thumb = imagecreatetruecolor($nw, $nh);
    switch($type) {
        case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($source); break;
        case IMAGETYPE_PNG: $src = imagecreatefrompng($source); imagealphablending($thumb,false); imagesavealpha($thumb,true); break;
        case IMAGETYPE_GIF: $src = imagecreatefromgif($source); break;
        case IMAGETYPE_WEBP: $src = imagecreatefromwebp($source); break;
        default: return false;
    }
    imagecopyresampled($thumb,$src,0,0,0,0,$nw,$nh,$w,$h);
    switch($type) {
        case IMAGETYPE_JPEG: imagejpeg($thumb,$dest,70); break;
        case IMAGETYPE_PNG: imagepng($thumb,$dest,8); break;
        case IMAGETYPE_GIF: imagegif($thumb,$dest); break;
        case IMAGETYPE_WEBP: imagewebp($thumb,$dest,70); break;
    }
    imagedestroy($thumb); imagedestroy($src);
    return true;
}

function create_video_thumb($vid, $thumb) {
    if (file_exists($thumb)) return true;
    $ffmpeg = shell_exec('which ffmpeg 2>/dev/null');
    if (empty($ffmpeg)) return false;
    exec("ffmpeg -i ".escapeshellarg($vid)." -ss 00:00:01 -vframes 1 -vf scale=200:-1 ".escapeshellarg($thumb)." 2>&1");
    return file_exists($thumb);
}

// Scan for images and videos from BOTH layouts
$images = [];
$videos = [];

$allowed_img = ['jpg','jpeg','png','gif','webp'];
$allowed_vid = ['mp4','mov','webm']; // Mobile-friendly formats only

// Layout 1: Dave/Images and Dave/Videos
$images_folder = find_folder($user_dir, 'images');
if ($images_folder) {
    $img_path = $user_dir . $images_folder . '/';
    foreach (array_diff(scandir($img_path), ['.','..']) as $file) {
        if (is_file($img_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_img)) {
            $thumb = $thumbs_dir . $file . '.jpg';
            $images[] = [
                'filename' => $file,
                'path' => 'data/archive/'.$archive_user.'/'.$images_folder.'/'.$file,
                'thumb' => file_exists($thumb) ? 'data/archive/'.$archive_user.'/thumbs/'.$file.'.jpg' : null,
                'size' => filesize($img_path.$file)
            ];
        }
    }
}

$videos_folder = find_folder($user_dir, 'videos');
if ($videos_folder) {
    $vid_path = $user_dir . $videos_folder . '/';
    foreach (array_diff(scandir($vid_path), ['.','..']) as $file) {
        if (is_file($vid_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_vid)) {
            $thumb = $thumbs_dir . $file . '.jpg';
            $videos[] = [
                'filename' => $file,
                'path' => 'data/archive/'.$archive_user.'/'.$videos_folder.'/'.$file,
                'thumb' => file_exists($thumb) ? 'data/archive/'.$archive_user.'/thumbs/'.$file.'.jpg' : null,
                'size' => filesize($vid_path.$file)
            ];
        }
    }
}

// Layout 2: Dave/posts/Images and Dave/posts/Videos
$posts_folder = find_folder($user_dir, 'posts');
if ($posts_folder) {
    $posts_path = $user_dir . $posts_folder . '/';
    
    $posts_img_folder = find_folder($posts_path, 'images');
    if ($posts_img_folder) {
        $img_path = $posts_path . $posts_img_folder . '/';
        foreach (array_diff(scandir($img_path), ['.','..']) as $file) {
            if (is_file($img_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_img)) {
                $thumb = $thumbs_dir . 'posts_' . $file . '.jpg';
                $images[] = [
                    'filename' => $file,
                    'path' => 'data/archive/'.$archive_user.'/'.$posts_folder.'/'.$posts_img_folder.'/'.$file,
                    'thumb' => file_exists($thumb) ? 'data/archive/'.$archive_user.'/thumbs/posts_'.$file.'.jpg' : null,
                    'size' => filesize($img_path.$file)
                ];
            }
        }
    }
    
    $posts_vid_folder = find_folder($posts_path, 'videos');
    if ($posts_vid_folder) {
        $vid_path = $posts_path . $posts_vid_folder . '/';
        foreach (array_diff(scandir($vid_path), ['.','..']) as $file) {
            if (is_file($vid_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_vid)) {
                $thumb = $thumbs_dir . 'posts_' . $file . '.jpg';
                $videos[] = [
                    'filename' => $file,
                    'path' => 'data/archive/'.$archive_user.'/'.$posts_folder.'/'.$posts_vid_folder.'/'.$file,
                    'thumb' => file_exists($thumb) ? 'data/archive/'.$archive_user.'/thumbs/posts_'.$file.'.jpg' : null,
                    'size' => filesize($vid_path.$file)
                ];
            }
        }
    }
}

// Count missing thumbnails - only check files that actually don't have thumbnails
$missing_thumbs = 0;
$total_files = 0;

// If we just completed generation (URL parameter), skip detection on this load
$just_completed = isset($_GET['thumbs_done']) && $_GET['thumbs_done'] === '1';

if (!$just_completed) {
    foreach ($images as $img) {
        $total_files++;
        if (!$img['thumb']) {
            $missing_thumbs++;
        }
    }
    foreach ($videos as $vid) {
        $total_files++;
        if (!$vid['thumb']) {
            $missing_thumbs++;
        }
    }
}

function format_bytes($b) {
    if($b>=1073741824) return number_format($b/1073741824,2).' GB';
    elseif($b>=1048576) return number_format($b/1048576,2).' MB';
    elseif($b>=1024) return number_format($b/1024,2).' KB';
    else return $b.' bytes';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?php echo htmlspecialchars($user_info['display_name']); ?> - Archive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'mobile-styles.php'; ?>
    <style>
        .gallery-item { position:relative; overflow:hidden; border-radius:8px; aspect-ratio:1; background:#1a1a1a; cursor:pointer; }
        .gallery-item img { width:100%; height:100%; object-fit:cover; transition:transform 0.3s; }
        .gallery-item:hover img { transform:scale(1.05); }
        .video-indicator { position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); padding:5px 10px; border-radius:4px; }
        .user-sidebar { position:sticky; top:20px; }
        
        /* Modal improvements */
        .modal-fullscreen-lg-down .modal-header {
            background: rgba(0, 0, 0, 0.9);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .modal-fullscreen-lg-down .modal-footer {
            background: rgba(0, 0, 0, 0.9);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Make close button more visible on mobile */
        .btn-close-white {
            filter: brightness(0) invert(1);
        }
        
        /* Navigation arrow hover effects */
        .modal-body button.btn-dark:hover {
            opacity: 1 !important;
            transform: translateY(-50%) scale(1.1);
        }
        
        @media (max-width:768px) { 
            .user-sidebar { position:relative; top:0; }
            
            /* Ensure close button is always visible on mobile */
            .modal-header .btn-close {
                font-size: 1.5rem;
                padding: 1rem;
            }
            
            /* Make navigation arrows easier to tap on mobile */
            .modal-body button.btn-dark {
                width: 60px !important;
                height: 60px !important;
            }
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'archive';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <a href="gallery.php" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-left"></i> Back</a>
                <h3 class="d-inline mb-0"><?php echo htmlspecialchars($user_info['display_name']); ?></h3>
            </div>
            <div class="text-muted"><?php echo count($images)+count($videos); ?> items</div>
        </div>
        
        <div class="container-fluid">
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($missing_thumbs > 0): ?>
                <div class="alert alert-info" id="thumb-progress-alert">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <i class="bi bi-hourglass-split"></i> <strong>Generating Thumbnails</strong>
                        </div>
                        <div class="small">
                            <span id="thumb-current">0</span> / <span id="thumb-total"><?php echo count($images)+count($videos); ?></span>
                        </div>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div id="thumb-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div class="small text-muted mt-2">Please wait while thumbnails are generated...</div>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="card user-sidebar">
                        <div class="card-header"><i class="bi bi-person-circle"></i> User Profile</div>
                        <div class="card-body">
                            <!-- Profile Image -->
                            <?php if ($user_info['profile_image']): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo htmlspecialchars($user_info['profile_image']); ?>" 
                                         class="rounded-circle" 
                                         style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #374151;"
                                         alt="Profile">
                                </div>
                            <?php else: ?>
                                <div class="text-center mb-3">
                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px; background: #374151; border: 3px solid #4b5563;">
                                        <i class="bi bi-person-fill" style="font-size: 4rem; color: #9ca3af;"></i>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <h5 class="text-center mb-3"><?php echo htmlspecialchars($user_info['display_name']); ?></h5>
                            
                            <!-- Display Info (when not editing) -->
                            <div id="info-display">
                                <?php if (!empty($user_info['location'])): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-geo-alt-fill text-danger"></i>
                                        <small><?php echo htmlspecialchars($user_info['location']); ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user_info['url_1'])): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-link-45deg text-primary"></i>
                                        <a href="<?php echo htmlspecialchars($user_info['url_1']); ?>" target="_blank" class="small text-decoration-none">
                                            <?php 
                                            $url_display = parse_url($user_info['url_1'], PHP_URL_HOST) ?: $user_info['url_1'];
                                            echo htmlspecialchars(substr($url_display, 0, 25)) . (strlen($url_display) > 25 ? '...' : '');
                                            ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user_info['url_2'])): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-link-45deg text-primary"></i>
                                        <a href="<?php echo htmlspecialchars($user_info['url_2']); ?>" target="_blank" class="small text-decoration-none">
                                            <?php 
                                            $url_display = parse_url($user_info['url_2'], PHP_URL_HOST) ?: $user_info['url_2'];
                                            echo htmlspecialchars(substr($url_display, 0, 25)) . (strlen($url_display) > 25 ? '...' : '');
                                            ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($user_info['url_3'])): ?>
                                    <div class="mb-2">
                                        <i class="bi bi-link-45deg text-primary"></i>
                                        <a href="<?php echo htmlspecialchars($user_info['url_3']); ?>" target="_blank" class="small text-decoration-none">
                                            <?php 
                                            $url_display = parse_url($user_info['url_3'], PHP_URL_HOST) ?: $user_info['url_3'];
                                            echo htmlspecialchars(substr($url_display, 0, 25)) . (strlen($url_display) > 25 ? '...' : '');
                                            ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($user_info['notes']): ?>
                                    <div class="mt-3">
                                        <small class="text-muted d-block mb-1">Notes:</small>
                                        <small><?php echo nl2br(htmlspecialchars($user_info['notes'])); ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-outline-light btn-sm w-100 mt-3" onclick="toggleEdit()">
                                    <i class="bi bi-pencil"></i> Edit Info
                                </button>
                                
                                <button type="button" class="btn btn-warning btn-sm w-100 mt-2" onclick="if(confirm('Archive this user? This will zip all files and remove from gallery.')) { window.location.href='archive_user.php?user=<?php echo urlencode($archive_user); ?>'; }">
                                    <i class="bi bi-archive"></i> Archive User
                                </button>
                            </div>
                            
                            <!-- Edit Form (hidden by default) -->
                            <form method="POST" id="info-edit" style="display: none;">
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Display Name</label>
                                    <input type="text" class="form-control form-control-sm" name="display_name" value="<?php echo htmlspecialchars($user_info['display_name']); ?>" required>
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small mb-1">
                                        <i class="bi bi-geo-alt-fill text-danger"></i> Location
                                    </label>
                                    <input type="text" class="form-control form-control-sm" name="location" value="<?php echo htmlspecialchars($user_info['location'] ?? ''); ?>" placeholder="e.g., Los Angeles, CA">
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small mb-1">
                                        <i class="bi bi-link-45deg text-primary"></i> URL 1
                                    </label>
                                    <input type="url" class="form-control form-control-sm" name="url_1" value="<?php echo htmlspecialchars($user_info['url_1'] ?? ''); ?>" placeholder="https://example.com">
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small mb-1">
                                        <i class="bi bi-link-45deg text-primary"></i> URL 2
                                    </label>
                                    <input type="url" class="form-control form-control-sm" name="url_2" value="<?php echo htmlspecialchars($user_info['url_2'] ?? ''); ?>" placeholder="https://example.com">
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small mb-1">
                                        <i class="bi bi-link-45deg text-primary"></i> URL 3
                                    </label>
                                    <input type="url" class="form-control form-control-sm" name="url_3" value="<?php echo htmlspecialchars($user_info['url_3'] ?? ''); ?>" placeholder="https://example.com">
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Notes</label>
                                    <textarea class="form-control form-control-sm" name="notes" rows="3"><?php echo htmlspecialchars($user_info['notes'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">Folder: <?php echo htmlspecialchars($archive_user); ?></small>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_info" class="btn btn-primary btn-sm flex-fill">
                                        <i class="bi bi-save"></i> Save
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEdit()">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                            
                            <hr>
                            <div class="small">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><i class="bi bi-images"></i> Images:</span>
                                    <strong><?php echo number_format(count($images)); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><i class="bi bi-play-circle"></i> Videos:</span>
                                    <strong><?php echo number_format(count($videos)); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#images" type="button">
                                <i class="bi bi-images"></i> Images (<?php echo count($images); ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#videos" type="button">
                                <i class="bi bi-play-circle"></i> Videos (<?php echo count($videos); ?>)
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mt-3">
                        <div class="tab-pane fade show active" id="images" role="tabpanel">
                            <?php if(empty($images)): ?>
                                <div class="text-center py-5"><i class="bi bi-images" style="font-size:4rem;color:#4b5563;"></i><p class="text-muted mt-3">No images</p></div>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach($images as $idx=>$img): ?>
                                        <div class="col-lg-2 col-md-3 col-4">
                                            <div class="gallery-item" data-bs-toggle="modal" data-bs-target="#im<?php echo $idx; ?>">
                                                <?php if($img['thumb']): ?>
                                                    <img src="<?php echo htmlspecialchars($img['thumb']); ?>" alt="<?php echo htmlspecialchars($img['filename']); ?>">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center h-100">
                                                        <div class="spinner-border text-light" role="status"><span class="visually-hidden">Generating...</span></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal fade" id="im<?php echo $idx; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo htmlspecialchars($img['filename']); ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-0 position-relative" style="background: #000;">
                                                        <!-- Image Wrapper - responsive height -->
                                                        <div style="display: flex; align-items: center; justify-content: center; min-height: 60vh; padding: 10px;">
                                                            <!-- Previous Button -->
                                                            <?php if ($idx > 0): ?>
                                                            <button class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-2 rounded-circle" 
                                                                    style="width: 50px; height: 50px; z-index: 10; opacity: 0.8;"
                                                                    onclick="navigateModal('im<?php echo $idx; ?>', 'im<?php echo $idx - 1; ?>')">
                                                                <i class="bi bi-chevron-left"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Image with viewport-based max height -->
                                                            <img src="<?php echo htmlspecialchars($img['path']); ?>" 
                                                                 style="max-height: 80vh; max-width: 100%; height: auto; width: auto; object-fit: contain;">
                                                            
                                                            <!-- Next Button -->
                                                            <?php if ($idx < count($images) - 1): ?>
                                                            <button class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-2 rounded-circle" 
                                                                    style="width: 50px; height: 50px; z-index: 10; opacity: 0.8;"
                                                                    onclick="navigateModal('im<?php echo $idx; ?>', 'im<?php echo $idx + 1; ?>')">
                                                                <i class="bi bi-chevron-right"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Info overlay at bottom -->
                                                        <div class="position-absolute bottom-0 w-100 p-3" style="background: linear-gradient(transparent, rgba(0,0,0,0.8)); z-index: 5;">
                                                            <div class="text-white small text-center">
                                                                Image <?php echo $idx + 1; ?> of <?php echo count($images); ?> • 
                                                                Size: <?php echo format_bytes($img['size']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <a href="<?php echo htmlspecialchars($img['path']); ?>" download class="btn btn-primary"><i class="bi bi-download"></i> Download</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tab-pane fade" id="videos" role="tabpanel">
                            <?php if(empty($videos)): ?>
                                <div class="text-center py-5"><i class="bi bi-play-circle" style="font-size:4rem;color:#4b5563;"></i><p class="text-muted mt-3">No videos</p></div>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach($videos as $idx=>$vid): ?>
                                        <div class="col-lg-2 col-md-3 col-4">
                                            <div class="gallery-item" data-bs-toggle="modal" data-bs-target="#vid<?php echo $idx; ?>">
                                                <span class="video-indicator"><i class="bi bi-play-fill"></i></span>
                                                <?php if($vid['thumb']): ?><img src="<?php echo htmlspecialchars($vid['thumb']); ?>" alt="<?php echo htmlspecialchars($vid['filename']); ?>">
                                                <?php else: ?><div class="d-flex align-items-center justify-content-center h-100"><i class="bi bi-play-circle" style="font-size:3rem;"></i></div><?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal fade" id="vid<?php echo $idx; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-centered modal-dialog-scrollable">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo htmlspecialchars($vid['filename']); ?></h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-0 position-relative" style="background: #000;">
                                                        <!-- Previous Button -->
                                                        <?php if ($idx > 0): ?>
                                                        <button class="btn btn-dark position-absolute top-50 start-0 translate-middle-y ms-2 rounded-circle" 
                                                                style="width: 50px; height: 50px; z-index: 10; opacity: 0.8;"
                                                                onclick="navigateModal('vid<?php echo $idx; ?>', 'vid<?php echo $idx - 1; ?>')">
                                                            <i class="bi bi-chevron-left"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Video - centered with padding -->
                                                        <div class="text-center" style="padding: 20px;">
                                                            <video 
                                                                id="vid-player-<?php echo $idx; ?>"
                                                                controls 
                                                                playsinline
                                                                poster="<?php echo htmlspecialchars($vid['thumb'] ?? ''); ?>"
                                                                data-src="<?php echo htmlspecialchars($vid['path']); ?>"
                                                                style="max-height: 70vh; max-width: 100%; width: auto; height: auto; display: block; margin: 0 auto;">
                                                                <!-- Source will be added dynamically when modal opens -->
                                                                Your browser does not support the video tag.
                                                            </video>
                                                        </div>
                                                        
                                                        <!-- Next Button -->
                                                        <?php if ($idx < count($videos) - 1): ?>
                                                        <button class="btn btn-dark position-absolute top-50 end-0 translate-middle-y me-2 rounded-circle" 
                                                                style="width: 50px; height: 50px; z-index: 10; opacity: 0.8;"
                                                                onclick="navigateModal('vid<?php echo $idx; ?>', 'vid<?php echo $idx + 1; ?>')">
                                                            <i class="bi bi-chevron-right"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Info overlay at bottom -->
                                                        <div class="position-absolute bottom-0 w-100 p-3" style="background: linear-gradient(transparent, rgba(0,0,0,0.8)); z-index: 5;">
                                                            <div class="text-white small text-center">
                                                                Video <?php echo $idx + 1; ?> of <?php echo count($videos); ?> • 
                                                                Size: <?php echo format_bytes($vid['size']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <a href="<?php echo htmlspecialchars($vid['path']); ?>" download class="btn btn-primary"><i class="bi bi-download"></i> Download</a>
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Video handling for mobile - load source only when modal opens
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.modal').forEach(function(modal) {
                // When modal is about to open
                modal.addEventListener('show.bs.modal', function() {
                    const video = this.querySelector('video');
                    if (video && video.dataset.src) {
                        console.log('Loading video source:', video.dataset.src);
                        
                        // Clear any existing sources
                        video.innerHTML = '';
                        
                        // Add source element
                        const source = document.createElement('source');
                        source.src = video.dataset.src;
                        source.type = 'video/mp4';
                        video.appendChild(source);
                        
                        // Load the video
                        video.load();
                        
                        console.log('Video loaded, readyState:', video.readyState);
                    }
                });
                
                // When modal closes
                modal.addEventListener('hidden.bs.modal', function() {
                    const video = this.querySelector('video');
                    if (video) {
                        console.log('Cleaning up video');
                        video.pause();
                        video.currentTime = 0;
                        // Remove source to stop loading
                        video.innerHTML = '';
                        video.load();
                    }
                });
            });
        });
        
        function toggleEdit() {
            const display = document.getElementById('info-display');
            const edit = document.getElementById('info-edit');
            
            if (display.style.display === 'none') {
                display.style.display = 'block';
                edit.style.display = 'none';
            } else {
                display.style.display = 'none';
                edit.style.display = 'block';
            }
        }
        
        // Navigate between modals
        function navigateModal(currentId, nextId) {
            // Hide current modal
            const currentModal = bootstrap.Modal.getInstance(document.getElementById(currentId));
            if (currentModal) {
                currentModal.hide();
            }
            
            // Show next modal after a brief delay
            setTimeout(() => {
                const nextModal = new bootstrap.Modal(document.getElementById(nextId));
                nextModal.show();
            }, 300);
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Check if any modal is open
            const openModal = document.querySelector('.modal.show');
            if (!openModal) return;
            
            const modalId = openModal.id;
            
            // ESC key - close modal (Bootstrap handles this by default)
            // LEFT arrow key
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                const prevBtn = openModal.querySelector('button[onclick*="navigateModal"]');
                if (prevBtn && prevBtn.textContent.includes('chevron-left')) {
                    prevBtn.click();
                }
            }
            
            // RIGHT arrow key
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                const buttons = openModal.querySelectorAll('button[onclick*="navigateModal"]');
                const nextBtn = buttons.length === 2 ? buttons[1] : buttons[0];
                if (nextBtn && nextBtn.textContent.includes('chevron-right')) {
                    nextBtn.click();
                }
            }
        });
        
        <?php if($missing_thumbs > 0): ?>
        // Async thumbnail generation with auto-reload
        const archiveUser = '<?php echo addslashes($archive_user); ?>';
        const totalItems = <?php echo count($images)+count($videos); ?>;
        
        async function generateThumbnails(start = 0) {
            try {
                const response = await fetch(`generate_thumbs.php?user=${encodeURIComponent(archiveUser)}&start=${start}`);
                const data = await response.json();
                
                if (data.success) {
                    const percentage = Math.round((data.processed / totalItems) * 100);
                    
                    // Update progress bar
                    document.getElementById('thumb-current').textContent = data.processed;
                    document.getElementById('thumb-progress-bar').style.width = percentage + '%';
                    document.getElementById('thumb-progress-bar').textContent = percentage + '%';
                    
                    if (!data.complete) {
                        // Continue with next batch
                        setTimeout(() => generateThumbnails(data.processed), 100);
                    } else {
                        // All done - reload with parameter to prevent re-trigger
                        const alert = document.getElementById('thumb-progress-alert');
                        if (alert) {
                            alert.classList.remove('alert-info');
                            alert.classList.add('alert-success');
                            alert.innerHTML = '<i class="bi bi-check-circle"></i> <strong>Complete!</strong> Reloading...';
                        }
                        setTimeout(() => {
                            // Add parameter to URL to indicate generation just completed
                            const url = new URL(window.location.href);
                            url.searchParams.set('thumbs_done', '1');
                            window.location.href = url.toString();
                        }, 1000);
                    }
                }
            } catch (error) {
                console.error('Thumbnail generation error:', error);
                document.getElementById('thumb-progress-alert').innerHTML = 
                    '<i class="bi bi-exclamation-triangle"></i> Error generating thumbnails. Please refresh the page.';
            }
        }
        
        // Start generation on page load
        document.addEventListener('DOMContentLoaded', () => {
            generateThumbnails(0);
        });
        <?php endif; ?>
    </script>
</body>
</html>
