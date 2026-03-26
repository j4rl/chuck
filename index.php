<?php
declare(strict_types=1);

/* Detta API är ett enkelt API för att hämta slumpmässiga Chuck Norris-fakta.
   Det är byggt i PHP och använder MySQLi för att ansluta till en MySQL-databas där fakta lagras.
   Vi har förbättrat det tidigare "basic.php" genom att lägga till mer robust felhantering, 
   stöd för flera olika typer av förfrågningar (hämta specifikt id, hämta flera slumpmässiga fakta, söka i fakta, etc.), 
   och en mer konsekvent struktur för att skicka JSON-svar. 
   Det är fortfarande ett enkelt API, men det är mer flexibelt och robust än det tidigare exemplet.
   Felhanteringen är förbättrad genom att använda try-catch-block för att fånga både databasrelaterade fel och andra oväntade fel, 
   och skicka tillbaka meningsfulla felmeddelanden i JSON-format. 
   Vi har också lagt till en hjälpfunktion "send_json" för att standardisera hur vi skickar JSON-svar, 
   och en "send_error" funktion för att skicka felmeddelanden på ett konsekvent sätt.
   Det finns en väl utbyggd CORS-huvud som tillåter förfrågningar från alla domäner, och hanterar även OPTIONS-förfrågningar korrekt.
   Även om vi skiter i OPTIONS-förfrågningar i det här enkla API:et, så är det viktigt att hantera dem korrekt för att säkerställa att CORS fungerar som det ska.
   ------------------------------------------------------------------------
   TL;DR: Man kan enkelt använda PHP för att bygga ett eget API.
*/

/*
|--------------------------------------------------------------------------
| CORS-huvud
|  CORS (Cross-Origin Resource Sharing) är en mekanism som tillåter webbläsare 
|  att göra förfrågningar till en annan domän än den som serverar webbplatsen. 
|  Detta är viktigt för API:er som kan användas av klienter på olika domäner.
|--------------------------------------------------------------------------
*/
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); //204 means "No Content" - vi behöver inte skicka något svar på en OPTIONS-förfrågan
    exit;
}

// Sätt rätt Content-Type för alla svar, så att klienter vet att det är JSON som returneras
header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Databasinställningar
|--------------------------------------------------------------------------
*/
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_example';

/*
|--------------------------------------------------------------------------
| Hjälpfunktioner
|--------------------------------------------------------------------------
*/
function send_json(array $data, int $status = 200): void //Man 'deklarerar' funktionen som void, vilket betyder att funktionen inte returnerar något värde
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function send_error(string $message, int $status = 400, array $extra = []): void
{
    send_json(array_merge([
        'success' => false,
        'error' => $message
    ], $extra), $status);
}

function normalize_bool_param($value): bool
{
    if ($value === null) {
        return false;
    }

    $value = strtolower((string)$value);
    return in_array($value, ['1', 'true', 'yes', 'on', 'sure', 'definitely', 'affirmative', 'yep', 'ja', 'visst'], true);
}

/*
|--------------------------------------------------------------------------
| Felhantering
|--------------------------------------------------------------------------
*/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
// Detta gör att mysqli kommer att kasta undantag (exceptions) istället för att bara returnera false vid fel.
// Det gör det lättare att hantera fel på ett konsekvent sätt med try-catch
// mysqli_report är en funktion för att sätta rapporteringnivå för mysqli.

try {
    /*
    |--------------------------------------------------------------------------
    | Anslut till databasen
    |--------------------------------------------------------------------------
    */
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name); // Skapa en ny mysqli-anslutning med objektorienterad syntax
    $conn->set_charset('utf8mb4');

    /*
    |--------------------------------------------------------------------------
    | Läs parametrar
    |--------------------------------------------------------------------------
    */
    $idRaw     = $_GET['id'] ?? null; // Finns $_GET['id'] annars sätt det till null (You know... )
    $countRaw  = $_GET['count'] ?? null;
    $searchRaw = $_GET['search'] ?? null;
    $allRaw    = $_GET['all'] ?? null;

    $hasId     = $idRaw !== null && $idRaw !== ''; // Hängslen och livrem
    $hasCount  = $countRaw !== null && $countRaw !== '';
    $hasSearch = $searchRaw !== null && trim((string)$searchRaw) !== '';
    $hasAll    = normalize_bool_param($allRaw);

    /*
    |--------------------------------------------------------------------------
    | Tillåt bara EN huvudtyp av fråga åt gången
    |--------------------------------------------------------------------------
    */
    $activeModes = 0;
    $activeModes += $hasId ? 1 : 0;
    $activeModes += $hasCount ? 1 : 0;
    $activeModes += $hasSearch ? 1 : 0;
    $activeModes += $hasAll ? 1 : 0;

    if ($activeModes > 1) {
        send_error(
            'Ange bara en av parametrarna: id, count, search eller all.',
            400 // 400 Bad Request - klienten har skickat en ogiltig förfrågan
        );
    }

    /* This is a list of HTTP status and error codes you can use:
    200 OK - Allt gick bra, och svaret innehåller det som efterfrågades.
    204 No Content - Allt gick bra, men det finns ingen innehåll att returnera.
    400 Bad Request - Klienten har skickat en ogiltig förfrågan, t.ex. ogiltiga parametrar.
    401 Unauthorized - Förfrågan kräver autentisering, och klienten har inte autentiserats.
    403 Forbidden - Klienten är autentiserad men har inte tillstånd att komma åt resursen.
    404 Not Found - Det efterfrågade resursen kunde inte hittas.
    418 I'm a teapot - Kan användas för att indikera att servern vägrar att brygga kaffe eftersom den är en tekanna.
    500 Internal Server Error - Ett oväntat fel uppstod på servern, t.ex. databasfel.
    501 Not Implemented - Den begärda funktionaliteten är inte implementerad på servern.
    503 Service Unavailable - Servern är tillfälligt överbelastad eller under underhåll.
    504 Gateway Timeout - Servern agerade som en gateway och fick ingen snabb respons från upstream-servern.
    505 HTTP Version Not Supported - Servern stöder inte den HTTP-protokollversion som användes i förfrågan.
    */

    /*
    |--------------------------------------------------------------------------
    | 1. Hämta specifikt id
    |--------------------------------------------------------------------------
    */
    if ($hasId) {
        $id = filter_var($idRaw, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            send_error('Ogiltigt id. Ange ett heltal större än 0.', 400);
        }

        $stmt = $conn->prepare("SELECT id, fact FROM chuck_norris_facts WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $fact = $result->fetch_assoc();
        $stmt->close();

        if (!$fact) {
            send_error('Ingen fakta hittades med detta id.', 404, [
                'requested_id' => $id
            ]);
        }

        send_json([
            'success' => true,
            'mode' => 'single_by_id',
            'data' => $fact
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Hämta x antal slumpmässiga fakta
    |--------------------------------------------------------------------------
    */
    if ($hasCount) {
        $count = filter_var($countRaw, FILTER_VALIDATE_INT);

        if ($count === false || $count < 1) {
            send_error('Ogiltigt count-värde. Ange ett heltal större än 0.', 400);
        }

        $result = $conn->query("SELECT COUNT(*) AS total FROM chuck_norris_facts");
        $totalFacts = (int)$result->fetch_assoc()['total'];

        if ($totalFacts === 0) {
            send_error('Tabellen innehåller inga fakta.', 404);
        }

        if ($count > $totalFacts) {
            $count = $totalFacts;
        }

        $stmt = $conn->prepare("SELECT id, fact FROM chuck_norris_facts ORDER BY RAND() LIMIT ?");
        $stmt->bind_param('i', $count);
        $stmt->execute();
        $result = $stmt->get_result();

        $facts = [];
        $seenIds = [];

        while ($row = $result->fetch_assoc()) {
            $rowId = (int)$row['id'];

            if (!isset($seenIds[$rowId])) {
                $seenIds[$rowId] = true;
                $facts[] = $row;
            }
        }

        $stmt->close();

        send_json([
            'success' => true,
            'mode' => 'random_multiple',
            'count' => count($facts),
            'requested_count' => (int)$countRaw,
            'data' => $facts
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Hämta alla fakta
    |--------------------------------------------------------------------------
    */
    if ($hasAll) {
        $result = $conn->query("SELECT id, fact FROM chuck_norris_facts ORDER BY id ASC");

        $facts = [];
        while ($row = $result->fetch_assoc()) {
            $facts[] = $row;
        }

        if (count($facts) === 0) {
            send_error('Tabellen innehåller inga fakta.', 404);
        }

        send_json([
            'success' => true,
            'mode' => 'all',
            'count' => count($facts),
            'data' => $facts
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Sök i fakta
    |--------------------------------------------------------------------------
    */
    if ($hasSearch) {
        $search = trim((string)$searchRaw);

        if (mb_strlen($search) < 2) {
            send_error('Söksträngen måste vara minst 2 tecken lång.', 400);
        }

        $like = '%' . $search . '%';

        $stmt = $conn->prepare("
            SELECT id, fact
            FROM chuck_norris_facts
            WHERE fact LIKE ?
            ORDER BY id ASC
        ");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $facts = [];
        $seenIds = [];

        while ($row = $result->fetch_assoc()) {
            $rowId = (int)$row['id'];

            if (!isset($seenIds[$rowId])) {
                $seenIds[$rowId] = true;
                $facts[] = $row;
            }
        }

        $stmt->close();

        if (count($facts) === 0) {
            send_error('Inga fakta matchade sökningen.', 404, [
                'search' => $search
            ]);
        }

        send_json([
            'success' => true,
            'mode' => 'search',
            'search' => $search,
            'count' => count($facts),
            'data' => $facts
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Default: en slumpmässig fakta
    |--------------------------------------------------------------------------
    */
    $result = $conn->query("SELECT id, fact FROM chuck_norris_facts ORDER BY RAND() LIMIT 1");
    $fact = $result->fetch_assoc();

    if (!$fact) {
        send_error('Tabellen innehåller inga fakta.', 404);
    }

    send_json([
        'success' => true,
        'mode' => 'random_single',
        'data' => $fact
    ]);

} catch (mysqli_sql_exception $e) {
    send_error('Databasfel uppstod.', 500, [
        'details' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    send_error('Ett oväntat fel uppstod.', 500, [
        'details' => $e->getMessage()
    ]);
}