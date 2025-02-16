<?php

// Redirect errors to STDOUT
ini_set('error_log', '/dev/stdout');
error_reporting(E_ALL);
ini_set('display_errors', 1);

class CSVToPostgres {

    private $pdo;
    private $tableName;
    private $columnMap;
        
    /**
     * Constructor to initialize database connection
     * @param string $host PostgreSQL host
     * @param string $dbname Database name
     * @param string $user Username
     * @param string $password Password
     * @param string $tableName Target table name
     * @param array $columnMap Mapping of CSV columns to database columns
     */
    public function __construct($host, $dbname, $user, $password, $tableName, $columnMap) {
        try {
            $this->pdo = new PDO(
                "pgsql:host=$host;dbname=$dbname",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->tableName = $tableName;
            $this->columnMap = $columnMap;
        } catch (PDOException $e) {
            fwrite(STDOUT, "Connection failed: " . $e->getMessage() . "\n");
            exit(1);
        }
    }
    
    /**
     * Import CSV file into PostgreSQL table
     * @param string $csvFile Path to CSV file
     * @param string $delimiter CSV delimiter character
     * @return array Statistics about the import
     */
    public function importCSV($csvFile, $delimiter = ',') {
        if (!file_exists($csvFile)) {
            fwrite(STDOUT, "CSV file not found: " . $csvFile . "\n");
            throw new Exception("CSV file not found: $csvFile");
        }
        
        $stats = [
            'total_rows' => 0,
            'successful_inserts' => 0,
            'failed_inserts' => 0,
            'errors' => []
        ];
        
        // Begin transaction for better performance and data integrity
        $this->pdo->beginTransaction();
        
        try {
            $handle = fopen($csvFile, 'r');
            
            // Read and validate headers
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers === false) {
                fwrite(STDOUT, "Failed to read CSV headers: " . $headers . "\n");
                throw new Exception("Failed to read CSV headers");
            }
            
            // Prepare the INSERT statement
            $columns = implode(', ', array_values($this->columnMap));
            $placeholders = str_repeat('?, ', count($this->columnMap) - 1) . '?';
            $stmt = $this->pdo->prepare(
                "INSERT INTO {$this->tableName} ($columns) VALUES ($placeholders)"
            );
            
            // Process each row
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $stats['total_rows']++;
                
                try {
                    // Map CSV columns to database columns
                    $values = [];
                    foreach ($this->columnMap as $csvCol => $dbCol) {
                        $colIndex = array_search($csvCol, $headers);
                        if ($colIndex === false) {
                            fwrite(STDOUT, "Column $csvCol not found in CSV \n");
                            throw new Exception("Column $csvCol not found in CSV");
                        }

                        //Error Checking
                        //echo "\ncolIndex: " . $colIndex;
                        //echo "\nValue: " . $row[$colIndex];
                        
                        if ($colIndex == 0) { // Name
                            $temp = $row[$colIndex];
                            echo "Before Name: " . $temp ."\n";
                            $temp = ucfirst(strtolower(trim($temp)));
                            echo "After Name: " . $temp ."\n";
                        } else if ($colIndex == 1) { // Surname
                            $temp = $row[$colIndex];
                            echo "Before Surname: " . $temp ."\n";
                            $temp = ucfirst(strtolower(trim($temp)));
                            echo "After Surname: " . $temp ."\n";
                        } else { // Email
                            $temp = $row[$colIndex];
                            echo "Before Email: " . $temp ."\n";
                            $temp = strtolower(trim($temp));
                            echo "After Email: " . $temp ."\n";

                                if (filter_var($temp, FILTER_VALIDATE_EMAIL)){
                                    echo "Valid Email\n";
                                }   else {
                                    echo "Invalid Email\n";
                                }
                            
                        }

                        $values[] = $row[$colIndex] ?? null;

                    }
                    


                    // Execute insert
                    //$stmt->execute($values);
                    $stats['successful_inserts']++;
                    
                } catch (Exception $e) {
                    $stats['failed_inserts']++;
                    $stats['errors'][] = "Row {$stats['total_rows']}: " . $e->getMessage();
                    
                    fwrite(STDOUT, "Failed inserts: " . $e->getMessage() . "\n");

                }
            }
            
            fclose($handle);
            $this->pdo->commit();
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            fwrite(STDOUT, "Import failed: " . $e->getMessage() . "\n");
            throw new Exception("Import failed: " . $e->getMessage());
        }
        
        return $stats;
    }
}

// Example usage
try {
    // Define database connection parameters
    $config = [
        'host' => 'localhost',
        'dbname' => 'moodledb',
        'user' => 'postgres',
        'password' => 'lockpostgres!21'
    ];
    
    // Define mapping between CSV columns and database columns
    $columnMap = [
        'name' => 'name',
	    'surname' => 'surname',
        'email' => 'email'
    ];
    
    // Initialize the importer
    $importer = new CSVToPostgres(
        $config['host'],
        $config['dbname'],
        $config['user'],
        $config['password'],
        'usertest',  // table name
        $columnMap
    );
    
    // Perform the import
    $stats = $importer->importCSV('/home/ubuntu/Documents/Moodle/user1.csv');
    
    // Display results
    echo "Import completed:\n";
    echo "Total rows processed: {$stats['total_rows']}\n";
    echo "Successful inserts: {$stats['successful_inserts']}\n";
    echo "Failed inserts: {$stats['failed_inserts']}\n";
    
    if (!empty($stats['errors'])) {
        echo "\nErrors encountered:\n";
        foreach ($stats['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
} catch (Exception $e) {
    fwrite(STDOUT, "Error: " . $e->getMessage() . "\n");
    echo "Error: " . $e->getMessage() . "\n";
}
?>