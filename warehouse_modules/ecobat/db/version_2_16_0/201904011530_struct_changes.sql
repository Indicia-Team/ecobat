ALTER TABLE ecobat_occurrence_passes
   ALTER COLUMN lat SET NOT NULL,
   ALTER COLUMN lon SET NOT NULL;

ALTER TABLE ecobat_occurrence_passes
  DROP COLUMN entered_sref,
  DROP COLUMN entered_sref_system,
  DROP COLUMN geom;