
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



