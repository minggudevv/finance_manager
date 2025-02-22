<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/security_helper.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $userId = $_SESSION['user_id'];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_email':
                $newEmail = cleanInput($_POST['email']);
                if (validateEmail($newEmail)) {
                    $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ? AND is_admin = TRUE");
                    $stmt->execute([$newEmail, $userId]);
                    $success = "Email admin berhasil diupdate!";
                } else {
                    $error = "Email tidak valid!";
                }
                break;
                
            case 'update_password':
                $currentPassword = $_POST['current_password'];
                $newPassword = $_POST['new_password'];
                $confirmPassword = $_POST['confirm_password'];
                
                // Get current user data
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND is_admin = TRUE");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if (!password_verify($currentPassword, $user['password'])) {
                    $error = "Password saat ini tidak sesuai!";
                } elseif ($newPassword !== $confirmPassword) {
                    $error = "Password baru dan konfirmasi tidak cocok!";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND is_admin = TRUE");
                    $stmt->execute([$hashedPassword, $userId]);
                    $success = "Password admin berhasil diupdate!";
                }
                break;
        }
    }
}

// Get current admin data
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ? AND is_admin = TRUE");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Admin - Pencatat Keuangan</title>
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
                        <span class="text-xl font-bold text-white">Pengaturan Admin</span>
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
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-check-circle mt-1 mr-2"></i>
                        <?php echo $success; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Update Email Form -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-envelope mr-2"></i>Update Email Admin
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_email">
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Email Saat Ini</label>
                                <input type="text" value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                       class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Email Baru</label>
                                <input type="email" name="email" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <button type="submit"
                                    class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
                                Update Email
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Update Password Form -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <i class="fas fa-key mr-2"></i>Update Password Admin
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="update_password">
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Password Saat Ini</label>
                                <input type="password" name="current_password" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Password Baru</label>
                                <input type="password" name="new_password" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Konfirmasi Password Baru</label>
                                <input type="password" name="confirm_password" required
                                       class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <button type="submit"
                                    class="w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600">
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
