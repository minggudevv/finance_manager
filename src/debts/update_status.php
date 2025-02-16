<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/security_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }

    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    $status = $_POST['status'] === 'lunas' ? 'lunas' : 'belum_lunas';

    try {
        $stmt = $conn->prepare("
            UPDATE debts 
            SET status = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$status, $id, $_SESSION['user_id']]);
        
        echo json_encode(['success' => true]);
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}
