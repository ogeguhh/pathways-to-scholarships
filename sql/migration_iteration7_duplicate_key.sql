
-- Pathways to Scholarships
-- Iteration 7: Composite Scholarship Duplicate Key
--
-- Updated FR-IMP-06 following professor clarification:
-- Duplicate detection uses normalized URL + scholarship name.
--
-- Run once against an existing database using the old URL key.
-- Back up the database before executing this migration.

-- Replace URL-only uniqueness with a composite generated hash.
-- Scholarship names are compared without capitalization
-- or surrounding spaces.
-- Archived scholarships remain exempt from uniqueness checks.

ALTER TABLE scholarships
    DROP INDEX uq_active_url,
    DROP COLUMN active_url_hash,

    ADD COLUMN active_duplicate_hash BINARY(32)
        GENERATED ALWAYS AS (
            CASE
                WHEN status = 'active'
                THEN UNHEX(
                    SHA2(
                        CONCAT(
                            SHA2(normalized_url, 256),
                            SHA2(
                                LOWER(TRIM(scholarship_name)),
                                256
                            )
                        ),
                        256
                    )
                )
                ELSE NULL
            END
        ) STORED,

    ADD UNIQUE KEY uq_active_duplicate (
        active_duplicate_hash
    );

-- Invalidate pending batches validated under the old rule.
-- Preserve their staging rows and historical validation results.
-- These batches must be uploaded again before committing.

UPDATE import_batches
SET batch_status = 'failed'
WHERE batch_status IN ('staged', 'validated');
