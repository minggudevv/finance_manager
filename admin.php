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

// Update user's last activity
$stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);

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

        case 'add_user':
            $nama = cleanInput($_POST['nama']);
            $email = cleanInput($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            try {
                $stmt = $conn->prepare("INSERT INTO users (nama, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$nama, $email, $password]);
                $success = "User baru berhasil ditambahkan!";
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Email sudah terdaftar!";
                } else {
                    $error = "Terjadi kesalahan, silakan coba lagi.";
                }
            }
            break;
    }
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Prepare the base query
$query = "
    SELECT 
        u.*,
        COUNT(DISTINCT t.id) as total_transactions,
        COUNT(DISTINCT ft.id) as total_targets,
        CASE 
            WHEN last_activity >= NOW() - INTERVAL 30 SECOND THEN 1 
            ELSE 0 
        END as is_online
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    LEFT JOIN financial_targets ft ON u.id = ft.user_id
    WHERE u.is_admin = FALSE
";

// Add search condition
if ($search !== '') {
    $query .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
}

// Add filter condition
if ($filter === 'online') {
    $query .= " AND last_activity >= NOW() - INTERVAL 5 MINUTE";
} elseif ($filter === 'offline') {
    $query .= " AND (last_activity < NOW() - INTERVAL 5 MINUTE OR last_activity IS NULL)";
}

$query .= " GROUP BY u.id ORDER BY u.nama";

// Execute query with search parameters
$stmt = $conn->prepare($query);
if ($search !== '') {
    $searchParam = "%$search%";
    $stmt->execute([$searchParam, $searchParam]);
} else {
    $stmt->execute();
}
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                
                <!-- Add search and filter controls -->
                <div class="p-4 border-b">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <div class="flex gap-4 items-center">
                            <select id="statusFilter" onchange="applyFilters()" 
                                    class="px-3 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Semua User</option>
                                <option value="online" <?php echo $filter === 'online' ? 'selected' : ''; ?>>Online</option>
                                <option value="offline" <?php echo $filter === 'offline' ? 'selected' : ''; ?>>Offline</option>
                            </select>
                            <div class="relative">
                                <input type="text" id="searchInput" 
                                       placeholder="Cari nama atau email..."
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       class="pl-10 pr-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            <button onclick="applyFilters()" 
                                    class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                                <i class="fas fa-filter mr-2"></i>Terapkan Filter
                            </button>
                        </div>
                        <button onclick="showAddUserModal()" 
                                class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition duration-200">
                            <i class="fas fa-user-plus mr-2"></i>Tambah User Baru
                        </button>
                        <div class="text-sm text-gray-600">
                            Total: <?php echo count($users); ?> user
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Transaksi</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Target</th>
                                    <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="usersTableBody">
                                <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?php echo $user['id']; ?>" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center">
                                            <span class="status-dot w-2 h-2 rounded-full mr-2 <?php echo $user['is_online'] ? 'bg-green-500' : 'bg-gray-500'; ?>"></span>
                                            <span class="status-text"><?php echo $user['is_online'] ? 'Online' : 'Offline'; ?></span>
                                        </span>
                                    </td>
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

    <!-- Add new modal for adding user -->
    <div id="add-user-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl p-6 w-96">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-user-plus mr-2 text-green-500"></i>
                Tambah User Baru
            </h3>
            <form id="add-user-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="add_user">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-user mr-2"></i>Nama
                        </label>
                        <input type="text" name="nama" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email
                        </label>
                        <input type="email" name="email" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" name="password" required id="new-user-password"
                                   onkeyup="checkNewUserPassword(this.value)"
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" onclick="toggleNewUserPassword()"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p id="password-hint" class="text-xs text-gray-500 mt-1">
                            Gunakan kombinasi huruf, angka, dan simbol untuk password yang kuat
                        </p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="hideModal('add-user-modal')"
                            class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                        <i class="fas fa-plus mr-2"></i>Tambah User
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

    function applyFilters() {
        const filter = document.getElementById('statusFilter').value;
        const search = document.getElementById('searchInput').value;
        window.location.href = `admin.php?filter=${filter}&search=${encodeURIComponent(search)}`;
    }

    // Add enter key handler for search input
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });

    // Combine both status update functions into one
    function updateStatus() {
        return $.ajax({
            url: 'src/api/get_online_status.php',
            method: 'GET',
            success: function(response) {
                if (response.users) {
                    response.users.forEach(user => {
                        const row = document.querySelector(`tr[data-user-id="${user.id}"]`);
                        if (row) {
                            const statusDot = row.querySelector('.status-dot');
                            const statusText = row.querySelector('.status-text');
                            
                            if (user.is_online) {
                                statusDot.classList.remove('bg-gray-500');
                                statusDot.classList.add('bg-green-500');
                                statusText.textContent = 'Online';
                                row.classList.add('is-online');
                            } else {
                                statusDot.classList.remove('bg-green-500');
                                statusDot.classList.add('bg-gray-500');
                                statusText.textContent = 'Offline';
                                row.classList.remove('is-online');
                            }
                        }
                    });
                }
            }
        });
    }

    // Function to update admin's activity and then check all users' status
    function updateActivity() {
        $.ajax({
            url: 'src/api/update_activity.php',
            method: 'POST',
            success: function() {
                updateStatus(); // Update status immediately after updating activity
            }
        });
    }

    // Initialize everything when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Initial updates
        updateActivity();
        updateStatus();
        
        // Set intervals for periodic updates
        setInterval(updateActivity, 10000); // Every 10 seconds
    });

    // Add live search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#usersTableBody tr');
        
        rows.forEach(row => {
            const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            
            if (name.includes(searchText) || email.includes(searchText)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    function showAddUserModal() {
        showModal('add-user-modal');
    }

    function toggleNewUserPassword() {
        const password = document.getElementById('new-user-password');
        const icon = event.currentTarget.querySelector('i');
        
        if (password.type === 'password') {
            password.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            password.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    function checkNewUserPassword(password) {
        const hint = document.getElementById('password-hint');
        let strength = 0;
        
        // Simple password strength check (just for feedback, not validation)
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        // Update hint color based on strength
        if (strength <= 1) {
            hint.className = 'text-xs text-red-500 mt-1';
            hint.textContent = 'Password lemah';
        } else if (strength === 2) {
            hint.className = 'text-xs text-orange-500 mt-1';
            hint.textContent = 'Password sedang';
        } else if (strength === 3) {
            hint.className = 'text-xs text-yellow-500 mt-1';
            hint.textContent = 'Password kuat';
        } else {
            hint.className = 'text-xs text-green-500 mt-1';
            hint.textContent = 'Password sangat kuat';
        }
    }
    </script>
</body>
</html>
