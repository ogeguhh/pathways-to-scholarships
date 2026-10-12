
-- ============================================
-- Pathways to Scholarships
-- Iteration 2 - Development Seed Data
-- ============================================

-- Development-only placeholder Administrator.
-- Not for production use.

-- Password: password
-- Hash generated using PHP's password_hash()
-- with bcrypt.

INSERT INTO users (
    role_id,
    email,
    password_hash,
    first_name,
    last_name,
    account_status
)
SELECT
    r.role_id,
    'admin@example.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
    'Development',
    'Admin',
    'active'
FROM roles AS r
WHERE r.role_name = 'Administrator'
AND NOT EXISTS (
    SELECT 1
    FROM users
    WHERE email = 'admin@example.test'
);

-- Verify the inserted development account.

SELECT
    u.user_id,
    u.email,
    u.first_name,
    u.last_name,
    u.account_status,
    r.role_name
FROM users AS u
JOIN roles AS r ON u.role_id = r.role_id
WHERE u.email = 'admin@example.test';
