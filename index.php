<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Databasinställningar
|--------------------------------------------------------------------------
*/
$db_host = 'mockelngymnasiet.com.mysql';
$db_user = 'mockelngymnasie';
$db_pass = 'PPeTExVh';
$db_name = 'mockelngymnasie';

/*
|--------------------------------------------------------------------------
| Hjälpfunktioner
|--------------------------------------------------------------------------
*/
function send_json(array $data, int $status = 200): void
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
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

/*
|--------------------------------------------------------------------------
| Felhantering
|--------------------------------------------------------------------------
*/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    /*
    |--------------------------------------------------------------------------
    | Anslut till databasen
    |--------------------------------------------------------------------------
    */
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    /*
    |--------------------------------------------------------------------------
    | Läs parametrar
    |--------------------------------------------------------------------------
    */
    $idRaw     = $_GET['id'] ?? null;
    $countRaw  = $_GET['count'] ?? null;
    $searchRaw = $_GET['search'] ?? null;
    $allRaw    = $_GET['all'] ?? null;

    $hasId     = $idRaw !== null && $idRaw !== '';
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
            400
        );
    }

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