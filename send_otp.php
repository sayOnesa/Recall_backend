<?php
/* Sends the One Time Passcode (OTP) to a client */
require_once './index.php';

$errors = [];
$email_min_len = 5;
$email_max_len = 50;
$email_regex = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';


try {
    // Get data from post request
    $post_data = json_decode(file_get_contents("php://input"), true);

    // Ensure POST request has data
    if(empty($post_data)) {
        throw new Exception("No POST data", 400);
    }

    // Verify that an email was sent to the backend
    if(!isset($post_data['email']) || empty($post_data['email'])) {
        $errors['email'] = "Email is required";
        throw new Exception("Missing email", 400);
    }

    // Verify this email is correctly formatted
    $clean_email = htmlspecialchars(trim($post_data['email']));
    $email_len = strlen($clean_email);
    if($email_len < $email_min_len) {
        $errors['email'] = 'Must be at least 5 characters';
        throw new Exception("Input too small", 400);
    } else if($email_len > $email_max_len) {
        $errors['email'] = 'Must be at most 50 characters';
        throw new Exception("Input too large", 400);
    } else if(!preg_match($email_regex, $clean_email)) {
        $errors['email'] = 'Invalid email';
        throw new Exception('Invalid email address', 400);
    }

    // Ensure the email belongs to an actual user
    $connection = connect_to_database($env);
    $user_id = get_id_from_table($connection, 'Users', 'email', $clean_email);
    if(!$user_id) {
        $errors['email'] = 'No user with this email exists';
        throw new Exception('No user exists', 400);
    }

    // Remove any previous password reset entries in Password_Reset
    remove_element_from_table($connection, 'Password_Reset', 'user_id', $user_id);


    // Generate OTP using mt_rand
    $otp = mt_rand(100000, 999999);
    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);

    // Insert $user_id, $otp_hash, and $otp_time_created into the Password_Reset table
    $sql = "INSERT INTO Password_Reset (user_id, otp_hash, otp_time_created) VALUES ($user_id, ?, NOW())";
    $statement = $connection->prepare($sql);
    if(!$statement) {
        die('Error preparing SQL statement: ' . $connection->error);
    }

    $statement->bind_param('s', $otp_hash);
    if(!$statement->execute()) {
        die('Error creating entry in database');
    }

    // Send email with OTP to client
    $subject = 'Password Reset for Recall';
    $message = "Your password reset code is: $otp";
    $headers = '';

    if(!mail($clean_email, $subject, $message, $headers)) {
        $errors['email'] = 'Error sending email';
        throw new Exception('Error Sending Email', 400);
    }

    // Send a success HTTP response to the client
    $response_data = [
        'success' => true,
        'user_id' => $user_id,
        'message' => 'OTP Sent'
    ];
    
    // Echo JSON back to the client
    echo json_encode($response_data);

    // Close connections to the database
    $statement->close();
    $connection->close();

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