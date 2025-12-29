<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM quotes LIKE 'unit_price_currency'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✓ unit_price_currency column already exists in quotes table<br>";
    } else {
        // Add the column
        $sql = "ALTER TABLE quotes 
                ADD COLUMN unit_price_currency ENUM('TL', 'USD', 'EUR', 'GBP') DEFAULT 'EUR' 
                AFTER unit_price";
        
        $db->exec($sql);
        echo "✓ Successfully added unit_price_currency column to quotes table<br>";
    }
    
    echo "<br>Migration completed successfully!";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
