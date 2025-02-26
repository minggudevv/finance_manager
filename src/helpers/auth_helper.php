<?php
function createRememberMeToken($userId) {
    global $conn;
    
    try {
        // Check if remember_token column exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'remember_token'");
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if (!$column_exists) {
            // If columns don't exist, create them
            $conn->exec("ALTER TABLE users 
                        ADD COLUMN remember_token VARCHAR(64) NULL,
                        ADD COLUMN token_expires DATETIME NULL");
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
        
        $stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $userId]);
        
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        setcookie('user_id', $userId, time() + (30 * 24 * 60 * 60), '/', '', true, false);
        
        return true;
    } catch (PDOException $e) {
        error_log("Remember Me Token Error: " . $e->getMessage());
        return false;
    }
}

function validateRememberMeToken() {
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id'])) {
        global $conn;
        $stmt = $conn->prepare("
            SELECT * FROM users 
            WHERE id = ? AND remember_token = ? 
            AND token_expires > NOW()
        ");
        $stmt->execute([$_COOKIE['user_id'], $_COOKIE['remember_token']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            return true;
        }
    }
    return false;
}

function clearRememberMeToken() {
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie('user_id', '', time() - 3600, '/');
}
