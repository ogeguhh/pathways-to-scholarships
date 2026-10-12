
-- ============================================
-- Pathways to Scholarships
-- Iteration 2 - Development Seed Data
-- ============================================

-- Purpose:
-- Create a placeholder Administrator user
-- for local development and testing.
--
-- IMPORTANT:
-- This is development data only.
-- Do not use this account in production.

-- 1. Generate a secure password hash
-- for the temporary password: password

SET @dev_password_hash = SHA2('password', 256);

-- 2. Get the Administrator role ID

SET @admin_role_id = (
    SELECT role_id
    FROM roles
    WHERE role_name = 'Administrator'
    LIMIT 1
);

-- 3. Insert placeholder development user
-- Prevent duplicate users based on email.

INSERT INTO users (
    full_name,
    email,
    password_hash,
    role_id
)
SELECT
    'Development Admin',
    'admin@example.test',
    @dev_password_hash,
    @admin_role_id
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE email = 'admin@example.test'
);

-- 4. Verify the development user

SELECT *
FROM users
WHERE email = 'admin@example.test';
