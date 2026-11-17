-- Pathways to Scholarships
-- Iteration 2: Development User
-- This account is for local development only.
-- Authentication will be implemented in Iteration 7.

INSERT INTO users (
    role_id,
    full_name,
    email,
    password_hash,
    account_status,
    must_change_password
)
SELECT
    role_id,
    'Development Administrator',
    'dev-admin@example.test',
    NULL,
    'active',
    1
FROM roles
WHERE role_name = 'Administrator';
