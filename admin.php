<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/security_helper.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

addSecurityHeaders();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $userId = intval($_POST['user_id']);
    
    switch ($_POST['action']) {
        case 'update_email':
            $newEmail = cleanInput($_POST['email']);
            if (validateEmail($newEmail)) {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$newEmail, $userId]);
                $success = "Email berhasil diupdate!";
            }
            break;
            
        case 'update_password':
            $newPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newPassword, $userId]);
            $success = "Password berhasil diupdate!";
            break;
            
        case 'delete_user':
            // Delete related records first
            $stmt = $conn->prepare("DELETE FROM transactions WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stmt = $conn->prepare("DELETE FROM financial_targets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $success = "User berhasil dihapus!";
            break;
    }
}

// Get all users
$stmt = $conn->prepare("
    SELECT 
        u.*,
        COUNT(DISTINCT t.id) as total_transactions,
        COUNT(DISTINCT ft.id) as total_targets
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    LEFT JOIN financial_targets ft ON u.id = ft.user_id
    WHERE u.is_admin = FALSE
    GROUP BY u.id
    ORDER BY u.nama
");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="public/css/sidebar.css">
</head>
<body class="bg-gray-50">
    <?php include 'src/components/admin_sidebar.php'; ?>
    
    <div id="main-content" class="ml-64 transition-all duration-300">
        <nav class="bg-gradient-to-r from-blue-600 to-purple-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <span class="text-xl font-bold text-white">Admin Panel</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white">
                            <i class="fas fa-user-shield mr-1"></i> 
                            Admin: <?php echo htmlspecialchars($_SESSION['nama']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-6 px-4">
            <?php if (isset($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-check-circle mt-1 mr-2"></i>
                        <?php echo $success; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-users mr-2"></i>Manajemen User
                    </h2>
                </div>
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Target</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-gray-400 mr-2"></i>
                                            <?php echo htmlspecialchars($user['nama']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $user['total_transactions']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $user['total_targets']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button onclick="showEditEmail(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')"
                                                class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-envelope"></i> Ubah Email
                                        </button>
                                        <button onclick="showEditPassword(<?php echo $user['id']; ?>)"
                                                class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-key"></i> Ubah Password
                                        </button>
                                        <button onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['nama']); ?>')"
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal backdrop -->
    <div id="modal-backdrop" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40"></div>

    <!-- Edit email modal -->
    <div id="edit-email-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Ubah Email</h3>
            <form id="edit-email-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_email">
                <input type="hidden" name="user_id" id="email-user-id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email Baru</label>
                    <input type="email" name="email" required id="new-email"
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('edit-email-modal')"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit password modal -->
    <div id="edit-password-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Ubah Password</h3>
            <form id="edit-password-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="user_id" id="password-user-id">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Password Baru</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('edit-password-modal')"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
        document.getElementById('modal-backdrop').classList.remove('hidden');
    }

    function hideModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
        document.getElementById('modal-backdrop').classList.add('hidden');
    }

    function showEditEmail(userId, currentEmail) {
        document.getElementById('email-user-id').value = userId;
        document.getElementById('new-email').value = currentEmail;
        showModal('edit-email-modal');
    }

    function showEditPassword(userId) {
        document.getElementById('password-user-id').value = userId;
        showModal('edit-password-modal');
    }

    function confirmDelete(userId, userName) {
        if (confirm(`Anda yakin ingin menghapus user ${userName}? Semua data transaksi dan target keuangan user ini akan ikut terhapus.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="${userId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>
