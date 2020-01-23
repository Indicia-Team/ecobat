DROP INDEX IF EXISTS ix_ecobat_occurrence_passes_match;
CREATE INDEX ix_ecobat_occurrence_passes_match ON ecobat_occurrence_passes(pass_definition_id, date_start);

DROP INDEX IF EXISTS ix_ecobat_occurrence_ref_set;
CREATE INDEX ix_ecobat_occurrence_ref_set ON ecobat_occurrences(pass_definition_id, day_of_year, external_key);

DROP INDEX IF EXISTS ix_ecobat_occurrence_ref_set_locality;
CREATE INDEX ix_ecobat_occurrence_ref_set_locality ON ecobat_occurrences(easting, northing);

DROP INDEX IF EXISTS ix_erccis_summary_import_lookup;
CREATE index ix_erccis_summary_import_lookup ON cache_taxa_taxon_lists(external_key)
WHERE taxon_list_id=1 AND preferred=true;

DROP INDEX IF EXISTS ix_erccis_summary_occurrences_unique_id;
CREATE index ix_erccis_summary_occurrences_unique_id ON erccis_summary_occurrences(unique_id);
