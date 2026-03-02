
<?php
/* Sends the One Time Passcode (OTP) to a client */
require_once './index.php';

// Array used to store any errors that should be returned to the client
$errors = [];

// Arrays for storing information associated with input fields
$required_fields = [
    'password_one'  => 'Password',
    'password_two'  => 'Confirmation Password',
    'one_time_auth_token' => 'Auth Token',
    'user_id' => 'User ID',
];

$min_length = [
    'password_one'  => 8,
    'password_two'  => 8
];

$max_length = [
    'password_one'  => 64,
    'password_two'  => 64
];

$field_regex = [
    'password_one'  => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/',
    'password_two'  => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/'
];

$valid_contents = [
    'password_one'  => "Include upper and lowercase letter, number and symbol",
    'password_two'  => "Include upper and lowercase letter, number and symbol"
];


try {
    // Get data from post request
    $post_data = json_decode(file_get_contents("php://input"), true);

    // Ensure POST request has data
    if(empty($post_data)) {
        throw new Exception("No POST data", 400);
    }

    // Ensure no input fields are missing or empty
    foreach ($required_fields as $field => $name) {
        if (!isset($post_data[$field]) || $post_data[$field] === '') {
            $errors[$field] = "'$name' is required";
            throw new Exception("Missing input data $name", 400);
        }
    }

    // Ensure that all input data follows correct formatting
    $clean_values = [];
    foreach ($required_fields as $field => $name) {
        if($field === 'one_time_auth_token' || $field === 'user_id') {
            break;
        }

        $clean_value = htmlspecialchars(trim($post_data[$field]));
        $str_len = strlen($clean_value);
        if($str_len < $min_length[$field]) {
            $min = $min_length[$field];
            $errors[$field] = "Must be at least '$min' characters";
            throw new Exception("Input too small", 400);
        } else if ($str_len > $max_length[$field]) {
            $max = $max_length[$field];
            $errors[$field] = "Must be at most '$max' characters";
            throw new Exception("Input too long", 400);
        } else if (!preg_match($field_regex[$field], $clean_value)) {
            $contents = $valid_contents[$field];
            $errors[$field] = "'$contents'";
            throw new Exception("Invalid input", 400);
        }

        // Add the current value to $clean_values if it is in fact clean
        $clean_values[$field] = $clean_value;
    }

    // Verify that user_id is a numeric value
    $clean_user_id = $post_data['user_id'];
    if(!ctype_digit((string)$clean_user_id)) {
        $errors['otp'] = 'Invalid recovery email';
        throw new Exception("Invalid email address", 400);
    }


    // Ensure the password and confirmation password match
    if($clean_values['password_one'] !== $clean_values['password_two']) {
        $errors['password_one'] = "Passwords do not match";
        throw new Exception("Passwords do not match", 400);
    }

    // Create a new connection to the database
    $connection = connect_to_database($env);
    
    // Verify that the inputted user_id belongs to a row in the Password_Reset table
    $select_sql = "SELECT one_time_auth_token FROM Users WHERE id = ? LIMIT 1";
    $bindings = "i";
    $values = [$clean_user_id];
    $row = get_rows_from_table($connection, $select_sql, $bindings, $values)[0];
    if(!$row || $row === null) {
        $errors['password_one'] = 'This user did not request a password reset';
        throw new Exception("User did not request a password reset", 400);
    }

    // Verify that the user's auth token is set
    if(empty($row['one_time_auth_token'])) {
        $errors['password_one'] = 'Cannot reset password';
        throw new Exception('Invalid auth token', 400);
    }

    // Verify that the user is using the correct auth token
    $one_time_auth_token = $row['one_time_auth_token'];
    if($one_time_auth_token !== $post_data['one_time_auth_token']) {
        $errors['password_one'] = 'Cannot reset password';
        throw new Exception('Invalid auth token', 400);
    }

    // Update  user's password
    $password_hash = password_hash($clean_values['password_one'], PASSWORD_DEFAULT);
    $update_sql = "UPDATE Users SET password_hash = '$password_hash', one_time_auth_token = NULL WHERE id = ?";
    $bindings = 'i';
    $values = [$clean_user_id];
    if(!insert_into_table($connection, $update_sql, $bindings, $values)) {
        throw new Exception("Error updating otp auth token", 500);
    }
    

    // Remove user's entry from Password_Reset table
    $delete_sql = "DELETE FROM Password_Reset WHERE user_id = ?";
    $bindings = 'i';
    $values = [$clean_user_id];
    if(!delete_from_table($connection, $delete_sql, $bindings, $values)) {
        throw new Exception("Error deleting entry from table", 500);
    }

    $response_data = [
        'success' => true,
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
?>