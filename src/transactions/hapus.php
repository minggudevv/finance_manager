<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security_helper.php';

addSecurityHeaders();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("
            DELETE FROM transactions 
            WHERE id = ? AND user_id = ?
        ");
        $result = $stmt->execute([$id, $user_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Transaksi tidak ditemukan atau Anda tidak memiliki akses'
            ]);
        }
    } catch(PDOException $e) {
        error_log($e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Terjadi kesalahan saat menghapus transaksi'
        ]);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing ID']);
}
