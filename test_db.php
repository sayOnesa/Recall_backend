<?php
header("Content-Type: application/json; charset=utf-8");

try {
    $env = parse_ini_file("./config.ini");
    
    if (!$env) {
        throw new Exception("Failed to read config.ini");
    }

    $mysqli = new mysqli($env['DB_HOST'], $env['DB_USER'], $env['DB_PASS'], $env['DB_NAME']);

    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }

    echo json_encode([
        "success" => true,
        "message" => "Database connection successful!"
    ]);

    $mysqli->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed",
        "debug" => $e->getMessage()
    ]);
}
?>