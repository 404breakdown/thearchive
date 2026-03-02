<?php
// generate_thumbs.php - API endpoint for async thumbnail generation
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');

$archive_user = $_GET['user'] ?? '';
$batch_start = intval($_GET['start'] ?? 0);
$batch_size = 10; // Generate 10 at a time

if (empty($archive_user)) {
    echo json_encode(['error' => 'No user specified']);
    exit;
}

$archive_base = __DIR__ . '/data/archive/';
$user_dir = $archive_base . $archive_user . '/';

if (!is_dir($user_dir)) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$thumbs_dir = $user_dir . 'thumbs/';
if (!file_exists($thumbs_dir)) mkdir($thumbs_dir, 0755, true);

// Thumbnail functions
function create_thumb($source, $dest, $max = 200) {
    if (file_exists($dest)) return true;
    if (!function_exists('imagecreatetruecolor')) return false;
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

// Collect all files that need thumbnails
$all_items = [];
$allowed_img = ['jpg','jpeg','png','gif','webp'];
$allowed_vid = ['mp4','mov','avi','mkv','webm'];

// Layout 1: Images and Videos
$images_folder = find_folder($user_dir, 'images');
if ($images_folder) {
    $img_path = $user_dir . $images_folder . '/';
    foreach (array_diff(scandir($img_path), ['.','..']) as $file) {
        if (is_file($img_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_img)) {
            $all_items[] = [
                'type' => 'image',
                'source' => $img_path.$file,
                'thumb' => $thumbs_dir . $file . '.jpg',
                'thumb_path' => 'data/archive/'.$archive_user.'/thumbs/'.$file.'.jpg'
            ];
        }
    }
}

$videos_folder = find_folder($user_dir, 'videos');
if ($videos_folder) {
    $vid_path = $user_dir . $videos_folder . '/';
    foreach (array_diff(scandir($vid_path), ['.','..']) as $file) {
        if (is_file($vid_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_vid)) {
            $all_items[] = [
                'type' => 'video',
                'source' => $vid_path.$file,
                'thumb' => $thumbs_dir . $file . '.jpg',
                'thumb_path' => 'data/archive/'.$archive_user.'/thumbs/'.$file.'.jpg'
            ];
        }
    }
}

// Layout 2: posts/Images and posts/Videos
$posts_folder = find_folder($user_dir, 'posts');
if ($posts_folder) {
    $posts_path = $user_dir . $posts_folder . '/';
    
    $posts_img_folder = find_folder($posts_path, 'images');
    if ($posts_img_folder) {
        $img_path = $posts_path . $posts_img_folder . '/';
        foreach (array_diff(scandir($img_path), ['.','..']) as $file) {
            if (is_file($img_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_img)) {
                $all_items[] = [
                    'type' => 'image',
                    'source' => $img_path.$file,
                    'thumb' => $thumbs_dir . 'posts_' . $file . '.jpg',
                    'thumb_path' => 'data/archive/'.$archive_user.'/thumbs/posts_'.$file.'.jpg'
                ];
            }
        }
    }
    
    $posts_vid_folder = find_folder($posts_path, 'videos');
    if ($posts_vid_folder) {
        $vid_path = $posts_path . $posts_vid_folder . '/';
        foreach (array_diff(scandir($vid_path), ['.','..']) as $file) {
            if (is_file($vid_path.$file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)), $allowed_vid)) {
                $all_items[] = [
                    'type' => 'video',
                    'source' => $vid_path.$file,
                    'thumb' => $thumbs_dir . 'posts_' . $file . '.jpg',
                    'thumb_path' => 'data/archive/'.$archive_user.'/thumbs/posts_'.$file.'.jpg'
                ];
            }
        }
    }
}

$total_items = count($all_items);

// Get batch to process
$batch = array_slice($all_items, $batch_start, $batch_size);
$generated = [];

foreach ($batch as $item) {
    if (!file_exists($item['thumb'])) {
        if ($item['type'] === 'image') {
            create_thumb($item['source'], $item['thumb']);
        } else {
            create_video_thumb($item['source'], $item['thumb']);
        }
    }
    
    if (file_exists($item['thumb'])) {
        $generated[] = $item['thumb_path'];
    }
}

echo json_encode([
    'success' => true,
    'total' => $total_items,
    'processed' => $batch_start + count($batch),
    'batch_size' => count($batch),
    'generated' => $generated,
    'complete' => ($batch_start + count($batch)) >= $total_items
]);
