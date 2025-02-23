<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/date_helper.php';
require_once __DIR__ . '/src/helpers/security_helper.php';
require_once __DIR__ . '/src/helpers/currency_helper.php';

addSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    header('Location: src/auth/login.php');
    exit;
}

// Mengambil data transaksi
$stmt = $conn->prepare("
    SELECT t.*, s.nama as storage_name, s.icon as storage_icon, s.jenis as storage_type 
    FROM transactions t
    JOIN storage_types s ON t.storage_type_id = s.id
    WHERE t.user_id = ? 
    ORDER BY t.tanggal DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Menghitung total pemasukan dan pengeluaran
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END), 0) as total_pemasukan,
        COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as total_pengeluaran
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totals = $stmt->fetch();

// Ambil preferensi kurs user
$stmt = $conn->prepare("SELECT preferensi_kurs FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Tambahkan perhitungan total setelah mengambil totals
$total_uang = $totals['total_pemasukan'] - $totals['total_pengeluaran'];

// Get conversion rate
$oppositeKurs = $user['preferensi_kurs'] === 'IDR' ? 'USD' : 'IDR';
$conversionRate = getCachedRate($user['preferensi_kurs'], $oppositeKurs);

// Get daily transactions for last 7 days
$stmt = $conn->prepare("
    SELECT 
        DATE(tanggal) as tanggal,
        SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END) as total_pemasukan,
        SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END) as total_pengeluaran
    FROM transactions 
    WHERE user_id = ? 
    AND tanggal >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    GROUP BY DATE(tanggal)
    ORDER BY tanggal ASC
");
$stmt->execute([$_SESSION['user_id']]);
$dailyTransactions = $stmt->fetchAll();

// Tambahkan query untuk hutang piutang
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'hutang' AND status = 'belum_lunas' THEN jumlah ELSE 0 END), 0) as total_hutang,
        COALESCE(SUM(CASE WHEN jenis = 'piutang' AND status = 'belum_lunas' THEN jumlah ELSE 0 END), 0) as total_piutang
    FROM debts 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$debt_totals = $stmt->fetch();

// Get active target for current month
$stmt = $conn->prepare("
    SELECT 
        t.*,
        COALESCE(SUM(tr.jumlah * CASE WHEN tr.jenis = 'pengeluaran' THEN -1 ELSE 1 END), 0) as current_balance
    FROM financial_targets t
    LEFT JOIN transactions tr ON tr.user_id = t.user_id 
        AND tr.tanggal BETWEEN t.start_date AND t.end_date
    WHERE t.user_id = ? 
    AND t.is_active = TRUE
    AND CURRENT_DATE BETWEEN t.start_date AND t.end_date
    GROUP BY t.id
    ORDER BY t.id DESC 
    LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$activeTarget = $stmt->fetch(PDO::FETCH_ASSOC);

// For AJAX calls
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    if (!validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencatat Keuangan - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="stylesheet" href="public/css/sidebar.css">
    <script src="public/js/main.js" defer></script>
</head>
<body class="bg-gray-50">
    <?php include 'src/components/sidebar.php'; ?>
    
    <!-- Update main content to have margin for sidebar -->
    <div id="main-content" class="ml-64 main-content">
        <!-- Move existing nav and main content here -->
        <nav class="bg-gradient-to-r from-blue-500 to-purple-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <span class="text-xl font-bold text-white">Pencatat Keuangan</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white">
                            <i class="fas fa-user mr-1"></i> 
                            Hai, <?php echo htmlspecialchars($_SESSION['nama']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 px-4">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-gradient-to-br from-green-400 to-green-500 p-6 rounded-lg shadow-lg transform hover:scale-105 transition duration-200">
                    <h2 class="text-xl font-semibold mb-4 text-white flex items-center">
                        <i class="fas fa-arrow-up mr-2"></i>Total Pemasukan
                    </h2>
                    <?php if ($totals['total_pemasukan'] > 0): ?>
                        <p class="text-3xl font-bold text-white">
                            <?php echo $user['preferensi_kurs'] ?> <?php echo number_format($totals['total_pemasukan'], 0, ',', '.'); ?>
                        </p>
                        <?php if ($conversionRate !== null): ?>
                            <div class="text-sm text-white/80 mt-2">
                                <span class="block">
                                    ≈ <?php echo $oppositeKurs . ' ' . number_format($totals['total_pemasukan'] * $conversionRate, 2); ?>
                                </span>
                                <span class="text-xs">
                                    IDR 10.000 = <?php echo $oppositeKurs . ' ' . number_format($conversionRate * 10000, 4); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-lg text-white italic">Tidak ada pemasukan</p>
                    <?php endif; ?>
                </div>

                <div class="bg-gradient-to-br from-red-400 to-red-500 p-6 rounded-lg shadow-lg transform hover:scale-105 transition duration-200">
                    <h2 class="text-xl font-semibold mb-4 text-white flex items-center">
                        <i class="fas fa-arrow-down mr-2"></i>Total Pengeluaran
                    </h2>
                    <?php if ($totals['total_pengeluaran'] > 0): ?>
                        <p class="text-3xl font-bold text-white">
                            <?php echo $user['preferensi_kurs'] ?> <?php echo number_format($totals['total_pengeluaran'], 0, ',', '.'); ?>
                        </p>
                        <?php if ($conversionRate !== null): ?>
                            <div class="text-sm text-white/80 mt-2">
                                <span class="block">
                                    ≈ <?php echo $oppositeKurs . ' ' . number_format($totals['total_pengeluaran'] * $conversionRate, 2); ?>
                                </span>
                                <span class="text-xs">
                                    IDR 10.000 = <?php echo $oppositeKurs . ' ' . number_format($conversionRate * 10000, 4); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-lg text-white italic">Tidak ada pengeluaran</p>
                    <?php endif; ?>
                </div>

                <div class="bg-gradient-to-br from-blue-400 to-blue-500 p-6 rounded-lg shadow-lg transform hover:scale-105 transition duration-200">
                    <h2 class="text-xl font-semibold mb-4 text-white flex items-center">
                        <i class="fas fa-coins mr-2"></i>Total Saldo
                    </h2>
                    <?php if ($totals['total_pemasukan'] > 0 || $totals['total_pengeluaran'] > 0): ?>
                        <p class="text-3xl font-bold text-white">
                            <?php echo $user['preferensi_kurs'] ?> <?php echo number_format($total_uang, 0, ',', '.'); ?>
                        </p>
                        <div class="text-sm text-white/80 mt-2">
                            <?php if ($conversionRate !== null): ?>
                                <span class="block">
                                    ≈ <?php echo $oppositeKurs . ' ' . number_format($total_uang * $conversionRate, 2); ?>
                                </span>
                                <span class="text-xs">
                                    IDR 10.000 = <?php echo $oppositeKurs . ' ' . number_format($conversionRate * 10000, 4); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-lg text-white italic">Tidak ada transaksi</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Target Card - Moved after summary cards -->
            <?php if ($activeTarget): 
                $progress = min(($total_uang / $activeTarget['amount']) * 100, 100);
                $remainingAmount = max($activeTarget['amount'] - $total_uang, 0);
                $statusColor = $progress >= 100 ? 'text-green-500' : 'text-yellow-500';
                $progressColor = $progress >= 100 ? 'bg-green-500' : 'bg-blue-500';
            ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-bullseye mr-2 text-blue-500"></i>
                        Target Bulan Ini
                    </h2>
                    <a href="targets.php" class="text-blue-500 hover:text-blue-600 text-sm">
                        <i class="fas fa-external-link-alt mr-1"></i>
                        Lihat Semua Target
                    </a>
                </div>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-sm text-gray-600">Target Saldo:</span>
                            <span class="ml-2 text-lg font-semibold">
                                <?php echo formatCurrency($activeTarget['amount'], $user['preferensi_kurs']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Saldo Saat Ini:</span>
                            <span class="ml-2 text-lg font-semibold <?php echo $total_uang >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                                <?php echo formatCurrency($total_uang, $user['preferensi_kurs']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="h-4 bg-gray-200 rounded-full overflow-hidden">
                            <div class="h-full <?php echo $progressColor; ?> transition-all duration-500"
                                 style="width: <?php echo $progress; ?>%">
                            </div>
                        </div>
                        <div class="absolute -top-1 left-0 w-full flex justify-center">
                            <span class="px-2 py-1 text-sm font-medium <?php echo $statusColor; ?>">
                                <?php echo number_format($progress, 1); ?>%
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <span class="text-sm text-gray-600 block mb-1">Sisa Target:</span>
                            <span class="text-lg font-semibold <?php echo $remainingAmount > 0 ? 'text-blue-500' : 'text-green-500'; ?>">
                                <?php echo formatCurrency($remainingAmount, $user['preferensi_kurs']); ?>
                            </span>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <span class="text-sm text-gray-600 block mb-1">Status:</span>
                            <span class="text-lg font-semibold <?php echo $statusColor; ?>">
                                <?php echo $progress >= 100 ? 'Target Tercapai!' : 'Dalam Progress'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="text-sm text-gray-500 mt-2">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        Periode: <?php echo date('d M Y', strtotime($activeTarget['start_date'])); ?> - 
                                <?php echo date('d M Y', strtotime($activeTarget['end_date'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enhanced Section Cards -->
            <div class="space-y-6">
                <!-- Grafik Section -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <div class="flex justify-between items-center cursor-pointer" 
                             onclick="toggleSection('graph')">
                            <h2 class="text-xl font-semibold text-white flex items-center">
                                <i class="fas fa-chart-bar mr-2"></i>Grafik Keuangan
                            </h2>
                            <i id="graph-icon" class="fas fa-chevron-up text-white transition-transform duration-200"></i>
                        </div>
                    </div>
                    <div id="graph-content" class="p-6">
                        <?php if ($totals['total_pemasukan'] > 0 || $totals['total_pengeluaran'] > 0 || $debt_totals['total_hutang'] > 0 || $debt_totals['total_piutang'] > 0): ?>
                            <div class="mb-4 flex justify-between items-center">
                                <div class="flex space-x-4">
                                    <select id="chartPeriod" onchange="changePeriod(this.value)" 
                                            class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                        <option value="total">Total Keseluruhan</option>
                                        <option value="daily">7 Hari Terakhir</option>
                                    </select>
                                    
                                    <select id="chartFilter" onchange="changeFilter(this.value)"
                                            class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                        <option value="all">Semua Data</option>
                                        <option value="transactions">Hanya Transaksi</option>
                                        <option value="debts">Hanya Hutang & Piutang</option>
                                    </select>
                                </div>
                                
                                <select id="chartType" onchange="changeChartType(this.value)" 
                                        class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    <option value="bar">Grafik Batang</option>
                                    <option value="line">Grafik Garis</option>
                                    <option value="pie">Grafik Lingkaran</option>
                                </select>
                            </div>
                            <div id="chart-container" class="w-full flex justify-center">
                                <canvas id="financeChart"></canvas>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500 italic py-8">Belum ada data untuk ditampilkan dalam grafik</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Storage Section -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <div class="flex justify-between items-center cursor-pointer"
                             onclick="toggleSection('storage')">
                            <h2 class="text-xl font-semibold text-white flex items-center">
                                <i class="fas fa-piggy-bank mr-2"></i>Ringkasan Penyimpanan
                            </h2>
                            <i id="storage-icon" class="fas fa-chevron-up text-white transition-transform duration-200"></i>
                        </div>
                    </div>
                    <div id="storage-content" class="p-6">
                        <div class="grid gap-4">
                            <?php
                            $stmt = $conn->prepare("
                                SELECT s.nama, s.icon, s.jenis,
                                       SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END) as total
                                FROM storage_types s
                                LEFT JOIN transactions t ON s.id = t.storage_type_id AND t.user_id = ?
                                GROUP BY s.id
                                HAVING total IS NOT NULL AND total != 0
                                ORDER BY total DESC
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $balances = $stmt->fetchAll();
                            
                            foreach ($balances as $balance): ?>
                                <div class="p-4 border rounded-lg hover:shadow-md transition duration-200">
                                    <div class="flex items-center justify-between">
                                        <span class="flex items-center">
                                            <i class="fas <?php echo $balance['icon']; ?> mr-2 
                                               <?php echo $balance['jenis'] === 'bank' ? 'text-blue-500' : 
                                                    ($balance['jenis'] === 'ewallet' ? 'text-green-500' : 'text-yellow-500'); ?>">
                                            </i>
                                            <?php echo htmlspecialchars($balance['nama']); ?>
                                        </span>
                                        <span class="<?php echo $balance['total'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $user['preferensi_kurs'] . ' ' . number_format(abs($balance['total']), 0, ',', '.'); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Transactions Section -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-semibold text-white flex items-center">
                                <i class="fas fa-list mr-2"></i>Transaksi Terbaru
                            </h2>
                            <div class="flex items-center space-x-4">
                                <a href="src/transactions/add.php" 
                                   class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition duration-200 shadow-md flex items-center">
                                    <i class="fas fa-plus mr-2"></i>Tambah Transaksi
                                </a>
                                <i id="transactions-icon" class="fas fa-chevron-up text-white cursor-pointer transition-transform duration-200"
                                   onclick="toggleSection('transactions')"></i>
                            </div>
                        </div>
                    </div>
                    <div id="transactions-content" class="p-6">
                        <?php if (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                                <div class="flex">
                                    <i class="fas fa-check-circle mt-1 mr-2"></i>
                                    Transaksi berhasil diupdate!
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (count($transactions) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disimpan di</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($transactions as $t): ?>
                                        <tr class="hover:bg-gray-50 transition duration-150">
                                            <td class="px-6 py-4"><?php echo formatTanggal($t['tanggal']); ?></td>
                                            <td class="px-6 py-4"><?php echo htmlspecialchars($t['kategori']); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center">
                                                    <i class="fas <?php echo $t['storage_icon']; ?> mr-2 
                                                        <?php 
                                                        echo $t['storage_type'] === 'bank' ? 'text-blue-500' : 
                                                             ($t['storage_type'] === 'ewallet' ? 'text-green-500' : 'text-yellow-500'); 
                                                        ?>">
                                                    </i>
                                                    <?php echo htmlspecialchars($t['storage_name']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="<?php echo $t['jenis'] === 'pemasukan' ? 'text-green-600' : 'text-red-600'; ?>">
                                                    <?php echo ucfirst($t['jenis']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php echo $t['kurs'] . ' ' . number_format($t['jumlah'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="src/transactions/edit.php?id=<?php echo $t['id']; ?>" 
                                                   class="text-blue-500 hover:underline mr-2">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button onclick="hapusTransaksi(<?php echo $t['id']; ?>)"
                                                        class="text-red-500 hover:underline">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-receipt text-gray-400 text-5xl mb-4"></i>
                                <p class="text-gray-500 italic">Belum ada transaksi yang tercatat</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Add CSRF token to all AJAX requests
    const csrfToken = '<?php echo generateCSRFToken(); ?>';
    
    // Store chart data globally with proper initialization
    const chartData = {
        pemasukan: <?php echo floatval($totals['total_pemasukan']); ?>,
        pengeluaran: <?php echo floatval($totals['total_pengeluaran']); ?>,
        hutang: <?php echo floatval($debt_totals['total_hutang']); ?>,
        piutang: <?php echo floatval($debt_totals['total_piutang']); ?>,
        currency: '<?php echo $user['preferensi_kurs']; ?>'
    };

    // Add daily transactions data
    const dailyData = <?php echo json_encode($dailyTransactions); ?>;

    // Add target amount to chart data
    const targetAmount = <?php echo $activeTarget ? $activeTarget['amount'] : 'null'; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Load preferred chart type and filter
        const preferredChartType = localStorage.getItem('preferredChartType') || 'bar';
        const preferredFilter = localStorage.getItem('preferredFilter') || 'all'; // Set default to 'all'
        
        if (document.getElementById('chartType')) {
            document.getElementById('chartType').value = preferredChartType;
        }
        if (document.getElementById('chartFilter')) {
            document.getElementById('chartFilter').value = preferredFilter;
        }

        // Initialize chart if element exists
        const financeChart = document.getElementById('financeChart');
        if (financeChart) {
            initFinanceChart(
                chartData.pemasukan,
                chartData.pengeluaran,
                chartData.hutang,
                chartData.piutang,
                chartData.currency
            );
        }
    });

    function changeChartType(type) {
        localStorage.setItem('preferredChartType', type);
        initFinanceChart(
            chartData.pemasukan,
            chartData.pengeluaran,
            chartData.hutang,
            chartData.piutang,
            chartData.currency
        );
    }

    function changePeriod(period) {
        localStorage.setItem('preferredPeriod', period);
        initFinanceChart(
            chartData.pemasukan,
            chartData.pengeluaran,
            chartData.hutang,
            chartData.piutang,
            chartData.currency
        );
    }

    function hapusTransaksi(id) {
        if (confirm('Apakah Anda yakin ingin menghapus transaksi ini?')) {
            fetch('src/transactions/hapus.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Gagal menghapus transaksi');
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($totals['total_pemasukan'] > 0 || $totals['total_pengeluaran'] > 0): ?>
        initFinanceChart(
            <?php echo $totals['total_pemasukan']; ?>,
            <?php echo $totals['total_pengeluaran']; ?>,
            '<?php echo $user['preferensi_kurs']; ?>'
        );
        <?php endif; ?>
    });
    
    // Enhanced toggle animation
    function toggleSection(sectionId) {
        const content = document.getElementById(`${sectionId}-content`);
        const icon = document.getElementById(`${sectionId}-icon`);
        
        content.classList.toggle('hidden');
        icon.style.transform = content.classList.contains('hidden') ? 'rotate(180deg)' : 'rotate(0)';
        
        saveSectionState(sectionId, !content.classList.contains('hidden'));
    }

    // Add conversion rate to chart tooltip
    const conversionRate = <?php echo $conversionRate ?: 'null'; ?>;
    const oppositeKurs = '<?php echo $oppositeKurs; ?>';

    // Modify chart options to include conversion
    const options = {
        // ...existing options...
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        const value = context.raw;
                        label += currency + ' ' + new Intl.NumberFormat('id-ID').format(value);
                        
                        if (conversionRate !== null) {
                            const convertedValue = value * conversionRate;
                            label += '\n≈ ' + oppositeKurs + 
                                    ' ' + new Intl.NumberFormat('id-ID').format(convertedValue.toFixed(2));
                        }
                        return label;
                    }
                }
            }
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('financeChart');
        if (!ctx) return;

        const labels = <?php echo json_encode(array_map(function($data) { 
            return date('d M', strtotime($data['tanggal']));
        }, $dailyTransactions)); ?>;

        const datasets = [
            {
                label: 'Pemasukan',
                data: <?php echo json_encode(array_map(function($data) { 
                    return floatval($data['total_pemasukan']);
                }, $dailyTransactions)); ?>,
                backgroundColor: 'rgba(34, 197, 94, 0.5)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 2,
                type: 'bar',
                order: 3
            },
            {
                label: 'Pengeluaran',
                data: <?php echo json_encode(array_map(function($data) {
                    return floatval($data['total_pengeluaran']);
                }, $dailyTransactions)); ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.5)',
                borderColor: 'rgb(239, 68, 68)',
                borderWidth: 2,
                type: 'bar',
                order: 2
            },
            {
                label: 'Saldo',
                data: <?php echo json_encode(array_map(function($data) {
                    return floatval($data['total_pemasukan'] - $data['total_pengeluaran']);
                }, $dailyTransactions)); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 2,
                type: 'line',
                order: 1,
                fill: false
            }
        ];

        // Add target line if exists
        <?php if ($activeTarget): ?>
        datasets.push({
            label: 'Target: <?php echo formatCurrency($activeTarget['amount'], $user['preferensi_kurs']); ?>',
            data: Array(<?php echo count($dailyTransactions); ?>).fill(<?php echo floatval($activeTarget['amount']); ?>),
            borderColor: 'rgba(234, 179, 8, 0.8)',
            backgroundColor: 'rgba(234, 179, 8, 0.1)',
            borderWidth: 2,
            borderDash: [5, 5],
            type: 'line',
            order: 0,
            tension: 0,
            fill: false,
            pointStyle: false
        });
        <?php endif; ?>

        new Chart(ctx, {
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '<?php echo $user['preferensi_kurs']; ?> ' + 
                                       new Intl.NumberFormat('id-ID').format(value);
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': <?php echo $user['preferensi_kurs']; ?> ' + 
                                       new Intl.NumberFormat('id-ID').format(context.raw);
                            }
                        }
                    }   
                }
            }
        });
    });
    </script>
    <script>
    // Update user activity status every 30 seconds
    function updateActivity() {
        fetch('src/api/update_activity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }

    // Initial update
    updateActivity();

    // Set interval for updates - Changed from 30000 to 10000 (10 seconds)
    setInterval(updateActivity, 10000);
    </script>
    <script>
function updateActivity() {
    return fetch('src/api/update_activity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    });
}

// Update status more frequently and immediately
function startActivityUpdates() {
    // Initial update
    updateActivity();
    
    // Update every 5 seconds
    setInterval(updateActivity, 5000);
}

// Start updates when page loads
document.addEventListener('DOMContentLoaded', startActivityUpdates);
</script>
</body>
</html>
