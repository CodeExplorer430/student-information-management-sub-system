# Deployment Checklist

## Purpose
Use this checklist for Linux VPS or VM deployments served by Apache. Treat it
as an operator runbook and implementation guide, not a certification claim.

## Target layout
The documented GitHub Actions deployment expects a server layout like this:

- `/var/www/student-information-management/current`
- `/var/www/student-information-management/releases`
- `/var/www/student-information-management/shared/.env`
- `/var/www/student-information-management/shared/storage/logs`
- `/var/www/student-information-management/shared/storage/framework/sessions`
- `/var/www/student-information-management/shared/storage/app/private/uploads`
- `/var/www/student-information-management/shared/storage/app/public/id-cards`
- `/var/www/student-information-management/shared/storage/backups`

Point Apache at `current/public`.

## Server prerequisites
- Linux VPS or VM reachable over SSH
- Apache with the document root pointed at `current/public`
- PHP 8.4+ with the extensions required by the app
- Composer installed on the host
- MySQL or MariaDB accessible from the host
- Writable permissions for the shared storage paths
- `APP_ENV=production` and `APP_DEBUG=false` in the deployed `.env`
- `SESSION_SECURE=true` when the public URL uses HTTPS

## Required `.env` preparation
- Set `APP_URL` to the real public URL.
- Replace `APP_KEY=change-me`.
- Keep `DB_DRIVER=mysql` for the real runtime.
- Replace the demo `DEFAULT_PASSWORD`.
- Prefer `php bin/console migrate` for deployed schema updates; use
  `database/import/mysql/001_schema_and_demo.sql` only for an intentional empty
  database bootstrap, not over a live database.
- Set `BACKUP_EXPORT_KEY` before relying on encrypted backup export or import.
- Set `BACKUP_MAX_AGE_HOURS`, `BACKUP_REMOTE_MAX_AGE_HOURS`, and
  `BACKUP_DRILL_MAX_AGE_HOURS` to the intended operating cadence.
- Set `BACKUP_REMOTE_*` values only when remote S3-compatible replication is
  actually configured.
- Change `NOTIFY_EMAIL_DRIVER` and `NOTIFY_SMS_DRIVER` away from `log` only
  when real delivery integrations are intended.
- Configure the `DEPLOY_SMOKE_*` credentials in GitHub Environment secrets, not
  in the server `.env`.

## GitHub Actions environment setup
Create `staging` and `production` environments with these variables:

- `APP_URL`
- `DEPLOY_PATH`
- `DEPLOY_PHP_BIN`
- `DEPLOY_COMPOSER_BIN`
- `DEPLOY_KEEP_RELEASES`

Create these secrets in each environment:

- `DEPLOY_HOST`
- `DEPLOY_PORT`
- `DEPLOY_USER`
- `DEPLOY_SSH_PRIVATE_KEY`
- `DEPLOY_SMOKE_ADMIN_EMAIL`
- `DEPLOY_SMOKE_ADMIN_PASSWORD`
- `DEPLOY_SMOKE_STUDENT_EMAIL`
- `DEPLOY_SMOKE_STUDENT_PASSWORD`

## Deployment sequence
1. Validate the target branch or tag through `CI / strict-check`.
2. Confirm the server already contains `shared/.env`.
3. Trigger `deploy-vps.yml` manually, or push to:
   - `develop` for `staging`
   - a `v*` tag for `production`
4. Let `scripts/deploy-vps.sh` on the server:
   - run `composer install --no-dev`
   - create and verify a backup
   - optionally export and push the backup remotely
   - run `php bin/console migrate`
   - run `php bin/console env:check`
   - run `php bin/console health:check`
   - run `php bin/console health:check --json`
   - switch the `current` symlink
5. Let GitHub Actions run `bash scripts/deployment-smoke.sh` against the
   public URL.
6. Review `GET /health/ready`, `/admin/diagnostics`, and `storage/logs/app.log`
   after deployment.

## Backup and rollback
- `php bin/console backup:create` writes a timestamped snapshot under
  `storage/backups/<backup-id>/`.
- `php bin/console backup:verify <backup-id>` validates checksums, sizes, dump
  readability, and manifest integrity.
- `php bin/console backup:export <backup-id>` writes an encrypted export under
  `storage/backups/exports/`.
- `php bin/console backup:push <backup-id>` uploads the latest verified export
  to the configured remote bucket.
- `php bin/console backup:run --push-remote --keep=10 --remote-keep=10 --json`
  is the scheduler-friendly automation path.
- `php bin/console backup:status` reports freshness for local backups, remote
  replication, and restore drills.
- `php bin/console ops:alerts:check --json` should run after scheduled backup
  automation so stale backups and failed operations raise visible alerts.
- `php bin/console backup:restore <backup-id>` is the primary rollback path
  after a bad release.

## Deployed-environment smoke checks
- `bash scripts/deployment-smoke.sh` validates:
  - `GET /health/live`
  - `GET /health/ready`
  - login-page security headers
  - session cookie policy
  - `X-Request-Id`
  - authenticated smoke coverage for admin and student flows
- Configure the smoke credentials through GitHub Environment secrets, not in
  the repo.
- Review `storage/logs/app.log` and `/admin/diagnostics` after smoke failures.

## Troubleshooting
- If `env:check` reports missing tables, run `php bin/console migrate`.
- If `Database Connectivity: failed` appears, verify the server DB credentials
  and network path first.
- If the public smoke step fails after a successful symlink switch, inspect the
  deployed `.env`, Apache virtual host root, file permissions, and health
  output before rolling back.
- If a migration is not backward compatible with the live code, plan a
  maintenance window or refactor the migration sequence before deploying.
