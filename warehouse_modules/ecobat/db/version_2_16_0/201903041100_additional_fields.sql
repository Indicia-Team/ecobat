ALTER TABLE ecobat_occurrences
  ADD COLUMN method_of_classification character varying,
  ADD COLUMN analysis_software_used character varying;

COMMENT ON COLUMN ecobat_occurrences.method_of_classification IS 'Method of identification of the bat species.';
COMMENT ON COLUMN ecobat_occurrences.analysis_software_used IS 'Analysis software used for the data.';

ALTER TABLE ecobat_occurrence_passes
  ADD COLUMN method_of_classification character varying,
  ADD COLUMN analysis_software_used character varying;

COMMENT ON COLUMN ecobat_occurrence_passes.method_of_classification IS 'Method of identification of the bat species.';
COMMENT ON COLUMN ecobat_occurrence_passes.analysis_software_used IS 'Analysis software used for the data.';

