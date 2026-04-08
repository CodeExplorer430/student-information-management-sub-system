-- Bestlink SIS MySQL/MariaDB import snapshot.
-- Generated from database/migrations/mysql/*.sql and database/seeds/mysql/*.sql.
-- Intended for empty demo/local databases. Preferred app-managed path: composer migrate && composer seed.
-- Demo login password for seeded users: Password123! Rotate credentials before shared deployment.

SET NAMES utf8mb4;


-- Source: database/migrations/mysql/001_create_schema.sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    mobile_phone VARCHAR(50) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    role VARCHAR(50) NOT NULL,
    department VARCHAR(255) DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(150) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    module VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255) DEFAULT '',
    last_name VARCHAR(255) NOT NULL,
    birthdate DATE NOT NULL,
    program VARCHAR(255) NOT NULL,
    year_level VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    address TEXT NOT NULL,
    guardian_name VARCHAR(255) NOT NULL,
    guardian_contact VARCHAR(50) NOT NULL,
    department VARCHAR(255) NOT NULL,
    enrollment_status VARCHAR(50) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    request_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'Normal',
    due_at DATETIME DEFAULT NULL,
    status VARCHAR(50) NOT NULL,
    assigned_user_id INT DEFAULT NULL,
    created_by_user_id INT NOT NULL,
    submitted_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    resolved_at DATETIME DEFAULT NULL,
    resolution_summary TEXT DEFAULT NULL,
    CONSTRAINT fk_request_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_assigned_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_request_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_status_histories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    remarks TEXT DEFAULT NULL,
    assigned_user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_request_history_request FOREIGN KEY (request_id) REFERENCES student_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_history_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS academic_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term_label VARCHAR(100) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_title VARCHAR(255) NOT NULL,
    units DECIMAL(4,1) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_academic_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS status_histories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    remarks TEXT DEFAULT NULL,
    assigned_user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_status_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS enrollment_status_histories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    remarks TEXT DEFAULT NULL,
    assigned_user_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollment_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS id_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    qr_payload TEXT NOT NULL,
    barcode_payload VARCHAR(255) NOT NULL,
    generated_by INT DEFAULT NULL,
    generated_at DATETIME NOT NULL,
    CONSTRAINT fk_id_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_id_user FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Source: database/migrations/mysql/002_multi_role_rbac.sql
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO user_roles (user_id, role_id, created_at)
SELECT users.id, roles.id, CURRENT_TIMESTAMP
FROM users
INNER JOIN roles ON roles.slug = users.role;


-- Source: database/migrations/mysql/003_workflow_notifications.sql
CREATE TABLE IF NOT EXISTS request_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    author_user_id INT NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'student',
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_request_note_request FOREIGN KEY (request_id) REFERENCES student_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_note_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    note_id INT DEFAULT NULL,
    uploaded_by_user_id INT NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'student',
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_request_attachment_request FOREIGN KEY (request_id) REFERENCES student_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_attachment_note FOREIGN KEY (note_id) REFERENCES request_notes(id) ON DELETE SET NULL,
    CONSTRAINT fk_request_attachment_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message TEXT DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_notification_delivery_notification FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Source: database/migrations/mysql/004_own_scope_permissions.sql
INSERT IGNORE INTO permissions (code, label, module, description, created_at, updated_at)
SELECT 'students.view_own', 'View own student profile', 'students', 'View only the signed-in student profile record.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE EXISTS (SELECT 1 FROM roles)
UNION ALL SELECT 'students.update_own', 'Update own student profile', 'students', 'Update only the signed-in student profile record.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE EXISTS (SELECT 1 FROM roles)
UNION ALL SELECT 'records.view_own', 'View own academic records', 'records', 'View only the signed-in student academic records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE EXISTS (SELECT 1 FROM roles)
UNION ALL SELECT 'statuses.view_own', 'View own status tracking', 'statuses', 'View only the signed-in student status timeline.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE EXISTS (SELECT 1 FROM roles)
UNION ALL SELECT 'id_cards.view_own', 'View own ID card', 'id_cards', 'View only the signed-in student ID card preview and download.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE EXISTS (SELECT 1 FROM roles);

DELETE role_permissions
FROM role_permissions
INNER JOIN roles ON roles.id = role_permissions.role_id
INNER JOIN permissions ON permissions.id = role_permissions.permission_id
WHERE roles.slug = 'student'
  AND permissions.code IN ('students.view', 'students.update', 'records.view', 'statuses.view', 'id_cards.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions
WHERE roles.slug = 'student'
  AND permissions.code IN ('students.view_own', 'students.update_own', 'records.view_own', 'statuses.view_own', 'id_cards.view_own');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions
WHERE roles.slug = 'admin'
  AND permissions.code IN ('students.view_own', 'students.update_own', 'records.view_own', 'statuses.view_own', 'id_cards.view_own');


-- Source: database/seeds/mysql/001_demo_data.sql
INSERT INTO users (name, email, password_hash, role, department, created_at, updated_at) VALUES
    ('Regina Santos', 'registrar@bcp.edu', '$2y$12$olJwP/wQm9T7c8TMDrQuhOB2qhf5eyYrufeewFTOmNnMKsi3Osf.K', 'registrar', 'Registrar', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Marco Villanueva', 'staff@bcp.edu', '$2y$12$olJwP/wQm9T7c8TMDrQuhOB2qhf5eyYrufeewFTOmNnMKsi3Osf.K', 'staff', 'Student Affairs', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Aira Mendoza', 'student@bcp.edu', '$2y$12$olJwP/wQm9T7c8TMDrQuhOB2qhf5eyYrufeewFTOmNnMKsi3Osf.K', 'student', 'BSIT', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Prof. Luis Navarro', 'faculty@bcp.edu', '$2y$12$olJwP/wQm9T7c8TMDrQuhOB2qhf5eyYrufeewFTOmNnMKsi3Osf.K', 'faculty', 'Academic Affairs', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('Elena Garcia', 'admin@bcp.edu', '$2y$12$olJwP/wQm9T7c8TMDrQuhOB2qhf5eyYrufeewFTOmNnMKsi3Osf.K', 'admin', 'ICT', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

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
    ('students.view_own', 'View own student profile', 'students', 'View only the signed-in student profile record.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('students.create', 'Register student profiles', 'students', 'Create student profile records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('students.update', 'Update student profiles', 'students', 'Update student profile records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('students.update_own', 'Update own student profile', 'students', 'Update only the signed-in student profile record.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('records.view', 'View academic records', 'records', 'View academic record data.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('records.view_own', 'View own academic records', 'records', 'View only the signed-in student academic records.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.view', 'View status tracking', 'statuses', 'View workflow and enrollment status boards.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.view_own', 'View own status tracking', 'statuses', 'View only the signed-in student status timeline.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.transition', 'Transition workflow status', 'statuses', 'Advance or change workflow statuses.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('statuses.enrollment_transition', 'Transition enrollment status', 'statuses', 'Advance or change enrollment statuses.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('id_cards.view', 'View ID generation module', 'id_cards', 'Access ID generation, preview, and verification context.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    ('id_cards.view_own', 'View own ID card', 'id_cards', 'View only the signed-in student ID card preview and download.', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
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
        'dashboard.view_student', 'students.view_own', 'students.update_own', 'records.view_own', 'statuses.view_own', 'id_cards.view_own',
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


-- Source: database/seeds/mysql/002_workflow_notifications.sql
UPDATE users SET mobile_phone = '09170000021' WHERE email = 'registrar@bcp.edu';
UPDATE users SET mobile_phone = '09170000022' WHERE email = 'staff@bcp.edu';
UPDATE users SET mobile_phone = '09170000023' WHERE email = 'student@bcp.edu';
UPDATE users SET mobile_phone = '09170000024' WHERE email = 'faculty@bcp.edu';
UPDATE users SET mobile_phone = '09170000025' WHERE email = 'admin@bcp.edu';

UPDATE student_requests
SET priority = 'High', due_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 2 DAY)
WHERE id = 1;

UPDATE student_requests
SET priority = 'Normal', due_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 DAY)
WHERE id = 2;

UPDATE student_requests
SET priority = 'Low', due_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 7 DAY), resolution_summary = 'Return-from-leave review completed and approved.'
WHERE id = 3;

INSERT INTO request_notes (request_id, author_user_id, visibility, body, created_at) VALUES
    (1, 2, 'student', 'We are validating the requested contact update against the registrar profile.', CURRENT_TIMESTAMP),
    (1, 1, 'internal', 'Awaiting final registrar sign-off before applying the profile change.', CURRENT_TIMESTAMP),
    (2, 3, 'student', 'Need the certified grades before the scholarship deadline next week.', CURRENT_TIMESTAMP);

INSERT INTO notifications (user_id, entity_type, entity_id, title, message, is_read, created_at) VALUES
    (1, 'request', 1, 'New request submitted', 'A student request requires registrar review.', 0, CURRENT_TIMESTAMP),
    (2, 'request', 1, 'Request assigned', 'You were assigned to review the profile update request.', 0, CURRENT_TIMESTAMP),
    (3, 'request', 1, 'Request update', 'Your profile update request is now under review.', 0, CURRENT_TIMESTAMP),
    (5, 'admin', 0, 'Workflow digest', 'Request operations and governance activity are available for review.', 0, CURRENT_TIMESTAMP);

INSERT INTO notification_deliveries (notification_id, channel, recipient, status, error_message, delivered_at, created_at) VALUES
    (1, 'email', 'registrar@bcp.edu', 'sent', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (1, 'sms', '09170000021', 'sent', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (2, 'email', 'staff@bcp.edu', 'sent', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (3, 'email', 'student@bcp.edu', 'sent', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
    (4, 'sms', '09170000025', 'sent', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
