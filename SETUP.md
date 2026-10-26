# Pathways to Scholarships files

Copy `includes/security.php` into the repository's existing `includes/` folder. Copy `about.php`, `faq.php`, `contact.php`, and `scholarships.php` into the repository root. Do not replace teammates' files.

**Dependencies:** Your teammate must supply `includes/db.php` that defines a connected `$pdo` variable. Do not commit database credentials. This package does not contain a database configuration file.

**Database setup:** Configure your local, uncommitted `includes/db.php` to provide a connected PDO variable named `$pdo`; do not commit credentials. For a fresh development database, run `sql/schema_v1.sql`, then `sql/seed_reference.sql`, `sql/seed_development.sql`, and `sql/seed_sample.sql` in that order. The sample seed inserts three fictional scholarships and their providers; it uses the schema's foreign keys and is safe to rerun.

**Scholarship search test:** Start Apache and MySQL in XAMPP, run the database setup above, and open `http://localhost/pathways-to-scholarships/index.php`. Choose a state and grade, optionally enter a field of study such as `STEM`, then submit the search. The results page should filter the three `DEMO` scholarships (state searches also include national awards), and provides a clear-filters link. Open `scholarships.php` directly to view the unfiltered read-only list. The page reads active scholarships and does not offer edits.

**Other pages:** Open `about.php`, `faq.php`, and `contact.php`. Check that contact submissions save in MySQL and invalid CSRF submissions are rejected. The authorization functions are deliberately nonfunctional stubs and must not be used as real access control.

**Push from the cloned repo:** `git switch -c feature/security-and-pages`, `git add includes/security.php about.php faq.php contact.php scholarships.php`, `git commit -m "Add security helpers and informational pages"`, `git push -u origin feature/security-and-pages`.

## Provider management and audit log

The administrator tools are available at `admin/providers.php` and `admin/audit_log.php`. Provider management supports searching, editing names and types, and merging duplicate providers. A merge reassigns scholarships and deletes the duplicate provider in one transaction. Audit records are read-only and can be searched, filtered, sorted, and paginated.

The existing schema already contains the `audit_log` table, so these changes do not require a database migration. Use the existing schema and development seed steps above for a fresh database.

**Administrator access is intentionally unavailable until authentication is implemented.** `includes/security.php` currently returns no signed-in user and denies every role. The new administrator pages and import page fail closed, and administrator links remain hidden. To enable them, the team must implement `current_user_id()` and `has_role()` using its real session and role checks. The audit logger uses the seeded `admin@example.test` user only as a development actor fallback; it does not grant access.

The existing import workflow only uploads and stages rows. It has no batch-commit or batch-rollback handler. This implementation records a `scholarship.import` audit event when a staging batch is saved successfully; it does not claim that staged rows have been published. There are also no scholarship creation, editing, archiving, or verification handlers in this repository, so those actions cannot yet be audited. Add their audit calls inside their successful database transactions when those handlers are implemented.

### Manual XAMPP checks

1. Start Apache and MySQL in XAMPP and configure the local, uncommitted `includes/db.php` to connect to the database.
2. In phpMyAdmin, confirm `audit_log`, `providers`, `provider_types`, and `scholarships` exist. No additional SQL script is needed for these features.
3. Before authentication is implemented, visit `admin/providers.php`, `admin/provider_merge.php`, and `admin/audit_log.php`; each should return HTTP 403 rather than display administrator data. The homepage should not show administrator links.
4. After the team implements and tests real administrator authorization, use **Manage Providers** to edit a provider, reject an empty or duplicate name, preview a merge, then confirm it. Check the reassigned scholarship rows and matching `provider.edit` / `provider.merge` entries in `audit_log`.
5. Submit a valid CSV or XLSX file at `admin/import.php`. Confirm the batch and `scholarship.import` audit row are both saved, or neither is saved if the transaction fails.
6. To test the audit interface, create enough audit records to cross a page boundary, then try the action, administrator, date, search, and timestamp-order filters.
7. Batch publish/rollback and scholarship create/edit/archive/verify audit cases remain blocked until their handlers are implemented.
