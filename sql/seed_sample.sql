-- Pathways to Scholarships
-- Iteration 2: Fictional Scholarship Test Data
--
-- These scholarships are fictional and for local testing only.
-- The example.test domain is reserved for testing.

-- Create a fictional scholarship provider.
INSERT INTO providers (provider_name, provider_type_id)
SELECT
    'Demo Future Scholars Foundation',
    provider_type_id
FROM provider_types
WHERE type_name = 'Foundation/nonprofit';

-- Scholarship 1: Minnesota
INSERT INTO scholarships (
    provider_id,
    state_id,
    scholarship_name,
    official_url,
    normalized_url,
    national_or_state_local,
    school_level,
    award_amount_min,
    award_amount_max,
    application_deadline,
    notes
)
SELECT
    p.provider_id,
    s.state_id,
    'Demo Minnesota Future Scholars Award',
    'https://example.test/scholarships/minnesota-future',
    'https://example.test/scholarships/minnesota-future',
    'State/Local',
    'High School',
    1000.00,
    1000.00,
    '2027-03-15',
    'Fictional scholarship for development testing only.'
FROM providers p
JOIN states s ON s.state_code = 'MN'
WHERE p.provider_name = 'Demo Future Scholars Foundation';

-- Scholarship 2: Tennessee
INSERT INTO scholarships (
    provider_id,
    state_id,
    scholarship_name,
    official_url,
    normalized_url,
    national_or_state_local,
    school_level,
    award_amount_min,
    award_amount_max,
    application_deadline,
    notes
)
SELECT
    p.provider_id,
    s.state_id,
    'Demo Tennessee Community Achievement Award',
    'https://example.test/scholarships/tennessee-community',
    'https://example.test/scholarships/tennessee-community',
    'State/Local',
    'Both',
    1500.00,
    1500.00,
    '2027-04-10',
    'Fictional scholarship for development testing only.'
FROM providers p
JOIN states s ON s.state_code = 'TN'
WHERE p.provider_name = 'Demo Future Scholars Foundation';

-- Scholarship 3: National
INSERT INTO scholarships (
    provider_id,
    state_id,
    theme_id,
    scholarship_name,
    official_url,
    normalized_url,
    national_or_state_local,
    school_level,
    award_amount_min,
    award_amount_max,
    application_deadline,
    notes
)
SELECT
    p.provider_id,
    s.state_id,
    t.theme_id,
    'Demo National Technology Scholarship',
    'https://example.test/scholarships/national-technology',
    'https://example.test/scholarships/national-technology',
    'National',
    'Both',
    2000.00,
    2000.00,
    '2027-05-01',
    'Fictional scholarship for development testing only.'
FROM providers p
JOIN states s ON s.state_code = 'NA'
JOIN national_themes t
    ON t.theme_name = 'Computer science, data science, and IT'
WHERE p.provider_name = 'Demo Future Scholars Foundation';
