
-- ============================================
-- Pathways to Scholarships
-- Iteration 2 - Sample Seed Data
-- ============================================

-- FICTIONAL TEST DATA ONLY
-- These scholarships and providers are not real.
-- Do not include them in the final research dataset.

-- 1. Insert sample scholarship providers

INSERT INTO providers (
    provider_type_id,
    provider_name
)
SELECT
    pt.provider_type_id,
    'Demo Future Scholars Foundation'
FROM provider_types AS pt
WHERE pt.provider_type_name = 'Foundation/nonprofit'
AND NOT EXISTS (
    SELECT 1 FROM providers
    WHERE provider_name = 'Demo Future Scholars Foundation'
);

INSERT INTO providers (
    provider_type_id,
    provider_name
)
SELECT
    pt.provider_type_id,
    'Demo Community Education Network'
FROM provider_types AS pt
WHERE pt.provider_type_name = 'community organization'
AND NOT EXISTS (
    SELECT 1 FROM providers
    WHERE provider_name = 'Demo Community Education Network'
);

INSERT INTO providers (
    provider_type_id,
    provider_name
)
SELECT
    pt.provider_type_id,
    'Demo Technology Careers Association'
FROM provider_types AS pt
WHERE pt.provider_type_name = 'professional association'
AND NOT EXISTS (
    SELECT 1 FROM providers
    WHERE provider_name = 'Demo Technology Careers Association'
);

-- 2. Insert national scholarship

INSERT INTO scholarships (
    provider_id,
    state_id,
    created_by,
    scholarship_name,
    official_url,
    normalized_url,
    discovery_source,
    national_or_state_local,
    school_level,
    award_amount_min,
    award_amount_max,
    number_of_awards,
    renewable,
    application_deadline,
    rolling_deadline,
    minimum_gpa,
    financial_need_required,
    essay_required,
    application_method
)
SELECT
    p.provider_id,
    s.state_id,
    u.user_id,
    'DEMO National Future Scholars Award',
    'https://example.com/demo-national-scholarship',
    'https://example.com/demo-national-scholarship',
    'Development sample data',
    'National',
    'High School',
    1000.00,
    5000.00,
    10,
    'No',
    '2027-04-15',
    0,
    3.00,
    'No',
    'Yes',
    'Online Form'
FROM providers AS p
JOIN states AS s ON s.state_code = 'NA'
JOIN users AS u ON u.email = 'admin@example.test'
WHERE p.provider_name = 'Demo Future Scholars Foundation'
AND NOT EXISTS (
    SELECT 1 FROM scholarships
    WHERE normalized_url =
        'https://example.com/demo-national-scholarship'
);

-- 3. Insert Minnesota scholarship

INSERT INTO scholarships (
    provider_id,
    state_id,
    created_by,
    scholarship_name,
    official_url,
    normalized_url,
    discovery_source,
    national_or_state_local,
    school_level,
    award_amount_min,
    award_amount_max,
    number_of_awards,
    renewable,
    application_deadline,
    rolling_deadline,
    minimum_gpa,
    financial_need_required,
    community_service_required,
    application_method
)
SELECT
    p.provider_id,
    s.state_id,
    u.user_id,
    'DEMO Minnesota Community Leadership Scholarship',
    'https://example.com/demo-minnesota-scholarship',
    'https://example.com/demo-minnesota-scholarship',
    'Development sample data',
    'State/Local',
    'High School',
    500.00,
    2000.00,
    5,
    'No',
    '2027-03-31',
    0,
    2.50,
    'Yes',
    'Yes',
    'Online Form'
FROM providers AS p
JOIN states AS s ON s.state_code = 'MN'
JOIN users AS u ON u.email = 'admin@example.test'
WHERE p.provider_name = 'Demo Community Education Network'
AND NOT EXISTS (
    SELECT 1 FROM scholarships
    WHERE normalized_url =
        'https://example.com/demo-minnesota-scholarship'
);

-- 4. Insert STEM scholarship

INSERT INTO scholarships (
    provider_id,
    state_id,
    created_by,
    scholarship_name,
    official_url,
    normalized_url,
    discovery_source,
    national_or_state_local,
    school_level,
    award_amount_min,
    award_amount_max,
    number_of_awards,
    renewable,
    application_deadline,
    rolling_deadline,
    minimum_gpa,
    financial_need_required,
    essay_required,
    application_method
)
SELECT
    p.provider_id,
    s.state_id,
    u.user_id,
    'DEMO National STEM Achievement Scholarship',
    'https://example.com/demo-stem-scholarship',
    'https://example.com/demo-stem-scholarship',
    'Development sample data',
    'National',
    'High School',
    1500.00,
    3000.00,
    8,
    'No',
    '2027-05-01',
    0,
    3.20,
    'No',
    'Yes',
    'Online Form'
FROM providers AS p
JOIN states AS s ON s.state_code = 'NA'
JOIN users AS u ON u.email = 'admin@example.test'
WHERE p.provider_name = 'Demo Technology Careers Association'
AND NOT EXISTS (
    SELECT 1 FROM scholarships
    WHERE normalized_url =
        'https://example.com/demo-stem-scholarship'
);

-- 5. Link demo awards to high-school grades so grade searches return them.

INSERT IGNORE INTO scholarship_grades (
    scholarship_id,
    grade_id
)
SELECT
    sc.scholarship_id,
    gl.grade_id
FROM scholarships AS sc
JOIN grade_levels AS gl ON gl.grade_level BETWEEN 9 AND 12
WHERE sc.scholarship_name LIKE 'DEMO %'
  AND sc.school_level IN ('High School', 'Both');

-- 6. Link the STEM demo award to its field of study.

INSERT IGNORE INTO scholarship_fields (
    scholarship_id,
    field_id
)
SELECT
    sc.scholarship_id,
    fs.field_id
FROM scholarships AS sc
JOIN fields_of_study AS fs ON fs.field_name = 'STEM'
WHERE sc.scholarship_name = 'DEMO National STEM Achievement Scholarship';

-- 7. Verify inserted sample data

SELECT
    sc.scholarship_id,
    sc.scholarship_name,
    p.provider_name,
    st.state_name,
    sc.award_amount_min,
    sc.award_amount_max,
    sc.application_deadline
FROM scholarships AS sc
JOIN providers AS p
    ON sc.provider_id = p.provider_id
LEFT JOIN states AS st
    ON sc.state_id = st.state_id
WHERE sc.scholarship_name LIKE 'DEMO %'
ORDER BY sc.scholarship_id;
