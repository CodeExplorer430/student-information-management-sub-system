# Security Controls

## Implemented controls
- Prepared statements for all database operations.
- CSRF tokens on POST requests, with invalid-token rejection redirected safely instead of surfacing raw exceptions.
- Session regeneration on login plus configurable cookie hardening through `SESSION_SAME_SITE` and `SESSION_SECURE`.
- Role-based access controls for admin, registrar, staff, faculty, and student accounts, with student ownership checks on self-service routes.
- Output escaping through centralized PHP view helpers and escaped templates.
- MIME-type and file-size validation for profile photo uploads.
- Security headers in the router for clickjacking, MIME sniffing, CSP, referrer policy, and browser capability reduction.
- Audit logging for create, update, and status transition actions.
- Dependency scanning through Composer audit and Roave security advisories.

## Secure development workflow
- `composer analyse` runs PHPStan static analysis.
- `composer format` enforces PSR-12 style.
- `composer test` runs unit and integration suites.
- `composer coverage:check` enforces `100%` PHPUnit line, method, and class coverage for handwritten runtime code.
- `composer check` runs the full strict gate through the repo-owned Docker validator for local/CI parity.
- `composer test:e2e` provides end-to-end coverage using Codeception.
- `bash scripts/deployment-smoke.sh` provides deployed-environment smoke validation for health, headers, cookies, and authenticated route access.

## Operational guidance
- Use MySQL/MariaDB credentials from `.env`, never hardcode them.
- Treat MySQL as the normal runtime database and keep SQLite limited to the automated test path.
- Limit academic record access to registrar/admin/faculty users, with student access restricted to self-view only.
- Enable `SESSION_SECURE=true` when the app is served over HTTPS.
- Keep `storage/app/private/uploads` non-public.
- Rotate default demo credentials before any shared deployment.
- Treat `database/import/*` as demo/local bootstrap snapshots because they embed
  a demo password hash for `Password123!`.
- Review logs in `storage/logs/app.log` during testing and troubleshooting.
- Use `php bin/console backup:create`, `backup:verify`, `backup:export`, `backup:push`, `backup:remote:list`, `backup:pull`, `backup:import`, `backup:drill`, `backup:prune`, `php bin/console env:check`, `php bin/console health:check`, `bash scripts/deployment-smoke.sh`, and `docs/deployment-checklist.md` as the primary shared-deployment verification and recovery flow.
