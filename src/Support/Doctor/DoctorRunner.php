<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support\Doctor;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\CorePath;
use Pinoox\PinxCli\Support\PinxVersion;

final class DoctorRunner
{
    private const PHP_MIN = '8.1.0';

    /** @var array<string, string> */
    private array $env = [];

    public function __construct(
        private readonly bool $skipDatabase = false,
        private readonly bool $skipFrontend = false,
    ) {
    }

    public function run(?AppContext $context): DoctorReport
    {
        $report = new DoctorReport();

        $this->checkProjectContext($report, $context);

        if ($context === null) {
            $this->checkPhpRuntime($report);

            return $report;
        }

        $root = $context->root;
        $this->env = $this->loadEnv($root . '/.env');

        $this->checkPhpRuntime($report);
        $this->checkPhpExtensions($report);
        $this->checkAppIdentity($report, $context);
        $this->checkProjectLayout($report, $context);
        $this->checkDependencies($report, $root);
        $this->checkEnvironment($report, $context);
        $this->checkWritablePaths($report, $root);
        $this->checkRoutesAndTheme($report, $context);
        $this->checkTooling($report, $root);

        if (!$this->skipDatabase) {
            $this->checkDatabase($report);
        }

        if (!$this->skipFrontend) {
            $this->checkFrontend($report, $context);
        }

        $this->checkBuildReadiness($report, $context);

        return $report;
    }

    private function checkProjectContext(DoctorReport $report, ?AppContext $context): void
    {
        if ($context === null) {
            $report->add(new CheckItem(
                group: 'Project',
                id: 'app_context',
                label: 'Single-app project',
                status: CheckStatus::Fail,
                detail: 'app.php with a valid "package" key was not found',
                hint: 'Run pinx init in your project root or cd into a folder with app.php',
            ));

            return;
        }

        $report->add(new CheckItem(
            group: 'Project',
            id: 'app_context',
            label: 'Single-app project',
            status: CheckStatus::Pass,
            detail: $context->root,
        ));
    }

    private function checkPhpRuntime(DoctorReport $report): void
    {
        $version = PHP_VERSION;
        $ok = version_compare($version, self::PHP_MIN, '>=');

        $report->add(new CheckItem(
            group: 'PHP',
            id: 'php_version',
            label: 'PHP version',
            status: $ok ? CheckStatus::Pass : CheckStatus::Fail,
            detail: $version . ' (required ' . self::PHP_MIN . '+)',
            hint: $ok ? null : 'Upgrade PHP to 8.1 or newer',
        ));

        $memory = ini_get('memory_limit');
        $memoryBytes = $this->iniSizeToBytes(is_string($memory) ? $memory : '');

        $report->add(new CheckItem(
            group: 'PHP',
            id: 'php_memory',
            label: 'memory_limit',
            status: $memoryBytes >= 128 * 1024 * 1024 || $memoryBytes === -1
                ? CheckStatus::Pass
                : CheckStatus::Warn,
            detail: is_string($memory) && $memory !== '' ? $memory : 'unknown',
            hint: 'Set memory_limit to at least 128M in php.ini for dev/build tasks',
        ));

        $maxExec = ini_get('max_execution_time');
        $report->add(new CheckItem(
            group: 'PHP',
            id: 'php_max_execution_time',
            label: 'max_execution_time',
            status: CheckStatus::Pass,
            detail: is_string($maxExec) && $maxExec !== '' ? $maxExec . 's' : 'unknown',
            scored: false,
        ));

        $sapi = PHP_SAPI;
        $report->add(new CheckItem(
            group: 'PHP',
            id: 'php_sapi',
            label: 'SAPI',
            status: CheckStatus::Pass,
            detail: $sapi,
            scored: false,
        ));
    }

    private function checkPhpExtensions(DoctorReport $report): void
    {
        $required = [
            'mysqli' => 'MySQL database driver',
            'zip' => 'Pinx package build/extract',
            'mbstring' => 'Unicode string handling',
            'json' => 'API and config parsing',
        ];

        foreach ($required as $ext => $why) {
            $loaded = extension_loaded($ext);
            $report->add(new CheckItem(
                group: 'PHP',
                id: 'ext_' . $ext,
                label: 'ext-' . $ext,
                status: $loaded ? CheckStatus::Pass : CheckStatus::Fail,
                detail: $why,
                hint: $loaded ? null : 'Enable the ' . $ext . ' extension in php.ini',
            ));
        }

        $recommended = [
            'curl' => 'HTTP client and external APIs',
            'openssl' => 'TLS and package signing',
            'fileinfo' => 'Upload MIME detection',
            'pdo' => 'Alternative DB layer',
            'dom' => 'XML/HTML parsing',
            'gd' => 'Image processing',
            'intl' => 'Locale and formatting',
        ];

        foreach ($recommended as $ext => $why) {
            $loaded = extension_loaded($ext);
            $report->add(new CheckItem(
                group: 'PHP',
                id: 'ext_' . $ext,
                label: 'ext-' . $ext,
                status: $loaded ? CheckStatus::Pass : CheckStatus::Warn,
                detail: $why,
                hint: $loaded ? null : 'Recommended: enable ext-' . $ext . ' in php.ini',
            ));
        }
    }

    private function checkAppIdentity(DoctorReport $report, AppContext $context): void
    {
        $package = $context->package;
        $configPackage = $context->config['package'] ?? null;
        $valid = is_string($configPackage) && trim($configPackage) === $package;

        $report->add(new CheckItem(
            group: 'App',
            id: 'package_key',
            label: 'app.php package',
            status: $valid ? CheckStatus::Pass : CheckStatus::Fail,
            detail: $package,
            hint: $valid ? null : 'Set a non-empty "package" key in app.php (e.g. com_vendor_app)',
        ));

        $registry = $context->root . '/config/apps.config.php';
        if (!is_file($registry)) {
            $report->add(new CheckItem(
                group: 'App',
                id: 'apps_registry',
                label: 'apps.config.php',
                status: CheckStatus::Fail,
                detail: 'Missing config/apps.config.php',
                hint: 'Add config/apps.config.php mapping your package to "~"',
            ));

            return;
        }

        $registryConfig = require $registry;
        $mapped = is_array($registryConfig)
            ? ($registryConfig['packages'][$package] ?? $registryConfig['apps'][$package] ?? null)
            : null;

        $rootMapped = $mapped === '~' || $mapped === '~/';

        $report->add(new CheckItem(
            group: 'App',
            id: 'apps_registry',
            label: 'apps.config.php mapping',
            status: $rootMapped ? CheckStatus::Pass : CheckStatus::Fail,
            detail: $rootMapped ? $package . ' => ~' : 'Package not mapped to project root',
            hint: $rootMapped ? null : "Set config/apps.config.php packages['{$package}'] = '~'",
        ));

        $enabled = filter_var($context->config['enable'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $report->add(new CheckItem(
            group: 'App',
            id: 'app_enabled',
            label: 'App enabled',
            status: $enabled ? CheckStatus::Pass : CheckStatus::Warn,
            detail: $enabled ? 'yes' : 'no',
            hint: $enabled ? null : 'Set enable => true in app.php for local development',
        ));

        $version = $context->versionName();
        $code = $context->versionCode();
        $report->add(new CheckItem(
            group: 'App',
            id: 'app_version',
            label: 'App version',
            status: $version !== null ? CheckStatus::Pass : CheckStatus::Warn,
            detail: $version !== null
                ? $version . ($code !== null ? ' #' . $code : '')
                : 'version-name not set in app.php',
            hint: $version !== null ? null : 'Add version-name and version-code to app.php',
            scored: false,
        ));
    }

    private function checkProjectLayout(DoctorReport $report, AppContext $context): void
    {
        $root = $context->root;
        $required = [
            'app.php' => 'App manifest',
            'index.php' => 'HTTP entry point',
            'composer.json' => 'Composer project file',
            'bin/pinx' => 'Pinx CLI entry',
        ];

        foreach ($required as $relative => $label) {
            $path = $root . '/' . $relative;
            $exists = is_file($path);
            $report->add(new CheckItem(
                group: 'Layout',
                id: 'file_' . str_replace(['/', '.'], '_', $relative),
                label: $relative,
                status: $exists ? CheckStatus::Pass : CheckStatus::Fail,
                detail: $label,
                hint: match ($relative) {
                    'bin/pinx' => 'Copy bin/pinx from the pinoox/app template',
                    'composer.json' => 'Run pinx init or copy composer.json from the template',
                    default => 'Restore missing file: ' . $relative,
                },
            ));
        }

        $pincore = CorePath::resolve($root);
        $pincoreLabel = CorePath::relativeLabel($root, $pincore);
        $pincoreReady = is_dir($pincore) && (is_file($pincore . '/functions/base.php') || is_file($pincore . '/launcher/bootstrap.php'));
        $report->add(new CheckItem(
            group: 'Layout',
            id: 'pincore_vendor',
            label: $pincoreLabel,
            status: $pincoreReady ? CheckStatus::Pass : CheckStatus::Fail,
            detail: $pincoreReady ? 'Ready' : 'Not installed',
            hint: $pincoreReady ? null : 'Run: composer install — or git clone pinoox/pincore into pincore/',
        ));
    }

    private function checkDependencies(DoctorReport $report, string $root): void
    {
        $lock = $root . '/composer.lock';
        $report->add(new CheckItem(
            group: 'Dependencies',
            id: 'composer_lock',
            label: 'composer.lock',
            status: is_file($lock) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_file($lock) ? 'Present' : 'Missing — versions are not pinned',
            hint: is_file($lock) ? null : 'Run: composer update to generate composer.lock',
        ));

        $autoload = $root . '/vendor/autoload.php';
        $autoloadOk = is_file($autoload);
        $report->add(new CheckItem(
            group: 'Dependencies',
            id: 'composer_autoload',
            label: 'Composer autoload',
            status: $autoloadOk ? CheckStatus::Pass : CheckStatus::Fail,
            detail: $autoloadOk ? 'Ready' : 'vendor/autoload.php missing',
            hint: $autoloadOk ? null : 'Run: composer install',
        ));

        $pincoreVersion = $this->readPackageVersion(CorePath::resolve($root) . '/composer.json');
        $report->add(new CheckItem(
            group: 'Dependencies',
            id: 'pincore_version',
            label: 'pinoox/pincore',
            status: $pincoreVersion !== null ? CheckStatus::Pass : CheckStatus::Warn,
            detail: $pincoreVersion ?? 'Version unknown',
            scored: false,
        ));

        $pinxVersion = $this->readPackageVersion($root . '/vendor/pinoox/pinx-cli/composer.json')
            ?? $this->detectPinxCliVersion();
        $report->add(new CheckItem(
            group: 'Dependencies',
            id: 'pinx_cli_version',
            label: 'pinoox/pinx-cli',
            status: $pinxVersion !== null ? CheckStatus::Pass : CheckStatus::Warn,
            detail: $pinxVersion ?? 'Not installed via Composer',
            hint: $pinxVersion !== null ? null : 'Add pinoox/pinx-cli to composer.json or use global pinx',
            scored: false,
        ));
    }

    private function checkEnvironment(DoctorReport $report, AppContext $context): void
    {
        $root = $context->root;
        $envFile = $root . '/.env';
        $exampleFile = $root . '/.env.example';

        if (!is_file($envFile)) {
            $report->add(new CheckItem(
                group: 'Environment',
                id: 'env_file',
                label: '.env file',
                status: CheckStatus::Warn,
                detail: 'Missing',
                hint: is_file($exampleFile)
                    ? 'Run: copy .env.example .env (Windows) or cp .env.example .env'
                    : 'Create a .env file with DB_* and PINX_PACKAGE variables',
            ));
        } else {
            $report->add(new CheckItem(
                group: 'Environment',
                id: 'env_file',
                label: '.env file',
                status: CheckStatus::Pass,
                detail: 'Present',
            ));
        }

        $requiredKeys = [
            'PINX_PACKAGE' => 'Must match app.php package',
            'DB_HOST' => 'Database host',
            'DB_DATABASE' => 'Database name',
            'DB_USERNAME' => 'Database user',
        ];

        foreach ($requiredKeys as $key => $desc) {
            $value = $this->env[$key] ?? '';
            $filled = $value !== '';

            if ($key === 'PINX_PACKAGE' && $filled) {
                $matches = $value === $context->package;
                $report->add(new CheckItem(
                    group: 'Environment',
                    id: 'env_' . strtolower($key),
                    label: $key,
                    status: $matches ? CheckStatus::Pass : CheckStatus::Fail,
                    detail: $matches ? $value : $value . ' (expected ' . $context->package . ')',
                    hint: $matches ? null : 'Set PINX_PACKAGE=' . $context->package . ' in .env',
                ));

                continue;
            }

            $report->add(new CheckItem(
                group: 'Environment',
                id: 'env_' . strtolower($key),
                label: $key,
                status: $filled ? CheckStatus::Pass : CheckStatus::Warn,
                detail: $desc,
                hint: $filled ? null : 'Set ' . $key . ' in .env',
            ));
        }

        $appKey = $this->env['APP_KEY'] ?? '';
        $appEnv = strtolower($this->env['APP_ENV'] ?? 'development');
        $production = in_array($appEnv, ['production', 'prod'], true);

        $report->add(new CheckItem(
            group: 'Environment',
            id: 'env_app_key',
            label: 'APP_KEY',
            status: match (true) {
                $appKey !== '' => CheckStatus::Pass,
                $production => CheckStatus::Fail,
                default => CheckStatus::Warn,
            },
            detail: $appKey !== '' ? 'Set' : 'Empty',
            hint: $appKey !== '' ? null : 'Generate and set APP_KEY in .env before production',
        ));

        $report->add(new CheckItem(
            group: 'Environment',
            id: 'env_app_env',
            label: 'APP_ENV',
            status: CheckStatus::Pass,
            detail: $appEnv,
            scored: false,
        ));
    }

    private function checkWritablePaths(DoctorReport $report, string $root): void
    {
        $paths = [
            'storage' => 'Runtime storage',
            'pinker' => 'Pinker build cache',
            'storage/logs' => 'Application logs',
            'storage/sessions' => 'Session files',
            'export' => 'Pinx build output',
        ];

        foreach ($paths as $relative => $label) {
            $path = $root . '/' . $relative;

            if (!is_dir($path)) {
                $severity = in_array($relative, ['storage', 'pinker'], true)
                    ? CheckStatus::Warn
                    : CheckStatus::Pass;

                $report->add(new CheckItem(
                    group: 'Permissions',
                    id: 'writable_' . str_replace('/', '_', $relative),
                    label: $relative,
                    status: $severity,
                    detail: 'Directory does not exist yet',
                    hint: in_array($relative, ['storage', 'pinker'], true)
                        ? 'Run: mkdir ' . str_replace('/', DIRECTORY_SEPARATOR, $relative)
                        : null,
                    scored: in_array($relative, ['storage', 'pinker'], true),
                ));

                continue;
            }

            $writable = is_writable($path);
            $report->add(new CheckItem(
                group: 'Permissions',
                id: 'writable_' . str_replace('/', '_', $relative),
                label: $relative,
                status: $writable ? CheckStatus::Pass : CheckStatus::Fail,
                detail: $label,
                hint: $writable ? null : 'Grant write permission on ' . $relative,
            ));
        }
    }

    private function checkRoutesAndTheme(DoctorReport $report, AppContext $context): void
    {
        $root = $context->root;
        $routes = $context->config['router']['routes'] ?? [];

        if (!is_array($routes) || $routes === []) {
            $report->add(new CheckItem(
                group: 'Routes',
                id: 'route_files',
                label: 'Route files',
                status: CheckStatus::Warn,
                detail: 'No routes registered in app.php router.routes',
                hint: 'Add routes/web.php to app.php router.routes',
            ));
        } else {
            $missing = [];
            foreach ($routes as $routeFile) {
                if (!is_string($routeFile)) {
                    continue;
                }

                if (!is_file($root . '/' . $routeFile)) {
                    $missing[] = $routeFile;
                }
            }

            $report->add(new CheckItem(
                group: 'Routes',
                id: 'route_files',
                label: 'Route files',
                status: $missing === [] ? CheckStatus::Pass : CheckStatus::Fail,
                detail: $missing === []
                    ? count($routes) . ' file(s) registered'
                    : 'Missing: ' . implode(', ', $missing),
                hint: $missing === [] ? null : 'Create missing route files or fix app.php router.routes',
            ));
        }

        $actionsFile = $root . '/routes/actions.php';
        $report->add(new CheckItem(
            group: 'Routes',
            id: 'route_actions',
            label: 'routes/actions.php',
            status: is_file($actionsFile) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_file($actionsFile) ? 'Present' : 'Missing — named actions unavailable',
            hint: is_file($actionsFile) ? null : 'Add routes/actions.php for @action routing',
        ));

        $theme = $context->theme();
        if ($theme === null) {
            $report->add(new CheckItem(
                group: 'Theme',
                id: 'theme_folder',
                label: 'Active theme',
                status: CheckStatus::Warn,
                detail: 'theme key not set in app.php',
                hint: 'Set theme => "default" (or your theme folder) in app.php',
            ));
        } else {
            $themePath = $root . '/theme/' . $theme;
            $report->add(new CheckItem(
                group: 'Theme',
                id: 'theme_folder',
                label: 'theme/' . $theme,
                status: is_dir($themePath) ? CheckStatus::Pass : CheckStatus::Fail,
                detail: is_dir($themePath) ? 'Present' : 'Theme folder missing',
                hint: is_dir($themePath) ? null : 'Create theme/' . $theme . ' or fix app.php theme key',
            ));
        }

        $resourcePath = $root . '/resource';
        $report->add(new CheckItem(
            group: 'Theme',
            id: 'resource_folder',
            label: 'resource/',
            status: is_dir($resourcePath) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_dir($resourcePath) ? 'Present' : 'Missing static resource folder',
            hint: is_dir($resourcePath) ? null : 'Create resource/ for app icon and assets',
            scored: false,
        ));

        $iconPath = $context->iconPath();
        $iconRelative = $context->iconRelativePath();
        $report->add(new CheckItem(
            group: 'Theme',
            id: 'app_icon',
            label: $iconRelative,
            status: is_file($iconPath) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_file($iconPath) ? 'Present' : 'Missing app icon',
            hint: is_file($iconPath) ? null : 'Add ' . $iconRelative . ' or update app.php icon key',
            scored: false,
        ));
    }

    private function checkTooling(DoctorReport $report, string $root): void
    {
        $schedule = $root . '/schedule.php';
        $report->add(new CheckItem(
            group: 'Tooling',
            id: 'schedule_file',
            label: 'schedule.php',
            status: is_file($schedule) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_file($schedule) ? 'Present' : 'Optional — cron tasks not defined',
            scored: false,
        ));

        $migrations = $root . '/database/migrations';
        $migrationCount = 0;
        if (is_dir($migrations)) {
            $migrationCount = count(glob($migrations . '/*.php') ?: []);
        }

        $report->add(new CheckItem(
            group: 'Tooling',
            id: 'migrations',
            label: 'database/migrations',
            status: is_dir($migrations) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_dir($migrations)
                ? $migrationCount . ' migration file(s)'
                : 'Folder missing',
            hint: is_dir($migrations) ? null : 'Run: pinx make migration create_initial_table',
            scored: false,
        ));

        $tests = $root . '/tests';
        $report->add(new CheckItem(
            group: 'Tooling',
            id: 'tests_folder',
            label: 'tests/',
            status: is_dir($tests) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_dir($tests) ? 'Present' : 'No test suite yet',
            hint: is_dir($tests) ? null : 'Run: pinx make test AppTest --feature',
            scored: false,
        ));
    }

    private function checkDatabase(DoctorReport $report): void
    {
        if (!extension_loaded('mysqli')) {
            $report->add(new CheckItem(
                group: 'Database',
                id: 'db_connection',
                label: 'MySQL connection',
                status: CheckStatus::Skip,
                detail: 'Skipped — ext-mysqli not loaded',
                scored: false,
            ));

            return;
        }

        $host = $this->env['DB_HOST'] ?? '';
        $port = (int) ($this->env['DB_PORT'] ?? 3306);
        $database = $this->env['DB_DATABASE'] ?? '';
        $username = $this->env['DB_USERNAME'] ?? '';
        $password = $this->env['DB_PASSWORD'] ?? '';

        if ($host === '' || $database === '' || $username === '') {
            $report->add(new CheckItem(
                group: 'Database',
                id: 'db_connection',
                label: 'MySQL connection',
                status: CheckStatus::Warn,
                detail: 'DB credentials incomplete in .env',
                hint: 'Fill DB_HOST, DB_DATABASE, and DB_USERNAME in .env then run pinx setup',
            ));

            return;
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $mysqli = @new \mysqli($host, $username, $password, '', $port);

        if ($mysqli->connect_errno) {
            $report->add(new CheckItem(
                group: 'Database',
                id: 'db_connection',
                label: 'MySQL connection',
                status: CheckStatus::Fail,
                detail: $mysqli->connect_error,
                hint: 'Verify DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD and that MySQL is running',
            ));

            return;
        }

        $report->add(new CheckItem(
            group: 'Database',
            id: 'db_connection',
            label: 'MySQL server',
            status: CheckStatus::Pass,
            detail: $host . ':' . $port,
        ));

        $dbSelected = @$mysqli->select_db($database);
        $report->add(new CheckItem(
            group: 'Database',
            id: 'db_database',
            label: 'Database exists',
            status: $dbSelected ? CheckStatus::Pass : CheckStatus::Warn,
            detail: $database,
            hint: $dbSelected ? null : 'Create database "' . $database . '" or update DB_DATABASE in .env',
        ));

        $mysqli->close();
    }

    private function checkFrontend(DoctorReport $report, AppContext $context): void
    {
        $stack = $context->config['frontend']['stack'] ?? null;
        $stack = is_string($stack) ? strtolower($stack) : 'twig';

        $report->add(new CheckItem(
            group: 'Frontend',
            id: 'frontend_stack',
            label: 'Frontend stack',
            status: CheckStatus::Pass,
            detail: $stack,
            scored: false,
        ));

        if (in_array($stack, ['twig', 'none', ''], true)) {
            $report->add(new CheckItem(
                group: 'Frontend',
                id: 'node_runtime',
                label: 'Node.js',
                status: CheckStatus::Skip,
                detail: 'Not required for twig-only stack',
                scored: false,
            ));

            return;
        }

        $nodeVersion = $this->commandVersion('node');
        $npmVersion = $this->commandVersion('npm');

        $report->add(new CheckItem(
            group: 'Frontend',
            id: 'node_runtime',
            label: 'Node.js',
            status: $nodeVersion !== null ? CheckStatus::Pass : CheckStatus::Fail,
            detail: $nodeVersion ?? 'Not found in PATH',
            hint: $nodeVersion !== null ? null : 'Install Node.js 18+ for Vite frontend development',
        ));

        $report->add(new CheckItem(
            group: 'Frontend',
            id: 'npm_runtime',
            label: 'npm',
            status: $npmVersion !== null ? CheckStatus::Pass : CheckStatus::Warn,
            detail: $npmVersion ?? 'Not found in PATH',
            hint: $npmVersion !== null ? null : 'Install npm (bundled with Node.js)',
        ));

        $theme = $context->theme();
        if ($theme === null) {
            return;
        }

        $packageJson = $context->root . '/theme/' . $theme . '/package.json';
        $report->add(new CheckItem(
            group: 'Frontend',
            id: 'theme_package_json',
            label: 'theme package.json',
            status: is_file($packageJson) ? CheckStatus::Pass : CheckStatus::Warn,
            detail: is_file($packageJson) ? 'theme/' . $theme . '/package.json' : 'Missing',
            hint: is_file($packageJson) ? null : 'Run: pinx fe scaffold --stack=' . $stack,
        ));

        if (is_file($packageJson)) {
            $hasModules = is_dir(dirname($packageJson) . '/node_modules');
            $report->add(new CheckItem(
                group: 'Frontend',
                id: 'theme_node_modules',
                label: 'node_modules',
                status: $hasModules ? CheckStatus::Pass : CheckStatus::Warn,
                detail: $hasModules ? 'Installed' : 'Not installed',
                hint: $hasModules ? null : 'Run: pinx deps install or pinx fe install',
            ));
        }
    }

    private function checkBuildReadiness(DoctorReport $report, AppContext $context): void
    {
        $minpin = $context->config['pinx']['minpin'] ?? null;
        $installed = $this->readPackageVersion(CorePath::resolve($context->root) . '/composer.json');

        if ($minpin === null) {
            return;
        }

        $minpin = is_numeric($minpin) ? (int) $minpin : null;
        $installedMajor = $this->majorVersion($installed);

        if ($minpin === null || $installedMajor === null) {
            return;
        }

        $ok = $installedMajor >= $minpin;
        $report->add(new CheckItem(
            group: 'Build',
            id: 'minpin_compat',
            label: 'pinx minpin',
            status: $ok ? CheckStatus::Pass : CheckStatus::Warn,
            detail: 'Requires Pinoox ' . $minpin . '+, installed ' . ($installed ?? 'unknown'),
            hint: $ok ? null : 'Update pinoox/pincore: composer update pinoox/pincore',
            scored: false,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function loadEnv(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $vars = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");

            if ($key !== '') {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    private function readPackageVersion(string $composerJson): ?string
    {
        if (!is_file($composerJson)) {
            return null;
        }

        $raw = file_get_contents($composerJson);

        if (!is_string($raw)) {
            return null;
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return null;
        }

        $version = $data['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    private function detectPinxCliVersion(): ?string
    {
        $candidates = [
            dirname(__DIR__, 3) . '/composer.json',
        ];

        foreach ($candidates as $path) {
            $version = $this->readPackageVersion($path);

            if ($version !== null) {
                return $version;
            }
        }

        return PinxVersion::VERSION;
    }

    private function commandVersion(string $command): ?string
    {
        $escaped = escapeshellcmd($command);
        $output = shell_exec($escaped . ' --version 2>&1');

        if (!is_string($output) || trim($output) === '') {
            return null;
        }

        $line = trim(explode("\n", $output)[0]);

        return $line !== '' ? $line : null;
    }

    private function iniSizeToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    private function majorVersion(?string $version): ?int
    {
        if ($version === null) {
            return null;
        }

        if (preg_match('/(\d+)/', $version, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
