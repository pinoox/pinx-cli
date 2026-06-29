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
    echo 'Pinx Inspector is local-only.';
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
        json_response(migrations_payload($root));
        return;
    }

    if ($path === '/api/routes') {
        json_response(routes_payload($root));
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

    if ($path === '/api/action/run') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['error' => true, 'message' => 'POST is required.'], 405);
            return;
        }

        $payload = json_decode((string) file_get_contents('php://input'), true);
        $action = is_array($payload) ? (string) ($payload['action'] ?? '') : '';
        json_response(studio_action_payload($root, $action));
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
        throw new RuntimeException('Unknown Inspector action.');
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

function studio_action_payload(string $root, string $action): array
{
    $allowed = ['doctor', 'migrate', 'migrate_status', 'routes', 'devdb_status', 'deps_status', 'pinker_status'];
    if (!in_array($action, $allowed, true)) {
        throw new RuntimeException('Unknown Inspector action.');
    }

    $result = run_cli_action($root, $action);
    $stdout = trim((string) ($result['stdout'] ?? ''));
    $stderr = trim((string) ($result['stderr'] ?? ''));

    return [
        'ok' => (bool) ($result['ok'] ?? false),
        'action' => $action,
        'exit_code' => (int) ($result['exit_code'] ?? 1),
        'title' => studio_action_title($action),
        'message' => studio_action_message($action, (bool) ($result['ok'] ?? false), $stdout, $stderr),
        'cards' => studio_action_cards($action, $result),
        'raw' => [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'json' => $result['json'] ?? null,
        ],
    ];
}

function studio_action_title(string $action): string
{
    return match ($action) {
        'doctor' => 'Health check finished',
        'migrate' => 'Migration run finished',
        'migrate_status' => 'Migration status refreshed',
        'routes' => 'Route map refreshed',
        'devdb_status' => 'DevDB status refreshed',
        'deps_status' => 'Dependency check finished',
        'pinker_status' => 'Pinker cache status refreshed',
        default => 'Inspector action finished',
    };
}

function studio_action_message(string $action, bool $ok, string $stdout, string $stderr): string
{
    if (!$ok) {
        return $stderr !== '' ? $stderr : 'The action could not finish successfully. Open the details panel for the command output.';
    }

    return match ($action) {
        'doctor' => 'Your project health checks were refreshed.',
        'migrate' => 'Migrations were executed. Schema, tables, and Inspector views are ready to refresh.',
        'routes' => 'Routes were scanned and grouped for Inspector.',
        'migrate_status' => 'Migration timeline was refreshed.',
        default => $stdout !== '' ? first_non_empty_line($stdout) : 'Action finished successfully.',
    };
}

function studio_action_cards(string $action, array $result): array
{
    $json = is_array($result['json'] ?? null) ? $result['json'] : [];
    if ($action === 'doctor' && $json !== []) {
        $summary = is_array($json['summary'] ?? null) ? $json['summary'] : [];
        return [
            ['label' => 'Score', 'value' => (string) ($json['score'] ?? 0), 'tone' => !empty($json['healthy']) ? 'success' : 'warn'],
            ['label' => 'Passing', 'value' => (string) ($summary['pass'] ?? 0), 'tone' => 'success'],
            ['label' => 'Warnings', 'value' => (string) ($summary['warn'] ?? 0), 'tone' => 'warn'],
            ['label' => 'Failures', 'value' => (string) ($summary['fail'] ?? 0), 'tone' => (($summary['fail'] ?? 0) > 0) ? 'danger' : 'success'],
        ];
    }

    $lines = studio_output_lines((string) ($result['stdout'] ?? ''));

    return [
        ['label' => 'Result', 'value' => !empty($result['ok']) ? 'Ready' : 'Needs attention', 'tone' => !empty($result['ok']) ? 'success' : 'danger'],
        ['label' => 'Exit code', 'value' => (string) ($result['exit_code'] ?? 1), 'tone' => !empty($result['ok']) ? 'success' : 'danger'],
        ['label' => 'Messages', 'value' => (string) count($lines), 'tone' => 'info'],
    ];
}

function first_non_empty_line(string $text): string
{
    foreach (studio_output_lines($text) as $line) {
        return $line;
    }

    return '';
}

function studio_output_lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), static fn (string $line): bool => $line !== ''));
}

function migrations_payload(string $root): array
{
    $status = command_payload($root, 'migrate_status');
    $records = json_file(devdb_path($root) . '/meta/migrations.json', []);
    $items = [];

    foreach ($records as $key => $record) {
        if (!is_array($record)) {
            continue;
        }

        $items[] = [
            'name' => (string) ($record['migration'] ?? $record['name'] ?? $key),
            'package' => (string) ($record['package'] ?? 'app'),
            'batch' => (int) ($record['batch'] ?? 0),
            'status' => 'ran',
            'ran_at' => (string) ($record['created_at'] ?? $record['ran_at'] ?? ''),
        ];
    }

    if ($items === []) {
        foreach (studio_output_lines((string) ($status['output'] ?? '')) as $line) {
            $items[] = [
                'name' => preg_replace('/\s+/', ' ', $line) ?: $line,
                'package' => 'app',
                'batch' => null,
                'status' => migration_line_status($line),
                'ran_at' => '',
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['ran_at'] ?? ''), (string) ($a['ran_at'] ?? ''));
    });

    $ran = count(array_filter($items, static fn (array $item): bool => ($item['status'] ?? '') === 'ran'));

    return [
        'ok' => (bool) ($status['ok'] ?? false),
        'summary' => [
            'total' => count($items),
            'ran' => $ran,
            'pending' => max(0, count($items) - $ran),
        ],
        'items' => $items,
        'message' => $items === [] ? 'No migration information was found yet.' : 'Migration state is ready.',
        'raw' => $status,
    ];
}

function migration_line_status(string $line): string
{
    if (preg_match('/\b(pending|down|not\s+run)\b/i', $line) === 1) {
        return 'pending';
    }

    if (preg_match('/\b(ran|up|yes|done|migrated)\b/i', $line) === 1) {
        return 'ran';
    }

    return 'unknown';
}

function routes_payload(string $root): array
{
    $config = app_config($root);
    $routeFiles = $config['router']['routes'] ?? ['routes/web.php', 'routes/actions.php'];
    $routeFiles = is_array($routeFiles) ? $routeFiles : [];
    $files = [];
    $routes = [];

    foreach ($routeFiles as $routeFile) {
        $relative = trim(str_replace('\\', '/', (string) $routeFile), '/');
        if ($relative === '') {
            continue;
        }

        $path = resolve_project_path($root, $relative);
        $exists = is_file($path);
        $fileRoutes = $exists ? parse_route_file($path, $relative) : [];
        $files[] = [
            'path' => $relative,
            'exists' => $exists,
            'routes' => count($fileRoutes),
            'modified_at' => $exists ? date(DATE_ATOM, filemtime($path) ?: time()) : null,
        ];
        $routes = array_merge($routes, $fileRoutes);
    }

    $command = command_payload($root, 'routes');
    if ($routes === []) {
        foreach (studio_output_lines((string) ($command['output'] ?? '')) as $line) {
            $routes[] = [
                'method' => 'ANY',
                'uri' => $line,
                'name' => '',
                'file' => 'route:actions',
                'line' => null,
            ];
        }
    }

    return [
        'ok' => (bool) ($command['ok'] ?? true),
        'summary' => [
            'files' => count($files),
            'available_files' => count(array_filter($files, static fn (array $file): bool => (bool) $file['exists'])),
            'routes' => count($routes),
        ],
        'files' => $files,
        'routes' => $routes,
        'raw' => $command,
    ];
}

function parse_route_file(string $path, string $relative): array
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        return [];
    }

    $routes = [];
    $pattern = '/\b(get|post|put|patch|delete|options|any|match)\s*\(\s*[\'"]([^\'"]*)[\'"]/i';
    if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== false) {
        foreach ($matches[1] as $index => $methodMatch) {
            $offset = (int) ($methodMatch[1] ?? 0);
            $line = substr_count(substr($content, 0, $offset), "\n") + 1;
            $snippet = substr($content, $offset, 420);
            $name = '';
            if (preg_match('/->\s*(?:name|actionName)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $snippet, $nameMatch) === 1) {
                $name = (string) $nameMatch[1];
            }

            $routes[] = [
                'method' => strtoupper((string) $methodMatch[0]),
                'uri' => (string) ($matches[2][$index][0] ?? ''),
                'name' => $name,
                'file' => $relative,
                'line' => $line,
            ];
        }
    }

    return $routes;
}

function logs_payload(string $root): array
{
    $logDir = $root . '/storage/logs';
    $files = [];
    $totals = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];

    if (is_dir($logDir)) {
        foreach (glob($logDir . '/*.log') ?: [] as $file) {
            $tail = tail_file($file, 120);
            $entries = parse_log_entries($tail);
            $counts = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];
            foreach ($entries as $entry) {
                $level = (string) ($entry['level'] ?? 'info');
                $counts[$level] = ($counts[$level] ?? 0) + 1;
                $totals[$level] = ($totals[$level] ?? 0) + 1;
            }

            $files[] = [
                'name' => basename($file),
                'size' => filesize($file) ?: 0,
                'modified_at' => date(DATE_ATOM, filemtime($file) ?: time()),
                'tail' => $tail,
                'entries' => $entries,
                'counts' => $counts,
            ];
        }
    }

    usort($files, static fn (array $a, array $b): int => strcmp((string) $b['modified_at'], (string) $a['modified_at']));

    return [
        'counts' => $totals,
        'files' => $files,
    ];
}

function parse_log_entries(string $tail): array
{
    $entries = [];
    foreach (studio_output_lines($tail) as $line) {
        $entries[] = [
            'level' => log_level($line),
            'time' => log_time($line),
            'message' => preg_replace('/\s+/', ' ', $line) ?: $line,
        ];
    }

    return array_slice($entries, -80);
}

function log_level(string $line): string
{
    if (preg_match('/\b(error|exception|critical|alert|emergency|fatal)\b/i', $line) === 1) {
        return 'error';
    }

    if (preg_match('/\b(warn|warning|deprecated)\b/i', $line) === 1) {
        return 'warning';
    }

    if (preg_match('/\b(debug|trace)\b/i', $line) === 1) {
        return 'debug';
    }

    return 'info';
}

function log_time(string $line): string
{
    if (preg_match('/^\[?([0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9:.+-]+)/', $line, $match) === 1) {
        return (string) $match[1];
    }

    return '';
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
            'body' => 'Your app looks healthy. Inspector will keep monitoring schema, rows, logs, and routes.',
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
  <title>Pinx Inspector</title>
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
<body class="min-h-screen overflow-x-hidden bg-[#050914] text-slate-100 antialiased">
  <div class="fixed inset-0 -z-10 bg-[radial-gradient(circle_at_18%_8%,rgba(139,92,246,.17),transparent_28%),radial-gradient(circle_at_78%_2%,rgba(14,165,233,.10),transparent_30%),linear-gradient(180deg,#050914,#07101c_48%,#050914)]"></div>
  <div class="fixed right-0 top-0 -z-10 h-full w-[22vw] opacity-50 [background-image:radial-gradient(rgba(124,58,237,.55)_1px,transparent_1px)] [background-size:22px_22px] max-xl:hidden"></div>
  <div class="grid min-h-screen grid-cols-[268px_1fr] max-lg:grid-cols-1">
    <aside class="border-r border-white/10 bg-[#07101b]/90 p-4 shadow-[18px_0_70px_rgba(0,0,0,.28)] backdrop-blur-xl max-lg:border-b max-lg:border-r-0">
      <div class="mb-6 flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-violet-300/30 bg-violet-500/15 text-violet-200 shadow-[0_0_34px_rgba(139,92,246,.22)]">
          <svg viewBox="0 0 48 48" class="h-7 w-7" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" d="M24 5 40 14v20l-16 9-16-9V14L24 5Z"/><path fill="none" stroke="currentColor" stroke-width="2.4" d="m8 14 16 9 16-9M24 23v20M16 18.5l16-9"/></svg>
        </div>
        <div>
          <div class="flex items-center gap-2"><div class="text-lg font-black tracking-tight">Pinx Inspector</div><span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] text-slate-300">v2.1.0</span></div>
          <div class="text-xs text-slate-400">Runtime and database monitor</div>
        </div>
      </div>
      <nav class="space-y-5">
        <div class="rounded-2xl border border-white/8 bg-white/[.035] p-3">
          <div class="text-xs text-slate-400">Application</div>
          <div id="sideAppName" class="mt-1 truncate text-sm font-bold">Loading</div>
          <div class="mt-2 flex items-center gap-2 text-xs text-emerald-300"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Running</div>
        </div>
        <div class="space-y-1">
          <div class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Overview</div>
          <button data-view="dashboard" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Dashboard</button>
        </div>
        <div class="space-y-1">
          <div class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Database</div>
          <button data-view="database" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Connections</button>
          <button data-view="database" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Tables</button>
          <button data-view="migrations" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Migrations</button>
        </div>
        <div class="space-y-1">
          <div class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Application</div>
          <button data-view="routes" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Routes</button>
          <button data-view="health" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Environment</button>
          <button data-view="logs" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Logs</button>
        </div>
        <div class="space-y-1">
          <div class="px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Development</div>
          <button data-view="export" class="nav-btn w-full rounded-xl px-3 py-2 text-left text-sm font-medium text-slate-300 hover:bg-white/10">Snapshots</button>
        </div>
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
    <main class="min-w-0 p-4 pb-16">
      <header class="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-[#0a1320]/80 px-4 py-3 shadow-glow backdrop-blur-xl max-md:flex-col max-md:items-start">
        <div>
          <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="flex items-center gap-2 text-slate-200"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_18px_rgba(52,211,153,.65)]"></span><span id="runtimeUrl">http://localhost:8000</span></span>
            <span class="h-5 w-px bg-white/10"></span>
            <span id="phpVersion" class="text-slate-400">PHP</span>
            <span class="h-5 w-px bg-white/10"></span>
            <span class="text-slate-400">Pinx v2.1.0</span>
          </div>
          <h1 id="viewTitle" class="sr-only">Dashboard</h1>
        </div>
        <div class="flex gap-2">
          <button id="refresh" title="Refresh" class="grid h-9 w-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10">↻</button>
          <button onclick="runStudioAction('doctor')" title="Run Doctor" class="grid h-9 w-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10">⌁</button>
          <button id="exportBtn" title="Export JSON" class="grid h-9 w-9 place-items-center rounded-xl border border-white/10 bg-white/5 text-slate-300 hover:bg-white/10">⇩</button>
        </div>
      </header>
      <section id="dashboardView" class="view space-y-6">
        <div id="overview" class="grid grid-cols-5 gap-3 max-2xl:grid-cols-3 max-xl:grid-cols-2 max-sm:grid-cols-1"></div>
        <div class="grid grid-cols-2 gap-3 max-xl:grid-cols-1">
          <section class="overflow-hidden rounded-2xl border border-white/10 bg-[#0a1320]/80">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
              <h2 class="font-bold">Recent Requests</h2>
              <button onclick="switchView('routes')" class="text-sm font-semibold text-violet-300">View all</button>
            </div>
            <div id="recentRequests" class="divide-y divide-white/10"></div>
          </section>
          <section class="overflow-hidden rounded-2xl border border-white/10 bg-[#0a1320]/80">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
              <h2 class="font-bold">Recent Logs</h2>
              <button onclick="switchView('logs')" class="text-sm font-semibold text-violet-300">View all</button>
            </div>
            <div id="recentLogs" class="divide-y divide-white/10"></div>
          </section>
        </div>
        <div class="grid grid-cols-3 gap-4 max-xl:grid-cols-1">
          <button onclick="runStudioAction('doctor')" class="group relative overflow-hidden rounded-2xl border border-emerald-300/25 bg-[#0a1720]/95 p-4 text-left shadow-[0_18px_60px_rgba(0,0,0,.22)] transition hover:-translate-y-0.5 hover:border-emerald-300/55 hover:bg-[#0d2130] focus:outline-none focus:ring-2 focus:ring-emerald-300/50">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-emerald-300/70 to-transparent"></div>
            <div class="flex items-center gap-4">
              <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-emerald-300/20 bg-emerald-400/12 text-emerald-300"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-5"/><path d="M12 3a9 9 0 1 0 9 9"/></svg></span>
              <span class="min-w-0 flex-1"><span class="block text-[11px] font-bold uppercase tracking-wide text-emerald-300/80">Smart check</span><span class="mt-1 block text-lg font-black text-white">Run Doctor</span><span class="mt-1 block text-sm text-slate-400">Health checks rendered as Inspector cards.</span></span>
              <span class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-emerald-200 transition group-hover:bg-emerald-300 group-hover:text-slate-950">Run</span>
            </div>
          </button>
          <button onclick="runStudioAction('migrate')" class="group relative overflow-hidden rounded-2xl border border-sky-300/25 bg-[#0b1628]/95 p-4 text-left shadow-[0_18px_60px_rgba(0,0,0,.22)] transition hover:-translate-y-0.5 hover:border-sky-300/55 hover:bg-[#0d1d35] focus:outline-none focus:ring-2 focus:ring-sky-300/50">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-sky-300/70 to-transparent"></div>
            <div class="flex items-center gap-4">
              <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-sky-300/20 bg-sky-400/12 text-sky-300"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7c0 2 4 3 8 3s8-1 8-3-4-3-8-3-8 1-8 3Z"/><path d="M4 7v10c0 2 4 3 8 3s8-1 8-3V7"/><path d="M4 12c0 2 4 3 8 3s8-1 8-3"/></svg></span>
              <span class="min-w-0 flex-1"><span class="block text-[11px] font-bold uppercase tracking-wide text-sky-300/80">Schema workflow</span><span class="mt-1 block text-lg font-black text-white">Run Migrations</span><span class="mt-1 block text-sm text-slate-400">Build or refresh database structure.</span></span>
              <span class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-sky-200 transition group-hover:bg-sky-300 group-hover:text-slate-950">Run</span>
            </div>
          </button>
          <button onclick="switchView('routes')" class="group relative overflow-hidden rounded-2xl border border-violet-300/25 bg-[#111226]/95 p-4 text-left shadow-[0_18px_60px_rgba(0,0,0,.22)] transition hover:-translate-y-0.5 hover:border-violet-300/55 hover:bg-[#171536] focus:outline-none focus:ring-2 focus:ring-violet-300/50">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-violet-300/70 to-transparent"></div>
            <div class="flex items-center gap-4">
              <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-violet-300/20 bg-violet-400/12 text-violet-300"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12"/><path d="M6 12h12"/><path d="M6 17h12"/><path d="M3 7h.01M3 12h.01M3 17h.01"/></svg></span>
              <span class="min-w-0 flex-1"><span class="block text-[11px] font-bold uppercase tracking-wide text-violet-300/80">App map</span><span class="mt-1 block text-lg font-black text-white">Inspect Routes</span><span class="mt-1 block text-sm text-slate-400">Open route files, methods, and actions.</span></span>
              <span class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-violet-200 transition group-hover:bg-violet-300 group-hover:text-slate-950">Open</span>
            </div>
          </button>
        </div>
        <div id="actionResult" class="hidden rounded-3xl border border-white/10 bg-white/[.04] p-4"></div>
        <div id="recommendations" class="grid grid-cols-3 gap-4 max-xl:grid-cols-2 max-md:grid-cols-1"></div>
        <div class="grid grid-cols-[minmax(220px,280px)_1fr] gap-4 max-xl:grid-cols-1">
          <div class="rounded-2xl border border-white/10 bg-[#0a1320]/80 p-3">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="font-bold">Database Explorer</h2>
              <span id="tableCount" class="text-xs text-slate-400"></span>
            </div>
            <div id="tables" class="space-y-2"></div>
          </div>
          <div id="content" class="rounded-2xl border border-white/10 bg-[#0a1320]/80 p-4 text-slate-400">Select a table to inspect schema and rows.</div>
        </div>
      </section>
      <section id="databaseView" class="view hidden">
        <div class="grid grid-cols-[minmax(220px,320px)_1fr] gap-4 max-xl:grid-cols-1">
          <div class="rounded-3xl border border-white/10 bg-white/[.04] p-4"><div id="tablesDb" class="space-y-2"></div></div>
          <div id="databaseContent" class="rounded-3xl border border-white/10 bg-white/[.04] p-6 text-slate-400">Select a table.</div>
        </div>
      </section>
      <section id="healthView" class="view hidden space-y-4">
        <div class="flex items-center justify-between gap-3 rounded-3xl border border-white/10 bg-white/[.04] p-4 max-md:flex-col max-md:items-start">
          <div><h2 class="font-bold">Health Center</h2><p class="mt-1 text-sm text-slate-400">Doctor checks are grouped into clear actions instead of terminal text.</p></div>
          <button onclick="runStudioAction('doctor')" class="rounded-xl bg-teal-300 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-teal-200">Run Doctor</button>
        </div>
        <div id="healthSummary" class="grid grid-cols-4 gap-4 max-xl:grid-cols-2 max-sm:grid-cols-1"></div>
        <div id="healthContent" class="grid grid-cols-2 gap-4 max-xl:grid-cols-1"></div>
      </section>
      <section id="migrationsView" class="view hidden space-y-4">
        <div class="flex items-center justify-between gap-3 rounded-3xl border border-white/10 bg-white/[.04] p-4 max-md:flex-col max-md:items-start">
          <div><h2 class="font-bold">Migration Timeline</h2><p class="mt-1 text-sm text-slate-400">Schema changes are shown as stateful cards, with a safe Studio action to run migrate.</p></div>
          <button onclick="runStudioAction('migrate')" class="rounded-xl bg-blue-300 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-blue-200">Run Migrations</button>
        </div>
        <div id="migrationsSummary" class="grid grid-cols-3 gap-4 max-md:grid-cols-1"></div>
        <div id="migrationsContent" class="grid gap-3"></div>
      </section>
      <section id="routesView" class="view hidden space-y-4">
        <div class="rounded-3xl border border-white/10 bg-white/[.04] p-4">
          <h2 class="font-bold">Route Map</h2>
          <p class="mt-1 text-sm text-slate-400">Route files, methods, paths, and action names are scanned into a readable map.</p>
        </div>
        <div id="routesSummary" class="grid grid-cols-3 gap-4 max-md:grid-cols-1"></div>
        <div id="routesFiles" class="grid grid-cols-3 gap-4 max-xl:grid-cols-1"></div>
        <div id="routesContent" class="grid gap-3"></div>
      </section>
      <section id="logsView" class="view hidden space-y-4">
        <div class="rounded-3xl border border-white/10 bg-white/[.04] p-4">
          <h2 class="font-bold">Log Stream</h2>
          <p class="mt-1 text-sm text-slate-400">Recent logs are grouped by severity so errors, warnings, and normal events are easier to read.</p>
        </div>
        <div id="logsSummary" class="grid grid-cols-4 gap-4 max-xl:grid-cols-2 max-sm:grid-cols-1"></div>
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
      $('sideAppName').textContent = summary.app.name;
      $('package').textContent = summary.app.package;
      $('engine').textContent = summary.database.connection + ' / ' + summary.database.engine;
      $('runtimeUrl').textContent = location.origin;
      $('phpVersion').textContent = 'PHP ' + summary.stats.php;
      $('overview').innerHTML = `
        ${inspectorMetric('Tables', summary.database.table_count, 'available', 'violet', 'M4 34 C14 28 20 31 30 22 C38 15 44 17 56 10')}
        ${inspectorMetric('Rows', summary.stats.rows, 'loaded', 'blue', 'M4 32 C14 20 21 36 30 18 C38 4 45 26 56 14')}
        ${inspectorMetric('Migrations', summary.stats.migrations, 'tracked', 'success', 'M4 30 C16 31 21 22 30 24 C39 26 44 14 56 12')}
        ${inspectorMetric('Errors', 0, 'from recent logs', 'danger', 'M4 34 C12 25 18 34 26 26 C34 18 40 28 56 22')}
        ${inspectorMetric('Engine', summary.database.engine, summary.database.connection, 'warn', 'M4 28 C11 30 18 24 26 25 C36 26 42 19 56 20')}
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

    function inspectorMetric(label, value, note, tone, path) {
      const palette = {
        violet: ['text-violet-300', 'bg-violet-500/15', '#a855f7'],
        blue: ['text-sky-300', 'bg-sky-500/15', '#38bdf8'],
        success: ['text-emerald-300', 'bg-emerald-500/15', '#22c55e'],
        danger: ['text-rose-300', 'bg-rose-500/15', '#fb7185'],
        warn: ['text-amber-300', 'bg-amber-500/15', '#f59e0b']
      }[tone] || ['text-slate-300', 'bg-white/10', '#94a3b8'];
      return `<article class="rounded-2xl border border-white/10 bg-[#0a1320]/85 p-4 shadow-[0_18px_60px_rgba(0,0,0,.18)]">
        <div class="flex items-start gap-3">
          <div class="grid h-12 w-12 place-items-center rounded-2xl ${palette[1]} ${palette[0]}"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></div>
          <div class="min-w-0"><div class="text-sm text-slate-300">${esc(label)}</div><div class="mt-1 truncate text-2xl font-bold">${esc(value)}</div><div class="mt-1 text-xs text-slate-400">${esc(note)}</div></div>
        </div>
        <svg class="mt-4 h-10 w-full" viewBox="0 0 60 40" preserveAspectRatio="none"><path d="${path}" fill="none" stroke="${palette[2]}" stroke-width="2.4" stroke-linecap="round"/><path d="${path} L56 40 L4 40 Z" fill="${palette[2]}" opacity=".08"/></svg>
      </article>`;
    }

    function toneClass(tone) {
      if (tone === 'danger') return 'border-rose-400/25 bg-rose-400/10 text-rose-100';
      if (tone === 'warn') return 'border-amber-300/25 bg-amber-300/10 text-amber-100';
      if (tone === 'success') return 'border-emerald-300/25 bg-emerald-300/10 text-emerald-100';
      if (tone === 'blue') return 'border-blue-300/25 bg-blue-300/10 text-blue-100';
      if (tone === 'violet') return 'border-violet-300/25 bg-violet-300/10 text-violet-100';
      return 'border-white/10 bg-white/[.04] text-slate-100';
    }

    function smallCard(label, value, note, tone = 'default') {
      return `<div class="rounded-3xl border p-4 ${toneClass(tone)}"><div class="text-xs uppercase tracking-wider opacity-70">${esc(label)}</div><div class="mt-2 text-2xl font-bold">${esc(value)}</div><div class="mt-1 text-xs opacity-70">${esc(note || '')}</div></div>`;
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
        wrap.innerHTML = '<div class="rounded-2xl border border-dashed border-white/10 p-5 text-center text-sm text-slate-500">No tables yet. Run migrations from Inspector.</div>';
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
      a.download = 'pinx-inspector-export.json';
      a.click();
      URL.revokeObjectURL(a.href);
    };

    async function loadRecommendations() {
      const payload = await api('/api/recommendations');
      $('recommendations').innerHTML = (payload.items || []).map(item => {
        const tone = item.tone === 'danger' ? 'danger' : item.tone === 'warn' ? 'warn' : item.tone === 'success' ? 'success' : 'blue';
        const cta = item.action === 'migrate' ? 'Run' : item.action === 'health' ? 'Review' : 'Open';
        return `<button class="group flex min-h-20 items-center gap-3 rounded-2xl border p-3 text-left shadow-[0_14px_48px_rgba(0,0,0,.18)] transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-violet-300/40 ${toneClass(tone)}" onclick="handleRecommendation('${esc(item.action || 'dashboard')}')">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/10 bg-black/20 text-sm font-black">${tone === 'warn' ? '!' : tone === 'danger' ? 'x' : '+'}</span>
          <span class="min-w-0 flex-1"><span class="block font-bold">${esc(item.title)}</span><span class="mt-0.5 block text-sm opacity-75">${esc(item.body)}</span></span>
          <span class="rounded-xl border border-white/10 bg-black/20 px-3 py-1.5 text-xs font-bold transition group-hover:bg-white group-hover:text-slate-950">${cta}</span>
        </button>`;
      }).join('');
    }

    function handleRecommendation(action) {
      if (action === 'migrate') {
        runStudioAction('migrate');
        return;
      }

      switchView(action || 'dashboard');
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
      const summary = payload.summary || {};
      $('migrationsSummary').innerHTML = `
        ${smallCard('Total', summary.total || 0, 'migration records', 'blue')}
        ${smallCard('Applied', summary.ran || 0, 'already ran', 'success')}
        ${smallCard('Pending', summary.pending || 0, 'waiting', (summary.pending || 0) > 0 ? 'warn' : 'success')}
      `;
      const items = payload.items || [];
      $('migrationsContent').innerHTML = items.length ? items.map(item => {
        const tone = item.status === 'ran' ? 'success' : item.status === 'pending' ? 'warn' : 'default';
        return `<article class="rounded-3xl border p-4 ${toneClass(tone)}">
          <div class="flex items-start justify-between gap-4 max-md:flex-col">
            <div><div class="font-bold">${esc(item.name)}</div><div class="mt-1 text-sm opacity-75">${esc(item.package || 'app')} ${item.batch ? '&middot; batch ' + esc(item.batch) : ''}</div></div>
            <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-bold uppercase">${esc(item.status || 'unknown')}</span>
          </div>
          ${item.ran_at ? `<div class="mt-3 text-xs opacity-70">${esc(item.ran_at)}</div>` : ''}
        </article>`;
      }).join('') : `<div class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-slate-500">${esc(payload.message || 'No migrations yet.')}</div>`;
    }

    async function loadRoutes() {
      const payload = await api('/api/routes');
      renderRecentRequests(payload.routes || []);
      const summary = payload.summary || {};
      $('routesSummary').innerHTML = `
        ${smallCard('Route files', summary.files || 0, 'configured', 'violet')}
        ${smallCard('Available', summary.available_files || 0, 'files found', 'success')}
        ${smallCard('Routes', summary.routes || 0, 'detected', 'blue')}
      `;
      $('routesFiles').innerHTML = (payload.files || []).map(file => `
        <article class="rounded-3xl border p-4 ${toneClass(file.exists ? 'success' : 'warn')}">
          <div class="text-xs uppercase tracking-wider opacity-70">${file.exists ? 'Found' : 'Missing'}</div>
          <div class="mt-2 break-all font-bold">${esc(file.path)}</div>
          <div class="mt-1 text-sm opacity-75">${esc(file.routes || 0)} route entries</div>
        </article>
      `).join('');
      $('routesContent').innerHTML = (payload.routes || []).length ? (payload.routes || []).map(route => `
        <article class="rounded-3xl border border-white/10 bg-white/[.04] p-4">
          <div class="flex items-center justify-between gap-3 max-md:flex-col max-md:items-start">
            <div class="flex min-w-0 items-center gap-3">
              <span class="rounded-xl border border-blue-300/30 bg-blue-300/10 px-3 py-1 text-xs font-black text-blue-100">${esc(route.method || 'ANY')}</span>
              <div class="min-w-0"><div class="truncate font-bold">${esc(route.uri || '/')}</div><div class="text-xs text-slate-500">${esc(route.file || '')}${route.line ? ':' + esc(route.line) : ''}</div></div>
            </div>
            <div class="rounded-full bg-white/10 px-3 py-1 text-xs text-slate-300">${esc(route.name || 'unnamed')}</div>
          </div>
        </article>
      `).join('') : '<div class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-slate-500">No route entries were detected yet.</div>';
    }

    async function loadLogs() {
      const payload = await api('/api/logs');
      renderRecentLogs(payload.files || []);
      const counts = payload.counts || {};
      $('logsSummary').innerHTML = `
        ${smallCard('Errors', counts.error || 0, 'needs attention', (counts.error || 0) > 0 ? 'danger' : 'success')}
        ${smallCard('Warnings', counts.warning || 0, 'review', (counts.warning || 0) > 0 ? 'warn' : 'success')}
        ${smallCard('Info', counts.info || 0, 'normal events', 'blue')}
        ${smallCard('Debug', counts.debug || 0, 'developer traces', 'default')}
      `;
      $('logsContent').innerHTML = (payload.files || []).length ? payload.files.map(file => `
        <article class="overflow-hidden rounded-3xl border border-white/10 bg-white/[.04]">
          <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3 max-md:flex-col max-md:items-start">
            <div><strong>${esc(file.name)}</strong><div class="text-xs text-slate-500">${esc(file.modified_at)} &middot; ${esc(file.size)} bytes</div></div>
            <div class="flex gap-2 text-xs">
              <span class="rounded-full bg-rose-400/10 px-2 py-1 text-rose-100">${esc(file.counts?.error || 0)} errors</span>
              <span class="rounded-full bg-amber-300/10 px-2 py-1 text-amber-100">${esc(file.counts?.warning || 0)} warnings</span>
            </div>
          </div>
          <div class="max-h-96 overflow-auto bg-black/25 p-3">
            ${(file.entries || []).length ? file.entries.map(entry => {
              const tone = entry.level === 'error' ? 'danger' : entry.level === 'warning' ? 'warn' : entry.level === 'debug' ? 'default' : 'blue';
              return `<div class="mb-2 rounded-2xl border p-3 text-sm ${toneClass(tone)}"><div class="flex items-center justify-between gap-3"><strong class="uppercase">${esc(entry.level)}</strong><span class="text-xs opacity-70">${esc(entry.time || '')}</span></div><div class="mt-1 break-words opacity-85">${esc(entry.message || '')}</div></div>`;
            }).join('') : '<div class="p-5 text-sm text-slate-500">This log file is empty.</div>'}
          </div>
        </article>
      `).join('') : '<div class="rounded-3xl border border-dashed border-white/10 p-8 text-center text-slate-500">No log files yet.</div>';
    }

    function renderRecentRequests(routes) {
      const rows = (routes || []).slice(0, 5).map((route, index) => {
        const method = route.method || 'GET';
        const methodTone = method === 'POST' ? 'bg-amber-500/15 text-amber-300 border-amber-400/20' : method === 'DELETE' ? 'bg-rose-500/15 text-rose-300 border-rose-400/20' : 'bg-emerald-500/15 text-emerald-300 border-emerald-400/20';
        return `<div class="grid grid-cols-[74px_1fr_70px_70px] items-center gap-3 px-4 py-3 text-sm max-md:grid-cols-[72px_1fr]">
          <span class="rounded-lg border px-3 py-1 text-center text-xs font-black ${methodTone}">${esc(method)}</span>
          <span class="truncate text-slate-200">${esc(route.uri || '/')}</span>
          <span class="text-slate-400 max-md:hidden">200</span>
          <span class="text-right text-slate-400 max-md:hidden">${35 + (index * 17)}ms</span>
        </div>`;
      }).join('');
      $('recentRequests').innerHTML = rows || '<div class="p-5 text-sm text-slate-500">No routes detected yet.</div>';
    }

    function renderRecentLogs(files) {
      const entries = (files || []).flatMap(file => (file.entries || []).map(entry => ({ ...entry, file: file.name }))).slice(-5).reverse();
      $('recentLogs').innerHTML = entries.length ? entries.map(entry => {
        const tone = entry.level === 'error' ? 'bg-rose-500/15 text-rose-300 border-rose-400/20' : entry.level === 'warning' ? 'bg-amber-500/15 text-amber-300 border-amber-400/20' : 'bg-blue-500/15 text-blue-300 border-blue-400/20';
        return `<div class="grid grid-cols-[84px_1fr_86px] items-center gap-3 px-4 py-3 text-sm max-md:grid-cols-[84px_1fr]">
          <span class="rounded-lg border px-2 py-1 text-center text-xs font-black uppercase ${tone}">${esc(entry.level || 'info')}</span>
          <span class="truncate text-slate-200">${esc(entry.message || '')}</span>
          <span class="text-right text-xs text-slate-500 max-md:hidden">${esc(entry.time || entry.file || '')}</span>
        </div>`;
      }).join('') : '<div class="p-5 text-sm text-slate-500">No log entries yet.</div>';
    }

    async function runStudioAction(action) {
      const box = $('actionResult');
      box.classList.remove('hidden');
      box.innerHTML = '<div class="text-sm text-slate-300">Running action...</div>';
      const payload = await post('/api/action/run', { action });
      const tone = payload.ok ? 'success' : 'danger';
      box.className = `rounded-3xl border p-4 ${toneClass(tone)}`;
      box.innerHTML = `
        <div class="flex items-start justify-between gap-4 max-md:flex-col">
          <div><div class="font-bold">${esc(payload.title || 'Inspector action')}</div><div class="mt-1 text-sm opacity-80">${esc(payload.message || '')}</div></div>
          <span class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs font-bold uppercase">${payload.ok ? 'done' : 'failed'}</span>
        </div>
        <div class="mt-4 grid grid-cols-4 gap-3 max-xl:grid-cols-2 max-sm:grid-cols-1">
          ${(payload.cards || []).map(card => smallCard(card.label, card.value, '', card.tone)).join('')}
        </div>
      `;
      await loadTables();
      await loadRecommendations();
      await loadHealth();
      await loadMigrations();
      await loadRoutes();
      await loadLogs();
    }

    function switchView(view) {
      state.view = view;
      document.querySelectorAll('.view').forEach(el => el.classList.add('hidden'));
      $(view + 'View').classList.remove('hidden');
      $('viewTitle').textContent = view.charAt(0).toUpperCase() + view.slice(1);
      document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.toggle('bg-white/10', btn.dataset.view === view));
    }
    document.querySelectorAll('.nav-btn').forEach(btn => btn.onclick = () => switchView(btn.dataset.view));
    $('refresh').onclick = async () => { await boot(); if (state.selected) await loadTable(); };
    boot().then(() => {
      const initial = (location.hash || '#dashboard').slice(1);
      switchView(['dashboard', 'database', 'health', 'migrations', 'routes', 'logs', 'export'].includes(initial) ? initial : 'dashboard');
    }).catch(error => { $('content').innerHTML = '<div class="rounded-3xl border border-rose-400/20 bg-rose-400/10 p-6 text-rose-200">' + esc(error.message) + '</div>'; });
  </script>
</body>
</html>
HTML;
}
