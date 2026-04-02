CREATE TABLE IF NOT EXISTS user_roles (
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

INSERT OR IGNORE INTO user_roles (user_id, role_id, created_at)
SELECT users.id, roles.id, CURRENT_TIMESTAMP
FROM users
INNER JOIN roles ON roles.slug = users.role;
