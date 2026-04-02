# Release Process

## Purpose
This document defines the repository release and hotfix flow that matches the
documented `Git Flow` branch model and the GitHub Actions deployment pipeline.

## Normal release flow
1. Merge approved feature branches into `develop`.
2. Branch `release/<version>` from `develop`.
3. Limit release-branch changes to stabilization, documentation, and release
   blockers.
4. Run the full validation gate:
   - `composer check`
   - `composer test:e2e`
   - `bash scripts/release-check.sh` when doing an operator-driven dry run
5. Open a pull request from `release/<version>` into `main`.
6. After approval, merge into `main`.
7. Tag the merged `main` commit as `v<major>.<minor>.<patch>`.
8. Allow `deploy-vps.yml` to promote the tagged release to `production`.
9. Merge the same release branch back into `develop`.

## Hotfix flow
1. Branch `hotfix/<scope>` from `main`.
2. Implement only the production fix and its required documentation.
3. Run the same validation gate used for normal releases.
4. Open a pull request from `hotfix/<scope>` into `main`.
5. Tag the merged fix if it produces a new production version.
6. Merge the hotfix changes back into `develop`.

## Deployment flow
The production and staging deployment path is:

1. GitHub Actions runs `CI / strict-check`.
2. The workflow packages the repository snapshot.
3. The archive is copied to the server over SSH.
4. `scripts/deploy-vps.sh` runs on the server and:
   - installs production Composer dependencies
   - links the shared `.env` and persistent storage paths
   - creates and verifies a backup
   - optionally exports and pushes the backup to remote storage
   - runs database migrations
   - runs `env:check`
   - runs `health:check`
   - flips the `current` symlink to the new release
   - prunes older releases
5. GitHub Actions runs `bash scripts/deployment-smoke.sh` against the public
   URL.

## Rollback
- Use `php bin/console backup:list` to identify the desired snapshot.
- Restore with `php bin/console backup:restore <backup-id>`.
- Rerun `php bin/console env:check` and `php bin/console health:check`.
- Repoint `current` manually only if the release directory itself is known-good
  and a rollback by backup is not the right recovery path.

## Important assumptions
- Deployments target a Linux VPS or VM served by Apache.
- The server already contains `shared/.env`.
- Migrations must be backward compatible with the currently live code, or the
  deployment must be performed during a maintenance window.
- GitHub Environments hold the deploy host, path, SSH key, app URL, and
  smoke-test credentials.
