-- Pathways to Scholarships
-- Iteration 2: Reference Data
-- This file will contain the required lookup values.

-- Five application roles
INSERT INTO roles (role_name) VALUES
('Visitor'),
('Student'),
('Parent'),
('Administrator'),
('Super-admin');

-- All 50 U.S. states plus National
INSERT INTO states (state_code, state_name) VALUES
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

-- Grade levels 6 through 12
INSERT INTO grade_levels (grade_number) VALUES
(6),
(7),
(8),
(9),
(10),
(11),
(12);

-- Scholarship provider types from Section 6.1
INSERT INTO provider_types (type_name) VALUES
('Foundation/nonprofit'),
('Corporation'),
('College/university'),
('Government agency'),
('Professional association'),
('Community organization'),
('Other');

-- Scholarship categories
INSERT INTO categories (category_name) VALUES
('Merit-Based'),
('Need-Based'),
('Community Service'),
('Leadership'),
('STEM'),
('Arts');

-- Fields of study for scholarship searching
INSERT INTO fields_of_study (field_name) VALUES
('General / Any Field'),
('Science'),
('Technology'),
('Engineering'),
('Mathematics'),
('Computer Science'),
('Healthcare'),
('Nursing'),
('Business'),
('Education'),
('Arts'),
('Humanities'),
('Social Sciences'),
('Agriculture'),
('Skilled Trades');

-- Official 29 national scholarship themes
-- Source: Pathways to Scholarships Student Assignments, Section 4
INSERT INTO national_themes (theme_name) VALUES
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
