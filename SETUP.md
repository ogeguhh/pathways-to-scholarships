# Your Pathways to Scholarships files

Copy `includes/security.php` into the repository's existing `includes/` folder. Copy `about.php`, `faq.php`, `contact.php`, and `scholarships.php` into the repository root. Do not replace teammates' files.

**Dependencies:** Your teammate must supply `includes/db.php` that defines a connected `$pdo` variable. Do not commit database credentials. This package does not contain a database configuration file.

**Schema assumptions (verify with your teammate):** `contact_messages` has `name`, `email`, `message`; `scholarships` has `title`, `amount`. Adapt the queries to the actual Section 6.2 schema. The sample scholarships should be inserted manually into the real database, observing any required foreign keys.

**Test:** Start Apache and MySQL in XAMPP. Open `http://localhost/pathways-to-scholarships/about.php`, `faq.php`, `contact.php`, and `scholarships.php`. Check that contact submissions save in MySQL and invalid CSRF submissions are rejected. The authorization functions are deliberately nonfunctional stubs and must not be used as real access control.

**Push from the cloned repo:** `git switch -c feature/security-and-pages`, `git add includes/security.php about.php faq.php contact.php scholarships.php`, `git commit -m "Add security helpers and informational pages"`, `git push -u origin feature/security-and-pages`.
