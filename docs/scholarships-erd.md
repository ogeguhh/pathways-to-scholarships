# Pathways to Scholarships — Entity-Relationship Diagram

## Iteration 1 — Database Design

This ERD represents the database structure planned for the
Pathways to Scholarships application.

```mermaid
erDiagram
    ROLES ||--o{ USERS : assigns
    USERS ||--o{ STUDENT_PROFILES : manages
    STATES ||--o{ STUDENT_PROFILES : residence
    GRADE_LEVELS ||--o{ STUDENT_PROFILES : grade
    STUDENT_PROFILES ||--o{ PROFILE_INTERESTS : has
    FIELDS_OF_STUDY ||--o{ PROFILE_INTERESTS : selected

    PROVIDER_TYPES ||--o{ PROVIDERS : classifies
    PROVIDERS ||--o{ SCHOLARSHIPS : offers
    STATES ||--o{ SCHOLARSHIPS : region
    NATIONAL_THEMES ||--o{ SCHOLARSHIPS : theme

    SCHOLARSHIPS ||--o{ SCHOLARSHIP_GRADES : has
    GRADE_LEVELS ||--o{ SCHOLARSHIP_GRADES : includes

    SCHOLARSHIPS ||--o{ SCHOLARSHIP_CATEGORIES : has
    CATEGORIES ||--o{ SCHOLARSHIP_CATEGORIES : includes

    SCHOLARSHIPS ||--o{ SCHOLARSHIP_FIELDS : has
    FIELDS_OF_STUDY ||--o{ SCHOLARSHIP_FIELDS : includes

    STUDENT_PROFILES ||--o{ SAVED_SCHOLARSHIPS : saves
    SCHOLARSHIPS ||--o{ SAVED_SCHOLARSHIPS : saved

    USERS ||--o{ IMPORT_BATCHES : uploads
    IMPORT_BATCHES ||--o{ IMPORT_STAGING_ROWS : contains
    IMPORT_BATCHES ||--o{ SCHOLARSHIPS : imported

    USERS ||--o{ CONTACT_MESSAGES : submits
    USERS ||--o{ AUDIT_LOG : performs

    ROLES {
        int role_id PK
        string role_name
    }

    USERS {
        int user_id PK
        int role_id FK
        string name
        string email
        string password_hash
        string account_status
    }

    STUDENT_PROFILES {
        int profile_id PK
        int user_id FK
        int state_id FK
        int grade_id FK
        string student_name
    }

    PROFILE_INTERESTS {
        int profile_id PK,FK
        int field_id PK,FK
    }

    PROVIDER_TYPES {
        int provider_type_id PK
        string type_name
    }

    PROVIDERS {
        int provider_id PK
        int provider_type_id FK
        string provider_name
    }

    STATES {
        int state_id PK
        string state_code
        string state_name
    }

    GRADE_LEVELS {
        int grade_id PK
        int grade_number
    }

    CATEGORIES {
        int category_id PK
        string category_name
    }

    FIELDS_OF_STUDY {
        int field_id PK
        string field_name
    }

    NATIONAL_THEMES {
        int theme_id PK
        string theme_name
    }

    SCHOLARSHIPS {
        int scholarship_id PK
        int provider_id FK
        int state_id FK
        int theme_id FK
        int import_batch_id FK
        string scholarship_name
        string official_url
        string normalized_url
        string national_or_state_local
        decimal award_amount_min
        decimal award_amount_max
        date application_deadline
        date verification_date
        string status
    }

    SCHOLARSHIP_GRADES {
        int scholarship_id PK,FK
        int grade_id PK,FK
    }

    SCHOLARSHIP_CATEGORIES {
        int scholarship_id PK,FK
        int category_id PK,FK
    }

    SCHOLARSHIP_FIELDS {
        int scholarship_id PK,FK
        int field_id PK,FK
    }

    SAVED_SCHOLARSHIPS {
        int profile_id PK,FK
        int scholarship_id PK,FK
        string application_status
    }

    IMPORT_BATCHES {
        int import_batch_id PK
        int uploaded_by FK
        string file_name
        string contributing_student
        datetime imported_at
        string batch_status
    }

    IMPORT_STAGING_ROWS {
        int staging_row_id PK
        int import_batch_id FK
        int row_number
        string validation_status
        string validation_message
    }

    CONTACT_MESSAGES {
        int message_id PK
        int user_id FK
        string sender_email
        string message
    }

    SEARCH_LOG {
        int search_id PK
        string state_filter
        string grade_filter
        string field_filter
        datetime searched_at
    }

    AUDIT_LOG {
        int audit_id PK
        int user_id FK
        string action
        string entity_type
        datetime created_at
    }
```

## Design Notes

- Primary keys uniquely identify records.
- Foreign keys connect related tables.
- A provider can offer multiple scholarships.
- Scholarships can have multiple grades, categories, and fields of study.
- Students and parents can maintain scholarship profiles.
- Saved scholarships belong to individual profiles.
- Uploaded scholarship files are recorded as import batches.
- Uploaded rows are staged before entering the live scholarship table.
- Audit records track important administrative actions.
- The complete scholarship fields from Section 6.1 will be included in the Iteration 2 database schema.
