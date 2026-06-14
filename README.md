# Pinx CLI

**The developer CLI for single-app Pinoox projects** — scaffold, run, migrate, build, and ship `.pinx` packages without touching a multi-app manager.

Built on [`pinoox/pincore`](https://github.com/pinoox/pincore) and the [`pinoox/app`](https://github.com/pinoox/app) template. Your project root **is** the app: one `app.php`, one package, one workflow.

---

## Quick start

Install Pinx once, create a new app, run it:

```bash
composer global require pinoox/pinx-cli

pinx new my-shop              # suggests com_my_shop — confirm or edit in the wizard
cd my-shop
cp .env.example .env          # set DB_* if you use a database
pinx setup                    # platform + app migrate, seed, and patch
pinx dev                      # http://127.0.0.1:8000
```

Add Composer’s global `bin` to your `PATH` if `pinx` is not found:

- Linux / macOS: `~/.composer/vendor/bin` or `~/.config/composer/vendor/bin`
- Windows: `%APPDATA%\Composer\vendor\bin`

| Step | What it does |
|------|----------------|
| `composer global require` | Installs the `pinx` command on your machine |
| `pinx new my-shop` | Scaffolds from `pinoox/app`; wizard suggests a 3-part package (e.g. `com_my_shop`) |
| `.env` | Database and project paths — copy from `.env.example` |
| `pinx setup` | One-shot: platform/app migrations → platform/app seeders → platform/app patches |
| `pinx dev` | PHP dev server; starts Vite too when a frontend stack is configured |

Package names follow `com_{vendor}_{name}` — e.g. `com_acme_shop`, `ir_yekdo_app`. Already inside an empty folder? Use `pinx init` instead of `pinx new`.

**Optional check before `setup`:** `pinx doctor` reports PHP, layout, env, DB, and build readiness.

---

## Alternative: `composer create-project`

No global install — the template ships with `bin/pinx` inside the project:

```bash
composer create-project pinoox/app my-shop
cd my-shop
cp .env.example .env
pinx setup
pinx dev
```

---

## What makes single-app different

Classic Pinoox installs keep many apps under `apps/` and pick one at runtime. **Single-app** flattens that:

- `app.php` at the project root holds package identity and pinx settings
- `Controller/`, `Model/`, `routes/`, `theme/` live at the root — not inside `apps/{package}/`
- `platform/` holds local routing and launcher config (excluded from `.pinx` builds)
- Pinx always targets **your** app — no package picker, no manager UI

```
my-shop/                    ← project root = app root
├── app.php                 ← package, version, pinx.sign, frontend.stack
├── Controller/ Model/ routes/ theme/
├── platform/               ← dev host + deploy layer (local only)
├── bin/pinx                ← project-local CLI entry
└── vendor/pinoox/pincore   ← framework
```

See the [pinoox/app README](../app) for the full layout and config layers.

---

## Installation

| Where | How | When to use |
|-------|-----|-------------|
| **Global** | `composer global require pinoox/pinx-cli` | Recommended — `pinx new` and `pinx init` from anywhere |
| **Per project** | Shipped as `bin/pinx` in `pinoox/app` | After `composer create-project` — no global install needed |
| **Monorepo** | `composer require pinoox/pinx-cli:@dev` with a path repo | Developing pinx-cli alongside `packages/app` |

```bash
pinx -v          # quick one-line version
pinx version     # version, install path, and update check
pinx self-update # upgrade global or project pinx install
pinx list        # grouped command overview
pinx help setup  # detail for one command
```

---

## Day-to-day workflow

```bash
pinx dev                    # local server (+ Vite when app.php → frontend.stack is set)
pinx dev --open             # open browser after start
pinx dev --no-frontend      # PHP only

pinx migrate                # run app migrations (--platform runs platform first)
pinx migrate:st             # migration status
pinx migrate:cr create_products_table

pinx db:list                # platform + app connections (--test, --json)
pinx db:show platform       # connection details
pinx db:test                # probe connectivity

pinx user:create admin --password=secret --role=admin
pinx role:list
pinx permission:list
pinx token:list
pinx file:list

pinx make controller ProductController
pinx make model ProductModel
pinx make migration create_products_table
pinx make portal ShopService

pinx routes                 # list named actions (--validate, --json)
pinx test                   # run app tests (Pest)
```

**Frontend** (when `theme/` uses Vue/React + Vite):

```bash
pinx fe:info                # stack, npm scripts, paths
pinx fe:i                   # npm install
pinx fe:d                   # Vite dev server
pinx fe:b                   # production build
pinx fe:sc --stack=vue      # scaffold starter files
```

**Dependencies:**

```bash
pinx deps:st                # Composer + npm status
pinx deps:i                 # install all
pinx deps:up                # update all
```

**Pinker** (build cache):

```bash
pinx pinker:st              # cache vs source
pinx pinker:rb              # rebuild
pinx pinker:df              # diff
```

---

## Ship to production

Build a `.pinx` package for installation on a full Pinoox platform (Manager → Applications):

```bash
pinx build                  # → export/*.pinx
pinx build -o /tmp/shop.pinx
pinx release --bump=patch   # bump version in app.php + build
pinx release --sign         # sign when key is configured in app.php → pinx.sign
```

`pinx build` applies sensible defaults (excludes `vendor/`, `bin/`, `.env`, `platform/`, dev tooling). Override in `app.php` only when needed:

```php
'build' => [
    'exclude' => ['my-private-notes/'],
    'composer' => false,
],
'pinx' => [
    'sign' => [
        'enabled' => false,
        'key' => null,
        'key_id' => null,
    ],
],
```

---

## App detection

Pinx walks up from the current working directory until it finds a valid single-app project:

1. `app.php` exists and returns an array with a non-empty `package` key
2. `pinoox/pincore` is required in `composer.json`, or `vendor/pinoox/pincore` / a local `pincore/` clone is present

Override the detected package with environment variables:

| Variable | Purpose |
|----------|---------|
| `PINX_PACKAGE` | Force CLI target package |
| `PINOOX_DEV_APP` | Alias for `PINX_PACKAGE` |
| `PINX_DEV=1` | Dev mode (set automatically by pinx when delegating to pincore) |

The package can also be resolved from `platform/apps.config.php` when the app is registered with path `~`.

---

## `pinx list` — grouped commands

Run `pinx list` for a sectioned overview. Shorthand aliases appear in brackets. Filter by prefix or use `--short` for names only.

```
Project        Scaffold and inspect the single-app project
Development    Local server and day-to-day workflow
Database       Connections, migrations, and seeders
Patches        Data patches and one-off app updates
Build & release  Package and ship .pinx artifacts
Scaffolding    Generate controllers, models, tests, and more
Users          Create and manage app users from CLI
Roles & permissions  Manage roles and permission keys
Tokens         Session tokens and API keys
Files          Uploaded files and storage records
Routes         Named actions and route diagnostics
Dependencies   Composer and npm dependency tooling
Frontend       Theme assets, Vite, and npm scripts
Schedule       Cron tasks from schedule.php
Pinker         Build cache and override management
Quality & docs Tests and API documentation
```

Examples:

```bash
pinx list
pinx list migrate
pinx list --short
pinx list --raw
```

---

## Command reference

### Project

| Command | Aliases | Description |
|---------|---------|-------------|
| `new` | — | Scaffold from `pinoox/app` (wizard or flags) |
| `init` | — | Initialize the current directory (`--force` to overwrite) |
| `setup` | — | DB: platform + app migrate, seed, and patch |
| `doctor` | `dr` | Health check — `--json`, `--skip-db`, `--skip-frontend` |
| `info` | `inf` | Show metadata from `app.php` |

### Development

| Command | Description |
|---------|-------------|
| `dev` | Dev server; Vite when `frontend.stack` is vue/react |

### Database

| Command | Aliases | Description |
|---------|---------|-------------|
| `migrate:run` | `migrate` | Run app migrations (`--platform` runs platform first) |
| `migrate:status` | `migrate:st` | Migration status |
| `migrate:rollback` | `migrate:rb` | Rollback last batch (`--ignore-fk`) |
| `migrate:create <name>` | `migrate:cr` | Create migration file |
| `migrate:platform` | `migrate:pl` | Platform migrations only |
| `seeder:run` | `seed` | Run seeders (`-c` class) |
| `db:list` | `databases` | List platform and app connections (`--test`, `--json`) |
| `db:show [target]` | `database:show` | Show connection details |
| `db:test [target]` | `database:test` | Test connectivity (or ad-hoc `--host`, `--database`, …) |
| `db:create [target]` | `database:create`, `make:db` | Configure platform or app DB (`--set`, `--driver`, …) |
| `db:update [target]` | `database:update` | Update connection settings |
| `db:prefix [package] [prefix]` | `database:prefix` | Change app table prefix (`--use`) |

### Patches

| Command | Aliases | Description |
|---------|---------|-------------|
| `patch:run` | `patch` | Run pending patches |
| `patch:status` | `patch:st` | Patch status |
| `patch:rollback` | `patch:rb` | Rollback last patch batch |

### Users

| Command | Aliases | Description |
|---------|---------|-------------|
| `user:create` | `make:user` | Create a user (`--username`, `--password`, `--email`, `--role`) |
| `user:list` | `users` | List users (`--status`, `--json`) |
| `user:show` | — | Show one user |
| `user:update` | — | Update profile fields |
| `user:status` | — | Set status (`active`, `inactive`, `suspend`, `pending`) |
| `user:password` | `user:passwd` | Admin password reset (`--revoke-sessions`) |
| `user:delete` | — | Delete user (`--force`) |
| `user:role` | `user:role:assign` | Attach or sync roles (`--role`, `--sync`) |

### Roles & permissions

| Command | Aliases | Description |
|---------|---------|-------------|
| `role:list` | `roles` | List roles (`--json`) |
| `role:create` | `make:role` | Create role (`--key`, `--name`, `--description`) |
| `role:show <role>` | — | Show role (`--permissions`, `--json`) |
| `role:update <role>` | — | Update role fields |
| `role:delete <role>` | — | Delete role (`--force`) |
| `role:permission <role>` | `role:permissions` | Attach/detach permissions (`--attach`, `--detach`, `--sync`) |
| `permission:list` | `permissions` | List permissions (`--json`) |
| `permission:create` | `make:permission` | Create permission |
| `permission:show <permission>` | — | Show permission (`--roles`, `--json`) |
| `permission:delete <permission>` | — | Delete permission (`--force`) |

### Tokens

| Command | Aliases | Description |
|---------|---------|-------------|
| `token:list` | `tokens` | List session tokens (`--json`) |
| `token:show <token>` | — | Show token (`--reveal`, `--json`) |
| `token:create` | `make:token` | Create token (`--user`, `--name`, `--lifetime`, …) |
| `token:update <token>` | — | Update token metadata or lifetime |
| `token:delete <token>` | `token:remove` | Delete token (`--force`) |
| `token:revoke-user <user>` | `token:revoke` | Revoke all tokens for a user |
| `token:purge` | `token:cleanup` | Delete expired tokens (`--force`, `--json`) |

### Files

| Command | Aliases | Description |
|---------|---------|-------------|
| `file:list` | `files` | List uploads (`--group`, `--json`) |
| `file:show <file>` | — | Show file metadata |
| `file:update <file>` | — | Update metadata or access |
| `file:delete <file>` | `file:remove` | Delete record and/or storage (`--db-only`, `--storage-only`, `--force`) |
| `file:purge` | `file:cleanup` | Bulk delete by group or age (`--group`, `--older-than`, `--force`) |

### Build & release

| Command | Aliases | Description |
|---------|---------|-------------|
| `build` | `bld` | Build `.pinx` package |
| `release` | `rel` | Version bump + build (`--bump`, `--sign`) |

### Scaffolding

| Command | Aliases | Description |
|---------|---------|-------------|
| `make <type> <name>` | `mk` | controller, model, migration, patch, portal, form-request, seeder, test |

### Routes

| Command | Description |
|---------|-------------|
| `route:actions` / `routes` | List named actions (`--validate`, `--json`) |

### Dependencies

| Command | Aliases | Description |
|---------|---------|-------------|
| `deps:status` | `deps:st` | Composer + npm status |
| `deps:install` | `deps:i` | Install dependencies |
| `deps:update` | `deps:up` | Update dependencies |

Legacy umbrella commands `deps` and `dep` still accept `status`, `install`, `update` as the first argument.

### Frontend

| Command | Aliases | Description |
|---------|---------|-------------|
| `fe:info` | `fe:inf` | Theme stack and npm scripts |
| `fe:install` | `fe:i` | `npm install` |
| `fe:build` | `fe:b` | Production build |
| `fe:dev` | `fe:d` | Vite dev server |
| `fe:scaffold` | `fe:sc` | Starter files (`--stack=vue\|react\|twig`) |

Legacy `frontend` / `fe` accept `info`, `install`, `build`, `dev`, `scaffold` as the first argument.

### Schedule

| Command | Aliases | Description |
|---------|---------|-------------|
| `schedule:list` | `sched:ls` | List cron tasks from `schedule.php` |
| `schedule:run` | `sched:run` | Run due tasks (`--dry-run`) |

### Pinker

| Command | Aliases | Description |
|---------|---------|-------------|
| `pinker:status` | `pinker:st` | Cache vs source |
| `pinker:rebuild` | `pinker:rb` | Rebuild cache |
| `pinker:diff` | `pinker:df` | Show differences |
| `pinker:clear` | `pinker:cl` | Clear cache |
| `pinker:overrides` | `pinker:ov` | List overrides |

### Quality & docs

| Command | Description |
|---------|-------------|
| `test` / `pest` | Run app tests (`--unit`, `--feature`) |
| `api:docs` | REST API documentation |
| `graphql:docs` | GraphQL schema documentation |

### Meta

| Command | Aliases | Description |
|---------|---------|-------------|
| `list` | — | Grouped command overview |
| `version` | `ver` | CLI version, install mode, and Packagist update check |
| `self-update` | `self:up` | Update pinx to the latest Packagist release via Composer |

---

## `pinx doctor` in depth

Doctor runs a structured diagnostic and suggests fix commands when something fails:

| Group | Checks |
|-------|--------|
| **Project** | `app.php`, package identity, `platform/` layout |
| **Runtime** | PHP version (≥ 8.1), extensions, writable paths |
| **Dependencies** | Composer vendor, optional Node/npm |
| **Environment** | `.env` presence and key variables |
| **Database** | Connection (skippable with `--skip-db`) |
| **Frontend** | Theme stack, `package.json` (skippable with `--skip-frontend`) |
| **Build** | Export readiness, icon, version fields |

```bash
pinx doctor
pinx doctor --skip-db
pinx doctor --json          # CI-friendly report
pinx doctor --no-fixes      # hide suggested commands
```

---

## Monorepo development

When working inside the [pinoox/pinoox](https://github.com/pinoox/pinoox) repository, `pinx new` resolves the template from `packages/app` automatically.

```bash
cd packages/pinx-cli
composer install

# scaffold a throwaway app (skip install to wire path repos yourself)
php bin/pinx new ../tmp-my-app --package=com_demo_shop --no-install

cd ../tmp-my-app
composer config repositories.pinx-cli path ../pinx-cli
composer require pinoox/pincore pinoox/pinx-cli:@dev
cp .env.example .env
php bin/pinx doctor
php bin/pinx setup
php bin/pinx dev
```

After `composer install` on a platform checkout, apply the pincore overlay for `DevApp` and improved `pinx:build`:

```bash
php packages/apply-pincore-overlay.php
```

---

## Requirements

- **PHP** ≥ 8.1 with extensions required by `pinoox/pincore`
- **Composer** 2.x
- **Node.js** + npm — only when using Vite/Vue/React frontends
- **Database** — MySQL/MariaDB or whatever your `.env` configures (optional for static/Twig-only apps)

---

## Related packages

| Package | Role |
|---------|------|
| [`pinoox/app`](../app) | `composer create-project` template — root app layout |
| [`pinoox/pincore`](https://github.com/pinoox/pincore) | HMVC framework (migrations, routing, CLI engine) |
| [`pincore-overlay`](../pincore-overlay) | Monorepo patches (`DevApp`, `pinx:build` fixes) |

**Publish targets:** `composer global require pinoox/pinx-cli` · `composer create-project pinoox/app` · enable **Template repository** on `github.com/pinoox/app`.

---

## License

MIT — see [composer.json](composer.json).
