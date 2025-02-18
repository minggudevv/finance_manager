<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: src/auth/login.php');
    exit;
}

// Get active target and progress
$stmt = $conn->prepare("
    SELECT 
        t.*,
        COALESCE(SUM(tr.jumlah * CASE WHEN tr.jenis = 'pengeluaran' THEN -1 ELSE 1 END), 0) as current_balance
    FROM financial_targets t
    LEFT JOIN transactions tr ON tr.user_id = t.user_id 
        AND tr.tanggal BETWEEN t.start_date AND t.end_date
    WHERE t.user_id = ? AND t.is_active = TRUE
    GROUP BY t.id
    ORDER BY t.start_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user currency preference
$stmt = $conn->prepare("SELECT preferensi_kurs FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Keuangan - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="public/css/sidebar.css">
</head>
<body class="bg-gray-50">
    <?php include 'src/components/sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 main-content">
        <nav class="bg-gradient-to-r from-blue-500 to-purple-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <span class="text-xl font-bold text-white">Target Keuangan</span>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 px-4">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 flex justify-between items-center">
                    <h1 class="text-xl font-bold text-white">Daftar Target</h1>
                    <a href="src/targets/add.php" 
                       class="bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-50 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Tambah Target
                    </a>
                </div>

                <div class="p-6">
                    <?php if (count($targets) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Target</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saldo Saat Ini</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($targets as $target): 
                                    $progress = min(($target['current_balance'] / $target['amount']) * 100, 100);
                                    $statusColor = $progress >= 100 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                                    $statusText = $progress >= 100 ? 'Tercapai' : 'Dalam Progress';
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <?php echo date('d M Y', strtotime($target['start_date'])); ?> -
                                        <?php echo date('d M Y', strtotime($target['end_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo formatCurrency($target['amount'], $user['preferensi_kurs']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo formatCurrency($target['current_balance'], $user['preferensi_kurs']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600"><?php echo number_format($progress, 1); ?>%</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusColor; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <a href="src/targets/edit.php?id=<?php echo $target['id']; ?>" 
                                           class="text-blue-500 hover:underline mr-3">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button onclick="deleteTarget(<?php echo $target['id']; ?>)"
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
                        <i class="fas fa-bullseye text-gray-400 text-5xl mb-4"></i>
                        <p class="text-gray-500 italic">Belum ada target yang ditambahkan</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    function deleteTarget(id) {
        if (confirm('Yakin ingin menghapus target ini?')) {
            fetch('src/targets/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: 'id=' + encodeURIComponent(id)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Gagal menghapus target');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus target');
            });
        }
    }
    </script>
</body>
</html>
