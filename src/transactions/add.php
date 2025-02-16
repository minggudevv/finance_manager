<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/date_helper.php';
require_once __DIR__ . '/../helpers/security_helper.php';

addSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Ambil preferensi kurs user
$stmt = $conn->prepare("SELECT preferensi_kurs FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Ambil daftar metode penyimpanan
$stmt = $conn->prepare("SELECT * FROM storage_types ORDER BY jenis, nama");
$stmt->execute();
$storage_types = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $user_id = $_SESSION['user_id'];
    $storage_type_id = filter_var($_POST['storage_type_id'], FILTER_SANITIZE_NUMBER_INT);
    $kategori = cleanInput($_POST['kategori']);
    $jumlah = filter_var($_POST['jumlah'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $kurs = cleanInput($_POST['kurs']);
    $jenis = cleanInput($_POST['jenis']);
    $tanggal = cleanInput($_POST['tanggal']);

    try {
        $stmt = $conn->prepare("
            INSERT INTO transactions (user_id, storage_type_id, kategori, jumlah, kurs, jenis, tanggal) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $storage_type_id, $kategori, $jumlah, $kurs, $jenis, $tanggal]);
        header('Location: ../../index.php?success=1');
        exit;
    } catch(PDOException $e) {
        $error = "Gagal menambah transaksi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Transaksi - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center py-6">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4 rounded-t-lg">
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-plus-circle mr-2"></i>Tambah Transaksi
            </h1>
        </div>

        <div class="p-6">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div>
                    <label class="block text-gray-700">Disimpan di</label>
                    <select name="storage_type_id" required class="w-full px-3 py-2 border rounded">
                        <?php foreach ($storage_types as $storage): ?>
                            <option value="<?php echo $storage['id']; ?>">
                                <i class="fas <?php echo $storage['icon']; ?>"></i>
                                <?php echo htmlspecialchars($storage['nama']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700">Kategori</label>
                    <input type="text" name="kategori" required 
                           class="w-full px-3 py-2 border rounded">
                </div>
                
                <div>
                    <label class="block text-gray-700">Jumlah</label>
                    <input type="text" 
                           name="display_jumlah" 
                           oninput="formatNumber(this)"
                           autocomplete="off"
                           pattern="[0-9\.]+"
                           class="w-full px-3 py-2 border rounded">
                    <input type="hidden" name="jumlah" id="real_display_jumlah">
                </div>

                <div>
                    <label class="block text-gray-700">Mata Uang</label>
                    <select name="kurs" required class="w-full px-3 py-2 border rounded">
                        <option value="IDR" <?php echo $user['preferensi_kurs'] === 'IDR' ? 'selected' : ''; ?>>IDR</option>
                        <option value="USD" <?php echo $user['preferensi_kurs'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700">Jenis</label>
                    <select name="jenis" required class="w-full px-3 py-2 border rounded">
                        <option value="pemasukan">Pemasukan</option>
                        <option value="pengeluaran">Pengeluaran</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700">Tanggal (<?php echo formatTanggal(date('Y-m-d')); ?>)</label>
                    <input type="date" name="tanggal" required 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>

                <div class="flex justify-end space-x-4 pt-4">
                    <a href="../../index.php" 
                       class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 
                              transition duration-200 inline-flex items-center">
                        <i class="fas fa-times mr-2"></i>Batal
                    </a>
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-500 text-white rounded-lg hover:bg-blue-600 
                                   transition duration-200 inline-flex items-center">
                        <i class="fas fa-save mr-2"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Enhanced input validation
    function formatNumber(input) {
        let value = input.value.replace(/\D/g, '');
        value = new Intl.NumberFormat('id-ID').format(value);
        input.value = value;
        document.getElementById('real_display_jumlah').value = value.replace(/\./g, '');
    }

    document.querySelector('form').addEventListener('submit', function(e) {
        const jumlah = document.getElementById('real_display_jumlah').value;
        if (!jumlah || isNaN(jumlah)) {
            e.preventDefault();
            alert('Jumlah harus berupa angka valid!');
        }
    });
    </script>
</body>
</html>
