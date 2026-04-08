# Requirements Coverage

## Summary
The current codebase covers the major functional modules in `Need-Finals.txt` and `project-requirements.txt`, and now also includes RBAC administration with own-only permission boundaries, student self-service requests, richer registrar/staff queue handling, notification delivery logging, reporting/export views, local vendor assets, database import snapshots, and an environment verification command. The one standing requirement deviation is still the approved renderer change from Twig to native PHP views. The functional and negative-path coverage is now stronger around ID issuance state, upload failure handling, security headers, session cookie policy, generated-file fallback behavior, request notes/attachments, and the reporting/notification surfaces.

## project-requirements.txt
| Requirement | Status | Notes |
| --- | --- | --- |
| Student Profile Registration | Implemented | Registration, student-number creation, guardian/contact capture, photo upload, and persisted profile records are present. |
| Student Personal Information Update | Implemented | Update flow exists and writes audit entries. |
| Academic Records Viewer | Implemented | Records can be searched and viewed; faculty access is supported. |
| Student ID Generation | Implemented | One-click generation, preview, download, print, QR verification, and generated-state listing are present. |
| Student Status Tracking | Implemented | Enrollment status and workflow status are tracked separately. |
| Clean MVC plus role-aware operations workspace | Implemented | Shared shell, request center, admin governance pages, and reporting screens now work inside the same MVC stack. |
| Configurable RBAC | Implemented | Roles, permissions, role-permission mapping, `user_roles`, permission middleware, aggregated effective permissions, and admin management screens are present. |
| Reporting and CSV export | Implemented | Admin/report viewers can inspect metrics and export request, student, audit, and notification datasets. |
| Operational workflow depth | Implemented | Requests now support priority, SLA target dates, resolution summaries, notes, attachments, and delivery-backed notifications. |
| PHP 8.4+, AltoRouter, PDO, Composer, PSR-4, PHPStan, PHP CS Fixer, Whoops, PHPUnit, Roave | Implemented | Present in the app stack and validation workflow. |
| MariaDB/MySQL runtime | Implemented | Runtime targets MySQL/MariaDB; SQLite remains test-only. |
| Direct SQL import snapshots | Implemented | Empty-database MySQL/MariaDB and local/test-only SQLite import snapshots are available under `database/import`. |
| Twig templating | Intentionally deviated | Replaced with native PHP views by approved direction change. |
| Codeception for form/integration-style coverage | Implemented | Codeception covers end-to-end browser flows; PHPUnit covers unit and integration behavior. |

## Need-Finals.txt
| Requirement | Status | Notes |
| --- | --- | --- |
| One-click ID generation based on BCP-style ID | Implemented | Student selection plus single generate action is present with derivative Bestlink card output. |
| Registration photo upload | Implemented | Upload pipeline exists and feeds ID generation. |
| Student profile tracking fields | Implemented | Student number, name, program, year, contact, and status history are shown. |
| Workflow statuses Pending / Under Review / Approved / Rejected / Completed | Implemented | Stored with timestamps, assigned personnel, and remarks. |
| Timeline / history view | Implemented | Timeline exists per student. |
| Search and filter | Implemented | Search/filter exists across the main modules, including workflow and ID-generation views. |
| Dashboard | Implemented | Summary counts and recent activity exist. |
| Student request submission and tracking | Implemented | Students can submit requests and follow them through the workflow. |
| Registrar/staff queue review | Implemented | Queue filtering, assignment, transition handling, notes, attachments, due targets, and per-request history are present. |
| Notification visibility | Implemented | In-app notifications plus email/SMS delivery logs are persisted and visible in the notification center and reporting views. |
| Status progress bar | Implemented | Present on the status timeline view. |
| Table view and timeline view | Implemented | Both are present. |
| Download and print for ID generation | Implemented | Preview, download, and print trigger exist. |
| UI/UX design for the ID and barcode | Implemented | Functional and styled, with responsive shell, collapsible sidebar, toasts, Tabler-driven dashboards, and final shared module polish now in place. |

## Test Coverage Status
The strict quality gate now enforces `100%` PHPUnit coverage for lines, methods, and classes across handwritten runtime code in `app/`, `public/`, and `bin/console`. PHPUnit coverage now includes in-process route/controller/view coverage plus a testable console kernel, while Codeception acceptance coverage remains required in `composer check` but is not part of the numeric PHPUnit threshold. `composer check` now runs through the repo-owned validator container so the full gate uses the same PHP 8.4 plus Xdebug runtime locally and in CI.

Latest local validation after the shared UI polish and database import documentation pass completed successfully through `composer check`, including PHP CS Fixer dry-run, PHPStan, PHPUnit coverage at `100%` for classes, methods, and lines, Codeception acceptance coverage, and Composer dependency audit.

### Currently covered
- login success, invalid-CSRF rejection, and protected-route guest redirects
- faculty academic-record access and denial from restricted modules
- student registration, valid photo upload, invalid MIME rejection, oversized upload rejection, and upload-move failure handling
- personal-info update and audit trail
- dashboard summary assertions
- workflow and enrollment transition persistence
- workflow filtering and timeline rendering
- student ownership boundaries across profiles, records, statuses, and ID preview
- ID generation, generated-state listing, preview, download, missing-file handling, and verification
- request-center submission, queue review, role-matrix access, admin user management, and reporting-screen access
- request note creation, notification persistence, and notification-report exports
- backend role aggregation, single-role admin assignment, immutable role slugs, and own-only permission behavior
- direct assertions on response security headers
- session cookie naming and SameSite / HttpOnly / secure-policy configuration
- validator behavior and repository search baseline

### Remaining explicit gaps
- No local functional requirement gaps remain in the implemented modules
- deployment smoke execution against the real Apache/XAMPP or production URL remains an operator-run step through `bash scripts/deployment-smoke.sh`, not part of the default local validator gate
