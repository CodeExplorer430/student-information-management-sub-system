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
