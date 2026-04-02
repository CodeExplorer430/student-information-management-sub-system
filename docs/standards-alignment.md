# Standards Alignment Summary

## Quality and architecture
- ISO/IEC 25010:2023: addressed through modular design, maintainability-oriented layering, usability-focused admin UI, reliability checks, and explicit testing.
- PSR-4 and PSR-12: enforced through Composer autoloading and PHP CS Fixer configuration.

## Secure development and application security
- ISO/IEC 27001:2022 and ISO/IEC 27002:2022: reflected through access control, configuration separation, audit logging, and dependency governance.
- ISO/IEC 27034: reflected through secure coding patterns, request validation, output escaping, and deployment guidance.
- NIST SSDF: reflected through repeatable setup, automated checks, vulnerability-aware dependency policy, and documented secure development workflow.
- OWASP ASVS and OWASP Top 10: addressed through authn/authz controls, CSRF protection, secure session handling, injection prevention, output encoding, and file upload controls.
- CWE Top 25: key mitigations include prepared statements, input validation, access checks, and structured error handling.

## Privacy-aware handling
- ISO/IEC 27701: reflected through minimization of exposed personal data, local-only secrets management, access restrictions, and auditability of profile updates.

## Caveat
This repository implements technical controls and documentation aligned with the requested standards. It does not claim formal certification or complete organizational compliance by itself.
