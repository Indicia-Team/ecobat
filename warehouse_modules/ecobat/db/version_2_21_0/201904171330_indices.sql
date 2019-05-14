DROP INDEX IF EXISTS ix_ecobat_occurrence_passes_match;
CREATE INDEX ix_ecobat_occurrence_passes_match ON ecobat_occurrence_passes(pass_definition_id, date_start);

DROP INDEX IF EXISTS ix_ecobat_occurrence_ref_set;
CREATE INDEX ix_ecobat_occurrence_ref_set ON ecobat_occurrences(pass_definition_id, day_of_year, external_key);

DROP INDEX IF EXISTS ix_ecobat_occurrence_ref_set_locality;
CREATE INDEX ix_ecobat_occurrence_ref_set_locality ON ecobat_occurrences(easting, northing);
