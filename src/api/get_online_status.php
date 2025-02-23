<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security_helper.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$stmt = $conn->prepare("
    SELECT id, 
           CASE WHEN last_activity >= NOW() - INTERVAL 30 SECOND THEN 1 ELSE 0 END as is_online,
           last_activity
    FROM users 
    WHERE is_admin = FALSE
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'users' => $users,
    'timestamp' => time()
]);
