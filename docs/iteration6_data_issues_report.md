# Iteration 6 — Data Issues Report

Project: Pathways to Scholarships
Course: ICS 325
Date: November 16, 2026

## Overview

During Iteration 6, we developed a data quality dashboard to identify problems in our imported scholarship records. The dashboard checks for expired application deadlines, outdated or missing verification dates, missing important information, and possible duplicate scholarships.

## Current Dataset

Our database contains 61 scholarship records:

- 58 active scholarships
- 3 archived scholarships
- 23 active national scholarships
- 35 active state/local scholarships

## Data Quality Findings

The dashboard identified:

- 12 active scholarships with expired application deadlines.
- 19 active scholarships with missing verification dates.
- 29 active scholarships flagged for missing key fields.
- 0 possible duplicates based on matching normalized official URLs.

Some scholarships may appear in multiple categories.

## Examples of Expired Scholarships

| Scholarship | Application Deadline |
|---|---|
| 3R Scholarship Fund | 2026-02-17 |
| Angelo and Irene Ravigoni Scholarship Fund | 2026-02-17 |
| Tennessee Promise | 2026-11-02 |
| NSHSS Your First Scholarship 2026 | 2026-10-15 |
| NSHSS Foundation STEM Scholarship 2026 | 2026-10-20 |

These records have official scholarship URLs and verification dates, but their recorded application deadlines have passed.

## Coverage Findings

The coverage report identified:

- 48 states with no active state/local scholarships.
- 26 national themes with no active national scholarships.

These gaps reflect the current imported dataset and do not necessarily indicate errors.

## Recommended Actions

Expired scholarship deadlines should be reviewed against official provider websites to determine whether updated application dates are available.

Missing information should be verified before updating records.

Future imports should continue checking for duplicate scholarships and incomplete data.

## Pending Work

The additional 20 scholarships per student have not yet been completed.

Once the next round of research is available, the team will combine the files, import the additional scholarships, and review the updated dataset.

No scholarship records were modified during this data quality review.
