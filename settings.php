<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: src/auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newKurs = $_POST['preferensi_kurs'];
    
    try {
        $conn->beginTransaction();
        
        // Get current user preferences
        $stmt = $conn->prepare("SELECT preferensi_kurs FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentPref = $stmt->fetch();
        
        // Only convert if currency preference changed
        if ($currentPref['preferensi_kurs'] !== $newKurs) {
            // Update transactions
            $stmt = $conn->prepare("
                SELECT id, jumlah, kurs 
                FROM transactions 
                WHERE user_id = ? AND kurs = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $currentPref['preferensi_kurs']]);
            $transactions = $stmt->fetchAll();

            foreach ($transactions as $transaction) {
                $newAmount = convertAmount($transaction['jumlah'], $currentPref['preferensi_kurs'], $newKurs);
                $updateStmt = $conn->prepare("
                    UPDATE transactions 
                    SET jumlah = ?, kurs = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$newAmount, $newKurs, $transaction['id']]);
            }

            // Update debts
            $stmt = $conn->prepare("
                SELECT id, jumlah, kurs 
                FROM debts 
                WHERE user_id = ? AND kurs = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $currentPref['preferensi_kurs']]);
            $debts = $stmt->fetchAll();

            foreach ($debts as $debt) {
                $newAmount = convertAmount($debt['jumlah'], $currentPref['preferensi_kurs'], $newKurs);
                $updateStmt = $conn->prepare("
                    UPDATE debts 
                    SET jumlah = ?, kurs = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$newAmount, $newKurs, $debt['id']]);
            }
        }
        
        // Update user preference
        $stmt = $conn->prepare("UPDATE users SET preferensi_kurs = ? WHERE id = ?");
        $stmt->execute([$newKurs, $_SESSION['user_id']]);
        
        $conn->commit();
        $success = "Pengaturan berhasil disimpan! Semua nilai telah dikonversi ke " . $newKurs;
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Ambil pengaturan user
$stmt = $conn->prepare("SELECT preferensi_kurs FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get both conversion rates
$idrToUsd = getCachedRate('IDR', 'USD');
$usdToIdr = getCachedRate('USD', 'IDR');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="public/css/sidebar.css">
</head>
<body class="bg-gray-50">
    <?php include 'src/components/sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 main-content">
        <div class="bg-white p-8 rounded-lg shadow-xl w-[450px] mx-auto my-8">
            <div class="text-center mb-8">
                <i class="fas fa-cog text-5xl text-blue-500 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800">Pengaturan Akun</h1>
                <p class="text-gray-600">Sesuaikan preferensi akun Anda</p>
            </div>
                
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-check-circle mt-1 mr-2"></i>
                        <?php echo $success; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-money-bill-wave mr-2"></i>Mata Uang Utama
                    </label>
                    <select name="preferensi_kurs" 
                            class="w-full px-4 py-3 rounded border focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        <option value="IDR" <?php echo $user['preferensi_kurs'] === 'IDR' ? 'selected' : ''; ?>>
                            Rupiah (IDR)
                        </option>
                        <option value="USD" <?php echo $user['preferensi_kurs'] === 'USD' ? 'selected' : ''; ?>>
                            US Dollar (USD)
                        </option>
                    </select>
                </div>

                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-exchange-alt mr-2"></i>Kurs Saat Ini
                    </h3>
                    <?php if ($idrToUsd !== null && $usdToIdr !== null): ?>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p>IDR 10.000 = USD <?php echo number_format($idrToUsd * 10000, 4); ?></p>
                            <p>USD 1 = IDR <?php echo number_format($usdToIdr, 2); ?></p>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic">
                            Tidak dapat memuat kurs mata uang saat ini
                        </p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-4">
                    <a href="index.php" 
                       class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 
                              transition duration-200 text-center inline-flex items-center justify-center whitespace-nowrap">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 
                                   transition duration-200 text-center inline-flex items-center justify-center whitespace-nowrap">
                        <i class="fas fa-save mr-2"></i>Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
