CREATE TABLE ecobat_occurrence_passes
(
  id serial NOT NULL,
  taxa_taxon_list_id integer NOT NULL,
  external_key char(16), --
  entered_sref character varying(40) NOT NULL,
  entered_sref_system character varying (10) NOT NULL,
  lat float,
  lon float,
  geom geometry(Geometry,900913),
  sensitivity integer NOT NULL default 1,
  number_of_bats integer NOT NULL default 1,
  date_start date NOT NULL,
  pass_time time NOT NULL,
  pass_definition_id integer NOT NULL,
  detector_make_id integer NOT NULL,
  detector_model character varying NOT NULL,
  detector_identity character varying NOT NULL,
  detector_height_m numeric(4,2),
  roost_within_25m boolean NOT NULL DEFAULT FALSE,
  activity_elevated_by_roost boolean NOT NULL DEFAULT FALSE,
  roost_species character varying,
  linear_feature_adjacent_id integer,
  linear_feature_25m_id integer,
  anthropogenic_feature_adjacent_id integer,
  anthropogenic_feature_25m_id integer,
  temperature_c numeric(4,2),
  rainfall_id integer,
  wind_speed_mph integer,
  ecobat_occurrence_id integer,
  group_id integer,
  created_on timestamp without time zone NOT NULL, -- Date this record was created.
  created_by_id integer NOT NULL, -- Foreign key to the users table (creator).
  updated_on timestamp without time zone NOT NULL, -- Date this record was last updated.
  updated_by_id integer NOT NULL,
  import_guid character varying,
  processed boolean default false,
  CONSTRAINT pk_ecobat_occurrence_passes PRIMARY KEY (id),
  CONSTRAINT fk_ecobat_occurrence_passes_taxon FOREIGN KEY (taxa_taxon_list_id)
      REFERENCES taxa_taxon_lists (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_pass_definition FOREIGN KEY (pass_definition_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_detector_make FOREIGN KEY (detector_make_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_linear_feature_adjacent FOREIGN KEY (linear_feature_adjacent_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_linear_feature_25m FOREIGN KEY (linear_feature_25m_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_anthropogenic_feature_adjacent FOREIGN KEY (linear_feature_adjacent_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_anthropogenic_feature_25m FOREIGN KEY (linear_feature_25m_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_rainfall_id FOREIGN KEY (rainfall_id)
      REFERENCES termlists_terms (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_ecobat_occurrences FOREIGN KEY (ecobat_occurrence_id)
      REFERENCES ecobat_occurrences (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT fk_ecobat_occurrence_passes_group FOREIGN KEY (group_id)
      REFERENCES groups (id) MATCH SIMPLE
      ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT chk_ecobat_passes_sensitivity
      CHECK (sensitivity is null or sensitivity in (1, 2, 3))
)
WITH (
  OIDS=FALSE
);

COMMENT ON TABLE ecobat_occurrence_passes
  IS 'Optimised table for ecobat reference range occurrences.';

COMMENT ON COLUMN ecobat_occurrence_passes.id IS 'Unique identifier of the reference range record.';
COMMENT ON COLUMN ecobat_occurrence_passes.taxa_taxon_list_id IS 'Foreign key to the taxa_taxon_lists table. Identifies the taxa on a taxon list that this occurrence is a record of.';
COMMENT ON COLUMN ecobat_occurrence_passes.external_key IS 'Preferred taxon version key.';
COMMENT ON COLUMN ecobat_occurrence_passes.entered_sref IS 'Spatial reference that was provided for the record.';
COMMENT ON COLUMN ecobat_occurrence_passes.entered_sref_system IS 'System that was used for the spatial reference in entered_sref.';
COMMENT ON COLUMN ecobat_occurrence_passes.lat IS 'WGS84 latitude for the record.';
COMMENT ON COLUMN ecobat_occurrence_passes.lon IS 'WGS84 longitiude for the record.';
COMMENT ON COLUMN ecobat_occurrence_passes.geom IS 'Geometry for the record.';
COMMENT ON COLUMN ecobat_occurrence_passes.sensitivity IS 'Sensitivity preferences for the record. 1=open, 2=10km blur, 3=open.';
COMMENT ON COLUMN ecobat_occurrence_passes.number_of_bats IS 'Count of bats represented by this data row.';
COMMENT ON COLUMN ecobat_occurrence_passes.date_start IS 'Date at the start of the nights surveying.';
COMMENT ON COLUMN ecobat_occurrence_passes.pass_time IS 'Time of the pass.';
COMMENT ON COLUMN ecobat_occurrence_passes.pass_definition_id IS 'Foreign key to the termlists_terms table. Defines the method used to identify a pass.';
COMMENT ON COLUMN ecobat_occurrence_passes.detector_make_id IS 'The makeof bat detector used, picked from a controlled list.';
COMMENT ON COLUMN ecobat_occurrence_passes.detector_model IS 'The model of bat detector used.';
COMMENT ON COLUMN ecobat_occurrence_passes.detector_identity IS 'Identifier of the individual detector.';
COMMENT ON COLUMN ecobat_occurrence_passes.detector_height_m IS 'Height of the detector from the ground in metres.';
COMMENT ON COLUMN ecobat_occurrence_passes.roost_within_25m IS 'Presence or absence of a roost within 25m.';
COMMENT ON COLUMN ecobat_occurrence_passes.activity_elevated_by_roost IS 'Flag set if activity was elevated because of the presence of a roost.';
COMMENT ON COLUMN ecobat_occurrence_passes.roost_species IS 'Free text list of species at the roost(s).';
COMMENT ON COLUMN ecobat_occurrence_passes.linear_feature_adjacent_id IS 'Type of linear feature adjacent to the detector.';
COMMENT ON COLUMN ecobat_occurrence_passes.linear_feature_25m_id IS 'Type of linear feature within 25m of the detector.';
COMMENT ON COLUMN ecobat_occurrence_passes.anthropogenic_feature_adjacent_id IS 'Type of anthropogenic feature adjacent to the detector.';
COMMENT ON COLUMN ecobat_occurrence_passes.anthropogenic_feature_25m_id IS 'Type of anthropogenic feature within 25m of the detector.';
COMMENT ON COLUMN ecobat_occurrence_passes.temperature_c IS 'Temperature at sunset (degrees centigrade)';
COMMENT ON COLUMN ecobat_occurrence_passes.rainfall_id IS 'Type of rainfall at sunset';
COMMENT ON COLUMN ecobat_occurrence_passes.wind_speed_mph IS 'Wind speed at sunset in mph';
COMMENT ON COLUMN ecobat_occurrence_passes.ecobat_occurrence_id IS 'Foreign key to the ecobat_occurrences table. Identifies the Ecobat occurrence generated for this set of passes.';
COMMENT ON COLUMN ecobat_occurrence_passes.group_id IS 'Foreign key to the groups table. Identifies the Consultants Portal project the record belongs to  .';
COMMENT ON COLUMN ecobat_occurrence_passes.created_on IS 'Date this record was created.';
COMMENT ON COLUMN ecobat_occurrence_passes.created_by_id IS 'Foreign key to the users table (creator).';
COMMENT ON COLUMN ecobat_occurrence_passes.updated_on IS 'Date this record was last updated.';
COMMENT ON COLUMN ecobat_occurrence_passes.updated_by_id IS 'Foreign key to the users table (last updater).';
COMMENT ON COLUMN ecobat_occurrence_passes.import_guid IS 'Unique identifier of the import event which added this record.';

CREATE INDEX ix_ecobat_occurrence_passes_import_guid
  ON ecobat_occurrence_passes (import_guid ASC NULLS LAST);