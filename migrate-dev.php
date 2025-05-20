<?php
require_once __DIR__ . '/src/config/database.php';

class DatabaseMigratorDev {
    private $conn;
    private $dbName;

    public function __construct($conn) {
        $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $this->conn = $conn;
        $this->dbName = $this->getDatabaseName();
        echo "Database development yang digunakan: {$this->dbName}\n";
    }

    private function getDatabaseName() {
        $stmt = $this->conn->query('SELECT DATABASE()');
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();
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
            $sql = "CREATE TABLE IF NOT EXISTS database_version_dev (
                id INT PRIMARY KEY AUTO_INCREMENT,
                version VARCHAR(10) NOT NULL,
                environment VARCHAR(10) DEFAULT 'dev',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->conn->exec($sql);

            // Check if tables exist
            $stmt = $this->conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt->closeCursor();

            // Check if we need to initialize dev database
            $stmt = $this->conn->query("SELECT COUNT(*) FROM database_version_dev WHERE environment = 'dev'");
            $hasDevVersion = (int)$stmt->fetchColumn() > 0;
            $stmt->closeCursor();

            if (!$hasDevVersion) {
                echo "\nMengimport skema development database...\n";
                
                // Read and execute dev schema
                $devSqlPath = __DIR__ . "/database/dev/schema.sql";
                if (!file_exists($devSqlPath)) {
                    die("\nError: File schema.sql tidak ditemukan di folder database/dev/\n");
                }

                $devSql = file_get_contents($devSqlPath);
                
                // Remove comments and split statements
                $sql = preg_replace(['/#.*[\r\n]+/', '/--.*[\r\n]+/', '/\/\*.*?\*\//s'], "\n", $devSql);
                $statements = array_filter(
                    array_map(
                        'trim',
                        preg_split("/;\s*[\r\n]+/", $sql)
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
                            // Ignore common errors
                            if (!in_array($e->getCode(), ['42S01', '42S21', '42S22', '42000', '23000'])) {
                                throw $e;
                            }
                        }
                    }
                }

                // Insert initial dev version
                try {
                    $this->conn->exec("INSERT INTO database_version_dev (version, environment) VALUES ('1.0.0', 'dev')");
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') { // Ignore duplicate key error
                        throw $e;
                    }
                }

                echo "✓ Skema development database berhasil diimport!\n";
            }
        } catch (Exception $e) {
            if (stripos($e->getMessage(), 'no active transaction') !== false) {
                echo "✓ Skema development database berhasil diimport!\n";
            } else {
                die("\n✗ Import database development gagal: " . $e->getMessage() . "\n");
            }
        }
    }

    private function runPendingMigrations() {
        // Get current dev version
        $stmt = $this->conn->prepare("SELECT version FROM database_version_dev WHERE environment = 'dev' ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $currentVersion = $stmt->fetchColumn();
        $stmt->closeCursor();

        if (!$currentVersion) {
            echo "\nVersion not found in development database. Please run schema.sql first.\n";
            return;
        }

        // Get available migrations from dev folder
        $migrations = [];
        foreach (glob(__DIR__ . "/database/dev/*.sql") as $file) {
            $basename = basename($file);
            if ($basename !== 'schema.sql' && preg_match('/(\d+\.\d+\.\d+)\.sql$/', $file, $matches)) {
                $version = $matches[1];
                if (version_compare($version, $currentVersion, '>')) {
                    $migrations[$version] = $file;
                }
            }
        }

        if (empty($migrations)) {
            echo "\nDevelopment database sudah dalam versi terbaru ({$currentVersion})\n";
            return;
        }

        ksort($migrations, SORT_NATURAL);
        
        echo "\nMemulai proses migrasi development database...\n";
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
                            // Skip certain errors
                            if (!in_array($e->getCode(), ['42S01', '42S21', '42S22'])) {
                                throw $e;
                            }
                        }
                    }
                }
                
                // Update version
                $this->conn->exec("INSERT INTO database_version_dev (version, environment) VALUES ('$version', 'dev')");
                echo "✓ Migrasi development ke versi {$version} berhasil!\n";
            } catch (Exception $e) {
                if (stripos($e->getMessage(), 'no active transaction') === false) {
                    die("\n✗ Migrasi development gagal: " . $e->getMessage() . "\n");
                }
                echo "✓ Migrasi development ke versi {$version} berhasil!\n";
            }
        }

        echo "\nMigrasi development database selesai.\n";
        echo "Versi development saat ini: {$version}\n";
    }
}

// Run development migration
$migratorDev = new DatabaseMigratorDev($conn);
$migratorDev->migrate();
