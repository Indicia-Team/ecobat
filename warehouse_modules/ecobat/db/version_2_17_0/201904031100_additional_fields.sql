ALTER TABLE ecobat_occurrences
  ADD COLUMN site_name character varying;

COMMENT ON COLUMN ecobat_occurrences.site_name IS 'Name of the site (as opposed to detector location).';

ALTER TABLE ecobat_occurrence_passes
  ADD COLUMN site_name character varying;

COMMENT ON COLUMN ecobat_occurrence_passes.site_name IS 'Name of the site (as opposed to detector location).';