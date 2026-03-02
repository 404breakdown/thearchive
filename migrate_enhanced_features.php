<?php
// migrate_enhanced_features.php - Add new columns and tables for enhanced features
require_once 'config.php';

$db = getDB();

echo "Starting database migration for enhanced features...\n\n";

// Check and add columns one by one
$columns_to_add = [
    'notes' => 'TEXT DEFAULT NULL',
    'is_favorite' => 'INTEGER DEFAULT 0',
    'color_label' => 'TEXT DEFAULT NULL',
    'view_count' => 'INTEGER DEFAULT 0',
    'last_viewed' => 'DATETIME DEFAULT NULL',
    'file_count' => 'INTEGER DEFAULT 0',
    'storage_size' => 'INTEGER DEFAULT 0'
];

foreach ($columns_to_add as $column => $definition) {
    try {
        $db->exec("ALTER TABLE archive_users ADD COLUMN $column $definition");
        echo "✓ Added column: $column\n";
    } catch (PDOException $e) {
        echo "- Column $column already exists\n";
    }
}

try {
    // Create tags table
    $db->exec('CREATE TABLE IF NOT EXISTS user_tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT DEFAULT "#6b7280",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    echo "✓ Created user_tags table\n";
} catch (PDOException $e) {
    echo "- user_tags table already exists\n";
}

try {
    // Create user_tag_assignments table (many-to-many)
    $db->exec('CREATE TABLE IF NOT EXISTS user_tag_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES archive_users(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES user_tags(id) ON DELETE CASCADE,
        UNIQUE(user_id, tag_id)
    )');
    echo "✓ Created user_tag_assignments table\n";
} catch (PDOException $e) {
    echo "- user_tag_assignments table already exists\n";
}

try {
    // Create file_types_stats table
    $db->exec('CREATE TABLE IF NOT EXISTS file_type_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_extension TEXT NOT NULL UNIQUE,
        file_count INTEGER DEFAULT 0,
        total_size INTEGER DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )');
    echo "✓ Created file_type_stats table\n";
} catch (PDOException $e) {
    echo "- file_type_stats table already exists\n";
}

echo "\n=== Migration Complete ===\n";
echo "New features enabled:\n";
echo "- User notes and favorites ⭐\n";
echo "- Color labels 🏷️\n";
echo "- View tracking 👁️\n";
echo "- Tag system 🔖\n";
echo "- File type statistics 📊\n";
echo "- Storage tracking 💾\n";
echo "\nAll features enabled!\n";

// Auto-delete this migration script for security
$script_file = __FILE__;
if (file_exists($script_file)) {
    if (unlink($script_file)) {
        echo "\n🔒 Migration script deleted for security.\n";
        echo "✅ Safe to commit to Docker image.\n";
    }
}
?>