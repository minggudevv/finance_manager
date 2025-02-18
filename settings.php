<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: src/auth/login.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_email':
                try {
                    $newEmail = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
                    $password = $_POST['password'];

                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();

                    if (!password_verify($password, $user['password'])) {
                        throw new Exception('Password salah');
                    }

                    // Check if email already exists
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$newEmail, $_SESSION['user_id']]);
                    if ($stmt->fetch()) {
                        throw new Exception('Email sudah digunakan');
                    }

                    // Update email
                    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                    $stmt->execute([$newEmail, $_SESSION['user_id']]);
                    $success = "Email berhasil diperbarui";
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'update_password':
                try {
                    $currentPassword = $_POST['current_password'];
                    $newPassword = $_POST['new_password'];
                    $confirmPassword = $_POST['confirm_password'];

                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();

                    if (!password_verify($currentPassword, $user['password'])) {
                        throw new Exception('Password saat ini salah');
                    }

                    if (strlen($newPassword) < 6) {
                        throw new Exception('Password baru minimal 6 karakter');
                    }

                    if ($newPassword !== $confirmPassword) {
                        throw new Exception('Konfirmasi password tidak cocok');
                    }

                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                    $success = "Password berhasil diperbarui";
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'delete_account':
                try {
                    $conn->beginTransaction();
                    
                    // Delete all related data
                    $stmt = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    $stmt = $conn->prepare("DELETE FROM debts WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    $stmt = $conn->prepare("DELETE FROM financial_targets WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    // Finally delete the user
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    $conn->commit();
                    
                    // Destroy session and redirect to login
                    session_destroy();
                    header('Location: src/auth/login.php?message=account_deleted');
                    exit;
                } catch(PDOException $e) {
                    $conn->rollBack();
                    $error = "Gagal menghapus akun: " . $e->getMessage();
                }
                break;
        }
    } else {
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
}

// Get user data
$stmt = $conn->prepare("SELECT email, preferensi_kurs FROM users WHERE id = ?");
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
    <style>
    /* Add smooth transition styles */
    .collapse-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }

    .collapse-content.expanded {
        max-height: 1000px; /* Adjust based on content */
        transition: max-height 0.5s ease-in;
    }

    .rotate-icon {
        transform: rotate(180deg);
        transition: transform 0.3s ease;
    }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'src/components/sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 main-content">
        <div class="bg-white p-8 rounded-lg shadow-xl w-[800px] mx-auto my-8">
            <!-- Currency Settings Section -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-money-bill-wave mr-2"></i>Pengaturan Mata Uang
                    </h2>
                </div>
                <div class="p-6">
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

            <!-- Email Settings Section -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                    <div class="flex justify-between items-center cursor-pointer" 
                         onclick="toggleSection('email')">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-envelope mr-2"></i>Ubah Email
                        </h2>
                        <i id="email-icon" class="fas fa-chevron-down text-white transition-transform duration-300"></i>
                    </div>
                </div>
                <div id="email-content" class="collapse-content">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_email">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Saat Ini</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="mt-1 w-full px-4 py-2 border rounded-lg bg-gray-50" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Baru</label>
                            <input type="email" name="new_email" required 
                                   class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" name="password" required 
                                   class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-save mr-2"></i>Perbarui Email
                        </button>
                    </form>
                </div>
            </div>

            <!-- Password Settings Section -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                    <div class="flex justify-between items-center cursor-pointer" 
                         onclick="toggleSection('password')">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-key mr-2"></i>Ubah Password
                        </h2>
                        <i id="password-icon" class="fas fa-chevron-down text-white transition-transform duration-300"></i>
                    </div>
                </div>
                <div id="password-content" class="collapse-content">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_password">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password Saat Ini</label>
                            <input type="password" name="current_password" required 
                                   class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password Baru</label>
                            <input type="password" name="new_password" required 
                                   class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" required 
                                   class="mt-1 w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-save mr-2"></i>Perbarui Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Danger Zone Section -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-red-500 to-red-600 p-4">
                    <div class="flex justify-between items-center cursor-pointer" 
                         onclick="toggleSection('danger')">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Zona Berbahaya
                        </h2>
                        <i id="danger-icon" class="fas fa-chevron-down text-white transition-transform duration-300"></i>
                    </div>
                </div>
                <div id="danger-content" class="collapse-content">
                    <h3 class="text-red-600 font-semibold mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Zona Berbahaya
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Menghapus akun akan menghapus semua data Anda termasuk transaksi, hutang piutang, dan target keuangan.
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                    <button onclick="confirmDeleteAccount()" 
                            class="w-full bg-red-500 text-white px-6 py-3 rounded-lg hover:bg-red-600 
                                   transition duration-200 flex items-center justify-center">
                        <i class="fas fa-user-times mr-2"></i>
                        Hapus Akun Saya
                    </button>
                    
                    <!-- Hidden form for delete action -->
                    <form id="deleteAccountForm" method="POST" class="hidden">
                        <input type="hidden" name="action" value="delete_account">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleSection(sectionId) {
        const content = document.getElementById(`${sectionId}-content`);
        const icon = document.getElementById(`${sectionId}-icon`);
        
        // Toggle expanded class for animation
        content.classList.toggle('expanded');
        icon.classList.toggle('rotate-icon');
        
        // Save state to localStorage
        localStorage.setItem(`settings_${sectionId}_expanded`, content.classList.contains('expanded'));
    }

    // Initialize sections on page load
    document.addEventListener('DOMContentLoaded', function() {
        const sections = ['email', 'password', 'danger'];
        sections.forEach(section => {
            const content = document.getElementById(`${section}-content`);
            const icon = document.getElementById(`${section}-icon`);
            const isExpanded = localStorage.getItem(`settings_${section}_expanded`) === 'true';
            
            if (isExpanded) {
                content.classList.add('expanded');
                icon.classList.add('rotate-icon');
            }
        });
    });

    function confirmDeleteAccount() {
        if (confirm('PERINGATAN: Semua data Anda akan dihapus permanen.\n\nApakah Anda yakin ingin menghapus akun?')) {
            if (confirm('Apakah Anda benar-benar yakin? Tindakan ini tidak dapat dibatalkan.')) {
                document.getElementById('deleteAccountForm').submit();
            }
        }
    }
    </script>
</body>
</html>
