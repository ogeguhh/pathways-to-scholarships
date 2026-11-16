# Iteration 3 — Scholarship Import and Data Cleaning

## Overview

The Pathways to Scholarships application accepts CSV and Excel scholarship spreadsheets. Excel files are converted to CSV in the browser before being uploaded to the PHP backend.

Uploaded records are staged and validated before being committed to the scholarship database.

## Template Validation

Each imported spreadsheet must contain the 29 official scholarship columns.

The importer:
- Rejects missing, extra, blank, or duplicate headings.
- Allows the required columns in different orders.
- Removes extra whitespace and nonbreaking spaces from headings.
- Rejects rows with an incorrect number of columns.

## Data Cleaning

### Text
Leading and trailing whitespace is removed. Repeated whitespace and nonbreaking spaces are normalized.

### State and Region
Full state names and two-letter abbreviations are converted to state codes.

Scholarships open to residents of two or more states are classified as National, as required by FR-IMP-05. Scholarships restricted to one state are classified as State/Local.

Regional descriptions that cannot be converted to a single state code are flagged for manual review without inventing a state.

### Yes/No/Unknown
Recognized affirmative and negative values are standardized to Yes or No. Unrecognized values become Unknown.

### Award Amounts
Numeric values such as $1,250.00 are converted into numbers.

Descriptive values such as "Varies" are preserved in the staging data and retained in scholarship notes when they cannot be stored in numeric database columns.

### Dates
Recognized dates are converted to YYYY-MM-DD.

Unknown or descriptive dates are flagged for review. Original descriptions are retained in staging; descriptive application dates are also preserved in scholarship notes.

### Eligible Grades
Grade ranges such as Grades 9-12 are expanded into individual grade numbers. Eligible grades are linked to scholarships through the scholarship_grades table.

### Official URLs
URLs are normalized for duplicate comparisons by standardizing the scheme and host capitalization and removing fragments, default ports, and trailing slashes.

Known tracking parameters, including utm_* parameters, fbclid, gclid, msclkid, mc_cid, and mc_eid, are removed during normalization.

Other query parameters are preserved because they may identify specific scholarships.

Duplicate detection uses the normalized official URL alone, as required by FR-IMP-06.

## Duplicate Detection

The importer checks normalized official scholarship URLs:
- Within the uploaded file.
- Against other active staged batches.
- Against scholarships already committed to the database.

Duplicate records are flagged rather than imported.

## Validation Statuses

- Ready: No detected validation problems.
- Warning: Importable record requiring review.
- Duplicate: Scholarship URL already detected.
- Error: Record contains a blocking validation problem.

## Commit and Rollback

Ready and Warning records can be committed through the administrator validation page.

Commit operations use database transactions. Scholarship grades, categories, and fields of study are saved with the scholarship.

Committed batches can be rolled back. Rollback removes the batch's imported scholarships and their relationships while preserving staging records and import history.

## Testing Completed

- Valid 29-column CSV accepted.
- Missing required column rejected.
- Duplicate scholarship URL detected.
- Browser-based commit and rollback tested.
- Fourteen cleaning tests passed.
- Three classification tests passed.

## Limitations and Follow-Up

Regional scholarships require review to confirm all eligible states.

Descriptive scholarship values may require manual review before publication.

The administrator interface currently uses a local-development access restriction. Proper role-based authentication is planned for Iteration 7.
