<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/security_helper.php';
require_once __DIR__ . '/src/helpers/currency_helper.php';
require_once __DIR__ . '/src/helpers/date_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: src/auth/login.php');
    exit;
}

// Ambil data hutang
$stmt = $conn->prepare("
    SELECT *, 
           DATEDIFF(jatuh_tempo, CURRENT_DATE) as sisa_hari
    FROM debts 
    WHERE user_id = ? 
    ORDER BY status ASC, jatuh_tempo ASC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Hitung total hutang dan piutang
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN jenis = 'hutang' AND status = 'belum_lunas' THEN jumlah ELSE 0 END), 0) as total_hutang,
        COALESCE(SUM(CASE WHEN jenis = 'piutang' AND status = 'belum_lunas' THEN jumlah ELSE 0 END), 0) as total_piutang
    FROM debts 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$totals = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catatan Hutang - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="public/css/sidebar.css">
</head>
<body class="bg-gray-50">
    <?php include 'src/components/sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Total Hutang Card -->
            <div class="bg-gradient-to-br from-red-400 to-red-500 p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4 text-white flex items-center">
                    <i class="fas fa-hand-holding-usd mr-2"></i>Total Hutang
                </h2>
                <?php if ($totals['total_hutang'] > 0): ?>
                    <p class="text-3xl font-bold text-white">
                        IDR <?php echo number_format($totals['total_hutang'], 0, ',', '.'); ?>
                    </p>
                <?php else: ?>
                    <p class="text-lg text-white/80 italic">
                        Belum ada hutang
                    </p>
                <?php endif; ?>
            </div>

            <!-- Total Piutang Card -->
            <div class="bg-gradient-to-br from-green-400 to-green-500 p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold mb-4 text-white flex items-center">
                    <i class="fas fa-money-bill-wave mr-2"></i>Total Piutang
                </h2>
                <?php if ($totals['total_piutang'] > 0): ?>
                    <p class="text-3xl font-bold text-white">
                        IDR <?php echo number_format($totals['total_piutang'], 0, ',', '.'); ?>
                    </p>
                <?php else: ?>
                    <p class="text-lg text-white/80 italic">
                        Belum ada piutang
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 flex justify-between items-center">
                <h1 class="text-xl font-bold text-white">Daftar Hutang & Piutang</h1>
                <a href="src/debts/add.php" 
                   class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Tambah Baru
                </a>
            </div>

            <div class="p-6">
                <?php if (count($debts) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jenis</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($debts as $debt): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($debt['nama']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-phone mr-1"></i>
                                            <?php echo htmlspecialchars($debt['nomor_hp']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $debt['jenis'] === 'hutang' ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo ucfirst($debt['jenis']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo $debt['kurs'] . ' ' . number_format($debt['jumlah'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <?php echo formatTanggal($debt['jatuh_tempo']); ?>
                                        <?php if ($debt['status'] === 'belum_lunas'): ?>
                                            <div class="text-sm <?php echo $debt['sisa_hari'] < 0 ? 'text-red-500' : 
                                                ($debt['sisa_hari'] <= 7 ? 'text-yellow-500' : 'text-green-500'); ?>">
                                                <?php
                                                if ($debt['sisa_hari'] < 0) {
                                                    echo 'Terlambat ' . abs($debt['sisa_hari']) . ' hari';
                                                } else {
                                                    echo $debt['sisa_hari'] . ' hari lagi';
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $debt['status'] === 'lunas' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $debt['status'] === 'lunas' ? 'Lunas' : 'Belum Lunas'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="src/debts/edit.php?id=<?php echo $debt['id']; ?>" 
                                       class="text-blue-500 hover:underline mr-3">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($debt['status'] === 'belum_lunas'): ?>
                                    <button onclick="markAsPaid(<?php echo $debt['id']; ?>)"
                                            class="text-green-500 hover:underline mr-3">
                                        <i class="fas fa-check"></i> Lunas
                                    </button>
                                    <?php endif; ?>
                                    <button onclick="deleteDebt(<?php echo $debt['id']; ?>)"
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
                    <i class="fas fa-scroll text-gray-400 text-5xl mb-4"></i>
                    <p class="text-gray-500 italic">Belum ada catatan hutang/piutang</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function markAsPaid(id) {
        if (confirm('Tandai sebagai lunas?')) {
            // Implement AJAX call to mark as paid
        }
    }

    function deleteDebt(id) {
        if (confirm('Yakin ingin menghapus catatan ini?')) {
            // Implement AJAX call to delete
        }
    }
    </script>
</body>
</html>
