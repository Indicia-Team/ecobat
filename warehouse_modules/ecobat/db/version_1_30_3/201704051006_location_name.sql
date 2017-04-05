ALTER TABLE ecobat_occurrences
ADD COLUMN location_name CHARACTER VARYING;

COMMENT ON COLUMN ecobat_occurrences.location_name IS 'Free text site name';