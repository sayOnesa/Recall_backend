<?php
/* Get all journal entries with tags for the logged-in user */
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

try {
    // Create a new connection to the database
    $connection = connect_to_database($env);

    // Get all journals with their tags for the user
    $sql = "SELECT J.id, J.title, J.mood, J.created_at as date
            FROM Journals J
            WHERE J.user_id = ?
            ORDER BY J.created_at DESC";
    
    $statement = $connection->prepare($sql);
    if (!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param("i", $user_id);
    if (!$statement->execute()) {
        die("Error executing SQL query");
    }

    $result = $statement->get_result();
    $entries = [];

    while ($row = $result->fetch_assoc()) {
        $journal_id = $row['id'];
        
        // Get tags for this journal
        $tagSql = "SELECT tag FROM Tags WHERE user_id = ? AND journal_id = ?";
        $tagStatement = $connection->prepare($tagSql);
        if (!$tagStatement) {
            die("Error preparing tag SQL statement: " . $connection->error);
        }
        
        $tagStatement->bind_param("ii", $user_id, $journal_id);
        if (!$tagStatement->execute()) {
            die("Error executing tag query");
        }
        
        $tagResult = $tagStatement->get_result();
        $tags = [];
        
        while ($tagRow = $tagResult->fetch_assoc()) {
            $tags[] = htmlspecialchars_decode($tagRow['tag']);
        }
        
        $entries[] = [
            'id' => $row['id'],
            'date' => $row['date'],
            'mood' => $row['mood'],
            'title' => htmlspecialchars_decode($row['title']),
            'tags' => $tags
        ];
        
        $tagStatement->close();
    }

    echo json_encode(['success' => true, 'results' => $entries]);
    
    // Close database connections
    $connection->close();
    $statement->close();
} catch (Exception $e) {
    // Handle any errors
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];

    echo json_encode($error_data);
    exit();
}
