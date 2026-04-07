# Database Import Snapshots

These files are direct import snapshots generated from the canonical SQL inputs:

- `database/migrations/<driver>/*.sql`
- `database/seeds/<driver>/*.sql`

Use `composer migrate` and `composer seed` when running the application through
its normal setup flow. The import snapshots are for empty database bootstraps,
classroom demos, local inspection, or phpMyAdmin/mysql/sqlite clients that need a
single SQL file.

## MySQL / MariaDB

Use this for the supported runtime database:

```bash
mysql --default-character-set=utf8mb4 -u <user> -p student_information_management < database/import/mysql/001_schema_and_demo.sql
```

The target database should already exist:

```sql
CREATE DATABASE student_information_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## SQLite

SQLite remains test/local-only, but a direct import snapshot is available for
inspection:

```bash
sqlite3 /tmp/student-information-management.sqlite < database/import/sqlite/001_schema_and_demo.sql
```

## Demo Credentials

The direct import snapshots embed a password hash for the demo password:
`Password123!`.

Rotate demo credentials before any shared deployment. The Composer seed path
hashes the configured `DEFAULT_PASSWORD` from `.env`; direct SQL imports do not.
