DROP INDEX IF EXISTS ix_ecobat_occurrence_ref_set;
CREATE INDEX ix_ecobat_occurrence_ref_set ON ecobat_occurrences(pass_definition_id, external_key, day_of_year);