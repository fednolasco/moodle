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
                        
                        $values[] = $row[$colIndex] ?? null;
                    
                    }

                    // START Validations

                    // Get trimmed values
                    $name = trim($values[0]);
                    $surname = trim($values[1]);
                    $email = trim($values[2]);
                    
                    $booEmailValid = FALSE;

                    // 1. Check if email format is valid
                    // 2. Check if there are invalid characters in email
                    // 3. If valid, continue cleansing 'name' & 'surname'
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) 
                            AND !str_contains($email,'!')
                            AND !str_contains($email,'\'')) {
                        
                        //echo "Valid Email.\n";
                        $booEmailValid = TRUE;

                        // 'name' cleansing
                        $name = ucfirst(strtolower($name));
                        
                        // 'surname' cleansing
                        // Retain the original surname if there is an apostrophe (e.g. O'Hare)
                        if (!str_contains($surname,'\'')) {
                            $surname = ucfirst(strtolower($surname));
                        } else {
                            $surname = ucfirst($surname);
                        }

                        // Swap the values in the same position in the array
                        array_splice($values,0,1,$name);
                        array_splice($values,1,1,$surname);
                        
                    //} else {
                    //    echo "Invalid Email.\n";
                    }
                    
                    //echo "Array contents: " . print_r($values) . "\n";

                    // Check if Email already exist
                    $booEmailExist = FALSE;
                    try {

                        $checkStmt = $this->pdo->prepare("SELECT email FROM $this->tableName WHERE email = :email");
                        $checkStmt->execute([':email' => $email]);
                        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {

                            //$this.pdo->rollBack();
                            if ($existing['email'] === $email) {
                                $booEmailExist = TRUE;
                            }
                            
                        }

                    } catch (PDOException $e) {
                        fwrite(STDOUT, "Database error occured: " . $e->getMessage() . "\n");
                    }
                    // END Validations

                    // If passed Validations, do insert
                    if ($booEmailValid == TRUE AND $booEmailExist == FALSE) {

                        echo "Inserted!\n";
                        $stmt->execute($values);
                        $stats['successful_inserts']++;

                    } else {

                        if ($booEmailValid == FALSE){
                            echo "Not Inserted: Invalid email.\n";
                        }
                        if ($booEmailExist == TRUE){
                            echo "Not Inserted: Email already exist.\n";
                        }
                        $stats['failed_inserts']++;

                    }
                    
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
        'users', // table name
        //'usertest', // test environment
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