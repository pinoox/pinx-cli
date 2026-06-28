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

    if ($path === '/api/health') {
        json_response(health_payload($root));
        return;
    }

    if ($path === '/api/migrations') {
        json_response(command_payload($root, 'migrate_status'));
        return;
    }

    if ($path === '/api/routes') {
        json_response(command_payload($root, 'routes'));
        return;
    }

    if ($path === '/api/logs') {
        json_response(logs_payload($root));
        return;
    }

    if ($path === '/api/recommendations') {
        json_response(recommendations_payload($root));
        return;
    }

    if ($path === '/api/cli/actions') {
        json_response(['actions' => cli_actions()]);
        return;
    }

    if ($path === '/api/cli/run') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['error' => true, 'message' => 'POST is required.'], 405);
            return;
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $action = is_array($payload) ? (string) ($payload['action'] ?? '') : '';
        json_response(run_cli_action($root, $action));
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
    $migrations = json_file(devdb_path($root) . '/meta/migrations.json', []);

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
        'stats' => [
            'rows' => array_sum(array_map(static fn (array $table): int => (int) ($table['rows'] ?? 0), $tables['tables'])),
            'migrations' => count($migrations),
            'php' => PHP_VERSION,
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

function cli_actions(): array
{
    return [
        ['id' => 'doctor', 'label' => 'Doctor', 'description' => 'Run project health checks', 'command' => 'doctor --json'],
        ['id' => 'migrate_status', 'label' => 'Migrations', 'description' => 'Show migration status', 'command' => 'migrate:status'],
        ['id' => 'routes', 'label' => 'Routes', 'description' => 'List route actions', 'command' => 'route:actions'],
        ['id' => 'devdb_status', 'label' => 'DevDB Status', 'description' => 'Inspect DevDB runtime status', 'command' => 'devdb:status --json'],
        ['id' => 'pinker_status', 'label' => 'Pinker', 'description' => 'Show Pinker cache status', 'command' => 'pinker:status'],
        ['id' => 'deps_status', 'label' => 'Dependencies', 'description' => 'Check dependency status', 'command' => 'deps:status'],
        ['id' => 'migrate', 'label' => 'Run Migrate', 'description' => 'Run app migrations', 'command' => 'migrate'],
    ];
}

function run_cli_action(string $root, string $action): array
{
    $commands = [
        'doctor' => ['doctor', '--json', '--no-ansi'],
        'migrate_status' => ['migrate:status', '--no-ansi'],
        'routes' => ['route:actions', '--no-ansi'],
        'devdb_status' => ['devdb:status', '--json', '--no-ansi'],
        'pinker_status' => ['pinker:status', '--no-ansi'],
        'deps_status' => ['deps:status', '--no-ansi'],
        'migrate' => ['migrate', '--no-ansi'],
    ];

    if (!isset($commands[$action])) {
        throw new RuntimeException('Unknown Studio action.');
    }

    $pinx = $root . '/bin/pinx';
    if (!is_file($pinx)) {
        throw new RuntimeException('Project-local bin/pinx was not found.');
    }

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(array_merge([PHP_BINARY, $pinx], $commands[$action]), $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Pinx command.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    $decoded = null;
    $trimmed = trim((string) $stdout);
    if ($trimmed !== '' && ($trimmed[0] ?? '') === '{') {
        $json = json_decode($trimmed, true);
        $decoded = is_array($json) ? $json : null;
    }

    return [
        'action' => $action,
        'exit_code' => $code,
        'ok' => $code === 0,
        'stdout' => (string) $stdout,
        'stderr' => (string) $stderr,
        'json' => $decoded,
    ];
}

function command_payload(string $root, string $action): array
{
    $result = run_cli_action($root, $action);

    return [
        'ok' => $result['ok'],
        'exit_code' => $result['exit_code'],
        'output' => trim((string) $result['stdout']),
        'error' => trim((string) $result['stderr']),
        'json' => $result['json'],
    ];
}

function health_payload(string $root): array
{
    $result = run_cli_action($root, 'doctor');
    $json = is_array($result['json']) ? $result['json'] : [];
    $summary = is_array($json['summary'] ?? null) ? $json['summary'] : [];
    $checks = is_array($json['checks'] ?? null) ? $json['checks'] : [];
    $blocking = array_values(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'fail'));
    $warnings = array_values(array_filter($checks, static fn (array $check): bool => ($check['status'] ?? '') === 'warn'));

    return [
        'ok' => (bool) ($json['healthy'] ?? $result['ok']),
        'score' => (int) ($json['score'] ?? 0),
        'summary' => $summary,
        'blocking' => array_slice($blocking, 0, 8),
        'warnings' => array_slice($warnings, 0, 8),
        'raw' => $json,
    ];
}

function logs_payload(string $root): array
{
    $logDir = $root . '/storage/logs';
    $files = [];

    if (is_dir($logDir)) {
        foreach (glob($logDir . '/*.log') ?: [] as $file) {
            $files[] = [
                'name' => basename($file),
                'size' => filesize($file) ?: 0,
                'modified_at' => date(DATE_ATOM, filemtime($file) ?: time()),
                'tail' => tail_file($file, 80),
            ];
        }
    }

    usort($files, static fn (array $a, array $b): int => strcmp((string) $b['modified_at'], (string) $a['modified_at']));

    return [
        'files' => $files,
    ];
}

function tail_file(string $file, int $lines): string
{
    $content = file_get_contents($file);
    if (!is_string($content) || $content === '') {
        return '';
    }

    $parts = preg_split('/\R/', $content) ?: [];

    return implode("\n", array_slice($parts, -$lines));
}

function recommendations_payload(string $root): array
{
    $summary = summary_payload($root);
    $tables = tables_payload($root)['tables'] ?? [];
    $health = health_payload($root);
    $items = [];

    if ((int) ($summary['database']['table_count'] ?? 0) === 0) {
        $items[] = [
            'tone' => 'info',
            'title' => 'Run migrations',
            'body' => 'No database tables were found. Run migrations to build the schema.',
            'action' => 'migrate',
        ];
    }

    if (!$health['ok']) {
        $items[] = [
            'tone' => 'danger',
            'title' => 'Fix blocking health checks',
            'body' => count($health['blocking']) . ' blocking issue(s) need attention before the app is fully ready.',
            'action' => 'health',
        ];
    } elseif (($health['summary']['warn'] ?? 0) > 0) {
        $items[] = [
            'tone' => 'warn',
            'title' => 'Review warnings',
            'body' => (string) ($health['summary']['warn'] ?? 0) . ' warning(s) were found. They are not blocking local development.',
            'action' => 'health',
        ];
    }

    foreach ($tables as $table) {
        if ((int) ($table['rows'] ?? 0) > 1000) {
            $items[] = [
                'tone' => 'info',
                'title' => 'Large table: ' . $table['name'],
                'body' => 'Use search and pagination when inspecting this table.',
                'action' => 'database',
            ];
            break;
        }
    }

    if ($items === []) {
        $items[] = [
            'tone' => 'success',
            'title' => 'Ready for development',
            'body' => 'Your app looks healthy. Studio will keep monitoring schema, rows, logs, and routes.',
            'action' => 'dashboard',
        ];
    }

    return [
        'items' => $items,
    ];
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
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            studio: {
              bg: '#070b14',
              panel: '#0f172a',
              soft: '#111c31',
              line: '#233047',
              mint: '#2dd4bf',
              blue: '#60a5fa',
              rose: '#fb7185'
            }
          },
          boxShadow: { glow: '0 0 40px rgba(45, 212, 191, .16)' }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen bg-studio-bg text-slate-100 antialiased">
  <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_20%_10%,rgba(45,212,191,.18),transparent_28%),radial-gradient(circle_at_80%_0%,rgba(96,165,250,.14),transparent_30%)]"></div>
  <div class="grid min-h-screen grid-cols-[280px_1fr] max-lg:grid-cols-1">
    <aside class="border-r border-white/10 bg-black/30 p-4 backdrop-blur-xl max-lg:border-b max-lg:border-r-0">
      <div class="mb-6 flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-300 to-blue-400 font-black text-slate-950 shadow-glow">P</div>
        <div>
          <div class="text-lg font-bold">Pinx Studio</div>
          <div class="text-xs text-slate-400">Development control center</div>
        </div>
      </div>
      <nav class="space-y-2">
        <button data-view="dashboard" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Overview</button>
        <button data-view="database" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Database Explorer</button>
        <button data-view="health" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Health Center</button>
        <button data-view="migrations" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Migrations</button>
        <button data-view="routes" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Routes</button>
        <button data-view="logs" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Logs</button>
        <button data-view="export" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-200 hover:bg-white/10">Snapshots</button>
      </nav>
      <div class="mt-6 rounded-2xl border border-white/10 bg-white/[.04] p-4">
        <div class="text-xs uppercase tracking-wider text-slate-500">Active app</div>
        <div id="appName" class="mt-1 font-semibold">Loading</div>
        <div id="package" class="mt-1 break-all text-xs text-slate-400"></div>
      </div>
      <div class="mt-3 rounded-2xl border border-teal-300/20 bg-teal-300/10 p-4">
        <div class="text-xs uppercase tracking-wider text-teal-200/80">Connection</div>
        <div id="engine" class="mt-1 text-sm font-semibold text-teal-200">Database</div>
      </div>
    </aside>
    <main class="min-w-0 p-6">
      <header class="mb-6 flex items-center justify-between gap-4 max-md:flex-col max-md:items-start">
        <div>
          <h1 id="viewTitle" class="text-3xl font-bold tracking-tight">Dashboard</h1>
          <p class="mt-1 text-sm text-slate-400">A smart local dashboard for schema, health, routes, logs, and development flow.</p>
        </div>
        <div class="flex gap-2">
          <button id="refresh" class="rounded-xl border border-white/10 bg-white/10 px-4 py-2 text-sm font-semibold hover:bg-white/15">Refresh</button>
          <button id="exportBtn" class="rounded-xl bg-teal-300 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-teal-200">Export JSON</button>
        </div>
      </header>
      <section id="dashboardView" class="view space-y-6">
        <div id="overview" class="grid grid-cols-4 gap-4 max-xl:grid-cols-2 max-sm:grid-cols-1"></div>
        <div id="recommendations" class="grid grid-cols-3 gap-4 max-xl:grid-cols-2 max-md:grid-cols-1"></div>
        <div class="grid grid-cols-[minmax(220px,320px)_1fr] gap-4 max-xl:grid-cols-1">
          <div class="rounded-3xl border border-white/10 bg-white/[.04] p-4">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="font-bold">Tables</h2>
              <span id="tableCount" class="text-xs text-slate-400"></span>
            </div>
            <div id="tables" class="space-y-2"></div>
          </div>
          <div id="content" class="rounded-3xl border border-white/10 bg-white/[.04] p-6 text-slate-400">Select a table to inspect schema and rows.</div>
        </div>
      </section>
      <section id="databaseView" class="view hidden">
        <div class="grid grid-cols-[minmax(220px,320px)_1fr] gap-4 max-xl:grid-cols-1">
          <div class="rounded-3xl border border-white/10 bg-white/[.04] p-4"><div id="tablesDb" class="space-y-2"></div></div>
          <div id="databaseContent" class="rounded-3xl border border-white/10 bg-white/[.04] p-6 text-slate-400">Select a table.</div>
        </div>
      </section>
      <section id="healthView" class="view hidden space-y-4">
        <div id="healthSummary" class="grid grid-cols-4 gap-4 max-xl:grid-cols-2 max-sm:grid-cols-1"></div>
        <div id="healthContent" class="grid grid-cols-2 gap-4 max-xl:grid-cols-1"></div>
      </section>
      <section id="migrationsView" class="view hidden">
        <pre id="migrationsOutput" class="min-h-96 overflow-auto rounded-3xl border border-white/10 bg-black/40 p-4 text-xs leading-relaxed text-slate-200"></pre>
      </section>
      <section id="routesView" class="view hidden">
        <pre id="routesOutput" class="min-h-96 overflow-auto rounded-3xl border border-white/10 bg-black/40 p-4 text-xs leading-relaxed text-slate-200"></pre>
      </section>
      <section id="logsView" class="view hidden">
        <div id="logsContent" class="grid gap-4"></div>
      </section>
      <section id="exportView" class="view hidden">
        <pre id="exportOutput" class="min-h-96 overflow-auto rounded-3xl border border-white/10 bg-black/40 p-4 text-xs leading-relaxed text-slate-200"></pre>
      </section>
    </main>
  </div>
  <script>
    const state = { selected: null, limit: 50, offset: 0, search: '', view: 'dashboard', tables: [] };
    const $ = (id) => document.getElementById(id);
    const base = location.pathname.startsWith('/~studio') ? '/~studio' : '';
    const api = (url) => fetch(base + url, { cache: 'no-store' }).then(r => r.json());
    const post = (url, body) => fetch(base + url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body || {}) }).then(r => r.json());
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    const cell = (value) => typeof value === 'object' && value !== null ? '<code>' + esc(JSON.stringify(value, null, 2)) + '</code>' : esc(value);

    async function boot() {
      const summary = await api('/api/summary');
      $('appName').textContent = summary.app.name;
      $('package').textContent = summary.app.package;
      $('engine').textContent = summary.database.connection + ' / ' + summary.database.engine;
      $('overview').innerHTML = `
        ${metric('Package', summary.app.package, summary.app.theme)}
        ${metric('Connection', summary.database.connection, summary.database.engine)}
        ${metric('Tables', summary.database.table_count, 'available')}
        ${metric('Rows', summary.stats.rows, 'loaded')}
      `;
      await loadTables();
      await loadRecommendations();
      await loadHealth();
      await loadMigrations();
      await loadRoutes();
      await loadLogs();
    }

    function metric(label, value, note) {
      return `<div class="rounded-3xl border border-white/10 bg-white/[.05] p-5 shadow-glow"><div class="text-xs uppercase tracking-wider text-slate-500">${esc(label)}</div><div class="mt-2 truncate text-2xl font-bold">${esc(value)}</div><div class="mt-1 text-xs text-slate-400">${esc(note)}</div></div>`;
    }

    async function loadTables() {
      const payload = await api('/api/tables');
      state.tables = payload.tables || [];
      $('tableCount').textContent = state.tables.length + ' tables';
      renderTableList($('tables'));
      renderTableList($('tablesDb'));
    }

    function renderTableList(wrap) {
      wrap.innerHTML = '';
      if (!state.tables.length) {
        wrap.innerHTML = '<div class="rounded-2xl border border-dashed border-white/10 p-5 text-center text-sm text-slate-500">No tables yet. Run pinx migrate.</div>';
        return;
      }
      state.tables.forEach(table => {
        const btn = document.createElement('button');
        btn.className = 'w-full rounded-2xl border px-3 py-3 text-left transition ' + (table.name === state.selected ? 'border-teal-300/70 bg-teal-300/10' : 'border-white/10 bg-white/[.03] hover:bg-white/[.07]');
        btn.innerHTML = '<div class="flex items-center justify-between gap-3"><strong class="truncate">' + esc(table.name) + '</strong><span class="rounded-full bg-white/10 px-2 py-0.5 text-xs text-slate-300">' + table.rows + ' rows</span></div><div class="mt-1 text-xs text-slate-500">' + table.columns + ' columns · pk ' + esc(table.primary_key || 'none') + '</div>';
        btn.onclick = () => { state.selected = table.name; state.offset = 0; state.search = ''; loadTable(); renderTableList($('tables')); renderTableList($('tablesDb')); };
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
      const html = `
        <div class="mb-4 flex items-center justify-between gap-3 max-lg:flex-col max-lg:items-start">
          <div><h2 class="text-2xl font-bold">${esc(payload.table)}</h2><div class="text-sm text-slate-400">${payload.row_count} rows · primary key: ${esc(payload.primary_key || 'none')}</div></div>
          <div class="flex flex-wrap gap-2"><input id="search" class="h-10 rounded-xl border border-white/10 bg-black/30 px-3 text-sm text-slate-100 outline-none focus:border-teal-300" placeholder="Search rows" value="${esc(state.search)}"><button class="rounded-xl bg-teal-300 px-3 py-2 text-sm font-bold text-slate-950" onclick="applySearch()">Search</button><button class="rounded-xl border border-white/10 px-3 py-2 text-sm" onclick="prevPage()">Prev</button><input id="limit" class="h-10 w-20 rounded-xl border border-white/10 bg-black/30 px-3 text-sm" type="number" min="1" max="500" value="${state.limit}"><button class="rounded-xl border border-white/10 px-3 py-2 text-sm" onclick="nextPage(${payload.row_count})">Next</button></div>
        </div>
        <div class="mb-4 grid grid-cols-2 gap-4 max-xl:grid-cols-1">
          <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/25"><div class="border-b border-white/10 px-4 py-3 font-bold">Schema</div><div class="overflow-auto"><table class="w-full text-left text-sm"><thead class="text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Name</th><th>Type</th><th>Nullable</th><th>Default</th><th>Primary</th></tr></thead><tbody class="divide-y divide-white/10">${schemaRows || '<tr><td colspan="5" class="px-4 py-6 text-slate-500">No columns</td></tr>'}</tbody></table></div></div>
          <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/25"><div class="border-b border-white/10 px-4 py-3 font-bold">Indexes</div><pre class="max-h-72 overflow-auto p-4 text-xs text-slate-300">${esc(JSON.stringify(payload.indexes || [], null, 2))}</pre></div>
        </div>
        <div class="overflow-hidden rounded-3xl border border-white/10 bg-black/25"><div class="flex items-center justify-between border-b border-white/10 px-4 py-3"><strong>Rows</strong><span class="text-xs text-slate-500">offset ${payload.offset}, limit ${payload.limit}</span></div><div class="overflow-auto"><table class="w-full text-left text-sm"><thead class="text-xs uppercase text-slate-500"><tr>${rowHeaders.map(h => '<th class="px-4 py-3">' + esc(h) + '</th>').join('')}</tr></thead><tbody class="divide-y divide-white/10">${dataRows || '<tr><td class="px-4 py-6 text-slate-500">No rows</td></tr>'}</tbody></table></div></div>
      `;
      $('content').innerHTML = html;
      $('databaseContent').innerHTML = html;
      $('limit').onchange = (event) => { state.limit = Math.max(1, Math.min(500, Number(event.target.value || 50))); state.offset = 0; loadTable(); };
      $('search').onkeydown = (event) => { if (event.key === 'Enter') applySearch(); };
    }

    function applySearch() { state.search = $('search').value || ''; state.offset = 0; loadTable(); }
    function nextPage(total) { state.offset = Math.min(Math.max(0, total - 1), state.offset + state.limit); loadTable(); }
    function prevPage() { state.offset = Math.max(0, state.offset - state.limit); loadTable(); }
    $('exportBtn').onclick = async () => {
      const payload = await api('/api/export');
      $('exportOutput').textContent = JSON.stringify(payload, null, 2);
      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'pinx-studio-export.json';
      a.click();
      URL.revokeObjectURL(a.href);
    };

    async function loadRecommendations() {
      const payload = await api('/api/recommendations');
      $('recommendations').innerHTML = (payload.items || []).map(item => {
        const colors = item.tone === 'danger' ? 'border-rose-400/30 bg-rose-400/10 text-rose-100' : item.tone === 'warn' ? 'border-amber-300/30 bg-amber-300/10 text-amber-100' : item.tone === 'success' ? 'border-emerald-300/30 bg-emerald-300/10 text-emerald-100' : 'border-blue-300/30 bg-blue-300/10 text-blue-100';
        return `<button class="rounded-3xl border p-4 text-left transition hover:scale-[1.01] ${colors}" onclick="switchView('${esc(item.action || 'dashboard')}')"><div class="font-bold">${esc(item.title)}</div><div class="mt-1 text-sm opacity-80">${esc(item.body)}</div></button>`;
      }).join('');
    }

    async function loadHealth() {
      const payload = await api('/api/health');
      $('healthSummary').innerHTML = `
        ${metric('Score', payload.score || 0, payload.ok ? 'healthy' : 'needs attention')}
        ${metric('Pass', payload.summary?.pass || 0, 'checks')}
        ${metric('Warnings', payload.summary?.warn || 0, 'review')}
        ${metric('Failures', payload.summary?.fail || 0, 'blocking')}
      `;
      $('healthContent').innerHTML = healthPanel('Blocking issues', payload.blocking || [], 'rose') + healthPanel('Warnings', payload.warnings || [], 'amber');
    }

    function healthPanel(title, items, tone) {
      const color = tone === 'rose' ? 'border-rose-400/20 bg-rose-400/10' : 'border-amber-300/20 bg-amber-300/10';
      const rows = items.length ? items.map(item => `<div class="rounded-2xl border border-white/10 bg-black/20 p-3"><div class="font-semibold">${esc(item.label || item.id)}</div><div class="mt-1 text-sm text-slate-400">${esc(item.detail || '')}</div><div class="mt-1 text-xs text-slate-500">${esc(item.hint || '')}</div></div>`).join('') : '<div class="rounded-2xl border border-white/10 bg-black/20 p-5 text-sm text-slate-400">Nothing to show.</div>';
      return `<div class="rounded-3xl border ${color} p-4"><h2 class="mb-3 font-bold">${esc(title)}</h2><div class="space-y-3">${rows}</div></div>`;
    }

    async function loadMigrations() {
      const payload = await api('/api/migrations');
      $('migrationsOutput').textContent = payload.output || payload.error || 'No migration output.';
    }

    async function loadRoutes() {
      const payload = await api('/api/routes');
      $('routesOutput').textContent = payload.output || payload.error || 'No route output.';
    }

    async function loadLogs() {
      const payload = await api('/api/logs');
      $('logsContent').innerHTML = (payload.files || []).length ? payload.files.map(file => `
        <article class="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
          <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
            <div><strong>${esc(file.name)}</strong><div class="text-xs text-slate-500">${esc(file.modified_at)} · ${esc(file.size)} bytes</div></div>
          </div>
          <pre class="max-h-96 overflow-auto bg-black/35 p-4 text-xs leading-relaxed text-slate-300">${esc(file.tail || '')}</pre>
        </article>
      `).join('') : '<div class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-slate-500">No log files yet.</div>';
    }

    function switchView(view) {
      state.view = view;
      document.querySelectorAll('.view').forEach(el => el.classList.add('hidden'));
      $(view + 'View').classList.remove('hidden');
      $('viewTitle').textContent = view.charAt(0).toUpperCase() + view.slice(1);
      document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.toggle('bg-white/10', btn.dataset.view === view));
    }
    document.querySelectorAll('.nav-btn').forEach(btn => btn.onclick = () => switchView(btn.dataset.view));
    $('refresh').onclick = () => { loadTables(); if (state.selected) loadTable(); };
    boot().then(() => {
      const initial = (location.hash || '#dashboard').slice(1);
      switchView(['dashboard', 'database', 'health', 'migrations', 'routes', 'logs', 'export'].includes(initial) ? initial : 'dashboard');
    }).catch(error => { $('content').innerHTML = '<div class="rounded-3xl border border-rose-400/20 bg-rose-400/10 p-6 text-rose-200">' + esc(error.message) + '</div>'; });
  </script>
</body>
</html>
HTML;
}
