<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$db = getDB();

// Function to get directory size
function getDirSize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Create stats table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS storage_stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_size INTEGER,
    image_count INTEGER,
    video_count INTEGER,
    user_count INTEGER,
    thumbnail_size INTEGER
)');

// Record current stats
$archive_path = __DIR__ . '/data/archive';
$thumbs_path = __DIR__ . '/data/archive/*/thumbs';

$total_size = getDirSize($archive_path);
$thumbnail_size = 0;

// Count thumbnails
foreach (glob($thumbs_path, GLOB_ONLYDIR) as $thumb_dir) {
    $thumbnail_size += getDirSize($thumb_dir);
}

// Count files
$image_count = 0;
$video_count = 0;
$user_count = 0;

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

$allowed_img = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowed_vid = ['mp4', 'mov', 'webm'];

if (is_dir($archive_path)) {
    $dirs = array_diff(scandir($archive_path), ['.', '..']);
    
    foreach ($dirs as $dir) {
        $dir_path = $archive_path . '/' . $dir . '/';
        if (!is_dir($dir_path)) continue;
        
        $user_count++;
        
        // Layout 1: User/Images and User/Videos
        $images_folder = find_folder($dir_path, 'images');
        if ($images_folder) {
            $image_count += count_files_in_folder($dir_path . $images_folder . '/', $allowed_img);
        }
        
        $videos_folder = find_folder($dir_path, 'videos');
        if ($videos_folder) {
            $video_count += count_files_in_folder($dir_path . $videos_folder . '/', $allowed_vid);
        }
        
        // Layout 2: User/posts/Images and User/posts/Videos
        $posts_folder = find_folder($dir_path, 'posts');
        if ($posts_folder) {
            $posts_path = $dir_path . $posts_folder . '/';
            
            $posts_img_folder = find_folder($posts_path, 'images');
            if ($posts_img_folder) {
                $image_count += count_files_in_folder($posts_path . $posts_img_folder . '/', $allowed_img);
            }
            
            $posts_vid_folder = find_folder($posts_path, 'videos');
            if ($posts_vid_folder) {
                $video_count += count_files_in_folder($posts_path . $posts_vid_folder . '/', $allowed_vid);
            }
        }
    }
}

// Check if we should record new stats (once per day)
$last_record = $db->query("SELECT MAX(recorded_at) as last FROM storage_stats")->fetch();
$should_record = true;

if ($last_record && $last_record['last']) {
    $last_time = strtotime($last_record['last']);
    $should_record = (time() - $last_time) > 86400; // 24 hours
}

if ($should_record) {
    $stmt = $db->prepare('INSERT INTO storage_stats (total_size, image_count, video_count, user_count, thumbnail_size) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$total_size, $image_count, $video_count, $user_count, $thumbnail_size]);
}

// Get historical data
$today = $db->query("SELECT * FROM storage_stats WHERE DATE(recorded_at) = DATE('now') ORDER BY id DESC LIMIT 1")->fetch();
$week = $db->query("SELECT * FROM storage_stats WHERE recorded_at >= DATE('now', '-7 days') ORDER BY id ASC LIMIT 1")->fetch();
$month = $db->query("SELECT * FROM storage_stats WHERE recorded_at >= DATE('now', '-30 days') ORDER BY id ASC LIMIT 1")->fetch();
$all_time = $db->query("SELECT * FROM storage_stats ORDER BY id ASC LIMIT 1")->fetch();

// Calculate growth
function calculateGrowth($old, $new) {
    if (!$old || $old == 0) return 0;
    return (($new - $old) / $old) * 100;
}

$growth_7d = $week ? calculateGrowth($week['total_size'], $total_size) : 0;
$growth_30d = $month ? calculateGrowth($month['total_size'], $total_size) : 0;
$growth_all = $all_time ? calculateGrowth($all_time['total_size'], $total_size) : 0;

// Get chart data (last 30 days)
$chart_data = $db->query("
    SELECT DATE(recorded_at) as date, total_size, image_count, video_count
    FROM storage_stats 
    WHERE recorded_at >= DATE('now', '-30 days')
    ORDER BY recorded_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$site_name = getSetting('site_name', 'TheArchive');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include 'mobile-styles.php'; ?>
</head>
<body>
    <?php 
    $currentPage = 'stats';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav">
            <h3><i class="bi bi-graph-up"></i> Statistics</h3>
        </div>
        
        <div class="container-fluid">
            <!-- Current Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-hdd"></i> Total Storage</h6>
                            <h3 class="mb-0"><?php echo formatBytes($total_size); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-image"></i> Images</h6>
                            <h3 class="mb-0"><?php echo number_format($image_count); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-camera-video"></i> Videos</h6>
                            <h3 class="mb-0"><?php echo number_format($video_count); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-warning text-dark">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-people"></i> Users</h6>
                            <h3 class="mb-0"><?php echo number_format($user_count); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Growth Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted">7-Day Growth</h6>
                            <h4 class="<?php echo $growth_7d > 0 ? 'text-success' : ($growth_7d < 0 ? 'text-danger' : ''); ?>">
                                <?php echo $growth_7d > 0 ? '+' : ''; ?><?php echo number_format($growth_7d, 1); ?>%
                            </h4>
                            <?php if ($week): ?>
                            <small class="text-muted">
                                <?php echo formatBytes($week['total_size']); ?> → <?php echo formatBytes($total_size); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted">30-Day Growth</h6>
                            <h4 class="<?php echo $growth_30d > 0 ? 'text-success' : ($growth_30d < 0 ? 'text-danger' : ''); ?>">
                                <?php echo $growth_30d > 0 ? '+' : ''; ?><?php echo number_format($growth_30d, 1); ?>%
                            </h4>
                            <?php if ($month): ?>
                            <small class="text-muted">
                                <?php echo formatBytes($month['total_size']); ?> → <?php echo formatBytes($total_size); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted">All-Time Growth</h6>
                            <h4 class="<?php echo $growth_all > 0 ? 'text-success' : ($growth_all < 0 ? 'text-danger' : ''); ?>">
                                <?php echo $growth_all > 0 ? '+' : ''; ?><?php echo number_format($growth_all, 1); ?>%
                            </h4>
                            <?php if ($all_time): ?>
                            <small class="text-muted">
                                <?php echo formatBytes($all_time['total_size']); ?> → <?php echo formatBytes($total_size); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row g-3 mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Storage Growth (30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="storageChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Content Growth (30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="contentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Stats -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Archive Content</span>
                                    <strong><?php echo formatBytes($total_size - $thumbnail_size); ?></strong>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo ($total_size - $thumbnail_size) / $total_size * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Thumbnails</span>
                                    <strong><?php echo formatBytes($thumbnail_size); ?></strong>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-secondary" style="width: <?php echo $thumbnail_size / $total_size * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-top">
                                <div class="d-flex justify-content-between">
                                    <strong>Total</strong>
                                    <strong><?php echo formatBytes($total_size); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Averages</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <h6 class="text-muted">Per User</h6>
                                    <h4><?php echo $user_count > 0 ? formatBytes($total_size / $user_count) : '0 B'; ?></h4>
                                </div>
                                <div class="col-6 mb-3">
                                    <h6 class="text-muted">Per Image</h6>
                                    <h4><?php echo $image_count > 0 ? formatBytes(($total_size - $thumbnail_size) / ($image_count + $video_count)) : '0 B'; ?></h4>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-muted">Images/User</h6>
                                    <h4><?php echo $user_count > 0 ? number_format($image_count / $user_count, 0) : 0; ?></h4>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-muted">Videos/User</h6>
                                    <h4><?php echo $user_count > 0 ? number_format($video_count / $user_count, 0) : 0; ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Storage chart
        const storageCtx = document.getElementById('storageChart').getContext('2d');
        new Chart(storageCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
                datasets: [{
                    label: 'Storage (GB)',
                    data: <?php echo json_encode(array_map(function($v) { return round($v / 1073741824, 2); }, array_column($chart_data, 'total_size'))); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });
        
        // Content chart
        const contentCtx = document.getElementById('contentChart').getContext('2d');
        new Chart(contentCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
                datasets: [{
                    label: 'Images',
                    data: <?php echo json_encode(array_column($chart_data, 'image_count')); ?>,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Videos',
                    data: <?php echo json_encode(array_column($chart_data, 'video_count')); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
