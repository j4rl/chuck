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

// Kontrollera anslutning, och skicka felmeddelande om det inte gick att ansluta
if (!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Databasfel'
    ]);
    exit;
}

// Hämta EN slumpmässig fakta
$sql = "SELECT id, fact FROM chuck_norris_facts ORDER BY RAND() LIMIT 1";
$result = mysqli_query($conn, $sql); // Objekt med resultatet av frågan, som vi sedan kan hämta arrayer från

// Hämta rad
$row = mysqli_fetch_assoc($result); // Hämtar den enstaka associativa arrayen som finns

// Skicka JSON (...eller mer korrekt: skriv ut JSON på vår php-sida)
echo json_encode([
    'success' => true,
    'data' => $row //json_encode kommer att konvertera den associativa arrayen till ett JSON-objekt
]);