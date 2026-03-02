<?php
/* Verifies the One Time Passcode (OTP) submitted by the client */
require_once './index.php';

$errors = [];
$required_fields = ['0', '1', '2', '3', '4', '5', 'user_id'];

try {
    // Get data from post request
    $post_data = json_decode(file_get_contents("php://input"), true);

    // Ensure POST request has data
    if(empty($post_data)) {
        throw new Exception("No POST data", 400);
    }

    // Verify that each input has data
    foreach($required_fields as $field) {
        if(!isset($post_data[$field]) || $post_data[$field] === '') {
            $errors['otp'] = "One Time Passcode is required";
            throw new Exception("Missing input data", 400);
        }
    }


    // Build the inputted opt
    $otp = 0;
    foreach($required_fields as $field) {
        if($field === 'user_id') {
            break;
        }

        $raw_digit = $post_data[$field];
        if(!is_digit($raw_digit)) {
            $errors['otp'] = "Invalid one time passcode";
            throw new Exception("Invalid OTP", 400);
        }

        $otp = $otp * 10 + (int)$raw_digit;
    }

    // Verify the inputted user_id
    $clean_user_id = $post_data['user_id'];
    if(!ctype_digit((string)$clean_user_id)) {
        $errors['otp'] = 'Invalid recovery email';
        throw new Exception("Invalid email address", 400);
    }

    // Connect to the database
    $connection = connect_to_database($env);

    // Verify that the inputted user_id belongs to a row in the Password_Reset table
    $select_sql = "SELECT otp_hash FROM Password_Reset WHERE user_id = ? LIMIT 1";
    $bindings = "i";
    $values = [$clean_user_id];
    $row = get_rows_from_table($connection, $select_sql, $bindings, $values)[0];
    if(!$row || $row === null) {
        $errors['otp'] = 'This user did not request a password reset';
        throw new Exception("User did not request a password reset", 400);
    }

    $otp_hash = $row['otp_hash'];
    $otp_time_created = new DateTime($row['otp_time_created']);

    // Verify that the inputted OTP is correct
    if(!password_verify($otp, $otp_hash)) {
        $errors['otp'] = 'Incorrect one time passcode';
        throw new Exception("Incorrect one time passcode", 400);
    }

    // Verify that the inputted OTP is not expired (5 minute lifetime)
    $now = new DateTime();
    $time_delta = $now->diff($otp_time_created, true);
    if($time_delta->i >= 5) {
        $errors['otp'] = 'One time passcode expired, request a new one';
        throw new Exception("Expired one time passcode", 400);
    }

    // add this password reset hash to the Users database table
    $one_time_auth_token = password_hash(random_bytes(32), PASSWORD_DEFAULT);
    
    // Update one time authentication code to user entry in Users table
    $update_sql = "UPDATE Users SET one_time_auth_token = ? WHERE id = ?";
    $bindings = 'si';
    $values = [$one_time_auth_token, $clean_user_id];
    if(!insert_into_table($connection, $update_sql, $bindings, $values)) {
        throw new Exception("Error updating database", 500);
    }

    $response_data = [
        'success' => true,
        'one_time_auth_token' => $one_time_auth_token,
    ];

    echo json_encode($response_data);
} catch(Exception $e) {
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

/**
 * Returns true if $digit is a digit
 * 
 * @param mixed $digit the inputted digit
 * 
 * @return bool true if $digit is a digit, false otherwise
 */
function is_digit($raw_digit) {
    return ctype_digit($raw_digit) && strlen($raw_digit) === 1;
}
?>