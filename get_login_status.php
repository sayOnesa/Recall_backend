<?php
/* Check if the current user is logged in */
require_once 'index.php';

$loggedIn = false;
if(isset($_SESSION['csrf_token'])) {
    $loggedIn = true;
}

echo json_encode(['success' => $loggedIn]);




