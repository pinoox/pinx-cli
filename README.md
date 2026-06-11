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

Run `pinx list` for a grouped overview with descriptions and shorthand aliases in brackets. Use `pinx list --short` for names only.

## App detection

A directory is treated as a Pinoox single-app project when:

1. `app.php` exists and returns an array with a non-empty `package` key
2. `composer.json` requires `pinoox/pincore` (or `vendor/pinoox/pincore` is present)

Pinx walks up from the current working directory until both conditions match.

## Commands

### Project

| Command | Description |
|---------|-------------|
| `new` | Scaffold from `pinoox/app` template |
| `init` | Initialize current directory |
| `setup` | DB migrate platform + app, then seed |
| `doctor` | Deep health check — `--json`, `--skip-db` |
| `info` | Show app metadata from `app.php` |

### Development

| Command | Description |
|---------|-------------|
| `dev` | Dev server (+ Vite when applicable) |

### Database

| Command | Shorthand | Description |
|---------|-----------|-------------|
| `migrate:run` | `migrate` | Run app migrations (`--platform` runs platform first) |
| `migrate:status` | `migrate:st` | Migration status |
| `migrate:rollback` | `migrate:rb` | Rollback last batch (`--ignore-fk`) |
| `migrate:create <name>` | `migrate:cr` | Create migration file |
| `migrate:platform` | `migrate:pl` | Run platform migrations only |
| `seeder:run` | `seed` | Run seeders (`-c` class) |

### Patches

| Command | Shorthand | Description |
|---------|-----------|-------------|
| `patch:run` | `patch` | Run pending patches |
| `patch:status` | `patch:st` | Patch status |
| `patch:rollback` | `patch:rb` | Rollback last patch batch |

### Build & release

| Command | Description |
|---------|-------------|
| `build` | Build `.pinx` package |
| `release` | Version bump + build |

### Scaffolding

| Command | Description |
|---------|-------------|
| `make <type> <name>` | controller, model, migration, patch, portal, form-request, seeder, test |

### Routes

| Command | Description |
|---------|-------------|
| `route:actions` / `routes` | List named actions (`--validate`, `--json`) |

### Dependencies

| Command | Shorthand | Description |
|---------|-----------|-------------|
| `deps <action>` | `dep` | status, install, update (legacy) |
| `deps:status` | `deps:st` | Composer + npm status |
| `deps:install` | `deps:i` | Install dependencies |
| `deps:update` | `deps:up` | Update dependencies |

### Frontend

| Command | Shorthand | Description |
|---------|-----------|-------------|
| `frontend <action>` | `fe` | info, install, build, dev, scaffold (legacy) |
| `fe:info` | `fe:inf` | Theme stack and npm scripts |
| `fe:install` | `fe:i` | npm install |
| `fe:build` | `fe:b` | Production build |
| `fe:dev` | `fe:d` | Vite dev server |
| `fe:scaffold` | `fe:sc` | Starter files (`--stack=vue|react|twig`) |

### Schedule

| Command | Shorthand | Description |
|---------|-----------|-------------|
| `schedule:list` | `sched:ls` | List cron tasks |
| `schedule:run` | `sched:run` | Run due tasks (`--dry-run`) |

### Pinker

| Command | Shorthand | Description |
|---------|-----------|-------------|
| `pinker <action>` | — | status, rebuild, diff, clear, overrides (legacy) |
| `pinker:status` | `pinker:st` | Cache vs source |
| `pinker:rebuild` | `pinker:rb` | Rebuild cache |
| `pinker:diff` | `pinker:df` | Show differences |
| `pinker:clear` | `pinker:cl` | Clear cache |
| `pinker:overrides` | `pinker:ov` | List overrides |

### Quality & docs

| Command | Description |
|---------|-------------|
| `test` / `pest` | Run app tests |
| `api:docs` | REST API docs |
| `graphql:docs` | GraphQL schema docs |

### Meta

| Command | Description |
|---------|-------------|
| `list` | Grouped command overview |
| `version` | CLI version |

## Examples

```bash
pinx list
pinx migrate
pinx migrate:st
pinx migrate:rb
pinx migrate:cr create_products_table
pinx make controller ProductController
pinx deps:install
pinx fe:dev --open
pinx pinker:status
pinx test --feature
```

## Monorepo install

```bash
cd packages/pinx-cli
composer install
php bin/pinx doctor
```

Template path resolves to `packages/app` automatically.
