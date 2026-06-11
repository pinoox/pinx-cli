# Pinx CLI

Developer CLI for **single-app** Pinoox projects built on `pinoox/pincore`.

Pinx detects the active app from `app.php` at the project root (must contain a `package` key). All scaffold and dev commands target that folder only — no multi-app or platform package picker.

## Global install (phase 2)

```bash
composer global require pinoox/pinx-cli
pinx new
pinx new my-shop --package=com_acme_shop --yes
```

Ensure `~/.composer/vendor/bin` (or `%APPDATA%\Composer\vendor\bin` on Windows) is on your `PATH`.

## Project-local use

Shipped with [pinoox/app](../app) as `bin/pinx`.

```bash
pinx setup
pinx dev
pinx build
pinx release --bump=patch
pinx doctor
```

## App detection

A directory is treated as a Pinoox single-app project when:

1. `app.php` exists and returns an array with a non-empty `package` key
2. `composer.json` requires `pinoox/pincore` (or `vendor/pinoox/pincore` is present)

Pinx walks up from the current working directory until both conditions match.

## Commands

### Project lifecycle

| Command | Description |
|---------|-------------|
| `new` | Scaffold from `pinoox/app` template |
| `init` | Initialize current directory |
| `setup` | DB migrate + seed |
| `dev` | Dev server (+ Vite when applicable) |
| `migrate` | App migrations (`--rollback`, `--status`, `--platform`) |
| `build` | `.pinx` package |
| `doctor` | Deep health check (PHP, layout, env, DB, frontend) — `--json`, `--skip-db` |
| `release` | Version bump + build |

### App info

| Command | Description |
|---------|-------------|
| `info` | Show app metadata and layout from `app.php` |

### Scaffolding (`make`)

| Command | Description |
|---------|-------------|
| `make controller <Name>` | `Controller/` |
| `make model <Name>` | `Model/` |
| `make migration <name>` | `database/migrations/` |
| `make patch <name>` | `patches/` |
| `make portal <Name>` | `Portal/` (`--service=`) |
| `make form-request <Name>` | `Request/` |
| `make seeder <Name>` | `database/seed/` |
| `make test <Name>` | `tests/` (`--unit`, `--feature`, `--force`) |

### Routes

| Command | Description |
|---------|-------------|
| `route:actions` / `routes` | List named actions (`--validate`, `--strict`, `--json`, `--cache`) |

### Dependencies

| Command | Description |
|---------|-------------|
| `deps status` | Composer + npm manifest status |
| `deps install` | Install app dependencies |
| `deps update` | Update app dependencies |

### Frontend

| Command | Description |
|---------|-------------|
| `frontend info` / `fe info` | Theme stack and npm scripts |
| `frontend dev` / `fe dev` | Vite dev server |
| `frontend build` / `fe build` | Production build |
| `frontend install` | npm install |
| `frontend scaffold` | Starter files (`--stack=vue|react|twig`) |

### Database extras

| Command | Description |
|---------|-------------|
| `seeder:run` / `seed` | Run seeders (`-c` class) |
| `patch` / `patch:run` | Run pending patches |
| `patch:status` | Patch status |
| `patch:rollback` | Rollback last batch |

### Schedule

| Command | Description |
|---------|-------------|
| `schedule:list` | List cron tasks |
| `schedule:run` | Run due tasks (`--dry-run`) |

### Pinker

| Command | Description |
|---------|-------------|
| `pinker status` | Compare cache vs source |
| `pinker rebuild` | Rebuild Pinker cache |
| `pinker diff` | Show differences |
| `pinker clear` | Clear cache |
| `pinker overrides` | List override files |

### Docs & tests

| Command | Description |
|---------|-------------|
| `api:docs` | REST API docs (`--format=md|html`) |
| `graphql:docs` | GraphQL schema docs |
| `test` / `pest` | Run app tests |

## Examples

```bash
pinx info
pinx make controller ProductController
pinx make model ProductModel
pinx make migration create_products_table
pinx routes --validate
pinx deps install
pinx fe dev
pinx seed
pinx pinker status
pinx test --feature
```

## Monorepo install

```bash
cd packages/pinx-cli
composer install
php bin/pinx doctor
```

Template path resolves to `packages/app` automatically.
