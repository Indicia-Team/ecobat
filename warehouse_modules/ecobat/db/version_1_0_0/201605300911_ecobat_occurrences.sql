
INSERT INTO termlists (title, description, created_on, created_by_id, updated_on, updated_by_id, external_key)
VALUES ('Ecobat pass definitions', 'Definitions of types of bat pass for Ecobat.', now(), 1, now(), 1, 'ecobat:pass_definitions');

select insert_term('Registration', 'eng', null, 'ecobat:pass_definitions');
select insert_term('Passes', 'eng', null, 'ecobat:pass_definitions');
select insert_term('Individual calls/pulses', 'eng', null, 'ecobat:pass_definitions');

CREATE TABLE ecobat_occurrences
(
  id serial NOT NULL,
  taxa_taxon_list_id integer NOT NULL,
  external_key char(16), --
  entered_sref character varying(40) NOT NULL,
  entered_sref_system character varying (10) NOT NULL,
  easting integer, --
  northing integer, --
  geom geometry(Geometry,900913),
  map_sq_10km_id integer, --
  sensitivity integer,
  date_start date NOT NULL,
  passes integer NOT NULL,
  pass_definition_id integer NOT NULL,
  detector_model character varying,
  min_temperature_c integer,
  precipitation_mm integer,
  wind_speed_mph integer,
  linear_features boolean NOT NULL DEFAULT FALSE,
  feature_type character varying,
  roost boolean NOT NULL DEFAULT FALSE,
  roost_taxa_taxon_list_id integer,
  roost_external_key char(16), --
  habitat_id integer,
  occurrence_id integer,
  group_id integer,
  created_on timestamp without time zone NOT NULL, -- Date this record was created.
  created_by_id integer NOT NULL, -- Foreign key to the users table (creator).
  updated_on timestamp without time zone NOT NULL, -- Date this record was last updated.
  updated_by_id integer NOT NULL,
  CONSTRAINT pk_ecobat_occurrences PRIMARY KEY (id),
  CONSTRAINT fk_ecobat_occurrence_10km_map_square FOREIGN KEY (map_sq_10km_id)
      REFERENCES map_squares (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_taxon FOREIGN KEY (taxa_taxon_list_id)
      REFERENCES taxa_taxon_lists (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_roost_taxon FOREIGN KEY (roost_taxa_taxon_list_id)
      REFERENCES taxa_taxon_lists (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_pass_definition FOREIGN KEY (pass_definition_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_habitat FOREIGN KEY (habitat_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_occurrences FOREIGN KEY (occurrence_id)
      REFERENCES occurrences (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_group FOREIGN KEY (group_id)
      REFERENCES groups (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT chk_ecobat_sensitivity
      CHECK (sensitivity is null or sensitivity in (100, 1000, 2000, 10000, 100000))
)
WITH (
  OIDS=FALSE
);

COMMENT ON TABLE ecobat_occurrences
  IS 'Optimised table for ecobat reference range occurrences.';

COMMENT ON COLUMN ecobat_occurrences.id IS 'Unique identifier of the reference range record.';
COMMENT ON COLUMN ecobat_occurrences.taxa_taxon_list_id IS 'Foreign key to the taxa_taxon_lists table. Identifies the taxa on a taxon list that this occurrence is a record of.';
COMMENT ON COLUMN ecobat_occurrences.external_key IS 'Preferred taxon version key.';
COMMENT ON COLUMN ecobat_occurrences.entered_sref IS 'Spatial reference that was provided for the record.';
COMMENT ON COLUMN ecobat_occurrences.entered_sref_system IS 'System that was used for the spatial reference in entered_sref.';
COMMENT ON COLUMN ecobat_occurrences.easting IS 'OSGB easting for the record.';
COMMENT ON COLUMN ecobat_occurrences.northing IS 'OSGB northing for the record.';
COMMENT ON COLUMN ecobat_occurrences.geom IS 'Geometry for the record.';
COMMENT ON COLUMN ecobat_occurrences.map_sq_10km_id IS 'Foreign key to the map_squares table. Identifies the 10km square the record falls into.';
COMMENT ON COLUMN ecobat_occurrences.sensitivity IS 'Sensitivity preferences for the record. Null = record withheld from the main occurrences dataset and only used for reference ranges. Otherwise this is the size of the grid square to blur to.';
COMMENT ON COLUMN ecobat_occurrences.date_start IS 'Date at the start of the nights surveying.';
COMMENT ON COLUMN ecobat_occurrences.passes IS 'Total number of passes during the night for this species.';
COMMENT ON COLUMN ecobat_occurrences.pass_definition_id IS 'Foreign key to the termlists_terms table. Defines the method used to identify a pass.';
COMMENT ON COLUMN ecobat_occurrences.detector_model IS 'The make and model of bat detector used.';
COMMENT ON COLUMN ecobat_occurrences.min_temperature_c IS 'Minimum temperature during the night (degrees centigrade)';
COMMENT ON COLUMN ecobat_occurrences.precipitation_mm IS 'Total precipitation during the night (mm)';
COMMENT ON COLUMN ecobat_occurrences.wind_speed_mph IS 'Maximum wind speed (mph)';
COMMENT ON COLUMN ecobat_occurrences.linear_features IS 'Presence or absence of linear features within 50m';
COMMENT ON COLUMN ecobat_occurrences.feature_type IS 'Type of linear feature';
COMMENT ON COLUMN ecobat_occurrences.roost IS 'Presence or absence of a roost within 50m.';
COMMENT ON COLUMN ecobat_occurrences.roost_taxa_taxon_list_id IS 'Foreign key to the taxa_taxon_lists table. Taxon at the roost.';
COMMENT ON COLUMN ecobat_occurrences.roost_taxa_taxon_list_id IS 'Foreign key to the taxa_taxon_lists table. Taxon at the roost.';
COMMENT ON COLUMN ecobat_occurrences.habitat_id IS 'Foreign key to the termlists_terms table. Identifies the Phase 1 habitat.';
COMMENT ON COLUMN ecobat_occurrences.occurrence_id IS 'Foreign key to the occurrences table. Identifies the occurrence lodged in the main occurrences table for reference range records which are made publically available.';
COMMENT ON COLUMN ecobat_occurrences.group_id IS 'Foreign key to the groups table. Identifies the Consultants Portal project the record belongs to  .';
COMMENT ON COLUMN ecobat_occurrences.created_on IS 'Date this record was created.';
COMMENT ON COLUMN ecobat_occurrences.created_by_id IS 'Foreign key to the users table (creator).';
COMMENT ON COLUMN ecobat_occurrences.updated_on IS 'Date this record was last updated.';
COMMENT ON COLUMN ecobat_occurrences.updated_by_id IS 'Foreign key to the users table (last updater).';