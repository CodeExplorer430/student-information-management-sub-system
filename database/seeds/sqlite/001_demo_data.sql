INSERT INTO users (name, email, password_hash, role, department, created_at, updated_at) VALUES
    ('Regina Santos', 'registrar@bcp.edu', '__DEFAULT_PASSWORD_HASH__', 'registrar', 'Registrar', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Marco Villanueva', 'staff@bcp.edu', '__DEFAULT_PASSWORD_HASH__', 'staff', 'Student Affairs', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Aira Mendoza', 'student@bcp.edu', '__DEFAULT_PASSWORD_HASH__', 'student', 'BSIT', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Prof. Luis Navarro', 'faculty@bcp.edu', '__DEFAULT_PASSWORD_HASH__', 'faculty', 'Academic Affairs', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Elena Garcia', 'admin@bcp.edu', '__DEFAULT_PASSWORD_HASH__', 'admin', 'ICT', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO roles (slug, name, description, created_at, updated_at) VALUES
    ('admin', 'System Administrator', 'Oversees configuration, users, permissions, and governance.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('registrar', 'Registrar', 'Owns student records, enrollment decisions, and approvals.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('staff', 'Operations Staff', 'Handles operational queue review and ID generation support.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('faculty', 'Faculty', 'Has authorized academic record visibility and limited dashboards.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('student', 'Student', 'Self-service access to own profile, requests, and status context.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO permissions (code, label, module, description, created_at, updated_at) VALUES
    ('dashboard.view_admin', 'View admin dashboard', 'dashboard', 'Access the admin governance dashboard.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('dashboard.view_operations', 'View operations dashboard', 'dashboard', 'Access the registrar/staff operations dashboard.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('dashboard.view_student', 'View student dashboard', 'dashboard', 'Access the student self-service dashboard.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('students.view', 'View student profiles', 'students', 'View student profile records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('students.create', 'Register student profiles', 'students', 'Create student profile records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('students.update', 'Update student profiles', 'students', 'Update student profile records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('records.view', 'View academic records', 'records', 'View academic record data.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.view', 'View status tracking', 'statuses', 'View workflow and enrollment status boards.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.transition', 'Transition workflow status', 'statuses', 'Advance or change workflow statuses.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.enrollment_transition', 'Transition enrollment status', 'statuses', 'Advance or change enrollment statuses.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('id_cards.view', 'View ID generation module', 'id_cards', 'Access ID generation, preview, and verification context.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('id_cards.generate', 'Generate student IDs', 'id_cards', 'Create printable student IDs.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('requests.create', 'Create student requests', 'requests', 'Submit self-service student requests.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('requests.view_own', 'View own requests', 'requests', 'View requests owned by the signed-in student.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('requests.view_queue', 'View request queue', 'requests', 'Review the registrar/staff request queue.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('requests.assign', 'Assign requests', 'requests', 'Assign requests to staff or registrar users.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('requests.transition', 'Transition requests', 'requests', 'Update request statuses and remarks.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('admin.users', 'Manage users', 'admin', 'Review and update system users and role assignments.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('admin.roles', 'Manage role permissions', 'admin', 'Review and update role permission mappings.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('reports.view', 'View reports', 'reports', 'View reporting surfaces and exports.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions
WHERE
    (roles.slug = 'admin')
    OR (roles.slug = 'registrar' AND permissions.code IN (
        'dashboard.view_operations', 'students.view', 'students.create', 'students.update', 'records.view',
        'statuses.view', 'statuses.transition', 'statuses.enrollment_transition',
        'id_cards.view', 'id_cards.generate',
        'requests.view_queue', 'requests.assign', 'requests.transition',
        'reports.view'
    ))
    OR (roles.slug = 'staff' AND permissions.code IN (
        'dashboard.view_operations', 'students.view', 'students.create', 'students.update', 'statuses.view',
        'statuses.transition', 'id_cards.view', 'id_cards.generate',
        'requests.view_queue', 'requests.assign', 'requests.transition'
    ))
    OR (roles.slug = 'faculty' AND permissions.code IN (
        'records.view', 'dashboard.view_operations'
    ))
    OR (roles.slug = 'student' AND permissions.code IN (
        'dashboard.view_student', 'students.view', 'students.update', 'records.view', 'statuses.view', 'id_cards.view',
        'requests.create', 'requests.view_own'
    ));

INSERT INTO user_roles (user_id, role_id, created_at)
SELECT users.id, roles.id, CURRENT_TIMESTAMP
FROM users
INNER JOIN roles ON roles.slug = users.role;

INSERT INTO user_roles (user_id, role_id, created_at)
SELECT users.id, roles.id, CURRENT_TIMESTAMP
FROM users
INNER JOIN roles ON roles.slug = 'registrar'
WHERE users.email = 'admin@bcp.edu';

INSERT INTO students (
    student_number, first_name, middle_name, last_name, birthdate, program, year_level, email, phone, address,
    guardian_name, guardian_contact, department, enrollment_status, photo_path, created_at, updated_at
) VALUES
    ('BSI-2026-1001', 'Aira', 'Lopez', 'Mendoza', '2005-03-14', 'BS Information Technology', '3', 'student@bcp.edu', '09170000001', 'Malolos, Bulacan', 'Marites Mendoza', '09170000011', 'BSIT', 'Active', 'seed-student-1.jpg', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('BSA-2026-1002', 'Paolo', 'Reyes', 'Lim', '2004-09-22', 'BS Accountancy', '4', 'paolo.lim@student.bcp.edu', '09170000002', 'Meycauayan, Bulacan', 'Jose Lim', '09170000012', 'BSA', 'Active', 'seed-student-2.jpg', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('BSC-2026-1003', 'Leah', 'Torres', 'Ramos', '2006-01-08', 'BS Criminology', '1', 'leah.ramos@student.bcp.edu', '09170000003', 'Marilao, Bulacan', 'Ramon Ramos', '09170000013', 'BSCrim', 'On Leave', 'seed-student-3.jpg', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO status_histories (student_id, status, remarks, assigned_user_id, created_at) VALUES
    (1, 'Pending', 'Student profile created.', 1, CURRENT_TIMESTAMP),
    (1, 'Under Review', 'Documents verified by registrar.', 1, CURRENT_TIMESTAMP),
    (1, 'Approved', 'Ready for ID printing.', 2, CURRENT_TIMESTAMP),
    (2, 'Pending', 'Awaiting staff review.', 2, CURRENT_TIMESTAMP),
    (3, 'Pending', 'Initial request submitted.', 1, CURRENT_TIMESTAMP),
    (3, 'Rejected', 'Missing supporting document.', 2, CURRENT_TIMESTAMP);

INSERT INTO enrollment_status_histories (student_id, status, remarks, assigned_user_id, created_at) VALUES
    (1, 'Active', 'Student admitted and eligible for enrollment.', 1, CURRENT_TIMESTAMP),
    (2, 'Active', 'Student remained in good standing.', 1, CURRENT_TIMESTAMP),
    (3, 'Active', 'Student profile created.', 1, CURRENT_TIMESTAMP),
    (3, 'On Leave', 'Leave of absence approved by registrar.', 1, CURRENT_TIMESTAMP);

INSERT INTO academic_records (student_id, term_label, subject_code, subject_title, units, grade, created_at) VALUES
    (1, '2025-2026 1st Sem', 'IT301', 'Secure Web Development', 3.0, '1.50', CURRENT_TIMESTAMP),
    (1, '2025-2026 1st Sem', 'IT305', 'Systems Integration', 3.0, '1.75', CURRENT_TIMESTAMP),
    (2, '2025-2026 1st Sem', 'AC401', 'Auditing Theory', 3.0, '1.75', CURRENT_TIMESTAMP),
    (3, '2025-2026 1st Sem', 'CR101', 'Introduction to Criminology', 3.0, '2.00', CURRENT_TIMESTAMP);

INSERT INTO student_requests (student_id, request_type, title, description, status, assigned_user_id, created_by_user_id, submitted_at, updated_at, resolved_at) VALUES
    (1, 'Profile Update', 'Update mobile number', 'Request to update registered mobile number for contact tracing and registrar records.', 'Under Review', 2, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
    (1, 'Record Certification', 'Request certified grades', 'Need a certified copy of grades for scholarship filing.', 'Pending', 1, 3, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL),
    (3, 'Leave Status Review', 'Clarify return from leave', 'Student is requesting evaluation for return from leave next term.', 'Approved', 1, 5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT INTO request_status_histories (request_id, status, remarks, assigned_user_id, created_at) VALUES
    (1, 'Pending', 'Request submitted through the student portal.', 3, CURRENT_TIMESTAMP),
    (1, 'Under Review', 'Operations staff is validating the supporting details.', 2, CURRENT_TIMESTAMP),
    (2, 'Pending', 'Awaiting registrar review.', 1, CURRENT_TIMESTAMP),
    (3, 'Pending', 'Leave evaluation request submitted.', 5, CURRENT_TIMESTAMP),
    (3, 'Approved', 'Student can proceed with registrar reactivation steps.', 1, CURRENT_TIMESTAMP);

INSERT INTO audit_logs (user_id, entity_type, entity_id, action, old_values, new_values, created_at) VALUES
    (1, 'student', 1, 'created', NULL, '{"status":"Pending"}', CURRENT_TIMESTAMP),
    (1, 'student', 1, 'status_transition', '{"status":"Pending"}', '{"status":"Approved"}', CURRENT_TIMESTAMP),
    (1, 'student', 3, 'enrollment_status_transition', '{"enrollment_status":"Active"}', '{"enrollment_status":"On Leave"}', CURRENT_TIMESTAMP),
    (2, 'student', 3, 'status_transition', '{"status":"Pending"}', '{"status":"Rejected"}', CURRENT_TIMESTAMP),
    (3, 'request', 1, 'created', NULL, '{"status":"Pending"}', CURRENT_TIMESTAMP),
    (2, 'request', 1, 'status_transition', '{"status":"Pending"}', '{"status":"Under Review"}', CURRENT_TIMESTAMP),
    (1, 'request', 3, 'status_transition', '{"status":"Pending"}', '{"status":"Approved"}', CURRENT_TIMESTAMP);
