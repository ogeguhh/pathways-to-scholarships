-- Pathways to Scholarships
-- Iteration 2: Database Schema Version 1
-- MySQL 8.4 / utf8mb4
-- Run only against pathways_scholarships_rebuild.
-- No DROP statements are included.

-- 1. User roles
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User accounts
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    account_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. States, including a National reference entry
CREATE TABLE states (
    state_id INT AUTO_INCREMENT PRIMARY KEY,
    state_code VARCHAR(2) NOT NULL UNIQUE,
    state_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Grade levels, 6 through 12
CREATE TABLE grade_levels (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_number INT NOT NULL UNIQUE,
    CONSTRAINT chk_grade CHECK (grade_number BETWEEN 6 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Scholarship categories
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Fields of study
CREATE TABLE fields_of_study (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Provider types
CREATE TABLE provider_types (
    provider_type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Scholarship providers
CREATE TABLE providers (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_type_id INT NOT NULL,
    provider_name VARCHAR(255) NOT NULL,
    FOREIGN KEY (provider_type_id)
        REFERENCES provider_types(provider_type_id),
    INDEX idx_provider_name (provider_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. National scholarship themes
CREATE TABLE national_themes (
    theme_id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Student or child profiles
-- The owner may be a Student or Parent account.
CREATE TABLE student_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_name VARCHAR(150) NULL,
    state_id INT NOT NULL,
    grade_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (state_id) REFERENCES states(state_id),
    FOREIGN KEY (grade_id) REFERENCES grade_levels(grade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Multiple interests per profile
CREATE TABLE profile_interests (
    profile_id INT NOT NULL,
    field_id INT NOT NULL,
    PRIMARY KEY (profile_id, field_id),
    FOREIGN KEY (profile_id)
        REFERENCES student_profiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Batch upload history
CREATE TABLE import_batches (
    import_batch_id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NULL,
    file_name VARCHAR(255) NOT NULL,
    contributing_student VARCHAR(150) NULL,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    committed_at DATETIME NULL,
    rolled_back_at DATETIME NULL,
    batch_status ENUM(
        'staged','validated','committed','rolled_back','failed'
    ) NOT NULL DEFAULT 'staged',
    total_rows INT NOT NULL DEFAULT 0,
    ready_rows INT NOT NULL DEFAULT 0,
    warning_rows INT NOT NULL DEFAULT 0,
    duplicate_rows INT NOT NULL DEFAULT 0,
    error_rows INT NOT NULL DEFAULT 0,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Live scholarship records
-- Includes all 29 template fields, directly or via relationships.
CREATE TABLE scholarships (
    scholarship_id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    state_id INT NULL,
    theme_id INT NULL,
    import_batch_id INT NULL,
    created_by INT NULL,
    updated_by INT NULL,

    scholarship_name VARCHAR(255) NOT NULL,
    official_url VARCHAR(1000) NOT NULL,
    normalized_url VARCHAR(1000) NOT NULL,
    discovery_source TEXT NULL,

    national_or_state_local ENUM('National','State/Local') NOT NULL,
    school_level ENUM(
        'Middle School','High School','Both'
    ) NULL,

    award_amount_min DECIMAL(12,2) NULL,
    award_amount_max DECIMAL(12,2) NULL,
    number_of_awards INT NULL,
    renewable ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',
    maximum_potential_value DECIMAL(12,2) NULL,

    application_open_date DATE NULL,
    application_deadline DATE NULL,
    rolling_deadline BOOLEAN NOT NULL DEFAULT FALSE,

    residency_requirement TEXT NULL,
    minimum_gpa DECIMAL(3,2) NULL,

    financial_need_required ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',
    essay_required ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',
    recommendation_required ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',
    transcript_test_required ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',
    community_service_required ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',
    leadership_required ENUM('Yes','No','Unknown')
        NOT NULL DEFAULT 'Unknown',

    other_eligibility TEXT NULL,
    application_method ENUM(
        'Online Form','Email','Mail','Other'
    ) NULL,
    verification_date DATE NULL,
    notes TEXT NULL,
    contributing_student VARCHAR(150) NULL,

    status ENUM('active','archived')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    -- A generated hash enforces URL uniqueness for active
    -- records while permitting archived records to retain URLs.
    active_url_hash BINARY(32)
        GENERATED ALWAYS AS (
            CASE
                WHEN status = 'active'
                THEN UNHEX(SHA2(normalized_url, 256))
                ELSE NULL
            END
        ) STORED,

    FOREIGN KEY (provider_id) REFERENCES providers(provider_id),
    FOREIGN KEY (state_id) REFERENCES states(state_id),
    FOREIGN KEY (theme_id) REFERENCES national_themes(theme_id),
    FOREIGN KEY (import_batch_id)
        REFERENCES import_batches(import_batch_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
        ON DELETE SET NULL,

    UNIQUE KEY uq_active_url (active_url_hash),
    INDEX idx_scholarship_state (state_id),
    INDEX idx_scholarship_deadline (application_deadline),
    INDEX idx_scholarship_normalized_url (normalized_url(191)),
    INDEX idx_scholarship_batch (import_batch_id),
    INDEX idx_scholarship_status (status),

    CONSTRAINT chk_award_amounts CHECK (
        award_amount_min IS NULL OR award_amount_min >= 0
    ),
    CONSTRAINT chk_award_max CHECK (
        award_amount_max IS NULL OR award_amount_max >= 0
    ),
    CONSTRAINT chk_award_range CHECK (
        award_amount_min IS NULL OR award_amount_max IS NULL
        OR award_amount_max >= award_amount_min
    ),
    CONSTRAINT chk_gpa CHECK (
        minimum_gpa IS NULL
        OR minimum_gpa BETWEEN 0 AND 4
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Scholarship-to-grade relationship
CREATE TABLE scholarship_grades (
    scholarship_id INT NOT NULL,
    grade_id INT NOT NULL,
    PRIMARY KEY (scholarship_id, grade_id),
    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    FOREIGN KEY (grade_id)
        REFERENCES grade_levels(grade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Scholarship-to-category relationship
CREATE TABLE scholarship_categories (
    scholarship_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (scholarship_id, category_id),
    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id)
        REFERENCES categories(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Scholarship-to-field relationship
CREATE TABLE scholarship_fields (
    scholarship_id INT NOT NULL,
    field_id INT NOT NULL,
    PRIMARY KEY (scholarship_id, field_id),
    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    FOREIGN KEY (field_id)
        REFERENCES fields_of_study(field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Saved scholarships and application tracking
CREATE TABLE saved_scholarships (
    profile_id INT NOT NULL,
    scholarship_id INT NOT NULL,
    application_status ENUM(
        'Interested','In Progress','Submitted',
        'Awarded','Not Awarded'
    ) NOT NULL DEFAULT 'Interested',
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (profile_id, scholarship_id),
    FOREIGN KEY (profile_id)
        REFERENCES student_profiles(profile_id) ON DELETE CASCADE,
    FOREIGN KEY (scholarship_id)
        REFERENCES scholarships(scholarship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Staged rows are not live scholarships.
-- Both original and cleaned data are retained.
CREATE TABLE import_staging_rows (
    staging_row_id INT AUTO_INCREMENT PRIMARY KEY,
    import_batch_id INT NOT NULL,
    duplicate_scholarship_id INT NULL,
    `row_number` INT NOT NULL,
    raw_data JSON NOT NULL,
    cleaned_data JSON NULL,
    validation_status ENUM(
        'Pending','Ready','Warning','Duplicate','Error'
    ) NOT NULL DEFAULT 'Pending',
    validation_message TEXT NULL,
    FOREIGN KEY (import_batch_id)
        REFERENCES import_batches(import_batch_id),
    FOREIGN KEY (duplicate_scholarship_id)
        REFERENCES scholarships(scholarship_id)
        ON DELETE SET NULL,
    UNIQUE KEY uq_batch_row (import_batch_id, `row_number`),
    INDEX idx_staging_status (validation_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Contact form submissions
-- Visitors may submit messages without a user account.
CREATE TABLE contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    sender_name VARCHAR(150) NOT NULL,
    sender_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Anonymous search statistics
CREATE TABLE search_log (
    search_id INT AUTO_INCREMENT PRIMARY KEY,
    state_filter VARCHAR(100) NULL,
    grade_filter VARCHAR(50) NULL,
    field_filter VARCHAR(150) NULL,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_search_date (searched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Administrative audit history
CREATE TABLE audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE SET NULL,
    INDEX idx_audit_date (created_at),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
