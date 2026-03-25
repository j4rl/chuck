<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Databasinställningar
$host = 'localhost';
$user = 'root';
$pass = '';
$name = 'db_example';

// Anslut till databasen
$conn = mysqli_connect($host, $user, $pass, $name);

// Kontrollera anslutning
if (!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Databasfel'
    ]);
    exit;
}

// Hämta en slumpmässig fakta
$sql = "SELECT id, fact FROM chuck_norris_facts ORDER BY RAND() LIMIT 1";
$result = mysqli_query($conn, $sql);

// Hämta rad
$row = mysqli_fetch_assoc($result);

// Skicka JSON
echo json_encode([
    'success' => true,
    'data' => $row
]);