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
