<?php
/* Inserts a voice note into the Voice_Notes table */
require_once './index.php';

require_login();

$user_id = $_SESSION['user_id'];

// Required fields for this endpoint
$required_fields = ['date'];

// Connect to the database
$connection = connect_to_database($env);

try {
    $post_data = $_POST;
    verify_csrf_token($post_data);

    // Verify that the date field is contained in the request
    verify_fields_in_request_data($post_data, $required_fields);

    // Verify that the inputted date is valid
    $date_string = trim($post_data['date']);
    if(!is_valid_date_string($date_string)) {
        throw new Exception("Invalid date string", 400);
    }
    $created_at = $post_data['date'];

    // Verify that a file was sent in a request  
    if(!isset($_FILES['audio']) || empty($_FILES['audio'])) {
        throw new Exception("Audio file not sent in request", 400);
    }
    $audio_file = $_FILES['audio'];

    // Ensure audio file is not larger than 5MB
    $max_size = 5 * 1024 * 1024;
    if($audio_file['size'] > $max_size) {
        throw new Exception("Audio file too large", 400);
    }

    // Verify that the uploaded file is actually a webm file
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $audio_file['tmp_name']);
    finfo_close($file_info);

    if($mime_type !== 'audio/webm' && $mime_type !== 'video/webm') {
        throw new Exception("Incorrect file type", 400);
    }

    // Insert the audio file into the database
    $audio_file_data = file_get_contents($audio_file['tmp_name']);

    $connection->begin_transaction();

    // Insert the voice note into the Voice_Notes table
    $insert_sql = "INSERT INTO Voice_Notes (user_id, title, created_at, audio) VALUES (?, ?, ?, ?)";
    $bindings = 'isss';
    $values = [$user_id, '', $created_at, $audio_file_data];
    if(!insert_into_table($connection, $insert_sql, $bindings, $values)) {
        throw new Exception("Error inserting voice note into database", 500);
    }

    // Get the id of the just inserted voice note
    $select_sql = "SELECT id FROM Voice_Notes WHERE user_id = ? AND created_at = ? ORDER BY id DESC LIMIT 1";
    $bindings = 'is';
    $values = [$user_id, $created_at];
    $row = get_rows_from_table($connection, $select_sql, $bindings, $values);
    if(!$row) {
        throw new Exception("Error getting voice note id from table", 500);
    }

    $connection->commit();

    echo json_encode(['id' => $row[0]['id'],'success' => true, "message" => "Voice note created"]);
} catch(Exception $e) {
    // Handle any errors caught by guard clauses
    $error_data = [
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ];

    $connection->rollback();

    echo json_encode($error_data);
    exit();
}
?>