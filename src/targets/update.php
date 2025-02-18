<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

try {
    $conn->beginTransaction();
    
    $amount = filter_var($_POST['target_amount'], FILTER_SANITIZE_NUMBER_INT);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $isActive = isset($_POST['is_active']);
    
    // Deactivate existing target for this month
    $stmt = $conn->prepare("
        UPDATE financial_targets 
        SET is_active = FALSE 
        WHERE user_id = ? 
        AND period_type = 'monthly'
        AND start_date = ?
        AND end_date = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $startDate, $endDate]);
    
    // Insert new target if active and amount > 0
    if ($isActive && $amount > 0) {
        $stmt = $conn->prepare("
            INSERT INTO financial_targets 
            (user_id, target_type, amount, kurs, is_active, period_type, start_date, end_date)
            VALUES (?, 'saldo', ?, ?, TRUE, 'monthly', ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $amount,
            $_SESSION['preferensi_kurs'] ?? 'IDR',
            $startDate,
            $endDate
        ]);
    }
    
    $conn->commit();
    header('Location: ../../targets.php?success=true');
} catch(PDOException $e) {
    $conn->rollBack();
    header('Location: ../../targets.php?error=' . urlencode($e->getMessage()));
}
