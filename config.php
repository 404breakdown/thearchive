<?php
function getDB() {
    static $db = null;
    if ($db === null) {
        $db_file = __DIR__ . '/data/thearchive.db';
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