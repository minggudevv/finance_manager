<?php
function createRememberMeToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expires = time() + (30 * 24 * 60 * 60); // 30 hari
    
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET remember_token = ?, token_expires = ? WHERE id = ?");
    $stmt->execute([$token, date('Y-m-d H:i:s', $expires), $userId]);
    
    setcookie('remember_token', $token, $expires, '/', '', true, true);
    setcookie('user_id', $userId, $expires, '/', '', true, false);
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
