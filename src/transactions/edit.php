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

// Secure GET parameter and fetch transaction
$id = filter_var($_GET['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
if (!$id) {
    header('Location: ../../index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    try {
        $conn->beginTransaction();

        // Clean and validate input
        $storage_type_id = filter_var($_POST['storage_type_id'], FILTER_SANITIZE_NUMBER_INT);
        $kategori = cleanInput($_POST['kategori']);
        $jumlah = str_replace('.', '', $_POST['display_jumlah']); // Remove thousand separators
        $jumlah = filter_var($jumlah, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $kurs = cleanInput($_POST['kurs']);
        $jenis = cleanInput($_POST['jenis']);
        $tanggal = cleanInput($_POST['tanggal']);

        // Update transaction
        $stmt = $conn->prepare("
            UPDATE transactions 
            SET storage_type_id = ?,
                kategori = ?,
                jumlah = ?,
                kurs = ?,
                jenis = ?,
                tanggal = ?
            WHERE id = ? AND user_id = ?
        ");

        $success = $stmt->execute([
            $storage_type_id,
            $kategori,
            $jumlah,
            $kurs,
            $jenis,
            $tanggal,
            $id,
            $_SESSION['user_id']
        ]);

        if ($success) {
            $conn->commit();
            header('Location: ../../index.php?success=edit');
            exit;
        } else {
            throw new PDOException("Failed to update transaction");
        }
    } catch(PDOException $e) {
        $conn->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch transaction data for display
$stmt = $conn->prepare("
    SELECT t.*, s.nama as storage_name 
    FROM transactions t
    JOIN storage_types s ON t.storage_type_id = s.id
    WHERE t.id = ? AND t.user_id = ?
");
$stmt->execute([$id, $_SESSION['user_id']]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    header('Location: ../../index.php');
    exit;
}

// Get storage types for dropdown
$stmt = $conn->prepare("SELECT * FROM storage_types ORDER BY jenis, nama");
$stmt->execute();
$storage_types = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaksi - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center py-6">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4 rounded-t-lg">
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-edit mr-2"></i>Edit Transaksi
            </h1>
        </div>

        <div class="p-6">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="id" value="<?php echo $transaksi['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div>
                    <label class="block text-gray-700">Disimpan di</label>
                    <select name="storage_type_id" required class="w-full px-3 py-2 border rounded">
                        <?php foreach ($storage_types as $storage): ?>
                            <option value="<?php echo $storage['id']; ?>" 
                                    <?php echo $transaksi['storage_type_id'] === $storage['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($storage['nama']); ?>
                                (<?php echo ucfirst($storage['jenis']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700">Kategori</label>
                    <input type="text" name="kategori" required 
                           value="<?php echo htmlspecialchars($transaksi['kategori']); ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>
                
                <div>
                    <label class="block text-gray-700">Jumlah</label>
                    <input type="text" 
                           name="display_jumlah" 
                           oninput="formatNumber(this)"
                           autocomplete="off"
                           pattern="[0-9\.]+"
                           value="<?php echo number_format($transaksi['jumlah'], 0, ',', '.'); ?>"
                           class="w-full px-3 py-2 border rounded">
                    <input type="hidden" name="jumlah" id="real_display_jumlah" 
                           value="<?php echo $transaksi['jumlah']; ?>">
                </div>

                <div>
                    <label class="block text-gray-700">Mata Uang</label>
                    <select name="kurs" required class="w-full px-3 py-2 border rounded">
                        <option value="IDR" <?php echo $transaksi['kurs'] === 'IDR' ? 'selected' : ''; ?>>IDR</option>
                        <option value="USD" <?php echo $transaksi['kurs'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700">Jenis</label>
                    <select name="jenis" required class="w-full px-3 py-2 border rounded">
                        <option value="pemasukan" <?php echo $transaksi['jenis'] === 'pemasukan' ? 'selected' : ''; ?>>Pemasukan</option>
                        <option value="pengeluaran" <?php echo $transaksi['jenis'] === 'pengeluaran' ? 'selected' : ''; ?>>Pengeluaran</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700">Tanggal (<?php echo formatTanggal($transaksi['tanggal']); ?>)</label>
                    <input type="date" name="tanggal" required 
                           value="<?php echo $transaksi['tanggal']; ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="../../index.php" 
                       class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                        <i class="fas fa-times mr-1"></i> Batal
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
function formatNumber(input) {
    // Remove all dots first
    let value = input.value.replace(/\./g, '');
    
    // Convert to number and format
    if (value !== '') {
        const number = parseInt(value);
        if (!isNaN(number)) {
            input.value = number.toLocaleString('id-ID');
            
            // Update hidden input
            const realInput = document.getElementById('real_display_jumlah');
            if (realInput) {
                realInput.value = number;
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const displayJumlah = document.querySelector('input[name="display_jumlah"]');
        const realJumlah = document.getElementById('real_display_jumlah');
        
        if (!displayJumlah.value) {
            e.preventDefault();
            alert('Jumlah harus diisi!');
            return;
        }
        
        // Remove thousand separators before submitting
        realJumlah.value = displayJumlah.value.replace(/\./g, '');
    });
});
</script>
</body>
</html>
