<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Databasinställningar
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_example';

// Anslut till databasen
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Kontrollera anslutning
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Databasfel'
    ]);
    exit;
}

// Hämta en slumpmässig fakta
$result = $conn->query("SELECT id, fact FROM chuck_norris_facts ORDER BY RAND() LIMIT 1");

// Hämta rad
$row = $result->fetch_assoc();

// Skicka JSON
echo json_encode([
    'success' => true,
    'data' => $row
]);