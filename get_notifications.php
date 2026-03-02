<?php
require_once __DIR__ . '/index.php';
require_login();

$user_id = $_SESSION['user_id'];

try {
    // Create a new connection to the database
    $connection = connect_to_database($env);
    
    // Get the current user's notifications from the notifications table
    $sql = "SELECT id, actor_id, entry_id_shared, type, message, is_read, created_at FROM Notifications WHERE user_id = ? LIMIT 100";
    $bindings = 'i';
    $values = [$user_id];
    $rows = get_rows_from_table($connection, $sql, $bindings, $values);
    
    $notifications = [];
    if($rows && count($rows) > 0) {
        foreach($rows as $row) {
            switch ($row['type']) {
                /* Handle friend_request notifications */
                case 'friend_request':
                    $parsed_notification = handle_friend_request_notifications($connection, $row);
                    if(!$parsed_notification) {
                        break;
                    }

                    $notifications[] = $parsed_notification;
                    break;

                /* Handle share_entry notifications */ 
                case 'share_journal':
                    $parsed_notification = handle_share_notifications($connection, $row);
                    if(!$parsed_notification) {
                        break;
                    }

                    $notifications[] = $parsed_notification;
                    break;

                /* Handle friend_request_accept notifications */
                case 'friend_request_accept':
                    $parsed_notification = handle_friend_request_accept_notifications($connection, $row);
                    if(!$parsed_notification) {
                        break;
                    }

                    $notifications[] = $parsed_notification;
                    break;

                default:
                    break;
            }
        }
    }


    echo json_encode($notifications);

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

// Prepares data for displaying different types of notifications
function handle_share_notifications($connection, $row) {
    // First query: get user info of actor
    $select_sql = "SELECT id, username, profile_picture FROM Users WHERE id = ?";
    $bindings = 'i';
    $values = [$row['actor_id']];
    $user_result = get_rows_from_table($connection, $select_sql, $bindings, $values);
    if (!$user_result || count($user_result) === 0) {
        return false;
    }
    $user_row = $user_result[0];

    // Second query: get the title of the shared journal
    $journal_sql = "SELECT title FROM Journals WHERE id = ?";
    $journal_bindings = 'i';
    $journal_values = [$row['entry_id_shared']];
    $journal_result = get_rows_from_table($connection, $journal_sql, $journal_bindings, $journal_values);
    $journal_title = ($journal_result && count($journal_result) > 0) ? $journal_result[0]['title'] : null;

    $parsed_notification = [
        'type' => 'share_journal',
        'actor_id' => $user_row['id'],
        'entry_id_shared' => $row['entry_id_shared'],
        'username' => $user_row['username'],
        'title' => htmlspecialchars_decode($journal_title),
        'message' => $row['message'],
        'is_read' => $row['is_read'],
        'created_at' => $row['created_at'], 
    ];

    return $parsed_notification;
}


function handle_friend_request_notifications($connection, $row) {
    $select_sql = "SELECT id, username, profile_picture FROM Users WHERE id = ?";
    $bindings = 'i';
    $values = [$row['actor_id']];
    $user_row = get_rows_from_table($connection, $select_sql, $bindings, $values)[0];
    if(!$user_row || count($user_row) <= 0) {
        return false;
    }
                    
    $parsed_notification = [
        'type' => 'friend_request',
        'actor_id' => $user_row['id'],
        'username' => $user_row['username'],
        'message' => $row['message'],
        'is_read' => $row['is_read'],
        'created_at' => $row['created_at'], 
    ];

    return $parsed_notification;
}

function handle_friend_request_accept_notifications($connection, $row) {
    $select_sql = "SELECT username FROM Users WHERE id = ?";
    $bindings = 'i';
    $values = [$row['actor_id']];
    $user_row = get_rows_from_table($connection, $select_sql, $bindings, $values)[0];
    if(!$user_row) {
        return false;
    }

    
    $parsed_notification= [
        'type' => 'friend_request_accept',
        'notification_id' => $row['id'],
        'username' => $user_row['username'],
        'message' => $row['message'],
        'is_read' => $row['is_read'],
        'created_at' => $row['created_at'], 
    ];

    return $parsed_notification;
}

?>