<?php

// Redirect errors to STDOUT
ini_set('error_log', '/dev/stdout');
error_reporting(E_ALL);
ini_set('display_errors', 1);

class DatabaseManager {
    private $pdo;
    private $options;

    public function __construct() {
        // Define command line options
        $shortopts = "h:u:p:d:n:";
        $longopts = [
            "host:",
            "user:",
            "password:",
            "dbname:",
            "table:",
            "help"
        ];

        // Parse command line options
        $this->options = getopt($shortopts, $longopts);

        // Show help if requested
        if (isset($this->options['help'])) {
            $this->showHelp();
            exit(0);
        }

        // Validate required options
        $this->validateOptions();

        // Connect to database
        $this->connect();
    }

    private function showHelp() {
        echo "Usage: php script.php [OPTIONS]\n\n";
        echo "Options:\n";
        echo "  -h, --host      PostgreSQL host (default: localhost)\n";
        echo "  -u, --user      Database username\n";
        echo "  -p, --password  Database password\n";
        echo "  -d, --dbname    Database name\n";
        echo "  -n, --table     Table name\n";
        echo "      --help      Display this help message\n";
    }

    private function validateOptions() {
        $required = [
            'user'     => ['u', 'user'],
            'password' => ['p', 'password'],
            'dbname'   => ['d', 'dbname'],
            'table'    => ['n', 'table']
        ];

        foreach ($required as $name => $opts) {
            $short = $opts[0];
            $long = $opts[1];
            
            if (!isset($this->options[$short]) && !isset($this->options[$long])) {
                die("Error: $name is required. Use -$short or --$long\n");
            }
        }
    }

    private function connect() {
        $host = $this->options['h'] ?? $this->options['host'] ?? 'localhost';
        $user = $this->options['u'] ?? $this->options['user'];
        $pass = $this->options['p'] ?? $this->options['password'];
        $db = $this->options['d'] ?? $this->options['dbname'];

        try {
            $this->pdo = new PDO(
                "pgsql:host=$host;dbname=$db",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "Connected to PostgreSQL successfully!\n";
        } catch (PDOException $e) {
            fwrite(STDOUT, "Connection failed: " . $e->getMessage() . "\n");
            die("Connection failed: " . $e->getMessage() . "\n");
        }
    }
    /**
     * Check if a table exists
     */
    public function tableExists($tableName) {
        try{
            $stmt = $this->pdo->prepare(
                "SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_name = ?
                )"
            );
            $stmt->execute([$tableName]);
            $exists = $stmt->fetchColumn();
            
            fwrite(STDOUT, "Table '$tableName' " . 
                ($exists ? "already exists." : "does not exist.") . "\n");
            return $exists;
        
        } catch (PDOException $e) {
            fwrite(STDOUT, "Check Table Error: " . $e->getMessage() . "\n");
            return false;
        }
    } 
    
    /**
     * Drop a table if it exists
     */
    public function dropTable($tableName) {
        try {
            $this->pdo->exec("DROP TABLE IF EXISTS $tableName CASCADE");
            return true;
        } catch (PDOException $e) {
            fwrite(STDOUT, "Failed to drop table: " . $e->getMessage() . "\n");
            return false;
        }
    }

    public function createTable() {
        $tableName = $this->options['n'] ?? $this->options['table'];
        
        try {

            // If table exist, drop it
            if ($this->tableExists($tableName)) {

                if ($this->dropTable($tableName)) {
                    echo "Current table '$tableName' was dropped.\n";
                }

            }
            
            // Create table structure
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                surname VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

            $this->pdo->exec($sql);
            echo "Table '$tableName' created successfully!\n";

        } catch (PDOException $e) {
            fwrite(STDOUT, "Error creating table: " . $e->getMessage() . "\n");
            die("Error creating table: " . $e->getMessage() . "\n");
        }
    }

}

// Ensure script is running from command line
if (PHP_SAPI !== 'cli') {
    die("This script can only be run from command line\n");
}

// Create and run the database manager
try {
    $manager = new DatabaseManager();
    $manager->createTable();
} catch (Exception $e) {
    fwrite(STDOUT, "Error: " . $e->getMessage() . "\n");
    die("Error: " . $e->getMessage() . "\n");
}
?>