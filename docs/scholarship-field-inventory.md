# Scholarship Field Inventory

## ICS 325 — Pathways to Scholarships

This document lists the 29 scholarship fields identified in Section 6.1 of the project requirements.

| # | Field | Database Requirement |
|---|---|---|
| 1 | Scholarship name | Required |
| 2 | Scholarship provider/organization | Foreign key to providers |
| 3 | Provider type | Foundation/nonprofit, corporation, college/university, government agency, professional association, community organization, other |
| 4 | Official scholarship URL | Required; normalized for duplicate detection |
| 5 | Discovery source | Source where scholarship was found |
| 6 | State/region | Two-letter state code or National |
| 7 | National vs. state/local | National or State/Local |
| 8 | Eligible grade levels | Many-to-many relationship, grades 6–12 |
| 9 | Middle school / high school / both | Derived from grade levels or stored consistently |
| 10 | Scholarship category | Many-to-many relationship |
| 11 | Field/area of interest | Many-to-many relationship |
| 12 | Award amount | Numeric; minimum and maximum for ranges |
| 13 | Number of awards | Nullable integer |
| 14 | Renewable | Yes, No, or Unknown |
| 15 | Maximum potential value | Nullable numeric |
| 16 | Application open date | Nullable date |
| 17 | Application deadline | Date or rolling deadline |
| 18 | Residency requirement | Text |
| 19 | Minimum GPA | Nullable decimal |
| 20 | Financial need required | Yes, No, or Unknown |
| 21 | Essay required | Yes, No, or Unknown |
| 22 | Recommendation required | Yes, No, or Unknown |
| 23 | Transcript/test score required | Yes, No, or Unknown |
| 24 | Community service requirement | Yes, No, or Unknown |
| 25 | Leadership requirement | Yes, No, or Unknown |
| 26 | Other major eligibility requirements | Text |
| 27 | Application method | Online form, email, mail, other |
| 28 | Verification date | Date |
| 29 | Notes | Text |

## Additional System Fields

The application also tracks:

- National theme
- Contributing student
- Import batch ID
- Created and updated timestamps
- Created and updated users
- Scholarship status (active or archived)

## Data Collection Rules

- A scholarship is National if it is open to residents of two or more states.
- Otherwise, it is State/Local.
- The official scholarship URL is required.
- Duplicate scholarships are identified using the normalized official URL.
- The application must preserve all template fields during import and export.
