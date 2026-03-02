<?php
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

$required_fields = ['query'];

try {
    verify_fields_in_request_data($_GET, $required_fields);    

    $query = htmlspecialchars(trim($_GET['query']));

    if ($query === '') {
        echo json_encode([]);
        exit();
    }

    // 🔹 Connect to database (your built-in helper)
    $connection = connect_to_database($env);

    // 🔹 Prepare LIKE pattern
    $like = "%" . $query . "%";

    // 🔹 Search users by name / username / email
    $select_sql = "
        SELECT id, first_name, last_name, username, email 
        FROM Users 
        WHERE id != ?
          AND (
                first_name LIKE ? 
             OR last_name LIKE ? 
             OR username LIKE ? 
             OR email LIKE ?
          )
        LIMIT 10
    ";

    $bindings = 'issss';
    $values = [$user_id, $like, $like, $like, $like];

    $rows = get_rows_from_table($connection, $select_sql, $bindings, $values);

    // 🔹 Filtering: remove users who are already friends OR already requested
    $users_list = [];

    foreach ($rows as $row) {

        // Check if already friends (order user1_id/user2_id doesn’t matter)
        $friend_check_sql = "
            SELECT user1_id 
            FROM Friends 
            WHERE (user1_id = ? AND user2_id = ?) 
               OR (user1_id = ? AND user2_id = ?)
        ";

        $bindings = "iiii";
        $values = [$user_id, $row['id'], $row['id'], $user_id];

        if (element_in_table($connection, $friend_check_sql, $bindings, $values)) {
            continue;
        }

        // Check if user already requested friend
        $request_check_sql = "
            SELECT id 
            FROM Friend_Requests 
            WHERE user_id = ? 
              AND friend_id = ?
        ";

        $bindings = "ii";
        $values = [$user_id, $row['id']];

        if (element_in_table($connection, $request_check_sql, $bindings, $values)) {
            continue;
        }

        // Pass this user into the list
        $users_list[] = $row;
    }

    echo json_encode($users_list);
    exit();

} catch (Exception $e) {

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
