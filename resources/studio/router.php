<?php

declare(strict_types=1);

$root = normalize_path((string) ($_SERVER['PINX_STUDIO_PROJECT_ROOT'] ?? getenv('PINX_STUDIO_PROJECT_ROOT') ?: getcwd()));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$basePath = rtrim((string) ($_SERVER['PINX_STUDIO_BASE_PATH'] ?? getenv('PINX_STUDIO_BASE_PATH') ?: ''), '/');

if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
    $path = substr($path, strlen($basePath));
    $path = $path === '' ? '/' : $path;
}

$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
if (!str_starts_with($remoteAddress, '127.') && $remoteAddress !== '::1') {
    http_response_code(403);
    echo 'Pinx Studio is local-only.';
    return;
}

try {
    if ($path === '/' || $path === '/index.html') {
        html_response(studio_html());
        return;
    }

    if ($path === '/api/summary') {
        json_response(summary_payload($root));
        return;
    }

    if ($path === '/api/tables') {
        json_response(tables_payload($root));
        return;
    }

    if ($path === '/api/table') {
        $table = (string) ($_GET['name'] ?? '');
        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        json_response(table_payload($root, $table, $limit, $offset));
        return;
    }

    if ($path === '/api/export') {
        json_response(export_payload($root));
        return;
    }

    http_response_code(404);
    echo 'Not found';
} catch (Throwable $e) {
    json_response([
        'error' => true,
        'message' => $e->getMessage(),
    ], 500);
}

function normalize_path(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

function read_env(string $root): array
{
    $env = [];
    $file = $root . '/.env';
    if (!is_file($file)) {
        return $env;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim(trim($value), "\"'");
    }

    return $env;
}

function resolve_project_path(string $root, string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || $path === '~') {
        return $root;
    }

    if (str_starts_with($path, '~/')) {
        return $root . '/' . substr($path, 2);
    }

    if (preg_match('/^[A-Za-z]:\//', $path) === 1 || str_starts_with($path, '/')) {
        return $path;
    }

    return $root . '/' . $path;
}

function devdb_path(string $root): string
{
    $env = read_env($root);
    $path = (string) ($env['DEVDB_PATH'] ?? 'storage/devdb');

    return resolve_project_path($root, $path);
}

function sqlite_database(string $root): string
{
    $env = read_env($root);
    $database = (string) ($env['DEVDB_SQLITE_DATABASE'] ?? '');

    return $database !== '' ? resolve_project_path($root, $database) : devdb_path($root) . '/devdb.sqlite';
}

function connection_config(string $root): array
{
    $env = read_env($root);
    $connection = strtolower((string) ($env['DB_CONNECTION'] ?? 'devdb'));

    if ($connection === 'auto') {
        $real = strtolower((string) ($env['DB_DRIVER'] ?? 'mysql'));
        if (!empty($env['DB_HOST']) && !empty($env['DB_DATABASE']) && !empty($env['DB_USERNAME']) && in_array($real, ['mysql', 'mariadb', 'pgsql'], true)) {
            $connection = $real;
        } elseif (!empty($env['DB_DATABASE']) && is_file(resolve_project_path($root, (string) $env['DB_DATABASE']))) {
            $connection = 'sqlite';
        } else {
            $connection = 'devdb';
        }
    }

    if ($connection === 'mariadb') {
        $connection = 'mysql';
    }

    return [
        'connection' => $connection,
        'env' => $env,
    ];
}

function engine(string $root): string
{
    $config = connection_config($root);
    $connection = $config['connection'];

    if (in_array($connection, ['mysql', 'pgsql', 'sqlite'], true)) {
        return $connection;
    }

    if (extension_loaded('pdo_sqlite') && is_file(sqlite_database($root))) {
        return 'devdb-sqlite';
    }

    return 'devdb-json';
}

function json_file(string $path, array $default): array
{
    if (!is_file($path)) {
        return $default;
    }

    $content = file_get_contents($path);
    $decoded = is_string($content) ? json_decode($content, true) : null;

    return is_array($decoded) ? $decoded : $default;
}

function app_config(string $root): array
{
    $file = $root . '/app.php';
    if (!is_file($file)) {
        return [];
    }

    $config = require $file;

    return is_array($config) ? $config : [];
}

function summary_payload(string $root): array
{
    $config = app_config($root);
    $tables = tables_payload($root);
    $connection = connection_config($root)['connection'];

    return [
        'app' => [
            'package' => (string) ($config['package'] ?? 'unknown'),
            'name' => (string) ($config['name'] ?? $config['title'] ?? 'Pinoox App'),
            'theme' => (string) ($config['theme'] ?? 'default'),
            'root' => $root,
        ],
        'database' => [
            'connection' => $connection,
            'engine' => engine($root),
            'path' => devdb_path($root),
            'sqlite_database' => sqlite_database($root),
            'table_count' => count($tables['tables']),
        ],
    ];
}

function tables_payload(string $root): array
{
    return match (engine($root)) {
        'mysql' => pdo_tables_payload($root, 'mysql'),
        'pgsql' => pdo_tables_payload($root, 'pgsql'),
        'sqlite' => pdo_tables_payload($root, 'sqlite'),
        'devdb-sqlite' => devdb_sqlite_tables_payload($root),
        default => devdb_json_tables_payload($root),
    };
}

function devdb_json_tables_payload(string $root): array
{
    $schema = json_file(devdb_path($root) . '/schema.json', ['tables' => []]);
    $tables = [];
    foreach (($schema['tables'] ?? []) as $name => $meta) {
        $rows = json_file(devdb_path($root) . '/data/' . safe_table_file((string) $name) . '.json', []);
        $tables[] = [
            'name' => (string) $name,
            'columns' => count($meta['columns'] ?? []),
            'rows' => count($rows),
            'primary_key' => $meta['primary_key'] ?? null,
        ];
    }

    usort($tables, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

    return [
        'engine' => 'devdb-json',
        'tables' => $tables,
    ];
}

function devdb_json_table_payload(string $root, string $table, int $limit, int $offset, string $search): array
{
    $schema = json_file(devdb_path($root) . '/schema.json', ['tables' => []]);
    $meta = $schema['tables'][$table] ?? null;
    if (!is_array($meta)) {
        throw new RuntimeException('Table "' . $table . '" does not exist.');
    }

    $rows = json_file(devdb_path($root) . '/data/' . safe_table_file($table) . '.json', []);
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
            return str_contains(mb_strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), $needle);
        }));
    }

    return [
        'engine' => 'devdb-json',
        'table' => $table,
        'columns' => $meta['columns'] ?? [],
        'indexes' => $meta['indexes'] ?? [],
        'primary_key' => $meta['primary_key'] ?? null,
        'row_count' => count($rows),
        'limit' => $limit,
        'offset' => $offset,
        'rows' => array_slice($rows, $offset, $limit),
    ];
}

function table_payload(string $root, string $table, int $limit, int $offset): array
{
    if ($table === '') {
        throw new RuntimeException('Table name is required.');
    }

    $search = trim((string) ($_GET['q'] ?? ''));

    return match (engine($root)) {
        'mysql' => pdo_table_payload($root, 'mysql', $table, $limit, $offset, $search),
        'pgsql' => pdo_table_payload($root, 'pgsql', $table, $limit, $offset, $search),
        'sqlite' => pdo_table_payload($root, 'sqlite', $table, $limit, $offset, $search),
        'devdb-sqlite' => devdb_sqlite_table_payload($root, $table, $limit, $offset, $search),
        default => devdb_json_table_payload($root, $table, $limit, $offset, $search),
    };
}

function export_payload(string $root): array
{
    if (engine($root) === 'devdb-sqlite') {
        $tables = devdb_sqlite_tables_payload($root)['tables'];
        $data = [];
        foreach ($tables as $table) {
            $data[$table['name']] = devdb_sqlite_table_payload($root, (string) $table['name'], 10000, 0, '');
        }

        return [
            'engine' => 'devdb-sqlite',
            'data' => $data,
        ];
    }

    if (in_array(engine($root), ['mysql', 'pgsql', 'sqlite'], true)) {
        return [
            'engine' => engine($root),
            'tables' => tables_payload($root)['tables'],
        ];
    }

    return [
        'engine' => 'devdb-json',
        'schema' => json_file(devdb_path($root) . '/schema.json', ['tables' => []]),
        'data' => json_data_payload($root),
        'meta' => [
            'migrations' => json_file(devdb_path($root) . '/meta/migrations.json', []),
            'sequences' => json_file(devdb_path($root) . '/meta/sequences.json', []),
        ],
    ];
}

function json_data_payload(string $root): array
{
    $schema = json_file(devdb_path($root) . '/schema.json', ['tables' => []]);
    $data = [];
    foreach (array_keys($schema['tables'] ?? []) as $table) {
        $data[(string) $table] = json_file(devdb_path($root) . '/data/' . safe_table_file((string) $table) . '.json', []);
    }

    return $data;
}

function safe_table_file(string $table): string
{
    return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $table) ?: $table;
}

function sqlite_pdo(string $root): PDO
{
    return new PDO('sqlite:' . sqlite_database($root), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function pdo_for_connection(string $root, string $driver): PDO
{
    $env = connection_config($root)['env'];

    if ($driver === 'sqlite') {
        $database = resolve_project_path($root, (string) ($env['DB_DATABASE'] ?? ''));
        return new PDO('sqlite:' . $database, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    $host = (string) ($env['DB_HOST'] ?? '127.0.0.1');
    $port = (int) ($env['DB_PORT'] ?? ($driver === 'pgsql' ? 5432 : 3306));
    $database = (string) ($env['DB_DATABASE'] ?? '');
    $username = (string) ($env['DB_USERNAME'] ?? '');
    $password = (string) ($env['DB_PASSWORD'] ?? '');
    $dsn = $driver === 'pgsql'
        ? "pgsql:host={$host};port={$port};dbname={$database}"
        : "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function pdo_tables_payload(string $root, string $driver): array
{
    $pdo = pdo_for_connection($root, $driver);
    $tables = [];

    if ($driver === 'pgsql') {
        $rows = $pdo->query("SELECT table_name AS name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll();
    } elseif ($driver === 'sqlite') {
        $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
    } else {
        $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $rows = array_map(static fn (array $row): array => ['name' => (string) ($row[0] ?? '')], $rows);
    }

    foreach ($rows as $row) {
        $name = (string) ($row['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $columns = pdo_columns($pdo, $driver, $name);
        $count = (int) $pdo->query('SELECT COUNT(*) AS count FROM ' . quote_identifier_for($driver, $name))->fetch()['count'];
        $tables[] = [
            'name' => $name,
            'columns' => count($columns),
            'rows' => $count,
            'primary_key' => sqlite_primary_key($columns),
        ];
    }

    return [
        'engine' => $driver,
        'tables' => $tables,
    ];
}

function pdo_table_payload(string $root, string $driver, string $table, int $limit, int $offset, string $search): array
{
    $pdo = pdo_for_connection($root, $driver);
    $columns = pdo_columns($pdo, $driver, $table);
    if ($columns === []) {
        throw new RuntimeException('Table "' . $table . '" does not exist.');
    }

    $quoted = quote_identifier_for($driver, $table);
    $where = '';
    $params = [];
    if ($search !== '') {
        $likes = [];
        foreach (array_keys($columns) as $column) {
            $castType = $driver === 'pgsql' || $driver === 'sqlite' ? 'TEXT' : 'CHAR';
            $likes[] = 'CAST(' . quote_identifier_for($driver, (string) $column) . ' AS ' . $castType . ') LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $where = $likes !== [] ? ' WHERE ' . implode(' OR ', $likes) : '';
    }

    $countStatement = $pdo->prepare('SELECT COUNT(*) AS count FROM ' . $quoted . $where);
    $countStatement->execute($params);
    $count = (int) $countStatement->fetch()['count'];

    $statement = $pdo->prepare('SELECT * FROM ' . $quoted . $where . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
    $statement->execute($params);

    return [
        'engine' => $driver,
        'table' => $table,
        'columns' => $columns,
        'indexes' => pdo_indexes($pdo, $driver, $table),
        'primary_key' => sqlite_primary_key($columns),
        'row_count' => $count,
        'limit' => $limit,
        'offset' => $offset,
        'rows' => $statement->fetchAll(),
    ];
}

function pdo_columns(PDO $pdo, string $driver, string $table): array
{
    if ($driver === 'sqlite') {
        return sqlite_columns($pdo, $table);
    }

    if ($driver === 'pgsql') {
        $statement = $pdo->prepare("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema='public' AND table_name=? ORDER BY ordinal_position");
        $statement->execute([$table]);
        $pkRows = $pdo->prepare("SELECT kcu.column_name FROM information_schema.table_constraints tc JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema WHERE tc.constraint_type='PRIMARY KEY' AND tc.table_schema='public' AND tc.table_name=?");
        $pkRows->execute([$table]);
        $primary = array_flip(array_map(static fn (array $row): string => (string) $row['column_name'], $pkRows->fetchAll()));
        $columns = [];
        foreach ($statement->fetchAll() as $column) {
            $name = (string) $column['column_name'];
            $columns[$name] = [
                'type' => strtolower((string) $column['data_type']),
                'nullable' => strtoupper((string) $column['is_nullable']) === 'YES',
                'default' => $column['column_default'],
                'primary' => isset($primary[$name]),
            ];
        }
        return $columns;
    }

    $statement = $pdo->query('DESCRIBE ' . quote_identifier_for($driver, $table));
    $columns = [];
    foreach ($statement->fetchAll() as $column) {
        $name = (string) $column['Field'];
        $columns[$name] = [
            'type' => strtolower((string) $column['Type']),
            'nullable' => strtoupper((string) $column['Null']) === 'YES',
            'default' => $column['Default'],
            'primary' => strtoupper((string) $column['Key']) === 'PRI',
        ];
    }

    return $columns;
}

function pdo_indexes(PDO $pdo, string $driver, string $table): array
{
    try {
        if ($driver === 'sqlite') {
            return $pdo->query('PRAGMA index_list(' . quote_identifier($table) . ')')->fetchAll();
        }

        if ($driver === 'pgsql') {
            $statement = $pdo->prepare("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname='public' AND tablename=? ORDER BY indexname");
            $statement->execute([$table]);
            return $statement->fetchAll();
        }

        return $pdo->query('SHOW INDEX FROM ' . quote_identifier_for($driver, $table))->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function quote_identifier_for(string $driver, string $name): string
{
    $quote = $driver === 'mysql' ? '`' : '"';
    $escaped = str_replace($quote, $quote . $quote, $name);

    return $quote . $escaped . $quote;
}

function devdb_sqlite_tables_payload(string $root): array
{
    $database = sqlite_database($root);
    if (!extension_loaded('pdo_sqlite') || !is_file($database)) {
        return [
            'engine' => 'devdb-sqlite',
            'tables' => [],
        ];
    }

    $pdo = sqlite_pdo($root);
    $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
    $tables = [];
    foreach ($rows as $row) {
        $name = (string) $row['name'];
        $columns = sqlite_columns($pdo, $name);
        $quoted = quote_identifier($name);
        $count = (int) $pdo->query('SELECT COUNT(*) AS count FROM ' . $quoted)->fetch()['count'];
        $tables[] = [
            'name' => $name,
            'columns' => count($columns),
            'rows' => $count,
            'primary_key' => sqlite_primary_key($columns),
        ];
    }

    return [
        'engine' => 'devdb-sqlite',
        'tables' => $tables,
    ];
}

function devdb_sqlite_table_payload(string $root, string $table, int $limit, int $offset, string $search): array
{
    $pdo = sqlite_pdo($root);
    $columns = sqlite_columns($pdo, $table);
    if ($columns === []) {
        throw new RuntimeException('DevDB table "' . $table . '" does not exist.');
    }

    $quoted = quote_identifier($table);
    $where = '';
    $params = [];
    if ($search !== '') {
        $likes = [];
        foreach (array_keys($columns) as $column) {
            $likes[] = 'CAST(' . quote_identifier((string) $column) . ' AS TEXT) LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $where = $likes !== [] ? ' WHERE ' . implode(' OR ', $likes) : '';
    }

    $countStatement = $pdo->prepare('SELECT COUNT(*) AS count FROM ' . $quoted . $where);
    $countStatement->execute($params);
    $count = (int) $countStatement->fetch()['count'];
    $statement = $pdo->prepare('SELECT * FROM ' . $quoted . $where . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
    $statement->execute($params);

    return [
        'engine' => 'devdb-sqlite',
        'table' => $table,
        'columns' => $columns,
        'indexes' => [],
        'primary_key' => sqlite_primary_key($columns),
        'row_count' => $count,
        'limit' => $limit,
        'offset' => $offset,
        'rows' => $statement->fetchAll(),
    ];
}

function sqlite_columns(PDO $pdo, string $table): array
{
    $statement = $pdo->query('PRAGMA table_info(' . quote_identifier($table) . ')');
    if ($statement === false) {
        return [];
    }

    $columns = [];
    foreach ($statement->fetchAll() as $column) {
        $columns[(string) $column['name']] = [
            'type' => strtolower((string) $column['type']),
            'nullable' => (int) $column['notnull'] === 0,
            'default' => $column['dflt_value'],
            'primary' => (int) $column['pk'] > 0,
        ];
    }

    return $columns;
}

function sqlite_primary_key(array $columns): ?string
{
    foreach ($columns as $name => $column) {
        if (!empty($column['primary'])) {
            return (string) $name;
        }
    }

    return null;
}

function quote_identifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function html_response(string $html): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo $html;
}

function studio_html(): string
{
    return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pinx Studio</title>
  <style>
    :root { color-scheme: light; --bg:#f5f7f9; --panel:#ffffff; --line:#d9e0e7; --text:#1b2733; --muted:#667789; --accent:#0b7a75; --accent-soft:#dff3f1; --danger:#b42318; }
    * { box-sizing:border-box; }
    body { margin:0; font:14px/1.45 system-ui,-apple-system,Segoe UI,sans-serif; color:var(--text); background:var(--bg); }
    header { height:56px; display:flex; align-items:center; justify-content:space-between; padding:0 18px; border-bottom:1px solid var(--line); background:var(--panel); }
    h1 { margin:0; font-size:18px; font-weight:700; }
    main { display:grid; grid-template-columns:280px 1fr; min-height:calc(100vh - 56px); }
    aside { border-right:1px solid var(--line); background:var(--panel); padding:14px; overflow:auto; }
    section { padding:18px; min-width:0; }
    .meta { display:flex; gap:8px; align-items:center; color:var(--muted); font-size:13px; }
    .pill { display:inline-flex; align-items:center; height:24px; padding:0 8px; border-radius:999px; background:var(--accent-soft); color:var(--accent); font-weight:650; }
    .table-list { display:grid; gap:8px; margin-top:14px; }
    button { border:1px solid var(--line); background:var(--panel); color:var(--text); min-height:34px; padding:7px 10px; border-radius:6px; cursor:pointer; text-align:left; }
    button:hover, button.active { border-color:var(--accent); background:var(--accent-soft); }
    .row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .muted { color:var(--muted); }
    .toolbar { display:flex; gap:8px; align-items:center; justify-content:space-between; margin:0 0 12px; }
    .panel { background:var(--panel); border:1px solid var(--line); border-radius:8px; overflow:hidden; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; padding:12px 14px; border-bottom:1px solid var(--line); }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    th, td { padding:9px 10px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
    th { color:var(--muted); font-weight:700; background:#fafbfc; position:sticky; top:0; }
    td code { white-space:pre-wrap; word-break:break-word; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
    .empty { padding:28px; text-align:center; color:var(--muted); }
    .error { color:var(--danger); }
    input { height:34px; border:1px solid var(--line); border-radius:6px; padding:0 9px; min-width:80px; }
    @media (max-width: 820px) { main { grid-template-columns:1fr; } aside { border-right:0; border-bottom:1px solid var(--line); } .grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <header>
    <div>
      <h1>Pinx Studio</h1>
      <div class="meta"><span id="appName">Loading</span><span id="package"></span></div>
    </div>
      <div class="meta"><button id="exportBtn">Export</button><span id="engine" class="pill">Database</span></div>
  </header>
  <main>
    <aside>
      <div class="row"><strong>Tables</strong><button id="refresh">Refresh</button></div>
      <div id="tables" class="table-list"></div>
    </aside>
    <section>
      <div id="overview" class="grid"></div>
      <div id="content" class="empty">Select a table to inspect schema and rows.</div>
    </section>
  </main>
  <script>
    const state = { selected: null, limit: 50, offset: 0, search: '' };
    const $ = (id) => document.getElementById(id);
    const base = location.pathname.startsWith('/~studio') ? '/~studio' : '';
    const api = (url) => fetch(base + url, { cache: 'no-store' }).then(r => r.json());
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    const cell = (value) => typeof value === 'object' && value !== null ? '<code>' + esc(JSON.stringify(value, null, 2)) + '</code>' : esc(value);

    async function boot() {
      const summary = await api('/api/summary');
      $('appName').textContent = summary.app.name;
      $('package').textContent = summary.app.package;
      $('engine').textContent = summary.database.connection + ' / ' + summary.database.engine;
      $('overview').innerHTML = `
        <div class="panel"><div class="panel-head"><strong>App</strong></div><div class="empty"><strong>${esc(summary.app.package)}</strong><br><span class="muted">${esc(summary.app.root)}</span></div></div>
        <div class="panel"><div class="panel-head"><strong>Database</strong></div><div class="empty"><strong>${esc(summary.database.engine)}</strong><br><span class="muted">${summary.database.table_count} tables</span></div></div>
      `;
      await loadTables();
    }

    async function loadTables() {
      const payload = await api('/api/tables');
      const wrap = $('tables');
      wrap.innerHTML = '';
      if (!payload.tables.length) {
        wrap.innerHTML = '<div class="empty">No tables yet. Run pinx migrate.</div>';
        return;
      }
      payload.tables.forEach(table => {
        const btn = document.createElement('button');
        btn.className = table.name === state.selected ? 'active' : '';
        btn.innerHTML = '<div class="row"><strong>' + esc(table.name) + '</strong><span class="muted">' + table.rows + ' rows</span></div><div class="muted">' + table.columns + ' columns</div>';
        btn.onclick = () => { state.selected = table.name; state.offset = 0; state.search = ''; loadTable(); loadTables(); };
        wrap.appendChild(btn);
      });
    }

    async function loadTable() {
      if (!state.selected) return;
      const payload = await api('/api/table?name=' + encodeURIComponent(state.selected) + '&limit=' + state.limit + '&offset=' + state.offset + '&q=' + encodeURIComponent(state.search));
      renderTable(payload);
    }

    function renderTable(payload) {
      const columns = Object.keys(payload.columns || {});
      const schemaRows = columns.map(name => {
        const col = payload.columns[name] || {};
        return '<tr><td><strong>' + esc(name) + '</strong></td><td>' + esc(col.type || '') + '</td><td>' + esc(col.nullable ? 'yes' : 'no') + '</td><td>' + esc(col.default ?? '') + '</td><td>' + esc(col.primary ? 'yes' : '') + '</td></tr>';
      }).join('');
      const rowHeaders = Array.from(new Set(payload.rows.flatMap(row => Object.keys(row || {}))));
      const dataRows = payload.rows.map(row => '<tr>' + rowHeaders.map(key => '<td>' + cell(row[key]) + '</td>').join('') + '</tr>').join('');
      $('content').innerHTML = `
        <div class="toolbar">
          <div><h2 style="margin:0">${esc(payload.table)}</h2><div class="muted">${payload.row_count} rows · primary key: ${esc(payload.primary_key || 'none')}</div></div>
          <div class="row"><input id="search" placeholder="Search rows" value="${esc(state.search)}"><button onclick="applySearch()">Search</button><button onclick="prevPage()">Prev</button><input id="limit" type="number" min="1" max="500" value="${state.limit}"><button onclick="nextPage(${payload.row_count})">Next</button></div>
        </div>
        <div class="grid">
          <div class="panel"><div class="panel-head"><strong>Schema</strong></div><table><thead><tr><th>Name</th><th>Type</th><th>Nullable</th><th>Default</th><th>Primary</th></tr></thead><tbody>${schemaRows || '<tr><td colspan="5" class="muted">No columns</td></tr>'}</tbody></table></div>
          <div class="panel"><div class="panel-head"><strong>Indexes</strong></div><div class="empty"><code>${esc(JSON.stringify(payload.indexes || [], null, 2))}</code></div></div>
        </div>
        <div class="panel"><div class="panel-head"><strong>Rows</strong><span class="muted">offset ${payload.offset}, limit ${payload.limit}</span></div><div style="overflow:auto"><table><thead><tr>${rowHeaders.map(h => '<th>' + esc(h) + '</th>').join('')}</tr></thead><tbody>${dataRows || '<tr><td class="muted">No rows</td></tr>'}</tbody></table></div></div>
      `;
      $('limit').onchange = (event) => { state.limit = Math.max(1, Math.min(500, Number(event.target.value || 50))); state.offset = 0; loadTable(); };
      $('search').onkeydown = (event) => { if (event.key === 'Enter') applySearch(); };
    }

    function applySearch() { state.search = $('search').value || ''; state.offset = 0; loadTable(); }
    function nextPage(total) { state.offset = Math.min(Math.max(0, total - 1), state.offset + state.limit); loadTable(); }
    function prevPage() { state.offset = Math.max(0, state.offset - state.limit); loadTable(); }
    $('exportBtn').onclick = async () => {
      const payload = await api('/api/export');
      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'pinx-studio-export.json';
      a.click();
      URL.revokeObjectURL(a.href);
    };
    $('refresh').onclick = () => { loadTables(); if (state.selected) loadTable(); };
    boot().catch(error => { $('content').innerHTML = '<div class="empty error">' + esc(error.message) + '</div>'; });
  </script>
</body>
</html>
HTML;
}
