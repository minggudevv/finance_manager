<?php
// Koneksi ke database production
try {
    $connProd = new PDO(
        "mysql:host=localhost;dbname=keuangan;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch(PDOException $e) {
    die("Koneksi database production gagal: " . $e->getMessage() . "\n");
}

// Koneksi ke database development
try {
    $connDev = new PDO(
        "mysql:host=localhost;dbname=keuangan_dev;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch(PDOException $e) {
    die("Koneksi database development gagal: " . $e->getMessage() . "\n");
}

try {
    // 1. Dapatkan semua tabel dari database production
    $tables = $connProd->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Memulai proses copy database ke development...\n\n";
    
    // 2. Drop database development jika sudah ada
    $connDev->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach ($tables as $table) {
        $connDev->exec("DROP TABLE IF EXISTS `{$table}_dev`");
    }
    
    // 3. Copy struktur dan data dari setiap tabel
    foreach ($tables as $table) {
        echo "Memproses tabel {$table}...\n";
        
        // Get table creation SQL
        $stmt = $connProd->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch();
        $createSql = $row['Create Table'];
        
        // Modify create statement for dev
        $createSql = str_replace(
            "CREATE TABLE `$table`",
            "CREATE TABLE `{$table}_dev`",
            $createSql
        );
        
        // Create table in dev
        $connDev->exec($createSql);
        
        // Copy data
        $data = $connProd->query("SELECT * FROM `$table`")->fetchAll();
        if (!empty($data)) {
            $columns = array_keys($data[0]);
            $columnList = "`" . implode("`, `", $columns) . "`";
            
            $insertSql = "INSERT INTO `{$table}_dev` ($columnList) VALUES ";
            $valueSets = [];
            
            foreach ($data as $row) {
                $values = array_map(function($value) use ($connDev) {
                    if ($value === null) return 'NULL';
                    return $connDev->quote($value);
                }, $row);
                $valueSets[] = "(" . implode(", ", $values) . ")";
            }
            
            $insertSql .= implode(",\n", $valueSets);
            $connDev->exec($insertSql);
        }
        
        echo "✓ Tabel {$table} berhasil di-copy ke {$table}_dev\n";
    }
    
    // 4. Restore foreign key checks
    $connDev->exec("SET FOREIGN_KEY_CHECKS=1");
    
    // 5. Update constraints untuk development
    foreach ($tables as $table) {
        $stmt = $connProd->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch();
        $createSql = $row['Create Table'];
        
        // Extract foreign key constraints
        if (preg_match_all("/CONSTRAINT `.+` FOREIGN KEY \(`(.+)`\) REFERENCES `(.+)` \(`(.+)`\)(.*)/", $createSql, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $column = $matches[1][$i];
                $refTable = $matches[2][$i];
                $refColumn = $matches[3][$i];
                $additional = $matches[4][$i];
                
                // Add _dev suffix to referenced table
                $alterSql = "ALTER TABLE `{$table}_dev` 
                            ADD FOREIGN KEY (`$column`) 
                            REFERENCES `{$refTable}_dev`(`$refColumn`)$additional";
                            
                try {
                    $connDev->exec($alterSql);
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') { // Ignore if constraint already exists
                        throw $e;
                    }
                }
            }
        }
    }
    
    echo "\n✓ Database berhasil di-copy ke development!\n";
    echo "Semua tabel telah ditambahkan suffix '_dev'\n";
    
} catch (Exception $e) {
    die("\n✗ Error: " . $e->getMessage() . "\n");
}
