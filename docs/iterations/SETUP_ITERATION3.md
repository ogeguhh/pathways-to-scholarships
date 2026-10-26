# Iteration 3 — Requirements 1–3 (local work; not committed)

## Added files
- `templates/scholarship_import_template.xlsx` and `.csv`: same 29 headers as Data Science workbook.
- `import.php`: template downloads, multiple-file upload, file validation, per-student count check, staging.
- `includes/import_reader.php`: CSV and XLSX row readers.

## Local setup (Mac XAMPP)
1. Extract this project folder into `/Applications/XAMPP/xamppfiles/htdocs/`.
2. Start Apache and MySQL. Keep the existing `pathways_scholarships` database with Iteration 2 schema and seeds.
3. Create your **local** `includes/db.php` defining `$pdo` as a PDO connection to `pathways_scholarships` (excluded from Git; do not commit credentials).
4. Enable PHP extensions `zip` and `SimpleXML` for XLSX support. Ensure PHP `file_uploads=On`, `upload_max_filesize >= 5M`, `post_max_size >= 55M`, and reload Apache after changes.
5. Open `http://localhost/pathways-to-scholarships-iteration3-req1-3/import.php` (adjust to your extracted folder name).

## Testing
- Download both templates and confirm 29 columns.
- Save 20 rows using filename `LastName_FirstName.csv`; upload and verify `Staged` and count 20.
- Upload 19 rows and verify count warning.
- Upload several CSV/XLSX files together.
- Reject incorrect headers, extension, oversized file, and invalid filename.
- In phpMyAdmin, inspect `import_batches` and `import_staging_rows` for staged raw data.

## Scope and handoff
- Each file creates one batch and preserves raw rows in `import_staging_rows` as JSON; `validation_status=Pending`.
- Count is per uploaded file, not aggregated across multiple submissions by the same student; team must agree whether 20 means exactly 20 per file or per student across files, and whether the state/national mix needs separate checks.
- State/national assignment checks are NOT implemented because no per-student assignment roster was supplied.
- Cleaning, duplicate detection, Ready/Warning/Duplicate/Error reports, committing to final scholarships, rollback, and import history belong to teammates' later requirements.
- XLSX parser reads the first worksheet (`sheet1.xml`), including the official provided workbook, and retains numeric Excel date serials for later validation/cleaning. Complex nonstandard XLSX formats should be tested with the team.
- This iteration's upload endpoint currently uses the shared CSRF helper but is **not role-restricted** because the current `require_role()` stub intentionally fails closed. Before deployment, integrate actual administrator authorization and add rate limits.
- Do not upload real personal/sensitive data to a publicly accessible development server until authentication and protections are completed.
