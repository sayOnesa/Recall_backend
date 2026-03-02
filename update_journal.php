<?php

$MAX_TITLE_LENGTH = 100;

/* Creates or updates a user's journal */
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

// The fields that must be sent in the POST request
$required_fields = ['id', 'title', 'content'];

try {
    $post_data = json_decode(file_get_contents("php://input"), true);

    verify_csrf_token($post_data);

    // Ensure POST request has data
    if(empty($post_data)) {
        throw new Exception("No POST data", 400);
    }

    // Ensure no input fields are missing or empty
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field])) {
            throw new Exception("Missing input data", 400);
        }
    }

    // Verify that the inputted date is valid
    $date_string = trim($post_data['id']);
    if(!is_valid_date_string($date_string)) {
        throw new Exception("Invalid date string", 400);
    }//error is thrown here

    // Create a new connection to the database
    $connection = connect_to_database($env);

    $sql = "SELECT id FROM Journals WHERE user_id = ? AND created_at = ?";
    $statement = $connection->prepare($sql);
    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $title = htmlspecialchars($post_data['title']);
    if(strlen($title) > $MAX_TITLE_LENGTH) {
        throw new Exception("Title too long", 400);
    }

    $content = htmlspecialchars($post_data['content']);
    $mood = isset($post_data['mood']) ? $post_data['mood'] : null;
    
    // Debug logging
    error_log("Mood value being saved: " . ($mood ? $mood : "NULL"));
    
    $statement->bind_param("is", $user_id,$date_string);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }

    
    // If the journal is already in the database, update it 

    $result = $statement->get_result();
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $journal_id = $row['id'];

        // Check if mood column exists
        $columnCheck = $connection->query("SHOW COLUMNS FROM Journals LIKE 'mood'");
        $moodColumnExists = $columnCheck->num_rows > 0;
        
        if ($moodColumnExists) {
            $sql = "UPDATE Journals SET title = ?, content = ?, mood = ?, updated_at = NOW() WHERE id = ?";
            $updateStatement = $connection->prepare($sql);
            if (!$updateStatement) {
                die("Error preparing UPDATE statement");
            }
            $updateStatement->bind_param("sssi", $title, $content, $mood, $journal_id);
        } else {
            // Fallback without mood column
            $sql = "UPDATE Journals SET title = ?, content = ?, updated_at = NOW() WHERE id = ?";
            $updateStatement = $connection->prepare($sql);
            if (!$updateStatement) {
                die("Error preparing UPDATE statement");
            }
            $updateStatement->bind_param("ssi", $title, $content, $journal_id);
        }

        if (!$updateStatement->execute()) {
            die("Error executing UPDATE query");
        }
    } else {
        // Check if mood column exists for INSERT
        $columnCheck = $connection->query("SHOW COLUMNS FROM Journals LIKE 'mood'");
        $moodColumnExists = $columnCheck->num_rows > 0;
        
        if ($moodColumnExists) {
            // If the journal is not in the database, insert it with mood
            $sql = "INSERT INTO Journals (user_id, title, content, mood, created_at) VALUES (?, ?, ?, ?, ?)";
            $insertStatement = $connection->prepare($sql);
            if (!$insertStatement) {
                die("Error preparing INSERT statement");
            }
            $insertStatement->bind_param("issss", $user_id, $title, $content, $mood, $date_string);
        } else {
            // If the journal is not in the database, insert it without mood
            $sql = "INSERT INTO Journals (user_id, title, content, created_at) VALUES (?, ?, ?, ?)";
            $insertStatement = $connection->prepare($sql);
            if (!$insertStatement) {
                die("Error preparing INSERT statement");
            }
            $insertStatement->bind_param("isss", $user_id, $title, $content, $date_string);
        }

        if (!$insertStatement->execute()) {
            die("Error executing INSERT query");
        }   
    }        

    echo json_encode(['success' => true]);
    
    // Close database connections
    $connection->close();
    $statement->close();
} catch (Exception $e) {
    // Handle any errors caught by guard clauses
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];

    echo json_encode($error_data);
    exit();
}