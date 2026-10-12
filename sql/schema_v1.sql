
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

