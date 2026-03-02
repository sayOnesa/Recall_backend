<?php
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

try {
    // ✅ Connect to database
    $connection = connect_to_database($env);

    // ✅ Get search query
    $query = isset($_POST['query']) ? trim($_POST['query']) : '';

    // ✅ If query is empty → return ALL results
    if ($query === '') {
        $sql = "
            SELECT DISTINCT j.id, j.title, j.content, j.created_at, j.updated_at,
                   GROUP_CONCAT(t.tag SEPARATOR ', ') AS tags
            FROM Journals AS j
            LEFT JOIN Tags AS t ON j.id = t.journal_id
            WHERE j.user_id = ?
            GROUP BY j.id
            ORDER BY j.updated_at DESC
        ";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $user_id);
    } else {
        // ✅ Normal search (title/content/tag)
        $like = "%" . $query . "%";
        $sql = "
            SELECT DISTINCT j.id, j.title, j.content, j.created_at, j.updated_at,
                   GROUP_CONCAT(t.tag SEPARATOR ', ') AS tags
            FROM Journals AS j
            LEFT JOIN Tags AS t ON j.id = t.journal_id
            WHERE j.user_id = ?
              AND (
                j.title LIKE ?
                OR j.content LIKE ?
                OR t.tag LIKE ?
              )
            GROUP BY j.id
            ORDER BY j.updated_at DESC
        ";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("isss", $user_id, $like, $like, $like);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $journals = [];

    while ($row = $result->fetch_assoc()) {
        $row['title'] = strip_tags(htmlspecialchars_decode($row['title']));
        $row['content'] = strip_tags(htmlspecialchars_decode($row['content']));
        $journals[] = $row;
    }

    echo json_encode($journals);

    $stmt->close();
    $connection->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}
?>
