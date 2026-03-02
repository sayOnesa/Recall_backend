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
  

try{
    // Parse the start and end dates from the query string (MM-DD-YYYY) as in DB
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    if (!$start || !$end) {
        throw new Exception("Start and end dates must be provided");
    }

    // Map string moods to numeric values
    // Map moods to numeric values
    $MOOD_MAP = [
        'Sad' => 1,
        'Angry' => 2,
        'Neutral' => 3,
        'Calm' => 4,
        'Happy' => 5
    ];

    // Query the Journals table for current user's entries within that range
    
    // Prepare the sql query
    $connection = connect_to_database($env);
    $sql = "SELECT created_at, mood FROM Journals 
            WHERE user_id = ? 
              AND STR_TO_DATE(created_at, '%m-%d-%Y') BETWEEN STR_TO_DATE(?, '%m-%d-%Y') AND STR_TO_DATE(?, '%m-%d-%Y')";

    $statement = $connection->prepare($sql);
    $statement->bind_param("iss", $user_id, $start, $end);
    $statement->execute();
    $result = $statement->get_result();

    // Return results in format: [{ date: "YYYY-MM-DD", count: 1–5 }].

    $moodData = [];

    while ($row = $result->fetch_assoc()) {
        $created_at = DateTime::createFromFormat('m-d-Y', $row['created_at']);
        if ($created_at) {
            $dateStr = $created_at->format('Y-m-d'); // convert to YYYY-MM-DD
            $moodStr = $row['mood'] ?? null;
            $count = $MOOD_MAP[$moodStr] ?? 0;      // php maps any NULL mood in the DB becomes count 0

            $moodData[] = [
                'date' => $dateStr,
                'count' => $count
            ];
        }
    }

    echo json_encode($moodData);

    $statement->close();
    $connection->close();

} catch (Exception $e){
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
    exit();
}

?>