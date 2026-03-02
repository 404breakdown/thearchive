<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$user_id = $_SESSION['user_id'] ?? 0;
$site_name = getSetting('site_name', 'TheArchive');
$success = '';
$error = '';

$db = getDB();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($current_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $stmt->execute(['hash' => $new_hash, 'id' => $user_id]);
                $success = 'Password changed successfully!';
            } else {
                $error = 'Current password is incorrect';
            }
        } catch (PDOException $e) {
            $error = 'Database error';
        }
    }
}

// Handle site name change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_site_name'])) {
    $new_site_name = trim($_POST['site_name'] ?? '');
    
    if (empty($new_site_name)) {
        $error = 'Site name cannot be empty';
    } else {
        try {
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (:key, :value)');
            $stmt->execute(['key' => 'site_name', 'value' => $new_site_name]);
            $site_name = $new_site_name;
            $success = 'Site name updated!';
        } catch (PDOException $e) {
            $error = 'Database error';
        }
    }
}

// Handle clear all thumbnails
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_thumbnails'])) {
    $archive_base = __DIR__ . '/data/archive/';
    $cleared = 0;
    
    if (is_dir($archive_base)) {
        $dirs = array_diff(scandir($archive_base), ['.', '..']);
        foreach ($dirs as $dir) {
            $thumbs_dir = $archive_base . $dir . '/thumbs/';
            if (is_dir($thumbs_dir)) {
                array_map('unlink', glob($thumbs_dir . '*'));
                rmdir($thumbs_dir);
                $cleared++;
            }
        }
    }
    
    $success = "Cleared thumbnails for $cleared user(s). They will regenerate on next view.";
}

// Get archive stats
$archive_base = __DIR__ . '/data/archive/';
$archive_path_display = realpath($archive_base) ?: $archive_base;

// Calculate thumbnail sizes
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

$total_thumb_size = 0;
$thumb_count = 0;

if (is_dir($archive_base)) {
    $dirs = array_diff(scandir($archive_base), ['.', '..']);
    foreach ($dirs as $dir) {
        $dir_path = $archive_base . $dir . '/';
        if (!is_dir($dir_path)) continue;
        
        $thumbs_folder = find_folder($dir_path, 'thumbs');
        if ($thumbs_folder) {
            $thumbs_path = $dir_path . $thumbs_folder . '/';
            $total_thumb_size += get_dir_size($thumbs_path);
            $files = array_diff(scandir($thumbs_path), ['.', '..']);
            foreach ($files as $file) {
                if (is_file($thumbs_path . $file)) {
                    $thumb_count++;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'mobile-styles.php'; ?>
</head>
<body>
    <?php 
    $currentPage = 'settings';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav">
            <h3 class="mb-0"><i class="bi bi-gear"></i> Settings</h3>
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
            
            <!-- Modern Tab Navigation -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="bi bi-sliders"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="bi bi-shield-lock"></i> Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="thumbnails-tab" data-bs-toggle="tab" data-bs-target="#thumbnails" type="button" role="tab">
                        <i class="bi bi-images"></i> Thumbnails
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                        <i class="bi bi-info-circle"></i> System
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content">
                <!-- General Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-gear"></i> Site Settings
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Site Name</label>
                                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
                                            <small class="text-muted">Displayed in the header and page titles</small>
                                        </div>
                                        <button type="submit" name="update_site_name" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Save Changes
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-folder2-open"></i> Archive Information
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Archive Path (Read-Only)</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control font-monospace" value="<?php echo htmlspecialchars($archive_path_display); ?>" readonly>
                                            <button class="btn btn-outline-secondary" type="button" onclick="copyPath()">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">This path is mounted via Docker</small>
                                    </div>
                                    
                                    <div class="alert alert-info small mb-0">
                                        <strong>Supported Folder Structures:</strong><br>
                                        <code class="small">
                                            Username/Images/<br>
                                            Username/Videos/<br>
                                            Username/posts/Images/<br>
                                            Username/posts/Videos/<br>
                                            Username/profile/Images/
                                        </code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-key"></i> Change Password
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Current Password</label>
                                            <input type="password" class="form-control" name="current_password" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <input type="password" class="form-control" name="new_password" required minlength="6">
                                            <small class="text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirm New Password</label>
                                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                                        </div>
                                        <button type="submit" name="change_password" class="btn btn-primary">
                                            <i class="bi bi-shield-check"></i> Update Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Thumbnails Tab -->
                <div class="tab-pane fade" id="thumbnails" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-images"></i> Thumbnail Management
                                </div>
                                <div class="card-body">
                                    <!-- Stats -->
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="text-center p-3 bg-dark rounded">
                                                <h3 class="mb-0"><?php echo number_format($thumb_count); ?></h3>
                                                <small class="text-muted">Total Thumbnails</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center p-3 bg-dark rounded">
                                                <h3 class="mb-0"><?php echo format_bytes($total_thumb_size); ?></h3>
                                                <small class="text-muted">Cache Size</small>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center p-3 bg-dark rounded">
                                                <h3 class="mb-0">200px</h3>
                                                <small class="text-muted">Thumbnail Size</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Generate All Progress Bar -->
                                    <div id="generate-progress" style="display: none;">
                                        <div class="alert alert-info">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <i class="bi bi-hourglass-split"></i> <strong>Generating Thumbnails</strong>
                                                </div>
                                                <div class="small">
                                                    <span id="gen-current">0</span> / <span id="gen-total">0</span>
                                                </div>
                                            </div>
                                            <div class="progress" style="height: 25px;">
                                                <div id="gen-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                                            </div>
                                            <div class="small text-muted mt-2">Please wait while thumbnails are generated...</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <h6 class="mb-3">Actions</h6>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success" onclick="generateAllThumbnails()">
                                            <i class="bi bi-arrow-clockwise"></i> Generate All Thumbnails
                                        </button>
                                        <small class="text-muted">Generate thumbnails for all users at once. Useful after clearing cache or adding new files.</small>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <form method="POST" onsubmit="return confirm('Are you sure? This will delete all <?php echo number_format($thumb_count); ?> thumbnail files (<?php echo format_bytes($total_thumb_size); ?>)?');">
                                        <div class="d-grid">
                                            <button type="submit" name="clear_thumbnails" class="btn btn-warning">
                                                <i class="bi bi-trash"></i> Clear All Thumbnails
                                            </button>
                                        </div>
                                        <small class="text-muted d-block mt-2">Remove all generated thumbnails. They will regenerate on next view with current settings (200px).</small>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Tab -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <i class="bi bi-cpu"></i> System Information
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">PHP Version:</span>
                                        <strong><?php echo phpversion(); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">GD Library:</span>
                                        <strong>
                                            <?php if (function_exists('imagecreatetruecolor')): ?>
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> Enabled</span>
                                            <?php else: ?>
                                                <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Disabled</span>
                                            <?php endif; ?>
                                        </strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">FFmpeg:</span>
                                        <strong>
                                            <?php if (trim(shell_exec('which ffmpeg 2>/dev/null'))): ?>
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> Available</span>
                                            <?php else: ?>
                                                <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Not Found</span>
                                            <?php endif; ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyPath() {
            const input = document.querySelector('.font-monospace');
            input.select();
            document.execCommand('copy');
            
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i>';
            setTimeout(() => {
                btn.innerHTML = originalHTML;
            }, 2000);
        }
        
        // Generate all thumbnails
        let allUsers = [];
        let currentUserIndex = 0;
        
        async function generateAllThumbnails() {
            if (!confirm('This will generate thumbnails for all users. This may take several minutes. Continue?')) {
                return;
            }
            
            // Get list of users from archive page
            try {
                const response = await fetch('get_users.php');
                const data = await response.json();
                allUsers = data.users || [];
                
                if (allUsers.length === 0) {
                    alert('No users found in archive');
                    return;
                }
                
                document.getElementById('gen-total').textContent = allUsers.length;
                document.getElementById('generate-progress').style.display = 'block';
                currentUserIndex = 0;
                
                processNextUser();
            } catch (error) {
                alert('Error loading users: ' + error.message);
            }
        }
        
        async function processNextUser() {
            if (currentUserIndex >= allUsers.length) {
                // All done!
                document.querySelector('#generate-progress .alert').classList.remove('alert-info');
                document.querySelector('#generate-progress .alert').classList.add('alert-success');
                document.querySelector('#generate-progress .alert').innerHTML = 
                    '<i class="bi bi-check-circle"></i> <strong>Complete!</strong> All thumbnails generated. Reloading...';
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
                return;
            }
            
            const user = allUsers[currentUserIndex];
            
            try {
                const response = await fetch(`generate_thumbs.php?user=${encodeURIComponent(user)}&start=0`);
                const data = await response.json();
                
                if (data.success) {
                    // Process this user's thumbnails in batches
                    await processUserThumbnails(user, data.total);
                    
                    currentUserIndex++;
                    document.getElementById('gen-current').textContent = currentUserIndex;
                    const percentage = Math.round((currentUserIndex / allUsers.length) * 100);
                    document.getElementById('gen-progress-bar').style.width = percentage + '%';
                    document.getElementById('gen-progress-bar').textContent = percentage + '%';
                    
                    // Process next user
                    setTimeout(processNextUser, 100);
                }
            } catch (error) {
                console.error('Error processing user:', user, error);
                currentUserIndex++;
                processNextUser(); // Continue with next user
            }
        }
        
        async function processUserThumbnails(user, total) {
            let processed = 0;
            
            while (processed < total) {
                try {
                    const response = await fetch(`generate_thumbs.php?user=${encodeURIComponent(user)}&start=${processed}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        processed = data.processed;
                        if (data.complete) break;
                    } else {
                        break;
                    }
                } catch (error) {
                    console.error('Error generating thumbnails for user:', user, error);
                    break;
                }
                
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }
    </script>
</body>
</html>
