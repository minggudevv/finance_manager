<?php
require_once __DIR__ . '/src/config/database.php';

class DatabaseMigrator {
    private $conn;
    private $dbName;

    public function __construct($conn) {
        // Enable buffered queries
        $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $this->conn = $conn;
        $this->dbName = $this->getDatabaseName();
        echo "Database yang digunakan: {$this->dbName}\n";
    }

    private function getDatabaseName() {
        $stmt = $this->conn->query('SELECT DATABASE()');
        $result = $stmt->fetchColumn();
        $stmt->closeCursor(); // Ensure the statement is closed
        return $result;
    }

    public function migrate() {
        try {
            $this->initializeDatabase();
            $this->runPendingMigrations();
        } catch (Exception $e) {
            die("Error: " . $e->getMessage() . "\n");
        }
    }

    private function initializeDatabase() {
        try {
            // Create version table if it doesn't exist
            $sql = "CREATE TABLE IF NOT EXISTS database_version (
                id INT PRIMARY KEY AUTO_INCREMENT,
                version VARCHAR(10) NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->conn->exec($sql);

            // Check if tables exist
            $stmt = $this->conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt->closeCursor();

            $needsDefaultImport = !in_array('users', $tables) || !in_array('transactions', $tables) || !in_array('debts', $tables);

            // Jika tabel-tabel utama belum ada atau versi kosong, jalankan default.sql
            if ($needsDefaultImport) {
                echo "\nMengimport skema default database...\n";
                
                // Drop existing tables if any to ensure clean state
                if (!empty($tables)) {
                    foreach ($tables as $table) {
                        $this->conn->exec("DROP TABLE IF EXISTS `$table`");
                    }
                }
                
                // Read and execute default.sql
                $defaultSql = file_get_contents(__DIR__ . "/database/default.sql");
                
                // Remove comments and split statements
                $sql = preg_replace(['/#.*[\r\n]+/', '/--.*[\r\n]+/', '/\/\*.*?\*\//s'], "\n", $defaultSql);
                $statements = array_filter(
                    array_map(
                        'trim',
                        preg_split("/;\s*[\r\n]+/", $sql)
                    )
                );
                
                // Execute each statement
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        try {
                            $stmt = $this->conn->query($statement);
                            if ($stmt) {
                                $stmt->closeCursor();
                            }
                        } catch (PDOException $e) {
                            // Ignore common errors
                            if (!in_array($e->getCode(), ['42S01', '42S21', '42S22', '42000', '23000'])) {
                                throw $e;
                            }
                        }
                    }
                }

                // Ensure version record exists
                try {
                    $this->conn->exec("DELETE FROM database_version");
                    $this->conn->exec("INSERT INTO database_version (id, version) VALUES (1, '1.0.0')");
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') { // Ignore duplicate key error
                        throw $e;
                    }
                }

                echo "✓ Skema default database berhasil diimport!\n";
            }
        } catch (Exception $e) {
            // Ignore "no active transaction" error
            if (stripos($e->getMessage(), 'no active transaction') !== false) {
                echo "✓ Skema default database berhasil diimport!\n";
            } else {
                die("\n✗ Import database gagal: " . $e->getMessage() . "\n");
            }
        }
    }

    private function runPendingMigrations() {
        // Get current version
        $stmt = $this->conn->prepare("SELECT version FROM database_version WHERE id = 1");
        $stmt->execute();
        $currentVersion = $stmt->fetchColumn();
        $stmt->closeCursor();

        if (!$currentVersion) {
            echo "\nVersion not found in database. Please run default.sql first.\n";
            return;
        }

        // Get available migrations
        $migrations = [];
        foreach (glob(__DIR__ . "/database/*.sql") as $file) {
            $basename = basename($file);
            if ($basename !== 'default.sql' && preg_match('/(\d+\.\d+\.\d+)\.sql$/', $file, $matches)) {
                $version = $matches[1];
                if (version_compare($version, $currentVersion, '>')) {
                    $migrations[$version] = $file;
                }
            }
        }

        if (empty($migrations)) {
            echo "\nDatabase sudah dalam versi terbaru ({$currentVersion})\n";
            return;
        }

        ksort($migrations, SORT_NATURAL);
        
        echo "\nMemulai proses migrasi database...\n";
        foreach ($migrations as $version => $file) {
            echo "\nMenjalankan migrasi ke versi {$version}...\n";
            
            try {
                $sql = file_get_contents($file);
                $statements = array_filter(
                    array_map(
                        'trim',
                        preg_split(
                            "/;\s*[\r\n]+/",
                            preg_replace(
                                ['/#.*[\r\n]+/', '/--.*[\r\n]+/', '/\/\*.*?\*\//s'],
                                "\n",
                                $sql
                            )
                        )
                    )
                );
                
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        try {
                            $stmt = $this->conn->query($statement);
                            if ($stmt) {
                                $stmt->closeCursor();
                            }
                        } catch (PDOException $e) {
                            // Skip certain errors:
                            // 42S01 - Table already exists
                            // 42S21 - Column already exists
                            // 42S22 - Column not found
                            if (!in_array($e->getCode(), ['42S01', '42S21', '42S22'])) {
                                throw $e;
                            }
                        }
                    }
                }
                echo "✓ Migrasi ke versi {$version} berhasil!\n";
            } catch (Exception $e) {
                // Ignore "no active transaction" error
                if (stripos($e->getMessage(), 'no active transaction') === false) {
                    die("\n✗ Migrasi gagal: " . $e->getMessage() . "\n");
                }
                echo "✓ Migrasi ke versi {$version} berhasil!\n";
            }
        }

        echo "\nMigrasi database selesai.\n";
        echo "Versi saat ini: {$version}\n";
    }
}

// Run migration
$migrator = new DatabaseMigrator($conn);
$migrator->migrate();