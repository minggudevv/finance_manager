<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/security_helper.php';
require_once __DIR__ . '/src/helpers/version_helper.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

// Get current database version
$stmt = $conn->prepare("SELECT version FROM database_version WHERE id = 1");
$stmt->execute();
$currentVersion = $stmt->fetchColumn();

// Get available versions from GitHub
$versions = getGitHubVersions();
$latestVersion = $versions[0] ?? null;

// Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_version'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $targetVersion = $_POST['target_version'];
    $result = updateDatabaseVersion($targetVersion);
    
    if ($result['success']) {
        $success = "Database berhasil diupdate ke versi " . $targetVersion;
        $currentVersion = $targetVersion;
    } else {
        $error = $result['message'];
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Version Management - Admin Panel</title>
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
                        <span class="text-xl font-bold text-white">Version Management</span>
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

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Version Info Card -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-code-branch mr-2"></i>Database Version Control
                    </h2>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Current Version -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-semibold mb-2">Current Version</h3>
                        <div class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-blue-600"><?php echo $currentVersion; ?></span>
                            <?php if ($latestVersion && version_compare($latestVersion, $currentVersion, '>')): ?>
                                <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">
                                    Update Available
                                </span>
                            <?php else: ?>
                                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                    Up to Date
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Available Versions -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Available Versions</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="grid gap-4">
                                <?php foreach ($versions as $version): 
                                    $isOldVersion = version_compare($version, '1.0.4', '<=');
                                    $isDisabled = $isOldVersion || $version === $currentVersion;
                                    $tooltipText = $isOldVersion ? 'Versi ini tidak mendukung fitur version control' : '';
                                ?>
                                    <div class="border rounded-lg p-4 <?php echo $version === $currentVersion ? 'bg-blue-50 border-blue-200' : 
                                        ($isOldVersion ? 'bg-gray-50 border-gray-200' : ''); ?>">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <input type="radio" name="target_version" value="<?php echo $version; ?>"
                                                       <?php echo $isDisabled ? 'disabled' : ''; ?>
                                                       <?php echo $version === $currentVersion ? 'checked' : ''; ?>
                                                       class="text-blue-600 focus:ring-blue-500 disabled:opacity-50">
                                                <div>
                                                    <span class="font-semibold">Version <?php echo $version; ?></span>
                                                    <?php if ($version === $currentVersion): ?>
                                                        <span class="ml-2 text-sm text-blue-600">(Current)</span>
                                                    <?php endif; ?>
                                                    <?php if ($isOldVersion): ?>
                                                        <span class="ml-2 text-sm text-gray-500" title="<?php echo $tooltipText; ?>">
                                                            <i class="fas fa-info-circle"></i> Not Supported
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if (!$isOldVersion): ?>
                                                <?php if (version_compare($version, $currentVersion, '>')): ?>
                                                    <span class="text-xs text-green-600">
                                                        <i class="fas fa-arrow-up"></i> Upgrade
                                                    </span>
                                                <?php elseif (version_compare($version, $currentVersion, '<')): ?>
                                                    <span class="text-xs text-red-600">
                                                        <i class="fas fa-arrow-down"></i> Downgrade
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="submit" name="update_version"
                                        class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 focus:ring-4 focus:ring-blue-300 disabled:opacity-50"
                                        <?php echo empty($versions) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-sync-alt mr-2"></i>Update Database Version
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
