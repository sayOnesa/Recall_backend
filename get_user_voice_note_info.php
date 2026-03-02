<?php
require_once './index.php';

require_login();

$user_id = isset($_GET['user_id'])
           ? intval($_GET['user_id'])
           : $_SESSION['user_id'];

$errors = [];

$required_fields = ['date'];

try {
    // Verify that the date field is contained in the request
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
    
    $select_sql = "SELECT id, title FROM Voice_Notes WHERE user_id = ? AND created_at = ?";
    $bindings = "is";
    $values = [$user_id, $date_string];
    $rows = get_rows_from_table($connection, $select_sql, $bindings, $values);
    
    $voice_note_ids = [];
    $voice_note_titles = [];
    foreach($rows as $row) {
        $voice_note_ids[] = $row['id'];
        $voice_note_titles[] = htmlspecialchars_decode($row['title']);
    }
    
    echo json_encode(["ids" => array_reverse($voice_note_ids), "titles" => array_reverse($voice_note_titles)]); 
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