<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get target data
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("
        SELECT t.*, 
               COALESCE(SUM(tr.jumlah * CASE WHEN tr.jenis = 'pengeluaran' THEN -1 ELSE 1 END), 0) as current_balance
        FROM financial_targets t
        LEFT JOIN transactions tr ON tr.user_id = t.user_id 
            AND tr.tanggal BETWEEN t.start_date AND t.end_date
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        header('Location: ../../targets.php');
        exit;
    }
} else {
    header('Location: ../../targets.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $amount = filter_var($_POST['target_amount'], FILTER_SANITIZE_NUMBER_INT);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $isActive = isset($_POST['is_active']);
        
        if ($amount <= 0) {
            throw new Exception('Target harus lebih dari 0');
        }
        
        $stmt = $conn->prepare("
            UPDATE financial_targets 
            SET amount = ?, 
                start_date = ?,
                end_date = ?,
                is_active = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([
            $amount,
            $startDate,
            $endDate,
            $isActive,
            $target['id'],
            $_SESSION['user_id']
        ]);
        
        header('Location: ../../targets.php?success=edit');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

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
    <title>Edit Target - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../public/css/sidebar.css">
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 main-content">
        <nav class="bg-gradient-to-r from-blue-500 to-purple-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="../../targets.php" class="text-white hover:text-gray-200 mr-4">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <span class="text-xl font-bold text-white">Edit Target</span>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 px-4">
            <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <div class="flex">
                    <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                    <?php echo $error; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Target Saldo
                            </label>
                            <input type="text" 
                                   name="display_target"
                                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   value="<?php echo number_format($target['amount'], 0, ',', '.'); ?>"
                                   oninput="formatNumber(this)"
                                   required
                                   autocomplete="off">
                            <input type="hidden" name="target_amount" id="real_display_target" value="<?php echo $target['amount']; ?>">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Tanggal Mulai
                                </label>
                                <input type="date" 
                                       name="start_date"
                                       value="<?php echo $target['start_date']; ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Tanggal Selesai
                                </label>
                                <input type="date" 
                                       name="end_date"
                                       value="<?php echo $target['end_date']; ?>"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" 
                                   name="is_active"
                                   id="is_active"
                                   class="h-4 w-4 text-blue-600 border-gray-300 rounded"
                                   <?php echo $target['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Target Aktif
                            </label>
                        </div>

                        <button type="submit"
                                class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:from-blue-600 hover:to-purple-700">
                            <i class="fas fa-save mr-2"></i>Update Target
                        </button>
                    </form>
                </div>
            </div>

            <!-- Progress Info -->
            <?php if ($target['current_balance'] > 0): 
                $progress = min(($target['current_balance'] / $target['amount']) * 100, 100);
                $statusColor = $progress >= 100 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
            ?>
            <div class="mt-6 bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Progress Target</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Saldo Saat Ini:</span>
                            <span><?php echo formatCurrency($target['current_balance'], $user['preferensi_kurs']); ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Progress:</span>
                            <span class="<?php echo $statusColor; ?> px-2 rounded-full">
                                <?php echo number_format($progress, 1); ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function formatNumber(input) {
        let value = input.value.replace(/\./g, '');
        if (value !== '') {
            const number = parseInt(value);
            if (!isNaN(number)) {
                input.value = new Intl.NumberFormat('id-ID').format(number);
                document.getElementById('real_display_target').value = number;
            }
        }
    }
    </script>
</body>
</html>
