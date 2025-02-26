<?php
function verifyRecaptcha($response) {
    global $conn;
    
    // First check if reCAPTCHA is enabled
    $stmt = $conn->prepare("
        SELECT setting_value, is_enabled, recaptcha_version 
        FROM security_settings 
        WHERE setting_key = 'recaptcha_secret_key' 
        LIMIT 1
    ");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If disabled or no settings, allow through
    if (!$settings || !$settings['is_enabled']) {
        return true;
    }

    // If enabled but no response provided, fail verification
    if (empty($response)) {
        return false;
    }

    $secret = $settings['setting_value'];
    
    if (empty($secret)) {
        return true;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $response
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $result = json_decode($result);
    
    // Different verification for v2 and v3
    if ($settings['recaptcha_version'] === 'v3') {
        return $result->success && $result->score >= 0.5;
    } else {
        return $result->success;
    }
}

function getRecaptchaSettings() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT setting_key, setting_value, is_enabled, recaptcha_version 
        FROM security_settings 
        WHERE setting_key = 'recaptcha_site_key' 
        LIMIT 1
    ");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'enabled' => $settings['is_enabled'] ?? false,
        'site_key' => $settings['setting_value'] ?? '',
        'version' => $settings['recaptcha_version'] ?? 'v3'
    ];
}
