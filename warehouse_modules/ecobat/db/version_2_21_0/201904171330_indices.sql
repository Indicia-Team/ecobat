DROP INDEX IF EXISTS ix_ecobat_occurrence_passes_match;
CREATE INDEX ix_ecobat_occurrence_passes_match ON ecobat_occurrence_passes(pass_definition_id, date_start);