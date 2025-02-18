<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/security_helper.php';

addSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $nama = cleanInput($_POST['nama']);
    $email = cleanInput($_POST['email']);
    
    if (!validateEmail($email)) {
        $error = "Email tidak valid!";
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO users (nama, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$nama, $email, $password]);
            
            header('Location: login.php?success=1');
            exit;
        } catch(PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Email sudah terdaftar!";
            } else {
                $error = "Terjadi kesalahan, silakan coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl w-96">
        <div class="text-center mb-8">
            <i class="fas fa-user-plus text-5xl text-blue-500 mb-4"></i>
            <h1 class="text-2xl font-bold text-gray-800">Buat Akun Baru</h1>
            <p class="text-gray-600">Daftar untuk mulai mencatat keuangan Anda</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <div class="flex">
                    <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                    <?php echo $error; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-user mr-2"></i>Nama
                </label>
                <input type="text" name="nama" required 
                       class="w-full px-4 py-3 rounded border focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-envelope mr-2"></i>Email
                </label>
                <input type="email" name="email" required 
                       class="w-full px-4 py-3 rounded border focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">
                    <i class="fas fa-lock mr-2"></i>Password
                </label>
                <div class="relative">
                    <input type="password" name="password" required id="password"
                           onkeyup="checkPasswordStrength(this.value)"
                           class="w-full px-4 py-3 rounded border focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <button type="button" onclick="togglePassword()"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <!-- Password strength indicator -->
                <div class="mt-2">
                    <div class="flex space-x-2 mb-1">
                        <div id="strength-bar-1" class="h-1 w-1/4 bg-gray-200 rounded transition-colors duration-200"></div>
                        <div id="strength-bar-2" class="h-1 w-1/4 bg-gray-200 rounded transition-colors duration-200"></div>
                        <div id="strength-bar-3" class="h-1 w-1/4 bg-gray-200 rounded transition-colors duration-200"></div>
                        <div id="strength-bar-4" class="h-1 w-1/4 bg-gray-200 rounded transition-colors duration-200"></div>
                    </div>
                    <p id="password-strength" class="text-xs text-gray-500">Password harus memiliki minimal 8 karakter</p>
                </div>
            </div>

            <button type="submit" id="submit-btn" disabled
                    class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 
                           disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-user-plus mr-2"></i>Daftar Sekarang
            </button>
        </form>

        <p class="mt-6 text-center text-gray-600">
            Sudah punya akun? 
            <a href="login.php" class="text-blue-500 hover:underline">
                <i class="fas fa-sign-in-alt mr-1"></i>Login disini
            </a>
        </p>
    </div>

    <script>
    function togglePassword() {
        const password = document.getElementById('password');
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

    function checkPasswordStrength(password) {
        const strengthBar1 = document.getElementById('strength-bar-1');
        const strengthBar2 = document.getElementById('strength-bar-2');
        const strengthBar3 = document.getElementById('strength-bar-3');
        const strengthBar4 = document.getElementById('strength-bar-4');
        const strengthText = document.getElementById('password-strength');
        const submitBtn = document.getElementById('submit-btn');

        // Reset bars
        [strengthBar1, strengthBar2, strengthBar3, strengthBar4].forEach(bar => {
            bar.className = 'h-1 w-1/4 bg-gray-200 rounded transition-colors duration-200';
        });

        let strength = 0;
        let feedback = [];

        // Length check
        if (password.length >= 8) {
            strength++;
            strengthBar1.classList.add('bg-red-500');
        } else {
            feedback.push('minimal 8 karakter');
        }

        // Lowercase and uppercase check
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) {
            strength++;
            strengthBar2.classList.add('bg-orange-500');
        } else {
            feedback.push('huruf besar dan kecil');
        }

        // Number check
        if (password.match(/[0-9]/)) {
            strength++;
            strengthBar3.classList.add('bg-yellow-500');
        } else {
            feedback.push('angka');
        }

        // Special character check
        if (password.match(/[^a-zA-Z0-9]/)) {
            strength++;
            strengthBar4.classList.add('bg-green-500');
        } else {
            feedback.push('karakter khusus');
        }

        // Update feedback text and button state
        if (strength === 0) {
            strengthText.className = 'text-xs text-gray-500';
            strengthText.textContent = 'Password sangat lemah';
            submitBtn.disabled = true;
        } else if (strength === 1) {
            strengthText.className = 'text-xs text-red-500';
            strengthText.textContent = 'Password lemah! Tambahkan: ' + feedback.join(', ');
            submitBtn.disabled = true;
        } else if (strength === 2) {
            strengthText.className = 'text-xs text-orange-500';
            strengthText.textContent = 'Password sedang! Tambahkan: ' + feedback.join(', ');
            submitBtn.disabled = true;
        } else if (strength === 3) {
            strengthText.className = 'text-xs text-yellow-500';
            strengthText.textContent = 'Password kuat! Tambahkan: ' + feedback.join(', ');
            submitBtn.disabled = false;
        } else {
            strengthText.className = 'text-xs text-green-500';
            strengthText.textContent = 'Password sangat kuat!';
            submitBtn.disabled = false;
        }
    }
    </script>
</body>
</html>
