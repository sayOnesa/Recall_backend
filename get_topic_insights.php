<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    require_once './index.php';
    require_login();
    $user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;}


try {
    $connection = connect_to_database($env);

    // Parse optional start and end dates from query string (MM-DD-YYYY)
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    if ($start && $end) {
        // If dates are provided, join Tags with Journals to filter by created_at
        $sql = "
            SELECT t.tag, COUNT(*) AS count
            FROM Tags t
            INNER JOIN Journals j ON t.journal_id = j.id
            WHERE t.user_id = ?
              AND STR_TO_DATE(j.created_at, '%m-%d-%Y') 
                  BETWEEN STR_TO_DATE(?, '%m-%d-%Y') AND STR_TO_DATE(?, '%m-%d-%Y')
            GROUP BY t.tag
            ORDER BY count DESC
            LIMIT 10
        ";

        $statement = $connection->prepare($sql);
        $statement->bind_param("iss", $user_id, $start, $end);

    } else {
        // If no dates provided, get all-time top 10 tags
        $sql = "
            SELECT tag, COUNT(*) AS count
            FROM Tags
            WHERE user_id = ?
            GROUP BY tag
            ORDER BY count DESC
            LIMIT 10
        ";

        $statement = $connection->prepare($sql);
        $statement->bind_param("i", $user_id);
    }

    $statement->execute();
    $result = $statement->get_result();

    $tagData = [];
    while ($row = $result->fetch_assoc()) {
        $tagData[] = [
            'name' => $row['tag'],
            'value' => (int)$row['count'],
        ];
    }

    echo json_encode($tagData);

    $statement->close();
    $connection->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}

?>