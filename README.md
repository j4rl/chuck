# Chuck Norris Facts API

Ett enkelt PHP-projekt med två API-varianter:

- `index.php` - full API med flera query-parametrar.
- `basic.php` - enkel API som alltid returnerar en slumpmässig fakta.

## Bas-URL (lokalt)

- Full API: `http://localhost/chuck/index.php`
- Basic API: `http://localhost/chuck/basic.php`

## Datamodell

Båda filerna använder tabellen `chuck_norris_facts` med:

- `id` (int)
- `fact` (text/string)

## `index.php` (full API)

### Parametrar

API:t stödjer följande query-parametrar:

- `id` - hämtar en specifik fakta via ID.
- `count` - hämtar flera slumpmässiga fakta.
- `search` - söker i texten i fältet `fact`.
- `all` - hämtar alla fakta (`all=1`, `all=true`, `all=yes`, `all=on`).

Viktigt: använd bara en av dessa parametrar per anrop. Om flera skickas samtidigt returneras `400`.

### Exempelanrop

1) Slumpmässig fakta (default)

```bash
curl "http://localhost/chuck/index.php"
```

2) Specifik fakta via ID

```bash
curl "http://localhost/chuck/index.php?id=5"
```

3) Flera slumpmässiga fakta

```bash
curl "http://localhost/chuck/index.php?count=3"
```

Obs: om `count` är större än antal rader i tabellen begränsas resultatet automatiskt.

4) Sök fakta

```bash
curl "http://localhost/chuck/index.php?search=norris"
```

Obs: `search` måste vara minst 2 tecken.

5) Hämta alla fakta

```bash
curl "http://localhost/chuck/index.php?all=1"
```

### Exempel på lyckat svar (`index.php`)

```json
{
  "success": true,
  "mode": "random_single",
  "data": {
    "id": "12",
    "fact": "Chuck Norris can divide by zero."
  }
}
```

`mode` kan vara:

- `random_single`
- `single_by_id`
- `random_multiple`
- `search`
- `all`

### Exempel på felsvar (`index.php`)

```json
{
  "success": false,
  "error": "Ogiltigt id. Ange ett heltal större än 0."
}
```

Vanliga felkoder:

- `400` - ogiltiga parametrar eller flera huvudparametrar samtidigt.
- `404` - ingen träff eller tom tabell.
- `500` - databasfel eller oväntat serverfel.

## `basic.php` (enkel API)

`basic.php` är en minimal endpoint för snabb testning. Den:

- tillåter CORS med `Access-Control-Allow-Origin: *`
- returnerar alltid JSON
- tar inga query-parametrar
- hämtar exakt 1 slumpmässig rad från `chuck_norris_facts`

### Exempelanrop

```bash
curl "http://localhost/chuck/basic.php"
```

### Exempel på svar (`basic.php`)

```json
{
  "success": true,
  "data": {
    "id": "12",
    "fact": "Chuck Norris can divide by zero."
  }
}
```

Vid databasfel returneras:

```json
{
  "success": false,
  "error": "Databasfel"
}
```

## Databasinställningar

Observera att filerna just nu har olika DB-konfiguration:

- `index.php` använder en uppsättning DB-credentials.
- `basic.php` använder `localhost`, `root`, tomt lösenord och databasen `db_example`.

Om båda endpoints ska fungera mot samma data, sätt samma databasinställningar i båda filerna.
