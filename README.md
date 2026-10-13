# Pathways to Scholarships

An ICS 325 project that helps students and families discover scholarship opportunities.

## Features

- Search active scholarships by state, grade level, and field of study.
- View scholarship providers, locations, award ranges, and application deadlines.
- Read project information and FAQs, or send a message through the contact form.
- Browse a small set of fictional demo scholarships for local development.

## Run locally

You need PHP with the PDO MySQL extension and a MySQL-compatible database such as MySQL or MariaDB. The app expects a local, uncommitted `includes/db.php` file that connects to the database and defines a PDO variable named `$pdo`.

1. Create the database by running [`sql/schema_v1.sql`](sql/schema_v1.sql).
2. Load the development data in order: [`sql/seed_reference.sql`](sql/seed_reference.sql), [`sql/seed_development.sql`](sql/seed_development.sql), then [`sql/seed_sample.sql`](sql/seed_sample.sql).
3. Configure `includes/db.php` for your local database. Do not commit database credentials.
4. Serve the project with Apache, or from the repository root run:

   ```sh
   php -S 127.0.0.1:8000
   ```

5. Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

See [`SETUP.md`](SETUP.md) for more setup and testing details.

> **Demo data:** The `DEMO` scholarships and development account are fictional and intended for local testing only. They are not real scholarship opportunities or production credentials.
