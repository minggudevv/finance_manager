<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/security_helper.php';
require_once __DIR__ . '/src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: src/auth/login.php');
    exit;
}

$period = $_GET['period'] ?? 'daily';
$currency_from = $_GET['from'] ?? 'IDR';
$currency_to = $_GET['to'] ?? 'USD';

// Get historical rates based on period
$historical_rates = [];
switch ($period) {
    case 'hourly':
        $endpoint = "https://api.frankfurter.app/latest?from={$currency_from}&to={$currency_to}";
        break;
    case 'daily':
        $endpoint = "https://api.frankfurter.app/2024-01-01..?from={$currency_from}&to={$currency_to}";
        break;
    case 'weekly':
        $endpoint = "https://api.frankfurter.app/2024-01-01..?from={$currency_from}&to={$currency_to}";
        break;
    case 'monthly':
        $endpoint = "https://api.frankfurter.app/2023-01-01..?from={$currency_from}&to={$currency_to}";
        break;
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurs Mata Uang - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="public/css/sidebar.css">
</head>
<body class="bg-gray-50">
    <?php include 'src/components/sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 main-content">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6">
                <i class="fas fa-exchange-alt mr-2 text-blue-500"></i>
                Kurs Mata Uang
            </h1>
            
            <div class="mb-6 flex items-center space-x-4">
                <select id="period" onchange="updateChart()" 
                        class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="hourly" <?php echo $period === 'hourly' ? 'selected' : ''; ?>>Per Jam</option>
                    <option value="daily" <?php echo $period === 'daily' ? 'selected' : ''; ?>>Harian</option>
                    <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                    <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                </select>
                
                <div class="flex items-center space-x-3">
                    <select id="currency_from" onchange="updateCurrencyPair()" 
                            class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="IDR" <?php echo $currency_from === 'IDR' ? 'selected' : ''; ?>>Rupiah (IDR)</option>
                        <option value="USD" <?php echo $currency_from === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                    </select>
                    
                    <i class="fas fa-exchange-alt text-blue-500"></i>
                    
                    <select id="currency_to" disabled 
                            class="px-3 py-2 border rounded-lg bg-gray-100 cursor-not-allowed">
                        <option value="USD" <?php echo $currency_to === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                        <option value="IDR" <?php echo $currency_to === 'IDR' ? 'selected' : ''; ?>>Rupiah (IDR)</option>
                    </select>
                </div>
            </div>
            
            <div class="w-full h-[400px] bg-white p-4 rounded-lg border">
                <canvas id="exchangeChart"></canvas>
            </div>

            <?php if (!$data): ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-exclamation-circle text-4xl mb-2"></i>
                <p>Tidak dapat memuat data kurs mata uang saat ini</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    const chartData = <?php echo json_encode($data); ?>;
    
    function initExchangeChart() {
        if (!chartData || !chartData.rates) {
            console.error('No exchange rate data available');
            return;
        }

        const ctx = document.getElementById('exchangeChart').getContext('2d');
        const dates = Object.keys(chartData.rates);
        const rates = dates.map(date => {
            const rate = chartData.rates[date][currency_to.value];
            return currency_from.value === 'IDR' ? rate * 10000 : rate;
        });
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: `${currency_from.value === 'IDR' ? '10.000 ' : '1 '}${currency_from.value}/${currency_to.value}`,
                    data: rates,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${currency_from.value === 'IDR' ? '10.000' : '1'} ${currency_from.value} = ${context.raw.toFixed(4)} ${currency_to.value}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return `${value.toFixed(4)} ${currency_to.value}`;
                            }
                        }
                    }
                }
            }
        });
    }

    function updateCurrencyPair() {
        const from = document.getElementById('currency_from').value;
        const to = document.getElementById('currency_to');
        
        // Auto-select opposite currency
        to.value = from === 'IDR' ? 'USD' : 'IDR';
        
        updateChart();
    }

    function updateChart() {
        const period = document.getElementById('period').value;
        const from = document.getElementById('currency_from').value;
        const to = document.getElementById('currency_to').value;
        window.location.href = `exchange.php?period=${period}&from=${from}&to=${to}`;
    }

    document.addEventListener('DOMContentLoaded', initExchangeChart);
    </script>
</body>
</html>
