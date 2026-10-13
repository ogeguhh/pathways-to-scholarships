# Your Pathways to Scholarships files

Copy `includes/security.php` into the repository's existing `includes/` folder. Copy `about.php`, `faq.php`, `contact.php`, and `scholarships.php` into the repository root. Do not replace teammates' files.

**Dependencies:** Your teammate must supply `includes/db.php` that defines a connected `$pdo` variable. Do not commit database credentials. This package does not contain a database configuration file.

**Database setup:** Configure your local, uncommitted `includes/db.php` to provide a connected PDO variable named `$pdo`; do not commit credentials. For a fresh development database, run `sql/schema_v1.sql`, then `sql/seed_reference.sql`, `sql/seed_development.sql`, and `sql/seed_sample.sql` in that order. The sample seed inserts three fictional scholarships and their providers; it uses the schema's foreign keys and is safe to rerun.

**Scholarship search test:** Start Apache and MySQL in XAMPP, run the database setup above, and open `http://localhost/pathways-to-scholarships/index.php`. Choose a state and grade, optionally enter a field of study such as `STEM`, then submit the search. The results page should filter the three `DEMO` scholarships (state searches also include national awards), and provides a clear-filters link. Open `scholarships.php` directly to view the unfiltered read-only list. The page reads active scholarships and does not offer edits.

**Other pages:** Open `about.php`, `faq.php`, and `contact.php`. Check that contact submissions save in MySQL and invalid CSRF submissions are rejected. The authorization functions are deliberately nonfunctional stubs and must not be used as real access control.

**Push from the cloned repo:** `git switch -c feature/security-and-pages`, `git add includes/security.php about.php faq.php contact.php scholarships.php`, `git commit -m "Add security helpers and informational pages"`, `git push -u origin feature/security-and-pages`.
