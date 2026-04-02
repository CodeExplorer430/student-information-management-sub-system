UPDATE users SET mobile_phone = '09170000021' WHERE email = 'registrar@bcp.edu';
UPDATE users SET mobile_phone = '09170000022' WHERE email = 'staff@bcp.edu';
UPDATE users SET mobile_phone = '09170000023' WHERE email = 'student@bcp.edu';
UPDATE users SET mobile_phone = '09170000024' WHERE email = 'faculty@bcp.edu';
UPDATE users SET mobile_phone = '09170000025' WHERE email = 'admin@bcp.edu';

UPDATE student_requests
SET priority = 'High', due_at = datetime(CURRENT_TIMESTAMP, '+2 day')
WHERE id = 1;

UPDATE student_requests
SET priority = 'Normal', due_at = datetime(CURRENT_TIMESTAMP, '+5 day')
WHERE id = 2;

UPDATE student_requests
SET priority = 'Low', due_at = datetime(CURRENT_TIMESTAMP, '+7 day'), resolution_summary = 'Return-from-leave review completed and approved.'
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
