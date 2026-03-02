<?php
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

try {
    $connection = connect_to_database($env);

    $sql = "
    SELECT 
        j.id,
        j.title,
        DATE_FORMAT(STR_TO_DATE(j.created_at, '%m-%d-%Y'), '%Y-%m-%d') AS date,
        GROUP_CONCAT(t.tag SEPARATOR ', ') AS tags
    FROM Journals AS j
    LEFT JOIN Tags AS t ON j.id = t.journal_id
    WHERE j.user_id = ?
    GROUP BY j.id
    ORDER BY j.created_at DESC
";

    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $journals = [];
    while ($row = $result->fetch_assoc()) {
        $row['title'] = htmlspecialchars_decode($row['title']);
        $row['tags'] = htmlspecialchars_decode($row['tags']);
        $journals[] = $row;
    }

    echo json_encode($journals, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $stmt->close();
    $connection->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    exit();
}
?>
