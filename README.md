
# Pathways to Scholarships

ICS 325 Final Project — Fall 2026

## Project Overview

Pathways to Scholarships is a web application designed
to help middle and high school students and their families
find scholarships based on state, grade level, field of
study, and other eligibility requirements.

## Technologies

- HTML5
- CSS3
- JavaScript
- jQuery
- Bootstrap 5
- PHP 8.x
- MySQL 8.x
- PDO prepared statements
- Apache
- Python 3 for automated testing

## Current Development Status

Iteration 7 — Authentication, Authorization, and
Super-admin Management.

## Implemented Features

### Public Features

- Scholarship search and filtering
- Scholarship details
- Scholarship facts
- About page
- Frequently asked questions
- Contact page
- Student and Parent registration
- Account login and logout

### Registered User Features

- Personal account profile
- Profile updates
- Secure password changes
- Role-based navigation
- Mandatory temporary password replacement

### Administrator Features

- Administrative dashboard
- Scholarship management
- Provider management and merging
- CSV data imports
- Import validation
- Import history and rollback
- Scholarship verification and archiving
- Bulk scholarship operations
- CSV export
- Data quality dashboard
- Coverage reporting

### Super-admin Features

- User management dashboard
- User account creation
- User role changes
- Account activation and deactivation
- Administrative password resets
- Last active Super-admin protection
- Reference data management
- Reference data dependency protection
- Administrative audit log viewer
- Audit log filtering

## Security Features

- Password hashing and verification
- PHP session authentication
- Session ID regeneration
- 30-minute session inactivity timeout
- Role-based access control
- Server-side input validation
- CSRF protection
- PDO prepared statements
- HTML output escaping
- Password-based session invalidation
- Internal directory access restrictions
- Private database configuration excluded from Git

## User Roles

| Role | Permissions |
|---|---|
| Visitor | Public website access |
| Student | Public features and personal account management |
| Parent | Public features and personal account management |
| Administrator | Scholarship, provider, and import management |
| Super-admin | Administrator permissions and system management |

## Project Structure

- admin/ - Administrative pages and actions
- assets/ - CSS, JavaScript, and website assets
- docs/ - Project documentation and testing reports
- imports/ - Local imported data
- includes/ - Shared PHP functions and configuration
- pages/ - Public and registered user pages
- scripts/ - Command-line setup utilities
- sql/ - Database schema and seed files
- tests/ - Automated testing scripts

## Local Installation

### 1. Requirements

Install Apache, PHP 8.x, and MySQL 8.x.

MAMP can be used to provide the required local
development environment.

PHP must have PDO MySQL and mbstring support enabled.

### 2. Configure the Web Server

Set the Apache document root to the project directory.

For MAMP, the default local website address is:

http://localhost:8888/

Apache must support .htaccess access-control directives.

After cloning the repository, protect the local Git
directory by running:

    printf "Require all denied\n" > .git/.htaccess

This file is local to each Git repository and is not
included in commits.

### 3. Configure the Database

Create a local MySQL database named:

    pathways_scholarships_rebuild

For a new, empty database, execute the following SQL
files in order:

1. sql/schema_v1.sql
2. sql/seed_reference.sql
3. sql/seed_sample.sql

The development user seed is no longer required for
new Iteration 7 installations.

Existing installations should retain their database
and should not rerun the initial schema.

### 4. Configure the Database Connection

Copy the example configuration file:

    cp includes/config.example.php includes/config.php

Update includes/config.php with your local MySQL
host, port, database name, username, and password.

The private configuration file must not be committed
to GitHub.

### 5. Create Administrative Accounts

From the project root, run:

    php scripts/seed_admins.php

The script creates the following accounts:

- superadmin@example.test
- admin@example.test

Enter unique, strong passwords when prompted.

The script also deactivates the previous placeholder
development administrator if present and passwordless.

Run the seed script only when these administrative
accounts do not already exist.

### 6. Launch the Website

Start Apache and MySQL.

Open the website using the configured local server
address.

For MAMP:

http://localhost:8888/

## Iteration 7 Testing

The automated access-control testing script is:

    python3 tests/access_control_test.py

The script uses the local test database, existing test
accounts, and specific record IDs.

Register the required Student and Parent test accounts
and adjust the test record IDs if necessary before
running against a different database.

Passwords are entered interactively and are not stored
in the testing script.

### Recorded Automated Test Results

- Total tests: 340
- Passed: 340
- Failed: 0
- Pass rate: 100%

All five user roles were tested.

The automated tests cover HTTP access restrictions,
direct URL access, navigation visibility, request
methods, and selected CSRF rejection.

These results do not represent a comprehensive
production security assessment.

### Testing Documentation

- docs/access_control_matrix.csv
- docs/access_control_report.md

## Team Workflow

Development work is completed on feature branches
and merged into main through reviewed pull requests.

Local database credentials, account passwords, and
private installation notes must not be committed
to the repository.
