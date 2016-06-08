<?php defined('SYSPATH') or die('No direct script access.');

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
 * @package	Ecobat
 * @subpackage Models
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

/**
 * Model class for the ecobat_occurrences table.
 *
 * @package	Ecobat
 * @subpackage Models
 * @link	http://code.google.com/p/indicia/wiki/DataModel
 */
class Ecobat_occurrence_Model extends ORM {

  protected $has_one = array('group');
  protected $belongs_to=array
  (
    'taxa_taxon_list',
    'roost_taxa_taxon_list'=>'taxa_taxon_list',
    'pass_definition'=>'termlists_term',
    'habitat'=>'termlists_term',
    'group'
  );

  public function validate(Validation $array, $save = FALSE) {
    $array->pre_filter('trim');

    // required fields
    $array->add_rules('taxa_taxon_list_id', 'required');
    $array->add_rules('entered_sref', 'required');
    $array->add_rules('entered_sref_system', 'required');
    // linear features and roost not required as they have defaults
    // other validation
    $array->add_rules('taxa_taxon_list_id', 'integer');
    $array->add_rules('easting', 'integer');
    $array->add_rules('northing', 'integer');
    $array->add_rules('map_sq_10km_id', 'integer');
    $array->add_rules('sensitivity', 'integer');
    $array->add_rules('date_start', 'date');
    $array->add_rules('passes', 'integer');
    $array->add_rules('pass_definition_id', 'integer');
    $array->add_rules('min_temperature_c', 'integer');
    $array->add_rules('precipitation_mm', 'integer');
    $array->add_rules('wind_speed_mph', 'integer');
    $array->add_rules('roost_taxa_taxon_list_id', 'integer');
    $array->add_rules('habitat_id', 'integer');
    $array->add_rules('occurrence_id', 'integer');
    $this->unvalidatedFields = array('external_key', 'geom', 'detector_model',
        'linear_features', 'roost', 'feature_type', 'roost_external_key');

    return parent::validate($array, $save);
  }

  /**
   * Define a form that is used to capture a set of predetermined values that apply to every record during an import.
   * @param array $options Model specific options, including
   *
   * * **occurrence_associations** - Set to 't' to enable occurrence associations options. The
   *   relevant warehouse module must also be enabled.
   */
  public function fixed_values_form($options=array()) {
    $srefs = array();
    $systems = spatial_ref::system_list();
    foreach ($systems as $code => $title) {
      $srefs[] = str_replace(array(',', ':'), array('&#44', '&#56'), $code) .
        ":" .
        str_replace(array(',', ':'), array('&#44', '&#56'), $title);
    }
    $retVal = array(
      'website_id' => array(
        'display' => 'Website',
        'description' => 'Select the website to import records into.',
        'datatype' => 'lookup',
        'population_call' => 'direct:website:id:title',
        'filterIncludesNulls' => TRUE
      ),
      'ecobat_occurrence:entered_sref_system' => array(
        'display' => 'Spatial ref. system',
        'description' => 'Select the spatial reference system used in this import file. Note, if you have a file with a mix of spatial reference systems then you need a ' .
          'column in the import file which is mapped to the Sample Spatial Reference System field containing the spatial reference system code.',
        'datatype' => 'lookup',
        'lookup_values' => implode(',', $srefs)
      ),
      // Also allow a field to be defined which defines the taxon list to look in when searching for species during a csv upload
      'ecobat_occurrence:fkFilter:taxa_taxon_list:taxon_list_id' => array(
        'display' => 'Species list',
        'description' => 'Select the species checklist which will be used when attempting to match species names.',
        'datatype' => 'lookup',
        'population_call' => 'direct:taxon_list:id:title',
        'linked_to' => 'website_id',
        'linked_filter_field' => 'website_id',
        'filterIncludesNulls' => TRUE
      ),
      'ecobat_occurrence:fkFilter:roost_taxa_taxon_list:taxon_list_id' => array(
        'display' => 'Roost species list',
        'description' => 'Select the species checklist which will be used when attempting to match roost species names.',
        'datatype' => 'lookup',
        'population_call' => 'direct:taxon_list:id:title',
        'linked_to' => 'website_id',
        'linked_filter_field' => 'website_id',
        'filterIncludesNulls' => TRUE
      ),
      'ecobat_occurrence:pass_definition_id' => array(
        'display' => 'Pass definition',
        'description' => 'Select the definition used as a criteria for the number of passes.',
        'datatype' => 'lookup',
        'population_call' => 'report:library/terms/terms_list:termlists_term_id:term:termlist_external_key=ecobat:pass_definitions,termlist_id='
      ),
      'ecobat_occurrence:sensitivity' => array(
        'display' => 'Choose the grid square size to blur records when publishing the records.',
        'description' => '',
        'datatype' => 'lookup',
        'lookup_values' => '100:100m,1000:1km,2000:2km,10000:10km,100000:100km,:Do not publish',
        'default'=>'100'
      ),
      'ecobat_occurrence:fkFilter:habitat:termlist_id' => array(
        'display' => 'Habitat classification',
        'description' => 'Select the habitat classification to use for the habitat lookup.',
        'datatype' => 'lookup',
        'population_call' => 'direct:termlist:id:title',
        'linked_to' => 'website_id',
        'linked_filter_field' => 'website_id',
        'filterIncludesNulls' => TRUE
      )
    );
    return $retVal;
  }

  /**
   * Before submission:
   * * fill in the geom field using the supplied spatial reference, if not already filled in
   */
  protected function preSubmit()
  {
    $this->preSubmitFillInGeom();
    return parent::presubmit();
  }

  /**
   * Allow an ecobat occurrence to be submitted with a spatial ref and system but no Geom. If so we
   * can work out the geom and fill it in.
   */
  private function preSubmitFillInGeom() {
    //
    if (array_key_exists('entered_sref', $this->submission['fields']) &&
      array_key_exists('entered_sref_system', $this->submission['fields']) &&
      !(array_key_exists('geom', $this->submission['fields']) && $this->submission['fields']['geom']['value']) &&
      $this->submission['fields']['entered_sref']['value'] &&
      $this->submission['fields']['entered_sref_system']['value']) {
      try {
        $this->submission['fields']['geom']['value'] = spatial_ref::sref_to_internal_wkt(
          $this->submission['fields']['entered_sref']['value'],
          $this->submission['fields']['entered_sref_system']['value']
        );
      } catch (Exception $e) {
        $this->errors['entered_sref'] = $e->getMessage();
      }
    }
  }

  /**
   * Override set handler to translate WKT to PostGIS internal spatial data. Also
   * syncs the easting and northing
   */
  public function __set($key, $value)
  {
    if (substr($key,-4) == 'geom')
    {
      if ($value) {
        $geom = "ST_GeomFromText('$value', ".kohana::config('sref_notations.internal_srid').")";
        $row = $this->db->query("SELECT $geom AS geom, " .
            "ST_X(ST_Centroid(ST_Transform($geom, 27700))) as x, " .
            "ST_Y(ST_Centroid(ST_Transform($geom, 27700))) as y")->current();
        $value = $row->geom;
        parent::__set('easting', $row->x);
        parent::__set('northing', $row->y);
      }
    }
    parent::__set($key, $value);
  }

  /**
   * Override get handler to translate PostGIS internal spatial data to WKT.
   */
  public function __get($column)
  {
    $value = parent::__get($column);

    if  (substr($column,-4) == 'geom' && $value!==null)
    {
      $row = $this->db->query("SELECT ST_asText('$value') AS wkt")->current();
      $value = $row->wkt;
    }
    return $value;
  }
}