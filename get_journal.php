<?php
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

$errors = [];

try {
    error_log("GET date param: " . ($_GET['date'] ?? 'none'));
    //$_GET['date'] refers to value passed in the URL query string
    if(!isset($_GET['date']) || empty($_GET['date'])) {
        throw new Exception("No date set", 400);
    }

    // Verify that the inputted date is valid
    $date_string = trim($_GET['date']);
    if(!is_valid_date_string($date_string)) {
        throw new Exception("Invalid date string", 400);
    }


    // Create a new connection to the database
    $connection = connect_to_database($env);
    
    // Check if mood column exists
    $columnCheck = $connection->query("SHOW COLUMNS FROM Journals LIKE 'mood'");
    $moodColumnExists = $columnCheck->num_rows > 0;
    
    if ($moodColumnExists) {
        $sql = "SELECT id, title, content, mood FROM Journals WHERE user_id = ? AND created_at = ?";
    } else {
        $sql = "SELECT id, title, content FROM Journals WHERE user_id = ? AND created_at = ?";
    }
    
    $statement = $connection->prepare($sql);
    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param("is", $user_id, $date_string);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }

    $result = $statement->get_result();
    $journal = $result->fetch_assoc();
    if($journal) {
        // Debug logging
        error_log("Retrieved journal - Title: " . $journal['title'] . ", Mood: " . ($journal['mood'] ?? 'NULL'));
        
        $entryData = [
            "id" => $journal['id'],
            "date" => $date_string,
            "title" => htmlspecialchars_decode($journal['title']),
            "content" => htmlspecialchars_decode($journal['content'])
        ];
        
        // Add mood if column exists (include even if null/empty)
        if ($moodColumnExists) {
            $entryData["mood"] = $journal['mood'] ?? "";
            error_log("Mood column exists, setting mood to: " . ($journal['mood'] ?? 'empty string'));
        } else {
            error_log("Mood column does not exist");
        }
        
        echo json_encode([
            "success" => true,
            "entry" => $entryData
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "entry" => null,
        ]);
    }
 } catch (Exception $e) {
    // Handle any errors caught by guard clauses
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'errors' => $errors,
        'code' => $e->getCode()
    ];

    echo json_encode($error_data);
    exit();
}
?>