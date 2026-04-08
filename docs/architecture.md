# Architecture

## Overview
The application follows a clean MVC structure:

- `public/` contains the front controller and Apache rewrite entrypoint.
- `app/Controllers` contains HTTP orchestration only.
- `app/Repositories` isolates all PDO queries.
- `app/Services` contains business rules such as status transitions, auditing, file handling, and ID generation.
- `app/Views` contains native PHP templates, shared layouts, and partials converted from the original HTML mockups.
- `app/Core` provides reusable infrastructure for routing, sessions, rendering, auth, configuration, logging, and validation.

## Database strategy
- Runtime and local development use MySQL/MariaDB through PDO.
- SQLite is retained for unit/integration tests and the isolated acceptance-test runner to keep automated checks off the live MySQL dataset.
- Schema creation and seeding are driven by `bin/console`, which emits driver-specific DDL for MySQL and the test-only SQLite path.
- Direct import snapshots live under `database/import` for empty-database setup; canonical schema/data changes still belong in `database/migrations` and `database/seeds`.
- RBAC is backed by `roles`, `permissions`, `role_permissions`, and `user_roles`; the admin UI assigns one active role, role slugs are immutable after creation, and `users.role` is kept as the derived primary display role.
- `php bin/console db:summary` exposes the active driver/database and the current seeded student set for quick runtime verification.
- `php bin/console env:check` reports DB connectivity, required PHP extensions, writable storage paths, and local frontend asset presence.
- `php bin/console backup:create`, `backup:list`, `backup:verify`, `backup:export`, `backup:push`, `backup:remote:list`, `backup:pull`, `backup:import`, `backup:drill`, `backup:prune`, and `backup:restore` provide the repo-owned local backup, encrypted export, S3-compatible remote replication, drill validation, retention, and recovery path for deployments.
- Student data now separates workflow/request status from enrollment status so operational tracking does not overwrite academic standing.

## Request flow
1. Apache routes requests to `public/index.php`.
2. `config/app.php` builds the application container and dependencies.
3. `config/routes.php` registers route-to-controller mappings.
4. `App\Core\Router` applies middleware, resolves controllers, and dispatches actions.
5. Controllers call repositories and services, then return PHP-template-rendered responses through the shared `View` abstraction.

## Developer workflow
- `composer serve` is the default interactive runtime on port `8000`.
- `composer serve:e2e` mirrors the Codeception base URL on port `18081`.
- `composer reset-db` is the canonical local cleanup path and restores the 3-student demo dataset while clearing generated uploads and ID artifacts.
- `composer test:e2e` now boots a temporary local PHP server against an isolated SQLite acceptance database, runs the suite, and stops the temporary process automatically.
- Frontend framework assets are vendored under `public/assets/vendor`, so local and production-like runs do not depend on external CDNs.

## Security boundaries
- Sessions are HTTP-only with CSRF protection on state-changing requests.
- Permission checks are enforced through route middleware and controller-side access guards, with broad permissions separated from own-only permissions such as `records.view_own`.
- Uploaded files are stored outside the public web root.
- Database access uses prepared statements via PDO only.
- Audit events track profile changes and workflow transitions.
