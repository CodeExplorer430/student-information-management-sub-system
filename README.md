# Bestlink SIS

Clean MVC PHP application for student profile registration, profile updates,
academic records viewing, request-center workflows, role-based
administration, reporting/export, student ID generation, status tracking, and
an operational dashboard.

The application models two separate student status concepts:

- workflow/request status: `Pending`, `Under Review`, `Approved`, `Rejected`,
  `Completed`
- enrollment status: `Active`, `Dropped`, `Graduated`, `On Leave`

## Highlights
- Student profile registration, self-service updates, and audit trail
- Academic records viewing with role and ownership boundaries
- Request center with queue management, notes, attachments, due dates, and
  notifications
- Configurable RBAC with multi-role aggregation and admin role management
- Student ID generation with preview, download, print, barcode, and QR
  verification
- Reporting, CSV export, diagnostics, health checks, backups, and operational
  alerts
- Responsive Tabler and Bootstrap shell with role-aware dashboards

## Stack
- PHP 8.4+ with `declare(strict_types=1);`
- AltoRouter, PDO, native PHP view templates, and `vlucas/phpdotenv`
- Tabler UI on top of Bootstrap 5 with self-hosted assets
- GD, `chillerlan/php-qrcode`, and `picqer/php-barcode-generator`
- PHPUnit, Codeception, PHPStan, PHP CS Fixer, Roave Security Advisories

## Documentation Map
- `CONTRIBUTING.md`: contributor workflow and Git Flow branch usage
- `docs/architecture.md`: MVC and runtime architecture
- `docs/deployment-checklist.md`: Linux VPS and Apache deployment runbook
- `docs/github-repository-setup.md`: GitHub settings, protections, secrets,
  and environments
- `docs/release-process.md`: release, tag, hotfix, and deployment flow
- `docs/requirements-coverage.md`: requirement-by-requirement coverage summary
- `docs/codebase-audit.md`: audit notes and residual risks
- `docs/security-controls.md`: secure-development and runtime controls
- `docs/standards-alignment.md`: standards mapping and caveats

## Local Setup
1. Install dependencies with `composer install`.
2. Start Apache and MySQL in XAMPP or your local MySQL service.
3. Create the database:
   `CREATE DATABASE student_information_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
4. Copy `.env.example` to `.env` and configure the MySQL credentials.
5. Run `composer migrate`.
6. Run `composer seed` if you want the canonical demo dataset.
7. Start the app with `composer serve`.

Use `composer reset-db` when you want a clean local rebuild of the seeded demo
state.

## Demo Credentials
- `admin@bcp.edu`
- `registrar@bcp.edu`
- `staff@bcp.edu`
- `faculty@bcp.edu`
- `student@bcp.edu`

Default password: `Password123!`

## Quality Commands
- `composer test`
- `composer test:e2e`
- `composer analyse`
- `composer coverage:check`
- `composer format`
- `composer check`
- `composer check:docker`
- `composer check:strict`
- `php bin/console env:check`
- `php bin/console health:check`
- `php bin/console health:check --json`
- `php bin/console backup:run --push-remote --keep=10 --remote-keep=10 --json`
- `php bin/console ops:alerts:check --json`
- `bash scripts/release-check.sh`
- `bash scripts/deployment-smoke.sh --url=https://your-host`

## Repository Workflow
This repository now documents a `Git Flow` process:

- `develop` is the integration branch for day-to-day work.
- `main` contains production-ready history.
- `feature/*` branches target `develop`.
- `release/*` branches stabilize upcoming releases.
- `hotfix/*` branches start from `main` and merge back to both `main` and
  `develop`.

Required GitHub checks:

- `CI / strict-check`
- `Docs and Repo Metadata / markdown-and-actions`

See `CONTRIBUTING.md` and `docs/github-repository-setup.md` for the full
branch protection, review, and release settings.

## GitHub Actions
The repository includes three GitHub Actions workflows:

- `ci.yml`: runs the strict validator gate on pull requests and protected
  branches
- `docs.yml`: lints Markdown and GitHub Actions workflow files
- `deploy-vps.yml`: deploys to a Linux VPS or VM for staging and production

The deployment workflow uses the repo-owned `scripts/deploy-vps.sh` helper and
expects GitHub Environments to provide the deploy host, path, SSH key, app
URL, and smoke-test credentials.

## Deployment Overview
- Point Apache at `current/public` on the server.
- Keep `.env`, logs, uploads, generated IDs, sessions, and backups under the
  server `shared/` directory.
- Run production deployments through GitHub Actions or
  `scripts/deploy-vps.sh`, not by editing the live server in place.
- Use the backup commands before migrations and rely on
  `bash scripts/deployment-smoke.sh` for deployed-environment verification.

The full operator runbook lives in `docs/deployment-checklist.md`.

## Notes
- `AGENTS.md` is intentionally gitignored and remains local-only.
- The app runtime is MySQL and MariaDB first; SQLite remains test-only.
- Docker is the supported path for the strict local and CI quality gate.
- The root-level HTML mockups remain as legacy visual references unless a task
  explicitly removes them.
- The canonical seeded dataset is 3 students: `Aira Mendoza`, `Paolo Lim`,
  and `Leah Ramos`.
