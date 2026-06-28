<?php

declare(strict_types=1);

$root = normalize_path((string) ($_SERVER['PINX_STUDIO_PROJECT_ROOT'] ?? getenv('PINX_STUDIO_PROJECT_ROOT') ?: getcwd()));
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

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

function engine(string $root): string
{
    if (extension_loaded('pdo_sqlite') && is_file(sqlite_database($root))) {
        return 'sqlite';
    }

    return 'json';
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

    return [
        'app' => [
            'package' => (string) ($config['package'] ?? 'unknown'),
            'name' => (string) ($config['name'] ?? $config['title'] ?? 'Pinoox App'),
            'theme' => (string) ($config['theme'] ?? 'default'),
            'root' => $root,
        ],
        'devdb' => [
            'engine' => engine($root),
            'path' => devdb_path($root),
            'sqlite_database' => sqlite_database($root),
            'table_count' => count($tables['tables']),
        ],
    ];
}

function tables_payload(string $root): array
{
    if (engine($root) === 'sqlite') {
        return sqlite_tables_payload($root);
    }

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
        'engine' => 'json',
        'tables' => $tables,
    ];
}

function table_payload(string $root, string $table, int $limit, int $offset): array
{
    if ($table === '') {
        throw new RuntimeException('Table name is required.');
    }

    if (engine($root) === 'sqlite') {
        return sqlite_table_payload($root, $table, $limit, $offset);
    }

    $schema = json_file(devdb_path($root) . '/schema.json', ['tables' => []]);
    $meta = $schema['tables'][$table] ?? null;
    if (!is_array($meta)) {
        throw new RuntimeException('DevDB table "' . $table . '" does not exist.');
    }

    $rows = json_file(devdb_path($root) . '/data/' . safe_table_file($table) . '.json', []);

    return [
        'engine' => 'json',
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

function export_payload(string $root): array
{
    if (engine($root) === 'sqlite') {
        $tables = sqlite_tables_payload($root)['tables'];
        $data = [];
        foreach ($tables as $table) {
            $data[$table['name']] = sqlite_table_payload($root, (string) $table['name'], 10000, 0);
        }

        return [
            'engine' => 'sqlite',
            'data' => $data,
        ];
    }

    return [
        'engine' => 'json',
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

function sqlite_tables_payload(string $root): array
{
    $database = sqlite_database($root);
    if (!extension_loaded('pdo_sqlite') || !is_file($database)) {
        return [
            'engine' => 'sqlite',
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
        'engine' => 'sqlite',
        'tables' => $tables,
    ];
}

function sqlite_table_payload(string $root, string $table, int $limit, int $offset): array
{
    $pdo = sqlite_pdo($root);
    $columns = sqlite_columns($pdo, $table);
    if ($columns === []) {
        throw new RuntimeException('DevDB table "' . $table . '" does not exist.');
    }

    $quoted = quote_identifier($table);
    $rows = $pdo->query('SELECT * FROM ' . $quoted . ' LIMIT ' . $limit . ' OFFSET ' . $offset)->fetchAll();
    $count = (int) $pdo->query('SELECT COUNT(*) AS count FROM ' . $quoted)->fetch()['count'];

    return [
        'engine' => 'sqlite',
        'table' => $table,
        'columns' => $columns,
        'indexes' => [],
        'primary_key' => sqlite_primary_key($columns),
        'row_count' => $count,
        'limit' => $limit,
        'offset' => $offset,
        'rows' => $rows,
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
    <div class="meta"><span id="engine" class="pill">DevDB</span></div>
  </header>
  <main>
    <aside>
      <div class="row"><strong>Tables</strong><button id="refresh">Refresh</button></div>
      <div id="tables" class="table-list"></div>
    </aside>
    <section>
      <div id="content" class="empty">Select a table to inspect schema and rows.</div>
    </section>
  </main>
  <script>
    const state = { selected: null, limit: 50, offset: 0 };
    const $ = (id) => document.getElementById(id);
    const api = (url) => fetch(url, { cache: 'no-store' }).then(r => r.json());
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    const cell = (value) => typeof value === 'object' && value !== null ? '<code>' + esc(JSON.stringify(value, null, 2)) + '</code>' : esc(value);

    async function boot() {
      const summary = await api('/api/summary');
      $('appName').textContent = summary.app.name;
      $('package').textContent = summary.app.package;
      $('engine').textContent = summary.devdb.engine + ' devdb';
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
        btn.onclick = () => { state.selected = table.name; state.offset = 0; loadTable(); loadTables(); };
        wrap.appendChild(btn);
      });
    }

    async function loadTable() {
      if (!state.selected) return;
      const payload = await api('/api/table?name=' + encodeURIComponent(state.selected) + '&limit=' + state.limit + '&offset=' + state.offset);
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
          <div class="row"><button onclick="prevPage()">Prev</button><input id="limit" type="number" min="1" max="500" value="${state.limit}"><button onclick="nextPage(${payload.row_count})">Next</button></div>
        </div>
        <div class="grid">
          <div class="panel"><div class="panel-head"><strong>Schema</strong></div><table><thead><tr><th>Name</th><th>Type</th><th>Nullable</th><th>Default</th><th>Primary</th></tr></thead><tbody>${schemaRows || '<tr><td colspan="5" class="muted">No columns</td></tr>'}</tbody></table></div>
          <div class="panel"><div class="panel-head"><strong>Indexes</strong></div><div class="empty"><code>${esc(JSON.stringify(payload.indexes || [], null, 2))}</code></div></div>
        </div>
        <div class="panel"><div class="panel-head"><strong>Rows</strong><span class="muted">offset ${payload.offset}, limit ${payload.limit}</span></div><div style="overflow:auto"><table><thead><tr>${rowHeaders.map(h => '<th>' + esc(h) + '</th>').join('')}</tr></thead><tbody>${dataRows || '<tr><td class="muted">No rows</td></tr>'}</tbody></table></div></div>
      `;
      $('limit').onchange = (event) => { state.limit = Math.max(1, Math.min(500, Number(event.target.value || 50))); state.offset = 0; loadTable(); };
    }

    function nextPage(total) { state.offset = Math.min(Math.max(0, total - 1), state.offset + state.limit); loadTable(); }
    function prevPage() { state.offset = Math.max(0, state.offset - state.limit); loadTable(); }
    $('refresh').onclick = () => { loadTables(); if (state.selected) loadTable(); };
    boot().catch(error => { $('content').innerHTML = '<div class="empty error">' + esc(error.message) + '</div>'; });
  </script>
</body>
</html>
HTML;
}
