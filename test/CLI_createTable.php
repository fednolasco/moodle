<?php
#!/usr/bin/env php

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
            die("Connection failed: " . $e->getMessage() . "\n");
        }
    }

    public function createTable() {
        $tableName = $this->options['n'] ?? $this->options['table'];
        
        try {
            // Example table structure - modify as needed
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";

            $this->pdo->exec($sql);
            echo "Table '$tableName' created successfully!\n";

        } catch (PDOException $e) {
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
    die("Error: " . $e->getMessage() . "\n");
}
?>