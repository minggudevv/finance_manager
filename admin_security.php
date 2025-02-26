<?php
session_start();
require_once __DIR__ . '/src/config/database.php';
require_once __DIR__ . '/src/helpers/security_helper.php';

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $site_key = cleanInput($_POST['recaptcha_site_key']);
    $secret_key = cleanInput($_POST['recaptcha_secret_key']);
    $is_enabled = isset($_POST['recaptcha_enabled']) ? 1 : 0;
    $version = $_POST['recaptcha_version'];

    // Update settings including version
    $stmt = $conn->prepare("
        UPDATE security_settings 
        SET setting_value = ?, 
            recaptcha_version = ?,
            updated_at = NOW() 
        WHERE setting_key = ?
    ");
    $stmt->execute([$site_key, $version, 'recaptcha_site_key']);
    $stmt->execute([$secret_key, $version, 'recaptcha_secret_key']);

    $stmt = $conn->prepare("UPDATE security_settings SET is_enabled = ?, updated_at = NOW() WHERE setting_key IN ('recaptcha_site_key', 'recaptcha_secret_key')");
    $stmt->execute([$is_enabled]);

    $success = "Pengaturan berhasil disimpan!";
}

// Get current settings
$stmt = $conn->prepare("SELECT setting_key, setting_value, is_enabled, recaptcha_version FROM security_settings WHERE setting_key IN ('recaptcha_site_key', 'recaptcha_secret_key')");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process the results
$settings = [
    'recaptcha_site_key' => '',
    'recaptcha_secret_key' => '',
    'recaptcha_version' => 'v3', // Default value
    'is_enabled' => false // Default value
];

// Populate settings from database results
foreach ($rows as $row) {
    if ($row['setting_key'] === 'recaptcha_site_key') {
        $settings['recaptcha_site_key'] = $row['setting_value'];
        $settings['recaptcha_version'] = $row['recaptcha_version'];
        $settings['is_enabled'] = (bool)$row['is_enabled'];
    } elseif ($row['setting_key'] === 'recaptcha_secret_key') {
        $settings['recaptcha_secret_key'] = $row['setting_value'];
    }
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - Admin Panel</title>
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
                        <span class="text-xl font-bold text-white">Security Settings</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white">
                            <i class="fas fa-shield-alt mr-1"></i> 
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

            <!-- reCAPTCHA Settings Card -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <i class="fas fa-robot mr-2"></i>reCAPTCHA Settings
                    </h2>
                </div>

                <div class="p-6">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Status Card -->
                        <div class="mb-6 p-4 <?php echo $settings['is_enabled'] ? 'bg-green-50 border-green-500' : 'bg-yellow-50 border-yellow-500'; ?> border-l-4 rounded-md">
                            <div class="flex items-center">
                                <i class="<?php echo $settings['is_enabled'] ? 'fas fa-check-circle text-green-500' : 'fas fa-exclamation-circle text-yellow-500'; ?> text-xl mr-3"></i>
                                <div>
                                    <h3 class="font-semibold <?php echo $settings['is_enabled'] ? 'text-green-700' : 'text-yellow-700'; ?>">
                                        reCAPTCHA Status: <?php echo $settings['is_enabled'] ? 'Active' : 'Inactive'; ?>
                                    </h3>
                                    <p class="text-sm <?php echo $settings['is_enabled'] ? 'text-green-600' : 'text-yellow-600'; ?>">
                                        <?php echo $settings['is_enabled'] 
                                            ? 'Your forms are protected by reCAPTCHA v3' 
                                            : 'Enable reCAPTCHA to protect your forms from spam and abuse'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Form -->
                        <div class="space-y-6">
                            <!-- Add version selector before the enable checkbox -->
                            <div class="space-y-2">
                                <label class="block font-medium text-gray-700">
                                    <i class="fas fa-code-branch mr-2"></i>reCAPTCHA Version
                                </label>
                                <select name="recaptcha_version" 
                                        class="w-full px-4 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="v3" <?php echo ($settings['recaptcha_version'] === 'v3') ? 'selected' : ''; ?>>
                                        reCAPTCHA v3 (Invisible)
                                    </option>
                                    <option value="v2" <?php echo ($settings['recaptcha_version'] === 'v2') ? 'selected' : ''; ?>>
                                        reCAPTCHA v2 (Checkbox)
                                    </option>
                                </select>
                                <p class="text-sm text-gray-500">
                                    v3 is invisible and scores the user automatically. v2 shows a checkbox challenge.
                                </p>
                            </div>

                            <div class="flex items-center space-x-3">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="recaptcha_enabled" 
                                           id="recaptcha_enabled"
                                           <?php echo $settings['is_enabled'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label for="recaptcha_enabled" class="font-medium text-gray-700">Enable reCAPTCHA Protection</label>
                                    <p class="text-gray-500 text-sm">Protect your login and registration forms from automated attacks</p>
                                </div>
                            </div>

                            <div class="grid md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block font-medium text-gray-700">
                                        <i class="fas fa-key mr-2"></i>Site Key
                                    </label>
                                    <input type="text" name="recaptcha_site_key" 
                                           value="<?php echo htmlspecialchars($settings['recaptcha_site_key'] ?? ''); ?>"
                                           class="w-full px-4 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-sm text-gray-500">Used in the HTML code your site serves to users</p>
                                </div>

                                <div class="space-y-2">
                                    <label class="block font-medium text-gray-700">
                                        <i class="fas fa-lock mr-2"></i>Secret Key
                                    </label>
                                    <input type="text" name="recaptcha_secret_key" 
                                           value="<?php echo htmlspecialchars($settings['recaptcha_secret_key'] ?? ''); ?>"
                                           class="w-full px-4 py-2 rounded-lg border focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-sm text-gray-500">Used for communication between your site and Google</p>
                                </div>
                            </div>

                            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                                <div class="flex">
                                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-2"></i>
                                    <div class="text-sm text-blue-700">
                                        <p class="font-semibold">How to get reCAPTCHA keys:</p>
                                        <ol class="list-decimal ml-4 mt-1">
                                            <li>Go to the <a href="https://www.google.com/recaptcha/admin" target="_blank" class="underline">Google reCAPTCHA Admin Console</a></li>
                                            <li>Sign in with your Google account</li>
                                            <li>Register a new site or app</li>
                                            <li>Choose reCAPTCHA v3</li>
                                            <li>Copy the Site Key and Secret Key</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button type="submit" 
                                    class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 focus:ring-4 focus:ring-blue-300 flex items-center">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
