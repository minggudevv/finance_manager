<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if (!isset($_POST['id'])) {
        throw new Exception('ID tidak valid');
    }

    $stmt = $conn->prepare("
        DELETE FROM financial_targets 
        WHERE id = ? AND user_id = ?
    ");
    
    $result = $stmt->execute([
        $_POST['id'],
        $_SESSION['user_id']
    ]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Gagal menghapus target');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
