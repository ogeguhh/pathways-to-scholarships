
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


-- 2. U.S. States + National
-- NA is our project-specific code for National.

INSERT IGNORE INTO states (state_code, state_name)
VALUES
('AL', 'Alabama'),
('AK', 'Alaska'),
('AZ', 'Arizona'),
('AR', 'Arkansas'),
('CA', 'California'),
('CO', 'Colorado'),
('CT', 'Connecticut'),
('DE', 'Delaware'),
('FL', 'Florida'),
('GA', 'Georgia'),
('HI', 'Hawaii'),
('ID', 'Idaho'),
('IL', 'Illinois'),
('IN', 'Indiana'),
('IA', 'Iowa'),
('KS', 'Kansas'),
('KY', 'Kentucky'),
('LA', 'Louisiana'),
('ME', 'Maine'),
('MD', 'Maryland'),
('MA', 'Massachusetts'),
('MI', 'Michigan'),
('MN', 'Minnesota'),
('MS', 'Mississippi'),
('MO', 'Missouri'),
('MT', 'Montana'),
('NE', 'Nebraska'),
('NV', 'Nevada'),
('NH', 'New Hampshire'),
('NJ', 'New Jersey'),
('NM', 'New Mexico'),
('NY', 'New York'),
('NC', 'North Carolina'),
('ND', 'North Dakota'),
('OH', 'Ohio'),
('OK', 'Oklahoma'),
('OR', 'Oregon'),
('PA', 'Pennsylvania'),
('RI', 'Rhode Island'),
('SC', 'South Carolina'),
('SD', 'South Dakota'),
('TN', 'Tennessee'),
('TX', 'Texas'),
('UT', 'Utah'),
('VT', 'Vermont'),
('VA', 'Virginia'),
('WA', 'Washington'),
('WV', 'West Virginia'),
('WI', 'Wisconsin'),
('WY', 'Wyoming'),
('NA', 'National');


-- 3. Grade Levels (6–12)
-- Supports middle school and high school students.

INSERT IGNORE INTO grade_levels (grade_level)
VALUES
(6),
(7),
(8),
(9),
(10),
(11),
(12);


-- 4. Scholarship Provider Types
-- Based on the ICS 325 project requirements.

INSERT IGNORE INTO provider_types (provider_type_name)
VALUES
('Foundation/nonprofit'),
('corporation'),
('college/university'),
('government agency'),
('professional association'),
('community organization'),
('other');


-- 5. Scholarship Categories
-- Initial categories based on project examples.
-- Additional categories can be added later.

INSERT IGNORE INTO categories (category_name)
VALUES
('Merit-based'),
('Need-based'),
('Community Service'),
('Leadership'),
('STEM'),
('Arts'),
('Athletics');


-- 6. Fields of Study
-- Initial fields based on ICS 325 project examples.
-- Additional fields can be added later.

INSERT IGNORE INTO fields_of_study (field_name)
VALUES
('STEM'),
('Arts'),
('Humanities'),
('Business'),
('Health');


-- 7. National Scholarship Themes
-- All 29 themes from the official student assignments document.

INSERT IGNORE INTO national_themes (theme_name)
VALUES
('Arts, music, and design'),
('Asian American and Pacific Islander heritage'),
('Black/African American heritage'),
('Business, finance, and accounting'),
('Community college, transfer, graduate, and adult/returning students'),
('Community service and volunteering'),
('Computer science, data science, and IT'),
('Corporate-sponsored programs'),
('Education and teaching'),
('Engineering'),
('Environment, agriculture, and natural resources'),
('First-generation college students'),
('Foster youth and independent students'),
('Health, nursing, and pre-med'),
('Hispanic/Latino heritage'),
('Humanities and social sciences'),
('Law, public service, and government'),
('Leadership and civic engagement'),
('LGBTQ+ students'),
('Military, veterans, and military families'),
('Native American and Indigenous'),
('Professional associations'),
('Religious and faith-based'),
('Rural and small-town students'),
('Skilled trades and technical programs'),
('STEM general (math, physics, chemistry, biology)'),
('Students with disabilities'),
('Women-focused'),
('Writing, journalism, and communications');


