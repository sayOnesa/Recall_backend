<?php
/* 
 * This script adds the mood column to the Journals table if it doesn't exist.
 * Run this once to enable mood functionality.
 */

require_once './index.php';

require_login();

try {
    // Create a new connection to the database
    $connection = connect_to_database($env);
    
    // Check if mood column exists
    $columnCheck = $connection->query("SHOW COLUMNS FROM Journals LIKE 'mood'");
    
    if ($columnCheck->num_rows === 0) {
        // Add the mood column
        $sql = "ALTER TABLE Journals ADD COLUMN mood VARCHAR(50) DEFAULT NULL";
        
        if ($connection->query($sql) === TRUE) {
            echo json_encode([
                'success' => true, 
                'message' => 'Mood column added successfully to Journals table'
            ]);
        } else {
            throw new Exception("Error adding mood column: " . $connection->error);
        }
    } else {
        echo json_encode([
            'success' => true, 
            'message' => 'Mood column already exists in Journals table'
        ]);
    }
    
    $connection->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>