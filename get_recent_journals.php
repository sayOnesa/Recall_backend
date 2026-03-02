<?php
/* Get the 9 most recent journal entries based on last modification date */
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

try {
    // Create a new connection to the database
    $connection = connect_to_database($env);

    // Get the 9 most recently modified journals with their tags for the user
    // ORDER BY updated_at to show entries that were recently changed
    $sql = "SELECT J.id, J.title, J.content, J.created_at, J.updated_at as date
            FROM Journals J
            WHERE J.user_id = ?
            ORDER BY J.updated_at DESC
            LIMIT 9";
    
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
            'created_at' => $row['created_at'],
            'title' => htmlspecialchars_decode($row['title']),
            'content' => htmlspecialchars_decode($row['content']),
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
?>
