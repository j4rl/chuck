# Chuck Norris Facts API

Ett enkelt PHP-API som hämtar Chuck Norris-fakta från MySQL-tabellen `chuck_norris_facts`.


## Parametrar

API:t stödjer följande query-parametrar:

- `id` - hämtar en specifik fakta via ID.
- `count` - hämtar flera slumpmässiga fakta.
- `search` - söker i texten i fältet `fact`.
- `all` - hämtar alla fakta (`all=1`, `all=true`, `all=yes`, `all=on`).

Viktigt: använd bara en av dessa parametrar per anrop. Om flera skickas samtidigt returneras `400`.

## Endpoints och exempel

### 1) Slumpmässig fakta (default)

Om inga parametrar skickas:

```bash
curl "http://localhost/chuck/index.php"
```

### 2) Specifik fakta via ID

```bash
curl "http://localhost/chuck/index.php?id=5"
```

### 3) Flera slumpmässiga fakta

```bash
curl "http://localhost/chuck/index.php?count=3"
```

Obs: om `count` är större än antal rader i tabellen begränsas resultatet automatiskt.

### 4) Sök fakta

```bash
curl "http://localhost/chuck/index.php?search=norris"
```

Obs: `search` måste vara minst 2 tecken.

### 5) Hämta alla fakta

```bash
curl "http://localhost/chuck/index.php?all=1"
```

## Exempel på lyckat svar

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

## Exempel på felsvar

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

## Datamodell

Tabell: `chuck_norris_facts`

- `id` (int)
- `fact` (text/string)
