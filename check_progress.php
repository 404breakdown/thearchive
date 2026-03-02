<?php
// check_progress.php - Universal progress checker
session_start();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($type) || empty($id)) {
    echo json_encode(['status' => 'idle']);
    exit;
}

$temp_dir = __DIR__ . '/data/temp/';
$progress_file = $temp_dir . $type . '_progress_' . $id . '.json';

if (file_exists($progress_file)) {
    $content = file_get_contents($progress_file);
    echo $content;
} else {
    echo json_encode(['status' => 'idle', 'progress' => 0, 'message' => 'Waiting...']);
}
?>