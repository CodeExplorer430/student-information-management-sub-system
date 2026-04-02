# GitHub Repository Setup

## Purpose
Use this checklist when creating or hardening the GitHub repository around the
documented `Git Flow` process, required checks, and GitHub Actions delivery
workflow.

## Branch structure
- Create `main` as the production branch.
- Create `develop` as the integration branch.
- Use `feature/*`, `release/*`, and `hotfix/*` as short-lived supporting
  branches.
- Set `develop` as the default branch so feature work and normal pull requests
  land on the integration line by default.
- Keep `main` protected for releases and hotfixes.

## Branch protection
### `develop`
- Require pull requests before merging.
- Require at least 1 approval.
- Dismiss stale approvals on new commits.
- Require conversation resolution before merge.
- Require these status checks:
  - `CI / strict-check`
  - `Docs and Repo Metadata / markdown-and-actions`
- Block force pushes and branch deletion.

### `main`
- Require pull requests before merging.
- Require at least 1 approval.
- Require review from code owners after `.github/CODEOWNERS` is updated with
  the real GitHub user or team names.
- Dismiss stale approvals on new commits.
- Require conversation resolution before merge.
- Require these status checks:
  - `CI / strict-check`
  - `Docs and Repo Metadata / markdown-and-actions`
- Restrict direct pushes to repository administrators only if emergency policy
  requires it.
- Block force pushes and branch deletion.

## Repository metadata
- Keep `.github/pull_request_template.md` active.
- Enable the issue forms under `.github/ISSUE_TEMPLATE/`.
- Keep `.github/CODEOWNERS` pointed at `@CodeExplorer430` until a real GitHub
  team exists to replace the single-maintainer ownership model.
- Keep labels lightweight and operationally meaningful, for example:
  - `bug`
  - `enhancement`
  - `documentation`
  - `security`
  - `release`
  - `hotfix`
  - `blocked`

## GitHub Environments
Create at least these environments:

- `staging`
- `production`

Recommended environment settings:

- require manual approval for `production`
- restrict who can approve `production`
- keep `staging` unblocked for automatic deploys from `develop`

## Required environment variables
Set these as GitHub Environment variables:

- `APP_URL`
- `DEPLOY_PATH`
- `DEPLOY_PHP_BIN`
- `DEPLOY_COMPOSER_BIN`
- `DEPLOY_KEEP_RELEASES`

Suggested defaults:

- `DEPLOY_PHP_BIN=php`
- `DEPLOY_COMPOSER_BIN=composer`
- `DEPLOY_KEEP_RELEASES=5`

## Required environment secrets
Set these as GitHub Environment secrets:

- `DEPLOY_HOST`
- `DEPLOY_PORT`
- `DEPLOY_USER`
- `DEPLOY_SSH_PRIVATE_KEY`
- `DEPLOY_SMOKE_ADMIN_EMAIL`
- `DEPLOY_SMOKE_ADMIN_PASSWORD`
- `DEPLOY_SMOKE_STUDENT_EMAIL`
- `DEPLOY_SMOKE_STUDENT_PASSWORD`

## Server expectations
The deployment workflow assumes:

- a Linux VPS or VM reachable over SSH from GitHub Actions
- PHP 8.4+ and Composer installed on the host
- Apache configured to serve `DEPLOY_PATH/current/public`
- a server directory layout that includes:
  - `DEPLOY_PATH/current`
  - `DEPLOY_PATH/releases`
  - `DEPLOY_PATH/shared/.env`
  - `DEPLOY_PATH/shared/storage/logs`
  - `DEPLOY_PATH/shared/storage/framework/sessions`
  - `DEPLOY_PATH/shared/storage/app/private/uploads`
  - `DEPLOY_PATH/shared/storage/app/public/id-cards`
  - `DEPLOY_PATH/shared/storage/backups`

## Workflow behavior
- `ci.yml` runs on pull requests and pushes to `develop`, `main`, `release/*`,
  and `hotfix/*`.
- `docs.yml` lints documentation and workflow metadata when repo docs or
  GitHub files change.
- `deploy-vps.yml` deploys:
  - automatically to `staging` from pushes to `develop`
  - automatically to `production` from tags matching `v*`
  - manually to `staging` or `production` through `workflow_dispatch`

## Recommended release conventions
- Release branches should be named `release/<version>`, such as
  `release/1.4.0`.
- Production tags should use semantic versioning, such as `v1.4.0`.
- Hotfix branches should be named `hotfix/<scope>` or `hotfix/<version>`.
- Merge release and hotfix changes back into `develop` after `main` is
  updated.
