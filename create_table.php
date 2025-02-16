<?php

class PostgresTableCreator {
    private $pdo;
    
    public function __construct($host, $dbname, $user, $password) {
        try {
            $this->pdo = new PDO(
                "pgsql:host=$host;dbname=$dbname",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create a table using raw SQL
     */
    public function createTableRawSQL($sql) {
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Table creation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create a table using a structured array definition
     */
    public function createTableFromSchema($tableName, $columns, $constraints = []) {
        $columnDefinitions = [];
        
        foreach ($columns as $name => $definition) {
            $columnDefinitions[] = "$name $definition";
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (\n    ";
        $sql .= implode(",\n    ", $columnDefinitions);
        
        if (!empty($constraints)) {
            $sql .= ",\n    ";
            $sql .= implode(",\n    ", $constraints);
        }
        
        $sql .= "\n)";
        
        return $this->createTableRawSQL($sql);
    }
    
    /**
     * Check if a table exists
     */
    public function tableExists($tableName) {
        $stmt = $this->pdo->prepare(
            "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_name = ?
            )"
        );
        $stmt->execute([$tableName]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Drop a table if it exists
     */
    public function dropTable($tableName) {
        try {
            $this->pdo->exec("DROP TABLE IF EXISTS $tableName CASCADE");
            return true;
        } catch (PDOException $e) {
            throw new Exception("Failed to drop table: " . $e->getMessage());
        }
    }
}

// Example usage
try {
    // Initialize the table creator
    $creator = new PostgresTableCreator(
        'localhost',
        'moodledb',
        'postgres',
        'lockpostgres!21'
    );
    
    // Example 1: Create a table using raw SQL
    $creator->createTableRawSQL("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            surname VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    /*
    // Example 2: Create a table using schema definition
    $columns = [
        'id' => 'SERIAL PRIMARY KEY',
        'product_name' => 'VARCHAR(100) NOT NULL',
        'price' => 'DECIMAL(10,2) NOT NULL',
        'stock' => 'INTEGER DEFAULT 0',
        'last_updated' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    
    $constraints = [
        'CONSTRAINT price_positive CHECK (price > 0)',
        'CONSTRAINT stock_non_negative CHECK (stock >= 0)'
    ];
    
    $creator->createTableFromSchema('products', $columns, $constraints);
    
    // Example 3: Create a table with foreign keys
    $orderColumns = [
        'id' => 'SERIAL PRIMARY KEY',
        'user_id' => 'INTEGER NOT NULL',
        'product_id' => 'INTEGER NOT NULL',
        'quantity' => 'INTEGER NOT NULL',
        'order_date' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];
    
    $orderConstraints = [
        'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
        'FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT',
        'CONSTRAINT quantity_positive CHECK (quantity > 0)'
    ];
    
    $creator->createTableFromSchema('orders', $orderColumns, $orderConstraints);
    */

    echo "Tables created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>