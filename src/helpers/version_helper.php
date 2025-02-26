<?php
function getGitHubVersions() {
    $url = "https://api.github.com/repos/minggudevv/finance_manager/tags";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP'
            ]
        ]
    ];

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [];
    }

    $tags = json_decode($response, true);
    $versions = array_map(function($tag) {
        return ltrim($tag['name'], 'v');
    }, $tags);

    usort($versions, 'version_compare');
    return array_reverse($versions);
}

function updateDatabaseVersion($targetVersion) {
    global $conn;
    
    try {
        // Prevent downgrade to unsupported versions
        if (version_compare($targetVersion, '1.0.4', '<=')) {
            return [
                'success' => false,
                'message' => "Versi {$targetVersion} tidak mendukung fitur version control"
            ];
        }

        // 1. Download new version files from GitHub
        $zipUrl = "https://github.com/minggudevv/finance_manager/archive/refs/tags/v{$targetVersion}.zip";
        $zipFile = __DIR__ . "/../../temp/v{$targetVersion}.zip";
        $extractPath = __DIR__ . "/../../temp/";
        
        // Create temp directory if not exists
        if (!file_exists(__DIR__ . "/../../temp")) {
            mkdir(__DIR__ . "/../../temp");
        }

        // Download zip file
        if (!file_put_contents($zipFile, file_get_contents($zipUrl))) {
            return [
                'success' => false,
                'message' => "Gagal mengunduh file versi baru"
            ];
        }

        // Extract zip file
        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== TRUE) {
            return [
                'success' => false,
                'message' => "Gagal mengekstrak file versi baru"
            ];
        }
        $zip->extractTo($extractPath);
        $zip->close();

        // 2. Update database
        $sqlFile = __DIR__ . "/../../database/{$targetVersion}.sql";
        if (!file_exists($sqlFile)) {
            return [
                'success' => false,
                'message' => "File SQL untuk versi {$targetVersion} tidak ditemukan"
            ];
        }

        $sql = file_get_contents($sqlFile);
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Execute SQL
        $conn->exec($sql);
        
        // Update version in database
        $stmt = $conn->prepare("UPDATE database_version SET version = ? WHERE id = 1");
        $stmt->execute([$targetVersion]);
        
        // 3. Copy new files
        $sourceDir = $extractPath . "finance_manager-{$targetVersion}/";
        $targetDir = __DIR__ . "/../../";
        
        // Copy files recursively
        if (!copyDirectory($sourceDir, $targetDir)) {
            $conn->rollBack();
            return [
                'success' => false,
                'message' => "Gagal mengupdate file sistem"
            ];
        }

        // 4. Cleanup
        unlink($zipFile);
        deleteDirectory($extractPath . "finance_manager-{$targetVersion}");
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "Berhasil mengupdate ke versi {$targetVersion}"
        ];
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollBack();
        return [
            'success' => false,
            'message' => "Error updating: " . $e->getMessage()
        ];
    }
}

function copyDirectory($source, $dest) {
    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }

    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        if ($entry == '.' || $entry == '..') {
            continue;
        }

        $srcPath = $source . $entry;
        $destPath = $dest . $entry;

        if (is_dir($srcPath)) {
            copyDirectory($srcPath . '/', $destPath . '/');
        } else {
            copy($srcPath, $destPath);
        }
    }
    $dir->close();
    return true;
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}
