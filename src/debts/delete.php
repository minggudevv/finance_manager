<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/security_helper.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("
        DELETE FROM debts 
        WHERE id = ? AND user_id = ?
    ");
    
    $success = $stmt->execute([$id, $_SESSION['user_id']]);
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Data berhasil dihapus' : 'Gagal menghapus data'
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
