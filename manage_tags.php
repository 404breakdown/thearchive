<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['logged_in'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$site_name = getSetting('site_name', 'TheArchive');
$success = '';
$error = '';

$db = getDB();

// Handle tag creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tag'])) {
    $tag_name = trim($_POST['tag_name'] ?? '');
    $tag_color = $_POST['tag_color'] ?? '#6b7280';
    
    if (!empty($tag_name)) {
        try {
            $stmt = $db->prepare('INSERT INTO user_tags (name, color) VALUES (?, ?)');
            $stmt->execute([$tag_name, $tag_color]);
            $success = 'Tag created successfully';
        } catch (PDOException $e) {
            $error = 'Tag already exists or error creating tag';
        }
    }
}

// Handle tag deletion
if (isset($_GET['delete'])) {
    $tag_id = (int)$_GET['delete'];
    $stmt = $db->prepare('DELETE FROM user_tags WHERE id = ?');
    $stmt->execute([$tag_id]);
    $success = 'Tag deleted successfully';
}

// Handle tag update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tag'])) {
    $tag_id = (int)$_POST['tag_id'];
    $tag_name = trim($_POST['tag_name'] ?? '');
    $tag_color = $_POST['tag_color'] ?? '#6b7280';
    
    $stmt = $db->prepare('UPDATE user_tags SET name = ?, color = ? WHERE id = ?');
    $stmt->execute([$tag_name, $tag_color, $tag_id]);
    $success = 'Tag updated successfully';
}

// Get all tags with user counts
$tags = $db->query("
    SELECT t.*, 
           COUNT(a.id) as user_count
    FROM user_tags t
    LEFT JOIN user_tag_assignments a ON t.id = a.tag_id
    GROUP BY t.id
    ORDER BY t.name
")->fetchAll(PDO::FETCH_ASSOC);

// Predefined colors
$preset_colors = [
    '#ef4444' => 'Red',
    '#f97316' => 'Orange',
    '#f59e0b' => 'Yellow',
    '#10b981' => 'Green',
    '#3b82f6' => 'Blue',
    '#8b5cf6' => 'Purple',
    '#ec4899' => 'Pink',
    '#6b7280' => 'Gray'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Tags - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include 'mobile-styles.php'; ?>
</head>
<body>
    <?php 
    $currentPage = 'gallery';
    include 'sidebar.php'; 
    ?>
    
    <div class="content-wrapper">
        <div class="top-nav">
            <h3><i class="bi bi-tags"></i> Manage Tags</h3>
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
            
            <div class="row">
                <!-- Create Tag Form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-plus-circle"></i> Create New Tag
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Tag Name</label>
                                    <input type="text" class="form-control" name="tag_name" required placeholder="e.g., Favorites, Archive, Work">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Color</label>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <?php foreach ($preset_colors as $color => $name): ?>
                                            <div>
                                                <input type="radio" class="btn-check" name="tag_color" value="<?php echo $color; ?>" id="color-<?php echo $color; ?>" <?php echo $color === '#6b7280' ? 'checked' : ''; ?>>
                                                <label class="btn btn-outline-secondary" for="color-<?php echo $color; ?>" style="width: 40px; height: 40px; background: <?php echo $color; ?>; border-color: <?php echo $color; ?>;" title="<?php echo $name; ?>"></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="color" class="form-control form-control-color" name="tag_color_custom" value="#6b7280">
                                </div>
                                <button type="submit" name="create_tag" class="btn btn-primary w-100">
                                    <i class="bi bi-plus"></i> Create Tag
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Tag List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="bi bi-list"></i> All Tags (<?php echo count($tags); ?>)
                        </div>
                        <div class="card-body">
                            <?php if (empty($tags)): ?>
                                <p class="text-muted text-center py-4">No tags created yet</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 20px; height: 20px; background: <?php echo htmlspecialchars($tag['color']); ?>; border-radius: 4px;"></div>
                                                <strong><?php echo htmlspecialchars($tag['name']); ?></strong>
                                                <span class="badge bg-secondary"><?php echo $tag['user_count']; ?> users</span>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editTag(<?php echo $tag['id']; ?>, '<?php echo htmlspecialchars($tag['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tag['color']); ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="?delete=<?php echo $tag['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this tag? It will be removed from all users.')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
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
    
    <!-- Edit Tag Modal -->
    <div class="modal fade" id="editTagModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Tag</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="tag_id" id="edit_tag_id">
                        <div class="mb-3">
                            <label class="form-label">Tag Name</label>
                            <input type="text" class="form-control" name="tag_name" id="edit_tag_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" name="tag_color" id="edit_tag_color">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_tag" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editTag(id, name, color) {
            document.getElementById('edit_tag_id').value = id;
            document.getElementById('edit_tag_name').value = name;
            document.getElementById('edit_tag_color').value = color;
            new bootstrap.Modal(document.getElementById('editTagModal')).show();
        }
    </script>
</body>
</html>
?>