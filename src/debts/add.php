<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/security_helper.php';
require_once __DIR__ . '/../../src/helpers/currency_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $nama = cleanInput($_POST['nama']);
    $nomor_hp = cleanInput($_POST['nomor_hp']);
    $jumlah = filter_var($_POST['jumlah'], FILTER_SANITIZE_NUMBER_FLOAT);
    $kurs = cleanInput($_POST['kurs']);
    $jenis = cleanInput($_POST['jenis']);
    $tanggal_pinjam = cleanInput($_POST['tanggal_pinjam']);
    $jatuh_tempo = cleanInput($_POST['jatuh_tempo']);
    $keterangan = cleanInput($_POST['keterangan']);

    try {
        $stmt = $conn->prepare("
            INSERT INTO debts (user_id, nama, nomor_hp, jumlah, kurs, jenis, 
                             tanggal_pinjam, jatuh_tempo, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $nama, $nomor_hp, $jumlah, $kurs, $jenis,
            $tanggal_pinjam, $jatuh_tempo, $keterangan
        ]);
        header('Location: ../../debts.php?success=1');
        exit;
    } catch(PDOException $e) {
        $error = "Gagal menambah data: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Hutang/Piutang - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center py-6">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 rounded-t-lg">
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>Tambah Hutang/Piutang Baru
            </h1>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Nama
                    </label>
                    <input type="text" name="nama" required class="w-full px-3 py-2 border rounded">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-phone mr-2"></i>Nomor HP
                    </label>
                    <input type="tel" name="nomor_hp" required class="w-full px-3 py-2 border rounded">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-money-bill mr-2"></i>Jumlah
                    </label>
                    <input type="text" name="display_jumlah" required 
                           oninput="formatNumber(this)"
                           class="w-full px-3 py-2 border rounded">
                    <input type="hidden" name="jumlah" id="real_display_jumlah">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-money-bill-wave mr-2"></i>Mata Uang
                    </label>
                    <select name="kurs" required class="w-full px-3 py-2 border rounded">
                        <option value="IDR">IDR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-2"></i>Tanggal Pinjam
                    </label>
                    <input type="date" name="tanggal_pinjam" required 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-calendar-times mr-2"></i>Jatuh Tempo
                    </label>
                    <input type="date" name="jatuh_tempo" required 
                           class="w-full px-3 py-2 border rounded">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">
                    <i class="fas fa-exchange-alt mr-2"></i>Jenis
                </label>
                <select name="jenis" required class="w-full px-3 py-2 border rounded">
                    <option value="hutang">Hutang (Saya Meminjam)</option>
                    <option value="piutang">Piutang (Saya Meminjamkan)</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">
                    <i class="fas fa-sticky-note mr-2"></i>Keterangan
                </label>
                <textarea name="keterangan" rows="3" 
                          class="w-full px-3 py-2 border rounded"></textarea>
            </div>

            <div class="flex justify-end space-x-4 pt-4">
                <a href="../../debts.php" 
                   class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    <i class="fas fa-times mr-2"></i>Batal
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    <i class="fas fa-save mr-2"></i>Simpan
                </button>
            </div>
        </form>
    </div>
    
    <script src="../../public/js/main.js"></script>
</body>
</html>
