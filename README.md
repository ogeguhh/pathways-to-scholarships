# Pathways to Scholarships

An ICS 325 team project designed to help students and families discover scholarship opportunities through an accessible, organized, and user-friendly web application.

## Project Features

The project includes the following features:

- Scholarship searching and filtering by state, grade level, and field of study.
- Scholarship information displaying providers, locations, award ranges, and application deadlines.
- About, FAQ, and Contact pages to provide project information, answer common questions, and support user inquiries.
- Fictional demonstration scholarships for local development and testing.

## Recent Development Updates

The following components have been added to the project:

- **Database Structure:** MySQL database schema established through `sql/schema_v1.sql`.
- **Reference Data:** Seed scripts added for scholarship categories, locations, grade levels, and other reference information.
- **Development Data:** Sample scholarships and a placeholder development account included for testing.
- **Backend Integration:** PHP backend structure using PDO for MySQL database connectivity.
- **Security Structure:** Shared PHP includes organized to support secure database access and application functionality.
- **Informational Pages:** About, FAQ, and Contact functionality incorporated into the application.
- **Local Development:** Project configured for testing through Apache or PHP's built-in development server.

## Project Structure and Configuration

The application uses PHP, MySQL, HTML, CSS, and JavaScript.

Database initialization and development data are organized into the following SQL files:

- `sql/schema_v1.sql`
- `sql/seed_reference.sql`
- `sql/seed_development.sql`
- `sql/seed_sample.sql`

Database connectivity is managed locally through `includes/db.php`, using PDO. Database credentials are excluded from version control to maintain security.

The project supports local development through Apache or PHP's built-in server at `http://127.0.0.1:8000`.

Additional configuration and testing documentation is available in `SETUP.md`.

## Development Status

The project has progressed through its initial database setup, sample data preparation, backend organization, and informational page development. These components establish the foundation for continued integration, feature testing, and collaborative development.

**Note:** All `DEMO` scholarships and development accounts are fictional and used exclusively for local testing. They do not represent real scholarship opportunities or production credentials.
