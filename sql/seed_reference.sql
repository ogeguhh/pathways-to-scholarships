
-- ============================================
-- Pathways to Scholarships
-- Iteration 2 - Reference Data
-- ============================================

-- 1. User Roles
-- INSERT IGNORE prevents duplicate entries
-- if the script is executed again.

INSERT IGNORE INTO roles (role_name)
VALUES
    ('Visitor'),
    ('Student'),
    ('Parent'),
    ('Administrator'),
    ('Super-admin');

