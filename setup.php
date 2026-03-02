<?php
session_start();

// Check if already setup
$config_file = __DIR__ . '/config.php';
$setup_complete_file = __DIR__ . '/data/.setup-complete';

if (file_exists($setup_complete_file) || file_exists($config_file)) {
    // Check if database exists and has admin user
    if (file_exists($config_file)) {
        require_once $config_file;
        
        try {
            $db = getDB();
            $stmt = $db->query("SELECT COUNT(*) FROM users");
            if ($stmt->fetchColumn() > 0) {
                // Already setup - show warning
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Already Setup - TheArchive</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; }
                        .card { background: #1e293b; color: #e2e8f0; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card shadow">
                                    <div class="card-body text-center py-5">
                                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                                        <h3 class="mt-3">Setup Already Complete</h3>
                                        <p class="text-muted">TheArchive is already configured.</p>
                                        <hr>
                                        <p class="small text-success">
                                            <strong><i class="bi bi-shield-check"></i> Secure:</strong> Setup is permanently disabled and cannot be run again.
                                        </p>
                                        <a href="index.php" class="btn btn-primary">Go to Login</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
                <?php
                exit;
            }
        } catch (Exception $e) {
            // Continue to setup
        }
    }
}

$success = '';
$error = '';

// Handle setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name'] ?? 'TheArchive');
    $db_name = trim($_POST['db_name'] ?? 'thearchive');
    $admin_username = trim($_POST['admin_username'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($admin_username) || empty($admin_password)) {
        $error = 'Username and password are required';
    } elseif ($admin_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($admin_password) < 4) {
        $error = 'Password must be at least 4 characters';
    } else {
        try {
            // Create config.php
            $config_content = <<<'CONFIGPHP'
<?php
function getDB() {
    static $db = null;
    if ($db === null) {
        $db_file = __DIR__ . '/data/{DB_NAME}.db';
        $db_dir = dirname($db_file);
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0755, true);
        }
        $db = new PDO('sqlite:' . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}

function getSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function getAllSettings() {
    try {
        $db = getDB();
        $stmt = $db->query('SELECT setting_key, setting_value FROM settings');
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}
?>
CONFIGPHP;
            
            $config_content = str_replace('{DB_NAME}', $db_name, $config_content);
            file_put_contents($config_file, $config_content);
            
            // Create database and tables
            require_once $config_file;
            $db = getDB();
            
            // Users table
            $db->exec('CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            
            // Settings table
            $db->exec('CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            
            // Archive users table
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
            
            // Insert admin user
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (:username, :password)');
            $stmt->execute(['username' => $admin_username, 'password' => $hashed_password]);
            
            // Insert site settings
            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)');
            $stmt->execute(['key' => 'site_name', 'value' => $site_name]);
            
            // Create archive directory
            $archive_dir = __DIR__ . '/data/archive';
            if (!file_exists($archive_dir)) {
                mkdir($archive_dir, 0755, true);
            }
            
            // Create setup complete flag
            file_put_contents($setup_complete_file, date('Y-m-d H:i:s'));
            
            $success = 'Setup complete! Redirecting to login...';
            header('Refresh: 2; URL=index.php');
            
        } catch (Exception $e) {
            $error = 'Setup failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - TheArchive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .setup-card {
            background: #1e293b;
            color: #e2e8f0;
        }
        .form-control {
            background: #334155;
            border: 1px solid #475569;
            color: #e2e8f0;
        }
        .form-control:focus {
            background: #334155;
            border-color: #60a5fa;
            color: #e2e8f0;
        }
        .form-label {
            color: #cbd5e1;
        }
        .form-text {
            color: #94a3b8;
        }
        h2, h5 {
            color: #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card setup-card shadow-lg">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-archive-fill text-primary" style="font-size: 4rem;"></i>
                            <h2 class="mt-3">Welcome to TheArchive</h2>
                            <p class="text-muted">Let's get you set up!</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <h5 class="mb-3">Site Configuration</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" name="site_name" value="TheArchive" required>
                                <div class="form-text">Your archive's display name</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Database Name</label>
                                <input type="text" class="form-control" name="db_name" value="thearchive" required pattern="[a-zA-Z0-9_]+">
                                <div class="form-text">SQLite database filename (letters, numbers, underscore only)</div>
                            </div>
                            
                            <hr class="my-4">
                            <h5 class="mb-3">Admin Account</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="admin_username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="admin_password" required>
                                <div class="form-text">Minimum 4 characters</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-rocket-takeoff"></i> Complete Setup
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-white">
                        <i class="bi bi-shield-check"></i> Setup will automatically disable after completion
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
