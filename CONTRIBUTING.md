# Contributing Guide

## Overview
This repository uses `Git Flow` with protected long-lived branches and
short-lived topic branches:

- `main`: production-ready history only
- `develop`: integration branch for upcoming work
- `feature/<ticket-or-scope>`: normal feature work branched from `develop`
- `release/<version>`: release stabilization branched from `develop`
- `hotfix/<version-or-scope>`: production fixes branched from `main`

Keep feature and hotfix branches focused. Rebase or merge from their source
branch often enough to avoid drift.

## Local setup
1. Copy `.env.example` to `.env` and configure the local database.
2. Install dependencies with `composer install`.
3. Run `composer migrate`.
4. Run `composer seed` when you want the demo dataset.
5. Start the app with `composer serve`.

## Required quality checks
Run these before opening or updating a pull request:

- `composer check`
- `composer test`
- `composer test:e2e`

Use these when you need narrower feedback while iterating:

- `composer analyse`
- `composer format`
- `composer coverage:check`

The repository standard is PSR-12 compliance and `declare(strict_types=1);`
retention in PHP files.

## Pull request expectations
- Open pull requests against `develop` for normal feature work.
- Open pull requests against `main` only for approved `hotfix/*` branches.
- Keep pull requests small enough for clear review and rollback.
- Update docs when behavior, setup, deployment, or operator workflow changes.
- Include screenshots for visible UI changes.
- Call out schema changes, new environment variables, and rollback concerns.
- Do not remove legacy static HTML references unless the task explicitly
  requires it.

## Branch and release flow
### Feature flow
1. Branch `feature/<scope>` from `develop`.
2. Implement the change and run the required checks.
3. Open a pull request into `develop`.
4. Merge only after required checks and review pass.

### Release flow
1. Branch `release/<version>` from `develop`.
2. Limit changes to stabilization, documentation, and release blockers.
3. Merge the release branch into `main` after approval.
4. Tag the merge commit as `v<major>.<minor>.<patch>`.
5. Merge the same release branch back into `develop`.

### Hotfix flow
1. Branch `hotfix/<scope>` from `main`.
2. Fix the production issue and run the required checks.
3. Merge the hotfix branch into `main`.
4. Tag the fix if it creates a new production version.
5. Merge the same hotfix changes back into `develop`.

## Reviews and ownership
- CODEOWNERS review should be required after `.github/CODEOWNERS` is updated
  with the real GitHub owners for this repository.
- Infrastructure, workflow, deployment, and security-affecting changes should
  be reviewed by maintainers who understand the operational runbooks.
- Treat docs under `docs/` as part of the deliverable, not optional follow-up.

## Repository automation
- `CI / strict-check` is the required application-quality status check.
- `Docs and Repo Metadata / markdown-and-actions` is the required repository
  governance status check.
- Deployments are driven by GitHub Actions and the repo-owned
  `scripts/deploy-vps.sh` workflow, not by ad hoc shell sessions on the
  server.

See `README.md`, `docs/github-repository-setup.md`, and
`docs/release-process.md` for the maintainer-facing workflow details.
