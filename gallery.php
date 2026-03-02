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
$success = $_GET['success'] ?? '';
$error = '';

$db = getDB();
$site_name = getSetting('site_name', 'TheArchive');

// Create tables with all enhanced columns
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
        is_favorite INTEGER DEFAULT 0,
        color_label TEXT DEFAULT NULL,
        view_count INTEGER DEFAULT 0,
        last_viewed DATETIME DEFAULT NULL,
        file_count INTEGER DEFAULT 0,
        storage_size INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
} catch (PDOException $e) {
    // Table exists
}

try {
    $db->exec('CREATE TABLE IF NOT EXISTS user_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT "#6b7280",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
} catch (PDOException $e) {
    // Table exists
}

try {
    $db->exec('CREATE TABLE IF NOT EXISTS user_tag_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES archive_users(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES user_tags(id) ON DELETE CASCADE,
        UNIQUE(user_id, tag_id)
    )');
} catch (PDOException $e) {
    // Table exists
}

try {
    $db->exec('CREATE TABLE IF NOT EXISTS file_type_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_extension TEXT NOT NULL UNIQUE,
        file_count INTEGER DEFAULT 0,
        total_size INTEGER DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
} catch (PDOException $e) {
    // Table exists
}

// Handle bulk archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_archive'])) {
    $selected_users = $_POST['selected_users'] ?? [];
    if (!empty($selected_users)) {
        $_SESSION['bulk_archive_users'] = $selected_users;
        header('Location: bulk_archive.php');
        exit;
    } else {
        $error = 'No users selected';
    }
}

// Handle bulk download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_download'])) {
    $selected_users = $_POST['selected_users'] ?? [];
    if (!empty($selected_users)) {
        $_SESSION['bulk_download_users'] = $selected_users;
        header('Location: bulk_download.php');
        exit;
    } else {
        $error = 'No users selected';
    }
}

$archive_base = __DIR__ . '/data/archive/';

// Get all users from database with stats
$users_query = "SELECT * FROM archive_users ORDER BY created_at DESC";
$db_users = $db->query($users_query)->fetchAll(PDO::FETCH_ASSOC);

// Get all tags
$tags = $db->query("SELECT * FROM user_tags ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Scan filesystem for users
$user_folders = [];
if (is_dir($archive_base)) {
    foreach (scandir($archive_base) as $folder) {
        if ($folder === '.' || $folder === '..') continue;
        $folder_path = $archive_base . $folder;
        if (!is_dir($folder_path)) continue;
        
        // Find user in database
        $user_data = null;
        foreach ($db_users as $db_user) {
            if ($db_user['folder_name'] === $folder) {
                $user_data = $db_user;
                break;
            }
        }
        
        // Calculate stats if not in DB
        if (!$user_data || !$user_data['storage_size']) {
            $size = 0;
            $file_count = 0;
            $image_count = 0;
            $video_count = 0;
            
            $allowed_img = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_vid = ['mp4', 'mov', 'webm'];
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder_path, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $file_count++;
                    
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, $allowed_img)) $image_count++;
                    if (in_array($ext, $allowed_vid)) $video_count++;
                }
            }
            
            // Update DB if user exists
            if ($user_data) {
                $stmt = $db->prepare("UPDATE archive_users SET storage_size = ?, file_count = ? WHERE id = ?");
                $stmt->execute([$size, $file_count, $user_data['id']]);
            }
        } else {
            $size = $user_data['storage_size'];
            $file_count = $user_data['file_count'];
            
            // Count image and video files
            $image_count = 0;
            $video_count = 0;
            $allowed_img = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_vid = ['mp4', 'mov', 'webm'];
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder_path, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, $allowed_img)) $image_count++;
                    if (in_array($ext, $allowed_vid)) $video_count++;
                }
            }
        }
        
        // Get user tags
        $user_tags = [];
        if ($user_data) {
            $stmt = $db->prepare("
                SELECT t.* FROM user_tags t
                JOIN user_tag_assignments a ON t.id = a.tag_id
                WHERE a.user_id = ?
            ");
            $stmt->execute([$user_data['id']]);
            $user_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $user_folders[] = [
            'folder' => $folder,
            'data' => $user_data,
            'size' => $size,
            'file_count' => $file_count,
            'image_count' => $image_count,
            'video_count' => $video_count,
            'tags' => $user_tags,
            'is_favorite' => $user_data['is_favorite'] ?? 0,
            'color_label' => $user_data['color_label'] ?? null,
            'view_count' => $user_data['view_count'] ?? 0
        ];
    }
}

// Apply filters and search
$search = $_GET['search'] ?? '';
$filter_tag = $_GET['tag'] ?? '';
$filter_favorite = isset($_GET['favorite']) ? 1 : 0;
$sort_by = $_GET['sort'] ?? 'name'; // name, size, files, views, date

if ($search) {
    $user_folders = array_filter($user_folders, function($user) use ($search) {
        $name = $user['data']['display_name'] ?? $user['folder'];
        return stripos($name, $search) !== false || stripos($user['folder'], $search) !== false;
    });
}

if ($filter_tag) {
    $user_folders = array_filter($user_folders, function($user) use ($filter_tag) {
        foreach ($user['tags'] as $tag) {
            if ($tag['name'] === $filter_tag) return true;
        }
        return false;
    });
}

if ($filter_favorite) {
    $user_folders = array_filter($user_folders, function($user) {
        return $user['is_favorite'] == 1;
    });
}

// Sort users
usort($user_folders, function($a, $b) use ($sort_by) {
    switch ($sort_by) {
        case 'size':
            return $b['size'] - $a['size'];
        case 'files':
            return $b['file_count'] - $a['file_count'];
        case 'views':
            return ($b['view_count'] ?? 0) - ($a['view_count'] ?? 0);
        case 'date':
            $date_a = $a['data']['created_at'] ?? '0';
            $date_b = $b['data']['created_at'] ?? '0';
            return strcmp($date_b, $date_a);
        case 'name':
        default:
            $name_a = $a['data']['display_name'] ?? $a['folder'];
            $name_b = $b['data']['display_name'] ?? $b['folder'];
            return strcasecmp($name_a, $name_b);
    }
});

function format_bytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    elseif ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    else return $bytes . ' B';
}
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
        .user-card { 
            cursor: pointer; 
            transition: transform 0.2s, box-shadow 0.2s; 
            position: relative;
        }
        .user-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.3); 
        }
        .user-card.selected {
            border: 3px solid #0d6efd;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .user-avatar { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            object-fit: cover; 
            background: #374151; 
        }
        .select-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 5;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .favorite-star {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 5;
        }
        .color-label {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        .tag-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
        }
        .bulk-actions {
            position: sticky;
            top: 70px;
            z-index: 10;
            background: var(--bs-dark);
            color: var(--bs-light);
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            display: none;
        }
        .bulk-actions.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'gallery';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="mb-0"><i class="bi bi-images"></i> Gallery</h3>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted"><?php echo count($user_folders); ?> users</span>
                <a href="manage_tags.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-tags"></i> Manage Tags
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadZipModal">
                    <i class="bi bi-upload"></i> Import ZIP
                </button>
            </div>
        </div>
        
        <div class="container-fluid">
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Bulk Actions Bar -->
            <div id="bulkActions" class="bulk-actions mb-3">
                <form method="POST" id="bulkForm">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-bold"><span id="selectedCount">0</span> selected</span>
                        <button type="button" class="btn btn-warning btn-sm" onclick="bulkArchive()">
                            <i class="bi bi-archive"></i> Archive Selected
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="bulkDownload()">
                            <i class="bi bi-download"></i> Download as ZIP
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                            <i class="bi bi-x"></i> Clear
                        </button>
                    </div>
                    <input type="hidden" name="selected_users" id="selectedUsersInput">
                </form>
            </div>
            
            <!-- Search and Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="sort">
                                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="size" <?php echo $sort_by === 'size' ? 'selected' : ''; ?>>Storage Size</option>
                                <option value="files" <?php echo $sort_by === 'files' ? 'selected' : ''; ?>>File Count</option>
                                <option value="views" <?php echo $sort_by === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                                <option value="date" <?php echo $sort_by === 'date' ? 'selected' : ''; ?>>Date Added</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="tag">
                                <option value="">All Tags</option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?php echo htmlspecialchars($tag['name']); ?>" <?php echo $filter_tag === $tag['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tag['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="favorite" id="filterFavorite" <?php echo $filter_favorite ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="filterFavorite">
                                    ⭐ Favorites Only
                                </label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- User Grid -->
            <div class="row g-3">
                <?php foreach ($user_folders as $user): ?>
                    <?php
                        $display_name = $user['data']['display_name'] ?? $user['folder'];
                        $profile_img = null;
                        
                        // Try multiple profile locations
                        $profile_locations = [
                            $archive_base . $user['folder'] . '/profile/Images/',
                            $archive_base . $user['folder'] . '/Profile/Images/',
                            $archive_base . $user['folder'] . '/profile/',
                            $archive_base . $user['folder'] . '/Profile/'
                        ];
                        
                        foreach ($profile_locations as $profile_dir) {
                            if (is_dir($profile_dir)) {
                                $profile_files = array_diff(scandir($profile_dir), ['.', '..']);
                                foreach ($profile_files as $file) {
                                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                        $relative_path = substr($profile_dir . $file, strlen($archive_base));
                                        $profile_img = 'data/archive/' . $relative_path;
                                        break 2;
                                    }
                                }
                            }
                        }
                    ?>
                    <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                        <div class="card user-card h-100" data-username="<?php echo htmlspecialchars($user['folder']); ?>">
                            <?php if ($user['color_label']): ?>
                                <div class="color-label" style="background: <?php echo htmlspecialchars($user['color_label']); ?>;"></div>
                            <?php endif; ?>
                            
                            <input type="checkbox" class="form-check-input select-checkbox" onclick="event.stopPropagation(); toggleSelection(this, '<?php echo htmlspecialchars($user['folder']); ?>');">
                            
                            <span class="favorite-star" onclick="event.stopPropagation(); toggleFavorite('<?php echo htmlspecialchars($user['folder']); ?>', this);">
                                <?php echo $user['is_favorite'] ? '⭐' : '☆'; ?>
                            </span>
                            
                            <div class="card-body text-center" onclick="window.location.href='gallery_view.php?user=<?php echo urlencode($user['folder']); ?>'">
                                <?php if ($profile_img): ?>
                                    <img src="<?php echo htmlspecialchars($profile_img); ?>" class="user-avatar mb-2" alt="<?php echo htmlspecialchars($display_name); ?>">
                                <?php else: ?>
                                    <div class="user-avatar mx-auto mb-2 d-flex align-items-center justify-content-center">
                                        <i class="bi bi-person-fill" style="font-size: 2rem; color: #9ca3af;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <h6 class="mb-1"><?php echo htmlspecialchars($display_name); ?></h6>
                                
                                <div class="small text-muted mb-2">
                                    <div><i class="bi bi-hdd"></i> <?php echo format_bytes($user['size']); ?></div>
                                    <div><i class="bi bi-image"></i> <?php echo number_format($user['image_count']); ?> images</div>
                                    <div><i class="bi bi-camera-video"></i> <?php echo number_format($user['video_count']); ?> videos</div>
                                    <?php if ($user['view_count'] > 0): ?>
                                        <div><i class="bi bi-eye"></i> <?php echo number_format($user['view_count']); ?> views</div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($user['tags'])): ?>
                                    <div class="d-flex flex-wrap gap-1 justify-content-center mb-2">
                                        <?php foreach ($user['tags'] as $tag): ?>
                                            <span class="badge tag-badge" style="background-color: <?php echo htmlspecialchars($tag['color']); ?>">
                                                <?php echo htmlspecialchars($tag['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($user_folders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-folder2-open" style="font-size: 4rem; color: #4b5563;"></i>
                    <p class="text-muted mt-3">No users found</p>
                    <?php if ($search || $filter_tag || $filter_favorite): ?>
                        <a href="gallery.php" class="btn btn-primary">Clear Filters</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedUsers = new Set();
        
        function toggleSelection(checkbox, username) {
            const card = checkbox.closest('.user-card');
            
            if (checkbox.checked) {
                selectedUsers.add(username);
                card.classList.add('selected');
            } else {
                selectedUsers.delete(username);
                card.classList.remove('selected');
            }
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const count = selectedUsers.size;
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectedUsersInput = document.getElementById('selectedUsersInput');
            
            selectedCount.textContent = count;
            selectedUsersInput.value = JSON.stringify(Array.from(selectedUsers));
            
            if (count > 0) {
                bulkActions.classList.add('show');
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        function clearSelection() {
            selectedUsers.clear();
            document.querySelectorAll('.select-checkbox').forEach(cb => cb.checked = false);
            document.querySelectorAll('.user-card').forEach(card => card.classList.remove('selected'));
            updateBulkActions();
        }
        
        function toggleFavorite(username, element) {
            fetch('toggle_favorite.php?user=' + encodeURIComponent(username))
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        element.textContent = data.is_favorite ? '⭐' : '☆';
                    }
                });
        }
        
        function bulkArchive() {
            if (selectedUsers.size === 0) {
                alert('No users selected');
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('bulkArchiveModal'));
            modal.show();
            
            // Save selected users to session
            fetch('bulk_archive.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'selected_users=' + encodeURIComponent(JSON.stringify(Array.from(selectedUsers)))
            }).then(() => {
                // Start actual archiving
                fetch('bulk_archive.php').catch(err => console.error(err));
            });
            
            // Poll for progress
            const checkProgress = setInterval(function() {
                fetch('bulk_archive.php?check_progress=1')
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'idle') return;
                        
                        const progressBar = document.getElementById('bulkArchiveProgressBar');
                        const progressText = document.getElementById('bulkArchiveProgressText');
                        
                        progressBar.style.width = data.progress + '%';
                        progressBar.textContent = data.progress + '%';
                        progressText.textContent = data.message;
                        
                        if (data.status === 'complete') {
                            clearInterval(checkProgress);
                            setTimeout(() => {
                                window.location.href = 'gallery.php?success=' + encodeURIComponent(data.message);
                            }, 1000);
                        }
                    });
            }, 500);
        }
        
        function bulkDownload() {
            if (selectedUsers.size === 0) {
                alert('No users selected');
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('bulkDownloadModal'));
            modal.show();
            
            // Save selected users and start download
            fetch('bulk_download.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'selected_users=' + encodeURIComponent(JSON.stringify(Array.from(selectedUsers)))
            }).then(() => {
                fetch('bulk_download.php').catch(err => console.error(err));
            });
            
            // Poll for progress
            const checkProgress = setInterval(function() {
                fetch('bulk_download.php?check_progress=1')
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'idle') return;
                        
                        const progressBar = document.getElementById('bulkDownloadProgressBar');
                        const progressText = document.getElementById('bulkDownloadProgressText');
                        
                        progressBar.style.width = data.progress + '%';
                        progressBar.textContent = data.progress + '%';
                        progressText.textContent = data.message;
                        
                        if (data.status === 'complete') {
                            clearInterval(checkProgress);
                            window.location.href = 'bulk_download_ready.php?file=' + encodeURIComponent(data.download_url.split('/').pop());
                        }
                    });
            }, 500);
        }
    </script>
    
    <!-- Bulk Archive Progress Modal -->
    <div class="modal fade" id="bulkArchiveModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Archiving...</h5>
                </div>
                <div class="modal-body">
                    <div class="progress mb-2" style="height: 30px;">
                        <div id="bulkArchiveProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" style="width: 0%">0%</div>
                    </div>
                    <div id="bulkArchiveProgressText" class="text-center text-muted">Starting...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Download Progress Modal -->
    <div class="modal fade" id="bulkDownloadModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Creating Bulk Download...</h5>
                </div>
                <div class="modal-body">
                    <div class="progress mb-2" style="height: 30px;">
                        <div id="bulkDownloadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%">0%</div>
                    </div>
                    <div id="bulkDownloadProgressText" class="text-center text-muted">Starting...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ZIP Upload Modal -->
    <div class="modal fade" id="uploadZipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="import_zip.php" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-upload"></i> Import User ZIP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select ZIP File</label>
                            <input type="file" class="form-control" name="zip_file" accept=".zip" required>
                            <small class="text-muted">Upload a ZIP file containing user media in the correct folder structure</small>
                        </div>
                        <div class="alert alert-info small">
                            <strong>Supported Structure:</strong><br>
                            <code>Username/Images/</code><br>
                            <code>Username/Videos/</code><br>
                            <code>Username/posts/Images/</code><br>
                            <code>Username/posts/Videos/</code><br>
                            <code>Username/profile/Images/</code>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Import ZIP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>