<?php
function getExchangeRate($from, $to) {
    $url = "https://api.frankfurter.app/latest?from={$from}&to={$to}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true, // Enable SSL verification
        CURLOPT_TIMEOUT => 10, // Timeout after 10 seconds
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => 'PencatatKeuangan/1.0'
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Currency API Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Currency API returned HTTP code {$httpCode}");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Currency API JSON Error: ' . json_last_error_msg());
        return null;
    }
    
    if (isset($data['rates'][$to])) {
        return $data['rates'][$to];
    }
    
    return null;
}

function convertCurrency($amount, $from, $to) {
    $rate = getExchangeRate($from, $to);
    if ($rate === null) {
        return null;
    }
    return $amount * $rate;
}

// Cache exchange rate in session to avoid too many API calls
function getCachedRate($from, $to) {
    $cacheKey = "exchange_rate_{$from}_{$to}";
    
    // Check if rate is cached and not expired (1 hour cache)
    if (!isset($_SESSION[$cacheKey]) || 
        !isset($_SESSION[$cacheKey . '_time']) ||
        (time() - $_SESSION[$cacheKey . '_time'] > 3600)) {
        
        $rate = getExchangeRate($from, $to);
        if ($rate !== null) {
            // Adjust rate for IDR to be per 10,000
            if ($from === 'IDR') {
                $rate = $rate;  // Rate is already per 1 IDR
            }
            $_SESSION[$cacheKey] = $rate;
            $_SESSION[$cacheKey . '_time'] = time();
            return $rate;
        }
        
        // If API fails, try to use existing cached rate if available
        if (isset($_SESSION[$cacheKey])) {
            return $_SESSION[$cacheKey];
        }
        
        return null;
    }
    
    return $_SESSION[$cacheKey];
}

function formatCurrency($amount, $currency = 'IDR') {
    if ($currency === 'IDR') {
        return number_format($amount, 0, ',', '.');
    } else {
        return number_format($amount, 2, '.', ',');
    }
}

function formatExchangeRate($rate, $from, $to) {
    if ($from === 'IDR') {
        // Show rate per 10,000 IDR
        return number_format($rate * 10000, 4);
    } else {
        // Show direct rate
        return number_format($rate, 2);
    }
}

// Debug function to check API response
function testExchangeRate($from = 'IDR', $to = 'USD') {
    $url = "https://api.frankfurter.app/latest?from={$from}&to={$to}";
    echo "Testing URL: {$url}\n";
    
    $response = file_get_contents($url);
    if ($response === false) {
        echo "Error fetching data\n";
        return;
    }
    
    $data = json_decode($response, true);
    echo "API Response:\n";
    print_r($data);
}

function getConversionRate($from, $to) {
    if ($from === $to) return 1;
    
    $rate = getCachedRate($from, $to);
    if ($rate === null) {
        error_log("Failed to get conversion rate from $from to $to");
        return 1;
    }
    
    if ($from === 'IDR' && $to === 'USD') {
        return $rate;
    } else if ($from === 'USD' && $to === 'IDR') {
        return $rate;
    }
    
    return 1;
}

function convertAmount($amount, $fromCurrency, $toCurrency) {
    if ($fromCurrency === $toCurrency) {
        return $amount;
    }

    $rate = getCachedRate($fromCurrency, $toCurrency);
    if ($rate === null) {
        return $amount;
    }

    // Menggunakan BCMath untuk presisi yang lebih baik
    if (!function_exists('bcmul')) {
        // Fallback jika BCMath tidak tersedia
        return round($amount * $rate, 4);
    }

    // Set scale ke 8 untuk presisi yang lebih baik
    bcscale(8);
    return rtrim(rtrim(bcmul($amount, $rate), '0'), '.');
}

// Add this function to verify successful conversion
function verifyConversion($conn, $userId, $targetKurs) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM transactions 
        WHERE user_id = ? AND kurs != ?
    ");
    $stmt->execute([$userId, $targetKurs]);
    $unconverted = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM debts
        WHERE user_id = ? AND kurs != ?
    ");
    $stmt->execute([$userId, $targetKurs]);
    $unconvertedDebts = $stmt->fetchColumn();
    
    return $unconverted === 0 && $unconvertedDebts === 0;
}
