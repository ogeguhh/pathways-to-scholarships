<?php
declare(strict_types=1);

/*
 * Pathways to Scholarships
 * Official 29-column spreadsheet template.
 *
 * These headings match the professor's original Excel file.
 * The import reader, validator, and downloadable template
 * will all use this same list.
 */

function scholarshipImportFields(): array
{
    return [
        'Scholarship name',
        'Scholarship provider/organization',
        'Provider type',
        'Official scholarship URL',
        'Discovery source',
        'State/region',
        'National vs. state/local',
        'Eligible grade levels',
        'Middle school / high school / both',
        'Scholarship category',
        'Field/area of interest',
        'Award amount',
        'Number of awards (if known)',
        'Renewable (Yes/No/Unknown)',
        'Maximum potential value',
        'Application open date (if known)',
        'Application deadline',
        'Residency requirement',
        'Minimum GPA (if any)',
        'Financial need required (Yes/No/Unknown)',
        'Essay required',
        'Recommendation required',
        'Transcript/test score required',
        'Community service requirement',
        'Leadership requirement',
        'Other major eligibility requirements',
        'Application method',
        'Verification date',
        'Notes',
    ];
}
