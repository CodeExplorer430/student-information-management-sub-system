# Codebase Audit

## Summary
This audit pass reviewed the current implementation against `project-requirements.txt`, `Need-Finals.txt`, the active test suite, and the major security-sensitive code paths in the PHP MVC application.

## Confirmed Coverage
- Student profile registration, update tracking, academic records, ID generation, dashboard, and dual-status tracking are implemented.
- Major role boundaries are covered for admin, registrar, staff, faculty, and student users.
- Configurable RBAC is now implemented through `roles`, `permissions`, and `role_permissions`, with admin screens for user-role assignment and permission-matrix updates.
- Student self-service request submission, registrar/staff queue handling, and reporting/export screens are now part of the active system scope.
- Quality gates are in place through PHPUnit, Codeception, PHPStan, PHP CS Fixer, and Composer dependency audit.
- SQL schema and seed scripts exist as first-class project artifacts.
- Bootstrap and Tabler are now self-hosted locally, and the runtime no longer depends on jsDelivr for core shell assets.

## Security Review Notes
- CSRF validation is enforced on protected POST routes.
- Session hardening is configurable and now explicitly covered in tests.
- Security headers are emitted by the router and are now explicitly covered in acceptance tests.
- File upload handling now has direct regression coverage for invalid MIME, oversize rejection, and failed storage moves.
- Generated ID file absence is handled with a safe redirect and explicit user feedback.
- Request queue assignment now resolves queue-manageable users from permissions rather than a hardcoded role list.
- Multi-role permission aggregation is now covered through repository and integration tests.

## Residual Risks / Technical Debt
- The original Twig requirement remains an intentional deviation; the current renderer is native PHP views.
- Final production validation is now expected to run through `bash scripts/deployment-smoke.sh` against the real Apache/XAMPP or production URL.
- UI refinement can continue, but the remaining design work is polish rather than a functional gap.
- The app now self-hosts framework assets; any remaining runtime validation gap is operational execution of the smoke flow against the intended deployment target.
