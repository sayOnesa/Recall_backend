<?php
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

$required_fields = ['query'];

try {
    verify_fields_in_request_data($_GET, $required_fields);    
    $query = htmlspecialchars(trim($_GET['query']));
    if($query === '') {
        echo json_encode([]);
        exit();
    }

    $connection = connect_to_database($env);

    $like = "%" . $query . "%";
    $select_sql = "SELECT id, first_name, last_name, username FROM Users WHERE id != ? AND (first_name LIKE ? OR last_name LIKE ? OR username LIKE ?) LIMIT 10";
    $bindings = 'isss';
    $values = [$user_id, $like, $like, $like];
    $rows = get_rows_from_table($connection, $select_sql, $bindings, $values);

    // Very inefficient, but I just want a working version
    $users_list = [];
    foreach($rows as $row) {
        // Check if this user is already a friend of the current user
        $select_sql = "SELECT user1_id FROM Friends WHERE user1_id = ? AND user2_id = ?";
        $bindings = 'ii';
        $values = [$user_id, $row['id']];
        sort($values);
        if(element_in_table($connection, $select_sql, $bindings, $values)) {
            continue;
        }

        // Check if the current user already requested to friend this user
        $select_sql = "SELECT id FROM Friend_Requests WHERE user_id = ? AND friend_id = ?";
        $bindings = 'ii';
        $values = [$user_id, $row['id']];
        if(element_in_table($connection, $select_sql, $bindings, $values)) {
            continue;
        }

        $users_list[] = $row;
    }

    echo json_encode($users_list);
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
