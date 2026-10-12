
-- Pathways to Scholarships
-- Iteration 2: Database Schema Version 1

CREATE DATABASE IF NOT EXISTS pathways_scholarships
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pathways_scholarships;

-- 1. Roles
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 2. States
CREATE TABLE states (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    state_code CHAR(2) NOT NULL UNIQUE,
    state_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. Grade Levels
CREATE TABLE grade_levels (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level INT NOT NULL UNIQUE,
    CHECK (grade_level BETWEEN 6 AND 12)
) ENGINE=InnoDB;

-- 4. Categories
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 5. Fields of Study
CREATE TABLE fields_of_study (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 6. National Themes
CREATE TABLE national_themes (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 7. Provider Types
CREATE TABLE provider_types (
    provider_type_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;


-- 8. Users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    account_status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- 9. Providers
CREATE TABLE providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_id INT,
    provider_name VARCHAR(200) NOT NULL,

    FOREIGN KEY (provider_type_id)
        REFERENCES provider_types(provider_type_id)
) ENGINE=InnoDB;

-- 10. Student Profiles
CREATE TABLE student_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    grade_id INT,
    state_id INT,
    profile_name VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (grade_id) REFERENCES grade_levels(grade_id),
    FOREIGN KEY (state_id) REFERENCES states(state_id)
) ENGINE=InnoDB;

-- 11. Profile Interests
CREATE TABLE profile_interests (
    profile_id INT NOT NULL,
    field_id INT NOT NULL,

    PRIMARY KEY (profile_id, field_id),

    FOREIGN KEY (profile_id)
        REFERENCES student_profiles(profile_id),

    FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
) ENGINE=InnoDB;


-- 12. Scholarships
CREATE TABLE scholarships (
    scholarship_id INT AUTO_INCREMENT PRIMARY KEY,

    -- Relationships
    provider_id INT NOT NULL,
    state_id INT,
    theme_id INT,
    import_batch_id INT NULL,
    created_by INT NULL,
    updated_by INT NULL,

    -- Basic Information
    scholarship_name VARCHAR(255) NOT NULL,
    official_url VARCHAR(1000) NOT NULL,
    normalized_url VARCHAR(1000) NOT NULL,
    discovery_source VARCHAR(1000),

    national_or_state_local ENUM('National', 'State/Local')
        NOT NULL,
    school_level ENUM('Middle School', 'High School', 'Both'),

    -- Award Information
    award_amount_min DECIMAL(12,2),
    award_amount_max DECIMAL(12,2),
    number_of_awards INT,
    renewable ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',
    maximum_potential_value DECIMAL(12,2),

    -- Application Dates
    application_open_date DATE,
    application_deadline DATE,
    rolling_deadline BOOLEAN NOT NULL DEFAULT FALSE,

    -- Eligibility
    residency_requirement TEXT,
    minimum_gpa DECIMAL(3,2),
    financial_need_required ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',
    essay_required ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',
    recommendation_required ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',
    transcript_test_required ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',
    community_service_required ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',
    leadership_required ENUM('Yes', 'No', 'Unknown')
        NOT NULL DEFAULT 'Unknown',

    other_eligibility TEXT,

    -- Application Details
    application_method ENUM(
        'Online Form', 'Email', 'Mail', 'Other'
    ),
    verification_date DATE,
    notes TEXT,

    -- Tracking
    contributing_student VARCHAR(150),
    status ENUM('active', 'archived')
        NOT NULL DEFAULT 'active',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Keys
    FOREIGN KEY (provider_id)
        REFERENCES providers(provider_id),

    FOREIGN KEY (state_id)
        REFERENCES states(state_id),

    FOREIGN KEY (theme_id)
        REFERENCES national_themes(theme_id),

    FOREIGN KEY (created_by)
        REFERENCES users(user_id),

    FOREIGN KEY (updated_by)
        REFERENCES users(user_id),

    -- Indexes
    INDEX idx_scholarship_state (state_id),
    INDEX idx_scholarship_deadline (application_deadline),
    INDEX idx_scholarship_url (normalized_url(191)),

    -- Validation
    CHECK (minimum_gpa BETWEEN 0 AND 4.00),
    CHECK (award_amount_min >= 0),
    CHECK (award_amount_max >= 0),
    CHECK (number_of_awards >= 0),
    CHECK (maximum_potential_value >= 0),
    CHECK (
        award_amount_min IS NULL
        OR award_amount_max IS NULL
        OR award_amount_min <= award_amount_max
    )
) ENGINE=InnoDB;


-- 13. Scholarship Grades
CREATE TABLE scholarship_grades (
    scholarship_id INT NOT NULL,
    grade_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, grade_id),

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE,

    FOREIGN KEY (grade_id)
        REFERENCES grade_levels(grade_id)
) ENGINE=InnoDB;

-- 14. Scholarship Categories
CREATE TABLE scholarship_categories (
    scholarship_id INT NOT NULL,
    category_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, category_id),

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE,

    FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
) ENGINE=InnoDB;

-- 15. Scholarship Fields
CREATE TABLE scholarship_fields (
    scholarship_id INT NOT NULL,
    field_id INT NOT NULL,

    PRIMARY KEY (scholarship_id, field_id),

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE,

    FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
) ENGINE=InnoDB;


-- 16. Saved Scholarships
CREATE TABLE saved_scholarships (
    profile_id INT NOT NULL,
    scholarship_id INT NOT NULL,
    saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    application_status VARCHAR(50),

    PRIMARY KEY (profile_id, scholarship_id),

    FOREIGN KEY (profile_id)
        REFERENCES student_profiles(profile_id)
        ON DELETE CASCADE,

    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- 17. Import Batches
CREATE TABLE import_batches (
    import_batch_id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NULL,
    file_name VARCHAR(255) NOT NULL,
    contributing_student VARCHAR(150),
    imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    batch_status VARCHAR(50),
    total_rows INT DEFAULT 0,
    ready_rows INT DEFAULT 0,
    warning_rows INT DEFAULT 0,
    duplicate_rows INT DEFAULT 0,
    error_rows INT DEFAULT 0,

    FOREIGN KEY (uploaded_by)
        REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 18. Import Staging Rows
CREATE TABLE import_staging_rows (
    staging_row_id INT AUTO_INCREMENT PRIMARY KEY,
    import_batch_id INT NOT NULL,
    duplicate_scholarship_id INT NULL,
    row_number INT NOT NULL,
    raw_data TEXT,
    validation_status VARCHAR(30),
    validation_message TEXT,

    FOREIGN KEY (import_batch_id)
        REFERENCES import_batches(import_batch_id)
        ON DELETE CASCADE,

    FOREIGN KEY (duplicate_scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- Add the previously deferred import batch relationship
ALTER TABLE scholarships
ADD CONSTRAINT fk_scholarship_import_batch
FOREIGN KEY (import_batch_id)
REFERENCES import_batches(import_batch_id)
ON DELETE SET NULL;


-- 19. Contact Messages
CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(150),
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(30) NOT NULL DEFAULT 'new',

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- 20. Search Log
CREATE TABLE search_log (
    search_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    state_id INT NULL,
    grade_id INT NULL,
    field_id INT NULL,
    search_term VARCHAR(255),
    searched_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL,

    FOREIGN KEY (state_id)
        REFERENCES states(state_id),

    FOREIGN KEY (grade_id)
        REFERENCES grade_levels(grade_id),

    FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
) ENGINE=InnoDB;


-- 21. Audit Log
CREATE TABLE audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT,
    action_details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB;


-- Enforce uniqueness for active scholarship URLs only.
-- Archived scholarships do not participate in this constraint.

ALTER TABLE scholarships
ADD COLUMN active_url_hash BINARY(32)
GENERATED ALWAYS AS (
    CASE
        WHEN status = 'active'
        THEN UNHEX(SHA2(normalized_url, 256))
        ELSE NULL
    END
) STORED,
ADD UNIQUE KEY uq_active_scholarship_url (active_url_hash);



