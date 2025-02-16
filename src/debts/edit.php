<?php
session_start();
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/helpers/security_helper.php';
require_once __DIR__ . '/../../src/helpers/date_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$id = filter_var($_GET['id'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
if (!$id) {
    header('Location: ../../debts.php');
    exit;
}

// Fetch existing debt data
$stmt = $conn->prepare("SELECT * FROM debts WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$debt = $stmt->fetch();

if (!$debt) {
    header('Location: ../../debts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("
            UPDATE debts 
            SET nama = ?,
                nomor_hp = ?,
                jumlah = ?,
                kurs = ?,
                jenis = ?,
                tanggal_pinjam = ?,
                jatuh_tempo = ?,
                keterangan = ?
            WHERE id = ? AND user_id = ?
        ");

        $success = $stmt->execute([
            cleanInput($_POST['nama']),
            cleanInput($_POST['nomor_hp']),
            str_replace('.', '', $_POST['display_jumlah']),
            cleanInput($_POST['kurs']),
            cleanInput($_POST['jenis']),
            cleanInput($_POST['tanggal_pinjam']),
            cleanInput($_POST['jatuh_tempo']),
            cleanInput($_POST['keterangan']),
            $id,
            $_SESSION['user_id']
        ]);

        if ($success) {
            header('Location: ../../debts.php?success=edit');
            exit;
        }
    } catch(PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hutang/Piutang - Pencatat Keuangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center py-6">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
        <!-- Form header -->
        <div class="p-4 bg-gradient-to-r from-blue-500 to-purple-600 rounded-t-lg">
            <h1 class="text-2xl font-bold text-white flex items-center">
                <i class="fas fa-edit mr-2"></i>Edit Hutang/Piutang
            </h1>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <!-- Existing form fields populated with $debt data -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Nama
                    </label>
                    <input type="text" name="nama" required 
                           value="<?php echo htmlspecialchars($debt['nama']); ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-phone mr-2"></i>Nomor HP
                    </label>
                    <input type="tel" name="nomor_hp" required 
                           value="<?php echo htmlspecialchars($debt['nomor_hp']); ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-money-bill mr-2"></i>Jumlah
                    </label>
                    <input type="text" name="display_jumlah" required 
                           value="<?php echo number_format($debt['jumlah'], 0, ',', '.'); ?>"
                           oninput="formatNumber(this)"
                           class="w-full px-3 py-2 border rounded">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-money-bill-wave mr-2"></i>Mata Uang
                    </label>
                    <select name="kurs" required class="w-full px-3 py-2 border rounded">
                        <option value="IDR" <?php echo $debt['kurs'] === 'IDR' ? 'selected' : ''; ?>>IDR</option>
                        <option value="USD" <?php echo $debt['kurs'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">
                    <i class="fas fa-exchange-alt mr-2"></i>Jenis
                </label>
                <select name="jenis" required class="w-full px-3 py-2 border rounded">
                    <option value="hutang" <?php echo $debt['jenis'] === 'hutang' ? 'selected' : ''; ?>>
                        Hutang (Saya Meminjam)
                    </option>
                    <option value="piutang" <?php echo $debt['jenis'] === 'piutang' ? 'selected' : ''; ?>>
                        Piutang (Saya Meminjamkan)
                    </option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-2"></i>Tanggal Pinjam
                    </label>
                    <input type="date" name="tanggal_pinjam" required 
                           value="<?php echo $debt['tanggal_pinjam']; ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">
                        <i class="fas fa-calendar-times mr-2"></i>Jatuh Tempo
                    </label>
                    <input type="date" name="jatuh_tempo" required 
                           value="<?php echo $debt['jatuh_tempo']; ?>"
                           class="w-full px-3 py-2 border rounded">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">
                    <i class="fas fa-sticky-note mr-2"></i>Keterangan
                </label>
                <textarea name="keterangan" rows="3" 
                          class="w-full px-3 py-2 border rounded"><?php echo htmlspecialchars($debt['keterangan']); ?></textarea>
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

    <script>
    function formatNumber(input) {
        let value = input.value.replace(/\./g, '');
        if (value !== '') {
            const number = parseInt(value);
            if (!isNaN(number)) {
                input.value = number.toLocaleString('id-ID');
            }
        }
    }
    </script>
</body>
</html>
