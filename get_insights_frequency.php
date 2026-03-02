<?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    require_once './index.php';
    require_login();
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;}

    
// This php endpoint aggregates a user's journal entries by month for given year and returns a json object like {Jan: 2, Feb: 4, Mar:14}
// This php code returns all months even if a month has 0 entries.
try{
    // Log incoming GET year parameter
    error_log("GET year param: " . ($_GET['year'] ?? 'none'));

    if(!isset($_GET['year']) || empty($_GET['year'])) {
        throw new Exception("No year in GET set", 400);
    }

    // Sanitize: only allow 4-digit year
    $year_string = trim($_GET['year']);
    if (!preg_match('/^\d{4}$/', $year_string)) {
        throw new Exception("Invalid year format", 400);
    }

    // Build LIKE pattern for SQL: %-2025 (for example)
    $year_pattern = "%-" . $year_string;

    $connection = connect_to_database($env);

    // Use a parameter for the LIKE filter
    $sql = 
        "SELECT SUBSTRING_INDEX(created_at, '-', 1) AS month_number,
                COUNT(*) AS entry_count 
         FROM Journals
         WHERE user_id = ? 
         AND created_at LIKE ?
         GROUP BY month_number
         ORDER BY month_number;";

    $statement = $connection->prepare($sql);

    if(!$statement) {
        die("Error preparing SQL statement: " . $connection->error);
    }

    $statement->bind_param("is", $user_id, $year_pattern);
    if(!$statement->execute()) {
        die("Error executing SQL query");
    }
    $result = $statement->get_result();

    // Initialize all months with 0
    $monthMap = [
    "01" => "Jan", "02" => "Feb", "03" => "Mar", "04" => "Apr",
    "05" => "May", "06" => "Jun", "07" => "Jul", "08" => "Aug",
    "09" => "Sep", "10" => "Oct", "11" => "Nov", "12" => "Dec"];

    $chartData = [];
    foreach ($monthMap as $num => $name) {
        $chartData[$num] = [
            'month' => $name,
            'entries' => 0
        ];
    }

    // Fill in data from SQL result
    while ($row = $result->fetch_assoc()) {
        $month = str_pad($row['month_number'], 2, '0', STR_PAD_LEFT); // ensure "01", "02", etc.
        if (isset($chartData[$month])) {
            $chartData[$month]['entries'] = (int)$row['entry_count'];
        }
    }

    // Return as JSON array (ordered by month)
    echo json_encode(array_values($chartData), JSON_PRETTY_PRINT);


    // Close database connections
    $connection->close();
    $statement->close();

} catch (Exception $e){
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    exit();
}

?>