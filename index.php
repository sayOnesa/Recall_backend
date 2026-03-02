<?php
/* 
* File containing all basic functionality for our web app. This includes connecting to
* the database, checking if an element is in the database, sending an email, handling 
* user sessions, etc.
*/

require_once "./config.php";


/**
 * Finds if an element matching $value already exists in column $field of a connected
 * database.
 * 
 * @param mixed $connection The mysqli object that handles connections to a database. 
 *                          Initially created by calling connect_to_database()
 * @param mixed $field  The column label we are searching through to find duplicates
 * @param mixed $value  The value we are checking to see if it already exists in column 
 *                      $field
 * 
 * @return bool true if a $field with a value of $value exists in the connected database
 */
function element_in_table($connection, $sql, $bindings, $values) {
    $statement = $connection->prepare($sql);
    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param($bindings, ...$values);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }

    $result = $statement->get_result();
    $statement->close();
    if(!$result) {
        die("Error getting result");
    }

    if($result->num_rows > 0) {
        return true;
    }

    return false;
}


function insert_into_table($connection, $sql, $bindings, $values) {
    $statement = $connection->prepare($sql);
    if(!$statement) {
        return false;
    }

    $statement->bind_param($bindings, ...$values);
    if(!$statement->execute()) {
        return false;
    }

    return true;
}

function delete_from_table($connection, $sql, $bindings, $values) {
    $statement = $connection->prepare($sql);
    if(!$statement) {
        return false;
    }

    $statement->bind_param($bindings, ...$values);
    if(!$statement->execute()) {
        return false;
    }

    return true;
}

function get_rows_from_table($connection, $sql, $bindings, $values) {
    $statement = $connection->prepare($sql);
    if(!$statement) {
        return false;
    }

    $statement->bind_param($bindings, ...$values);
    if(!$statement->execute()) {
        return false;
    }

    $result = $statement->get_result();
    $statement->close();
    if(!$result) {
        return false;
    }

    if($result->num_rows > 0) {
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    return false;
}

function get_id_from_table($connection, $table, $field, $value) {
    $statement = $connection->prepare("SELECT id FROM $table WHERE $field = ? LIMIT 1");
    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param("s", $value);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }

    $result = $statement->get_result();
    $statement->close();
    if(!$result) {
        die("Error getting result");
    }

    if($result->num_rows > 0) {
        $data = mysqli_fetch_assoc($result); 
        if(!$data || $data === null) {
            return false;
        }

        return $data['id'];
    }

    return false;
}



function remove_element_from_table($connection, $table, $field, $value) {
    $statement = $connection->prepare("DELETE FROM $table WHERE $field = ?");
    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param("i", $value);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }

   $statement->close();
}


/**
 * Connects to the database specified by the credentials in "./config.ini"
 * 
 * @param array $env An array containing the parsed contents of "./config.ini"
 * 
 * @return mysqli The mysqli object created by mysqli() on success
 */
function connect_to_database($env) {

    $db_host = (string)$env["DB_HOST"];
    $db_user = (string)$env["DB_USER"];
    $db_pass = (string)$env["DB_PASS"];
    $db_name = (string)$env["DB_NAME"];

    // Create a new connection to the database
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check if we connected to the database successfully
    if($mysqli->connect_error) {
        die("Error connecting to database: " . $mysqli->connect_error);
    }
    
    return $mysqli;
}

function is_valid_date_string($date_string) {
    $date = DateTime::createFromFormat('m-d-Y', $date_string);
    return $date && $date->format('m-d-Y') === $date_string;
}

function require_login() {
    if(session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if(!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'user not verified']);
        exit;
    }
}

/**
 * Verifies is a CSRF token is valid
 *
 *  @param mixed $data POST data sent from the client
 * @throws \Exception throws exception if invalid 
 * @return void
 */
function verify_csrf_token($data) {
    if(!isset($data['token']) || empty($data['token'])) {
        throw new Exception("No Token", 400);
    }

    if($data['token'] !== $_SESSION['csrf_token']) {
        throw new Exception("Invalid token", 400);
    }
}


function verify_fields_in_request_data($request_data, $required_fields) {
    // Ensure POST request has data
    if(empty($request_data)) {
        throw new Exception("No data", 400);
    }

    // Ensure no input fields are missing or empty
    foreach ($required_fields as $field) {
        if (!isset($request_data[$field])) {
            throw new Exception("Missing input data", 400);
        }
    }
}

?>
