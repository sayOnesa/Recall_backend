<?php
require_once "./index.php";

require_login();

$mysqli = connect_to_database($env);
$mysqli->begin_transaction();
$mode = $_POST['mode'] ?? null; //Finds what mode the request was sent in

$field_regex = [
    'firstName'    => '/^[a-zA-Z]+$/',
    'lastName'     => '/^[a-zA-Z]+$/',
    'newUsername'      => '/^[a-zA-Z][a-zA-Z0-9_-]{2,19}$/',
    'email'         => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
    'password'  => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/',
];

if (!$mysqli) {
    echo json_encode(["success" => false, "message" => "Database connection error"]);
    exit();
}

$save_fields = [
    'firstName'    => 'First Name',
    'lastName'     => 'Last Name',
    'newUsername'         => 'New Username',
    'email'  => ''
];

$password_fields = [
    'oldPassword'      => 'Old Password',
    'newPassword'         => 'New Password',
    'confirmPassword'  => 'Confirm New Password'
];

$min_length = [
    'firstName'    => 2,
    'lastName'     => 2,
    'newUsername'      => 3,
    'email'         => 5,
    'newPassword'     => 8

];

$max_length = [
    'firstName'    => 20,
    'lastName'     => 20,
    'newUsername'      => 20,
    'email'         => 50,
    'newPassword'     => 64
];

$valid_contents = [
    'firstName'    => "Must contain only letters",
    'lastName'     => "Must contain only letters",
    'newUsername'      => "Must start with a letter followed by letters, numbers, _ or - only",
    'email'         => "Emails must start with letters or numbers, followed by an @ symbol and a valid domain",
];

$allowedTypes = ['jpg', 'jpeg', 'png'];
$allowedMimeTypes = ['image/jpeg', 'image/png'];

function getStatus(){
    global $mysqli, $_POST;
    $stmt = $mysqli->prepare("SELECT * FROM Users WHERE username = ?");
    $username = htmlspecialchars(trim($_POST["username"]));
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $out = $result->fetch_assoc();
    $stmt->close();
    return $out;
}

try{
    $profile = getStatus();
    if (!$profile) {
        echo json_encode(["success" => false, "message" => "Profile not found!"]);
        exit;
    }
    switch($mode) {
        case null:
            echo json_encode(["success" => false, "message" => "Incorrect save mode"]);
            break;
        case "SAVE":
            $clean_values = [];
            foreach ($save_fields as $field => $name) {
                if (!isset($_POST[$field]) || empty($_POST[$field])) {
                    echo json_encode(["success" => false, "message" => "$name is required"]);
                    exit();
                } else {
                    $clean_value = htmlspecialchars(trim($_POST[$field]));
                    $str_len = strlen($clean_value);
                    if($str_len < $min_length[$field]) {
                        $min = $min_length[$field];
                        echo json_encode(["success" => false, "message" => "$name must be at least $min characters long"]);
                        exit;
                    } else if ($str_len > $max_length[$field]) {
                        $max = $max_length[$field];
                        echo json_encode(["success" => false, "message" => "$name must be at most $max characters long"]);
                        exit;
                    } else if (!preg_match($field_regex[$field], $clean_value)) {
                        $contents = $valid_contents[$field];
                        echo json_encode(["success" => false, "message" => "$name $contents"]);
                        exit;
                    }
                    $clean_values[$field] = $clean_value;
                }
            }

            if (isset($_FILES['profilePic'])){ //Uploading profile picture to server
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['profilePic']['tmp_name']);
                $extension = strtolower(pathinfo($_FILES['profilePic']['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedTypes) || !in_array($mimeType, $allowedMimeTypes)) {
                    echo json_encode(["success" => false, "message" => "Invalid profile picture format! Only JPG and PNG files are allowed."]);
                    exit;
                }
                if ($_FILES['profilePic']['size'] > 10 * 1024 * 1024) {
                    echo json_encode(["success" => false, "message" => "Profile picture size exceeds the 10MB limit!"]);
                    exit;
                }
                $fileName = preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['profilePic']['name'])); //Sanitizes file name
                $fileName = time() . '_' . $fileName;

                $upload_dir = 'uploads/';
                $filename = uniqid() . '.' . pathinfo($_FILES['profilePic']['name'], PATHINFO_EXTENSION);
                
                if (move_uploaded_file($_FILES['profilePic']['tmp_name'], $upload_dir.$filename)) { //Uploads image to server
                    chmod($upload_dir . $filename, 0777); //Sets permissions
                }
                if ($profile['profile_picture'] != null) { //Deletes old profile picture from server
                    unlink("uploads/" . $profile['profile_picture']);
                }
                $request = $mysqli->prepare("UPDATE Users SET profile_picture = ? WHERE username = ?");
                $request->bind_param("ss", $filename, $profile['username']);
                $request->execute();

                $_SESSION['profile_picture'] = "server/uploads/" . $filename;
            }

            if ($profile['email'] != $clean_values['email']) { //Updating email if changed
                $request = $mysqli->prepare("SELECT email FROM Users WHERE email = ?");
                $request->bind_param("s",$clean_values['email']);
                $request->execute(); //Checks if email is in use
                $email = $request->get_result();

                if ($email->num_rows === 0) {
                    $request = $mysqli->prepare("UPDATE Users SET email = ? WHERE username = ?");
                    $request->bind_param("ss",$clean_values['email'],$profile['username']);
                    $request->execute();
                    $mysqli->commit();
                    $_SESSION['email'] = $clean_values['email'];
                } else {
                    echo json_encode(["success" => false, "message" => "Email already in use!"]);
                    exit;
                }
            }

            $request = $mysqli->prepare("UPDATE Users SET first_name = ?, last_name = ? WHERE username = ?");
            $request->bind_param("sss",$clean_values['firstName'],$clean_values['lastName'],$profile['username']);
            if ($request->execute()) {
                $mysqli->commit();
                $_SESSION['first_name'] = $clean_values['firstName'];
                $_SESSION['last_name'] = $clean_values['lastName'];
            } else {
                echo json_encode(["success" => false, "message" => $request->error]);
                $mysqli->rollback();
                exit;
            }
            
            if ($profile['username'] != $clean_values['newUsername']) {
                $request = $mysqli->prepare("SELECT username FROM Users WHERE username = ?");
                $request->bind_param("s",$clean_values['newUsername']);
                $request->execute(); //Checks if username is in use
                $usernames = $request->get_result();

                if ($usernames->num_rows === 0) {
                    $request = $mysqli->prepare("UPDATE Users SET username = ? WHERE username = ?");
                    $request->bind_param("ss", $clean_values['newUsername'], $profile['username']);
                    $request->execute();
                    $mysqli->commit();
                    $_SESSION['username'] = $clean_values['newUsername'];
                } else {
                    echo json_encode(["success" => false, "message" => "Username already in use!"]);
                    exit;
                }
            }

            echo json_encode(["success" => true, "message" => "Profile Updated!"]);
            
            $request->close();
            $mysqli->commit();

            break;

        case "PASSWORD":

            foreach ($password_fields as $field => $name) {
                if (!isset($_POST[$field]) || empty($_POST[$field])) {
                    echo json_encode(["success" => false, "message" => "'$name' is required"]);
                    exit();
                }
            }

            $_POST['oldPassword'] = htmlspecialchars(trim($_POST['oldPassword']));
            $_POST['newPassword'] = htmlspecialchars(trim($_POST['newPassword']));
            $_POST['confirmPassword'] = htmlspecialchars(trim($_POST['confirmPassword']));

            if (!preg_match($field_regex['password'], $_POST['newPassword'])){
                echo json_encode(["success" => false, "message" => "The new password must be at least 8 characters long and include an upper and lowercase letter, number, and symbol."]);
                break;
            } else if (strlen($_POST['newPassword']) < $min_length['newPassword']) {
                $min = $min_length['newPassword'];
                echo json_encode(["success" => false, "message" => "New Password must be at least $min characters long"]);
                exit;
            } else if (strlen($_POST['newPassword']) > $max_length['newPassword']) {
                $max = $max_length['newPassword'];
                echo json_encode(["success" => false, "message" => "New Password must be at most $max characters long"]);
                exit;
            }

            $password = password_hash($_POST['newPassword'], PASSWORD_DEFAULT);
            if (!$profile['password_hash']) {
                echo json_encode(["success" => false, "message" => "Password not found!"]);
                exit;
            } else if (!password_verify($_POST['oldPassword'], $profile['password_hash'])){
                echo json_encode(["success" => false, "message" => "Previous password is incorrect!"]);
                exit;
            } else if ($_POST['newPassword'] != $_POST['confirmPassword']) {
                echo json_encode(["success" => false, "message" => "New password and confirm password do not match!"]);
                exit;
            } else if (password_verify($_POST['newPassword'], $profile['password_hash'])) {
                echo json_encode(["success" => false, "message" => "New password cannot be the same as the old password!"]);
                exit;
            } else {
                $request = $mysqli->prepare("UPDATE Users SET password_hash = ? WHERE username = ?");
                $request->bind_param("ss",$password,$_POST['username']);
                $request->execute();
                $mysqli->commit();
                $request->close();
                echo json_encode(["success" => true, "message" => "Password updated!"]);
            }
            break;
    }
} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>