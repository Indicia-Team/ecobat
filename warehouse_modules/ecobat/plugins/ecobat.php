<?php

/**
 * Indicia, the OPAL Online Recording Toolkit.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/gpl.html.
 *
 * @author Indicia Team
 * @license http://www.gnu.org/licenses/gpl.html GPL
 * @link http://code.google.com/p/indicia/
 */

/**
 * Declares the ecobat occurrences table is available via data services for
 * uploading into.
 * @return array
 */
function ecobat_extend_data_services() {
  return array(
    'ecobat_occurrences' => array()
  );
}

/**
 * Plugs into the scheduled tasks system to build some of the additional field
 * values required in the ecobat occurrences table.
 * @param $last_run_date
 * @param $db
 */
function ecobat_scheduled_task($last_run_date, $db) {
  ecobat_passes_set_calc_fields($db);
  rollup_passes_into_occurrences($db);
  _ecobat_update_reporting_fields($db);
  _ecobat_update_map_sq($last_run_date, $db);
  _ecobat_insert_missing_taxonomic_parents($last_run_date, $db);
  _ecobat_calculated_summed_passes($last_run_date, $db);
  // Insert the occurrences that have been imported into the reference range which can be made public.
  // We'll only do this once a day.
  if (substr(date('c', time()), 0, 10) <> substr($last_run_date, 0, 10)) {
    echo 'Inserting ecobat occurrences<br/>';
    _ecobat_insert_occurrences($db);
  }
}

/**
 * Calculate field values in passes table.
 *
 * Belt and braces to ensure lat lon fields and external_key populated in
 * ecobat_occurrence_passes table.
 */
function ecobat_passes_set_calc_fields($db) {
  $db->query(<<<QRY
UPDATE ecobat_occurrence_passes eop SET
  lon=ST_X(ST_Centroid(ST_Transform(geom, 4326))),
  lat=ST_Y(ST_Centroid(ST_Transform(geom, 4326))),
  external_key=cttl.external_key
FROM cache_taxa_taxon_lists cttl
WHERE (eop.lon is null or eop.lat is null or eop.external_key is null)
AND cttl.id=eop.taxa_taxon_list_id;
QRY
  );
}

function rollup_passes_into_occurrences($db) {
  $db->query(<<<QRY
INSERT INTO ecobat_occurrences (
  taxa_taxon_list_id,
  external_key,
  entered_sref,
  entered_sref_system,
  geom,
  sensitivity,
  date_start,
  day_of_year,
  pass_definition_id,
  detector_make_id,
  detector_model,
  detector_height_m,
  roost_within_25m,
  activity_elevated_by_roost,
  roost_species,
  linear_feature_adjacent_id,
  linear_feature_25m_id,
  anthropogenic_feature_adjacent_id,
  anthropogenic_feature_25m_id,
  temperature_c,
  rainfall_id,
  wind_speed_mph,
  group_id,
  created_by_id,
  updated_by_id,
  import_guid,
  location_name,
  passes,
  created_on,
  updated_on
)
SELECT taxa_taxon_list_id,
  external_key,
  entered_sref,
  entered_sref_system,
  geom,
  sensitivity,
  date_start,
  extract(doy from date_start),
  pass_definition_id,
  detector_make_id,
  detector_model,
  detector_height_m,
  roost_within_25m,
  activity_elevated_by_roost,
  roost_species,
  linear_feature_adjacent_id,
  linear_feature_25m_id,
  anthropogenic_feature_adjacent_id,
  anthropogenic_feature_25m_id,
  temperature_c,
  rainfall_id,
  wind_speed_mph,
  group_id,
  created_by_id,
  updated_by_id,
  import_guid,
  detector_identity as location_name,
  sum(number_of_bats) as passes,
  now(),
  now()
FROM ecobat_occurrence_passes
WHERE processed=false
GROUP BY taxa_taxon_list_id,
  external_key,
  entered_sref,
  entered_sref_system,
  geom,
  sensitivity,
  date_start,
  pass_definition_id,
  detector_make_id,
  detector_model,
  detector_identity,
  detector_height_m,
  roost_within_25m,
  activity_elevated_by_roost,
  roost_species,
  linear_feature_adjacent_id,
  linear_feature_25m_id,
  anthropogenic_feature_adjacent_id,
  anthropogenic_feature_25m_id,
  temperature_c,
  rainfall_id,
  wind_speed_mph,
  group_id,
  created_by_id,
  updated_by_id,
  import_guid;

UPDATE ecobat_occurrence_passes eop
SET ecobat_occurrence_id=eo.id
FROM ecobat_occurrences eo
WHERE eop.taxa_taxon_list_id=eo.taxa_taxon_list_id
AND eop.entered_sref=eo.entered_sref
AND eop.entered_sref_system=eo.entered_sref_system
AND eop.sensitivity=eo.sensitivity
AND eop.date_start=eo.date_start
AND eop.pass_definition_id=eo.pass_definition_id
AND eop.detector_make_id=eo.detector_make_id
AND eop.detector_model=eo.detector_model
AND eop.detector_identity=eo.location_name
AND COALESCE(eop.detector_height_m, 0)=COALESCE(eo.detector_height_m, 0)
AND eop.roost_within_25m=eo.roost_within_25m
AND eop.activity_elevated_by_roost=eo.activity_elevated_by_roost
AND COALESCE(eop.roost_species, '')=COALESCE(eo.roost_species, '')
AND COALESCE(eop.linear_feature_adjacent_id, 0)=COALESCE(eo.linear_feature_adjacent_id, 0)
AND COALESCE(eop.linear_feature_25m_id, 0)=COALESCE(eo.linear_feature_25m_id, 0)
AND COALESCE(eop.anthropogenic_feature_adjacent_id, 0)=COALESCE(eo.anthropogenic_feature_adjacent_id, 0)
AND COALESCE(eop.anthropogenic_feature_25m_id, 0)=COALESCE(eo.anthropogenic_feature_25m_id, 0)
AND COALESCE(eop.temperature_c, 0)=COALESCE(eo.temperature_c, 0)
AND COALESCE(eop.rainfall_id, 0)=COALESCE(eo.rainfall_id, 0)
AND COALESCE(eop.wind_speed_mph, 0)=COALESCE(eo.wind_speed_mph, 0)
AND COALESCE(eop.group_id, 0)=COALESCE(eo.group_id, 0)
AND eop.created_by_id=eo.created_by_id
AND COALESCE(eop.import_guid, '')=COALESCE(eo.import_guid, '')
AND eop.ecobat_occurrence_id IS NULL;

UPDATE ecobat_occurrence_passes SET processed=true WHERE processed=false;
QRY
  );
}

function _ecobat_update_reporting_fields($db) {
  $db->query(<<<QRY
UPDATE ecobat_occurrences eo SET
  easting=ST_X(ST_Centroid(ST_Transform(geom, 27700))),
  northing=ST_Y(ST_Centroid(ST_Transform(geom, 27700))),
  external_key=cttl.external_key
FROM cache_taxa_taxon_lists cttl
WHERE (eo.easting is null or eo.northing is null or eo.external_key is null)
AND cttl.id=eo.taxa_taxon_list_id
QRY
  );
  $db->query(<<<QRY
UPDATE ecobat_occurrence_passes eop SET
  lon=ST_X(ST_Centroid(ST_Transform(geom, 4326))),
  lat=ST_Y(ST_Centroid(ST_Transform(geom, 4326))),
  external_key=cttl.external_key
FROM cache_taxa_taxon_lists cttl
WHERE (eop.lat is null or eop.lon is null or eop.external_key is null)
AND cttl.id=eop.taxa_taxon_list_id
QRY
  );
}

function _ecobat_update_map_sq($last_run_date, $db) {
  static $srid;
  if (!isset($srid)) {
    $srid = kohana::config('sref_notations.internal_srid');
  }
  // Seems much faster to break this into small queries than one big left join.
  $requiredSquares = $db->query(
    "SELECT DISTINCT id as ecobat_occurrence_id, st_astext(geom) as geom,
          round(st_x(st_centroid(reduce_precision(geom, false, 10000, 'osgb')))) as x,
          round(st_y(st_centroid(reduce_precision(geom, false, 10000, 'osgb')))) as y
        FROM ecobat_occurrences
        WHERE geom is not null and created_on>='$last_run_date' and map_sq_10km_id is null")->result_array(TRUE);
  foreach ($requiredSquares as $s) {
    $existing = $db->query("SELECT id FROM map_squares WHERE x={$s->x} AND y={$s->y} AND size=10000")->result_array(FALSE);
    if (count($existing) === 0) {
      $qry = $db->query("INSERT INTO map_squares (geom, x, y, size)
            VALUES (reduce_precision(st_geomfromtext('{$s->geom}', $srid), false, 10000, 'osgb'), {$s->x}, {$s->y}, 10000)");
      $msqId = $qry->insert_id();
    }
    else {
      $msqId = $existing[0]['id'];
    }
    $db->query("UPDATE ecobat_occurrences SET map_sq_10km_id=$msqId WHERE id={$s->ecobat_occurrence_id}");
  }
}

function _ecobat_insert_occurrences($db) {
  $smpAttrs = kohana::config('ecobat.sample_attrs');
  $occAttrs = kohana::config('ecobat.occurrence_attrs');
  $passTerms = kohana::config('ecobat.pass_terms');
  // Find up to 2000 occurrences that need to be generated
  $occs = $db->query(<<<SELECT
SELECT * from ecobat_occurrences
WHERE occurrence_id IS NULL
AND autogenerated=FALSE
AND sensitivity<3
AND passes IS NOT NULL
LIMIT 2000
SELECT
  )->result_array(FALSE);
  $lastSample = '';
  // Fields we'll use to look for unique samples
  $allSampleFields = array_merge(array(
    'entered_sref',
    'entered_sref_system',
    'date_start',
    'group_id',
    'location_name',
  ), array_keys($smpAttrs));
  echo count($occs) . ' ecobat occurrences to process<br/>';
  foreach ($occs as $ecobat_occurrence) {
    $thisSampleFields = array_intersect_key($ecobat_occurrence, array_combine($allSampleFields, $allSampleFields));
    $thisSample = implode('|', $thisSampleFields);
    if ($thisSample !== $lastSample) {
      // If a new sample, create the sample record.
      $s = array(
        'website_id' => kohana::config('ecobat.website_id'),
        'survey_id' => kohana::config('ecobat.survey_id'),
        'date_start' => $ecobat_occurrence['date_start'],
        'date_end' => $ecobat_occurrence['date_start'],
        'date_type' => 'D',
        'entered_sref' => $ecobat_occurrence['entered_sref'],
        'entered_sref_system' => $ecobat_occurrence['entered_sref_system'],
        'location_name' => $ecobat_occurrence['location_name'],
        'privacy_precision' => $ecobat_occurrence['sensitivity'] === 2 ? 10000 : null,
        'record_status' => 'C',
      );
      foreach ($smpAttrs as $ecobatFieldName => $attrId) {
        $s[$attrId] = $ecobat_occurrence[$ecobatFieldName];
      }
      $sample = ORM::Factory('sample');
      $sample->set_submission_data($s);
      $sample->submit();
      if ($errors = $sample->getAllErrors()) {
        kohana::log('error', 'Unable to save ecobat sample: ' . var_export($s, true));
        foreach ($errors as $error)
          kohana::log('error', $error);
        continue;
      }
      // @todo Error Check
      $currentSampleId = $sample->id;
      $thisSample===$lastSample;
    }
    // create the occurrence record
    $s = array(
      'website_id' => kohana::config('ecobat.website_id'),
      'survey_id' => kohana::config('ecobat.survey_id'),
      'sample_id' => $currentSampleId,
      'taxa_taxon_list_id' => $ecobat_occurrence['taxa_taxon_list_id'],
      'sensitivity_precision' => $ecobat_occurrence['sensitivity']===2 ? 10000 : null,
      'record_status' => 'C'
    );
    foreach ($occAttrs as $ecobatFieldName => $attrId) {
      $s[$attrId] = $passTerms[$ecobat_occurrence[$ecobatFieldName]];
    }
    $occurrence = ORM::Factory('occurrence');
    $occurrence->set_submission_data($s);
    $occurrence->submit();
    if ($errors = $occurrence->getAllErrors()) {
      kohana::log('error', 'Unable to save ecobat occurrence: ' . var_export($s, TRUE));
      foreach ($errors as $error) {
        kohana::log('error', $error);
      }
    } else {
      // Create a link between the 2 occurrence records
      $db->update('ecobat_occurrences',
        array('occurrence_id' => $occurrence->id),
        array('id' => $ecobat_occurrence['id'])
      );
    }
  }
}

/**
 * For each imported record in ecobat_occurrences, add a record of the genus to the reference dataset if one does not
 * already exist. Also adds Nyctaloid (i.e. the level above genus) if it is present in the species list used.
 * @param $last_run_date
 * @param $db
 */
function _ecobat_insert_missing_taxonomic_parents($last_run_date, $db) {
  $qry = <<<QRY
insert into ecobat_occurrences (
    taxa_taxon_list_id, external_key, entered_sref, entered_sref_system, easting, northing, geom, map_sq_10km_id,
    sensitivity, date_start, day_of_year, passes, pass_definition_id, detector_make_id, detector_model, detector_height_m,
    roost_within_25m, activity_elevated_by_roost, roost_species,
    linear_feature_adjacent_id, linear_feature_25m_id, anthropogenic_feature_adjacent_id, anthropogenic_feature_25m_id,
    temperature_c, rainfall_id, wind_speed_mph, notes,
    created_on, created_by_id, updated_on, updated_by_id, import_guid, autogenerated)
  -- insert rows for missing parent taxa
  select distinct ttlparent.id, ttlparent.external_key, eo.entered_sref, eo.entered_sref_system, eo.easting, eo.northing, eo.geom, eo.map_sq_10km_id,
  eo.sensitivity, eo.date_start, eo.day_of_year, 0, eo.pass_definition_id, eo.detector_make_id, eo.detector_model, eo.detector_height_m,
  eo.roost_within_25m, eo.activity_elevated_by_roost, eo.roost_species,
  eo.linear_feature_adjacent_id, eo.linear_feature_25m_id, eo.anthropogenic_feature_adjacent_id, eo.anthropogenic_feature_25m_id,
  eo.temperature_c, eo.rainfall_id, eo.wind_speed_mph, eo.notes,
  now(), 1, now(), 1, eo.import_guid, true
from ecobat_occurrences eo
join cache_taxa_taxon_lists ttl on ttl.id=eo.taxa_taxon_list_id
join cache_taxa_taxon_lists ttlpref on ttlpref.taxon_meaning_id=ttl.taxon_meaning_id and ttlpref.taxon_list_id=ttl.taxon_list_id and ttlpref.preferred=true
left join cache_taxa_taxon_lists ttlparent on ttlparent.id=ttlpref.parent_id and ttlparent.preferred=true
left join ecobat_occurrences eoparent on
  eoparent.external_key=ttlparent.external_key and
  eoparent.easting = eo.easting and
  eoparent.northing = eo.northing and
  eoparent.date_start = eo.date_start and
  eoparent.pass_definition_id = eo.pass_definition_id and
  eoparent.detector_make_id = eo.detector_make_id and
  eoparent.detector_model = eo.detector_model and
  coalesce(eoparent.detector_height_m, 0) = coalesce(eo.detector_height_m, 0) and
  eoparent.roost_within_25m = eo.roost_within_25m and
  eoparent.activity_elevated_by_roost = eo.activity_elevated_by_roost and
  coalesce(eoparent.roost_species, '') = coalesce(eo.roost_species, '') and
  coalesce(eoparent.linear_feature_adjacent_id, -1) = coalesce(eo.linear_feature_adjacent_id, -1) and
  coalesce(eoparent.linear_feature_25m_id, -1) = coalesce(eo.linear_feature_25m_id, -1) and
  coalesce(eoparent.anthropogenic_feature_adjacent_id, -1) = coalesce(eo.anthropogenic_feature_adjacent_id, -1) and
  coalesce(eoparent.anthropogenic_feature_25m_id, -1) = coalesce(eo.anthropogenic_feature_25m_id, -1) and
  coalesce(eoparent.temperature_c, -100) = coalesce(eo.temperature_c, -100) and
  coalesce(eoparent.rainfall_id, -1) = coalesce(eo.rainfall_id, -1) and
  coalesce(eoparent.wind_speed_mph, -1) = coalesce(eo.wind_speed_mph, -1) and
  coalesce(eoparent.notes, '') = coalesce(eo.notes, '') and
  eoparent.import_guid=eo.import_guid
where eoparent.id is null
  and ttlparent.id is not null
  and eo.autogenerated=false
  and eo.created_on>='$last_run_date'
union
-- insert rows for missing grandparent taxa
  select distinct ttlgrandparent.id, ttlgrandparent.external_key, eo.entered_sref, eo.entered_sref_system, eo.easting, eo.northing, eo.geom, eo.map_sq_10km_id,
  eo.sensitivity, eo.date_start, eo.day_of_year, 0, eo.pass_definition_id, eo.detector_make_id, eo.detector_model, eo.detector_height_m,
  eo.roost_within_25m, eo.activity_elevated_by_roost, eo.roost_species,
  eo.linear_feature_adjacent_id, eo.linear_feature_25m_id, eo.anthropogenic_feature_adjacent_id, eo.anthropogenic_feature_25m_id,
  eo.temperature_c, eo.rainfall_id, eo.wind_speed_mph, eo.notes,
  now(), 1, now(), 1, eo.import_guid, true
from ecobat_occurrences eo
join cache_taxa_taxon_lists ttl on ttl.id=eo.taxa_taxon_list_id
join cache_taxa_taxon_lists ttlpref on ttlpref.taxon_meaning_id=ttl.taxon_meaning_id and ttlpref.taxon_list_id=ttl.taxon_list_id and ttlpref.preferred=true
left join cache_taxa_taxon_lists ttlparent on ttlparent.id=ttlpref.parent_id and ttlparent.preferred=true
left join cache_taxa_taxon_lists ttlgrandparent on ttlgrandparent.id=ttlparent.parent_id and ttlgrandparent.preferred=true
left join ecobat_occurrences eograndparent on
  eograndparent.external_key=ttlgrandparent.external_key and
  eograndparent.easting = eo.easting and
  eograndparent.northing = eo.northing and
  eograndparent.date_start = eo.date_start and
  eograndparent.pass_definition_id = eo.pass_definition_id and
  eograndparent.detector_make_id = eo.detector_make_id and
  eograndparent.detector_model = eo.detector_model and
  coalesce(eograndparent.detector_height_m, 0) = coalesce(eo.detector_height_m, 0) and
  eograndparent.roost_within_25m = eo.roost_within_25m and
  eograndparent.activity_elevated_by_roost = eo.activity_elevated_by_roost and
  coalesce(eograndparent.roost_species, '') = coalesce(eo.roost_species, '') and
  coalesce(eograndparent.linear_feature_adjacent_id, -1) = coalesce(eo.linear_feature_adjacent_id, -1) and
  coalesce(eograndparent.linear_feature_25m_id, -1) = coalesce(eo.linear_feature_25m_id, -1) and
  coalesce(eograndparent.anthropogenic_feature_adjacent_id, -1) = coalesce(eo.anthropogenic_feature_adjacent_id, -1) and
  coalesce(eograndparent.anthropogenic_feature_25m_id, -1) = coalesce(eo.anthropogenic_feature_25m_id, -1) and
  coalesce(eograndparent.temperature_c, -100) = coalesce(eo.temperature_c, -100) and
  coalesce(eograndparent.rainfall_id, -1) = coalesce(eo.rainfall_id, -1) and
  coalesce(eograndparent.wind_speed_mph, -1) = coalesce(eo.wind_speed_mph, -1) and
  coalesce(eograndparent.notes, '') = coalesce(eo.notes, '') and
  eograndparent.import_guid=eo.import_guid
where eograndparent.id is null
  and ttlgrandparent.id is not null
  and eo.autogenerated=false
  and eo.created_on>='$last_run_date'
QRY;
  $db->query($qry);
}

function _ecobat_calculated_summed_passes($last_run_date, $db) {
  $qry = <<<QRY
update ecobat_occurrences
set passes_summed = passes + coalesce(children.passes_summed, 0) + coalesce(grandchildren.passes_summed, 0)
from (
  select eo.id, sum(coalesce(eochild.passes, 0)) as passes_summed
  from ecobat_occurrences eo
  join cache_taxa_taxon_lists ttl on ttl.id=eo.taxa_taxon_list_id
  join cache_taxa_taxon_lists ttlpref on ttlpref.taxon_meaning_id=ttl.taxon_meaning_id and ttlpref.taxon_list_id=ttl.taxon_list_id and ttlpref.preferred=true
  left join cache_taxa_taxon_lists ttlchild on ttlchild.parent_id=ttl.id and ttlchild.preferred=true
  left join ecobat_occurrences eochild on
    eochild.external_key = ttlchild.external_key and
    eochild.easting = eo.easting and
    eochild.northing = eo.northing and
    eochild.date_start = eo.date_start and
    eochild.pass_definition_id = eo.pass_definition_id and
    eochild.detector_make_id = eo.detector_make_id and
    eochild.detector_model = eo.detector_model and
    coalesce(eochild.detector_height_m, 0) = coalesce(eo.detector_height_m, 0) and
    eochild.roost_within_25m = eo.roost_within_25m and
    eochild.activity_elevated_by_roost = eo.activity_elevated_by_roost and
    coalesce(eochild.roost_species, '') = coalesce(eo.roost_species, '') and
    coalesce(eochild.linear_feature_adjacent_id, -1) = coalesce(eo.linear_feature_adjacent_id, -1) and
    coalesce(eochild.linear_feature_25m_id, -1) = coalesce(eo.linear_feature_25m_id, -1) and
    coalesce(eochild.anthropogenic_feature_adjacent_id, -1) = coalesce(eo.anthropogenic_feature_adjacent_id, -1) and
    coalesce(eochild.anthropogenic_feature_25m_id, -1) = coalesce(eo.anthropogenic_feature_25m_id, -1) and
    coalesce(eochild.temperature_c, -100) = coalesce(eo.temperature_c, -100) and
    coalesce(eochild.rainfall_id, -1) = coalesce(eo.rainfall_id, -1) and
    coalesce(eochild.wind_speed_mph, -1) = coalesce(eo.wind_speed_mph, -1) and
    coalesce(eochild.notes, '') = coalesce(eo.notes, '') and
    eochild.import_guid = eo.import_guid
  where eo.created_on>='$last_run_date'
  group by eo.id
) as children,
  (
  select eo.id, sum(coalesce(eograndchild.passes, 0)) as passes_summed
  from ecobat_occurrences eo
  join cache_taxa_taxon_lists ttl on ttl.id=eo.taxa_taxon_list_id
  join cache_taxa_taxon_lists ttlpref on ttlpref.taxon_meaning_id=ttl.taxon_meaning_id and ttlpref.taxon_list_id=ttl.taxon_list_id and ttlpref.preferred=true
  left join cache_taxa_taxon_lists ttlchild on ttlchild.parent_id=ttl.id and ttlchild.preferred=true
  left join cache_taxa_taxon_lists ttlgrandchild on ttlgrandchild.parent_id=ttlchild.id and ttlgrandchild.preferred=true
  left join ecobat_occurrences eograndchild on
    eograndchild.external_key = ttlgrandchild.external_key and
    eograndchild.easting = eo.easting and
    eograndchild.northing = eo.northing and
    eograndchild.date_start = eo.date_start and
    eograndchild.pass_definition_id = eo.pass_definition_id and
    eograndchild.detector_make_id = eo.detector_make_id and
    eograndchild.detector_model = eo.detector_model and
    coalesce(eograndchild.detector_height_m, 0) = coalesce(eo.detector_height_m, 0) and
    eograndchild.roost_within_25m = eo.roost_within_25m and
    eograndchild.activity_elevated_by_roost = eo.activity_elevated_by_roost and
    coalesce(eograndchild.roost_species, '') = coalesce(eo.roost_species, '') and
    coalesce(eograndchild.linear_feature_adjacent_id, -1) = coalesce(eo.linear_feature_adjacent_id, -1) and
    coalesce(eograndchild.linear_feature_25m_id, -1) = coalesce(eo.linear_feature_25m_id, -1) and
    coalesce(eograndchild.anthropogenic_feature_adjacent_id, -1) = coalesce(eo.anthropogenic_feature_adjacent_id, -1) and
    coalesce(eograndchild.anthropogenic_feature_25m_id, -1) = coalesce(eo.anthropogenic_feature_25m_id, -1) and
    coalesce(eograndchild.temperature_c, -100) = coalesce(eo.temperature_c, -100) and
    coalesce(eograndchild.rainfall_id, -1) = coalesce(eo.rainfall_id, -1) and
    coalesce(eograndchild.wind_speed_mph, -1) = coalesce(eo.wind_speed_mph, -1) and
    coalesce(eograndchild.notes, '') = coalesce(eo.notes, '') and
    eograndchild.import_guid = eo.import_guid
  where eo.created_on>='$last_run_date'
  group by eo.id
) as grandchildren
where children.id=ecobat_occurrences.id and grandchildren.id=ecobat_occurrences.id;
QRY;
  $db->query($qry);
}