<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $amount = filter_var($_POST['target_amount'], FILTER_SANITIZE_NUMBER_INT);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        
        if ($amount <= 0) {
            throw new Exception('Target harus lebih dari 0');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO financial_targets (user_id, amount, start_date, end_date, is_active, kurs)
            VALUES (?, ?, ?, ?, TRUE, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $amount,
            $startDate,
            $endDate,
            $_SESSION['preferensi_kurs'] ?? 'IDR'
        ]);
        
        header('Location: ../../targets.php?success=true');
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
    <title>Tambah Target - Pencatat Keuangan</title>
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
                        <span class="text-xl font-bold text-white">Tambah Target Baru</span>
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
                                   placeholder="Masukkan target saldo"
                                   oninput="formatNumber(this)"
                                   required
                                   autocomplete="off">
                            <input type="hidden" name="target_amount" id="real_display_target">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Tanggal Mulai
                                </label>
                                <input type="date" 
                                       name="start_date"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                            
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Tanggal Selesai
                                </label>
                                <input type="date" 
                                       name="end_date"
                                       class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                       required>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:from-blue-600 hover:to-purple-700">
                            <i class="fas fa-save mr-2"></i>Simpan Target
                        </button>
                    </form>
                </div>
            </div>
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
