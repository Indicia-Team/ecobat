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
 * @author Indicia Team
 * @license http://www.gnu.org/licenses/gpl.html GPL
 * @link http://code.google.com/p/indicia/
 */

/**
 * Model class for the ecobat_occurrence_passes table.
 */
class Ecobat_occurrence_pass_Model extends ORM {

  protected $has_one = array('group');
  protected $belongs_to = [
    'taxa_taxon_list',
    'pass_definition' => 'termlists_term',
    'detector_make' => 'termlists_term',
    'linear_feature_adjacent' => 'termlists_term',
    'linear_feature_25m' => 'termlists_term',
    'anthropogenic_feature_adjacent' => 'termlists_term',
    'anthropogenic_feature_25m' => 'termlists_term',
    'rainfall' => 'termlists_term',
    'group',
  ];

  public function validate(Validation $array, $save = FALSE) {
    $array->pre_filter('trim');

    // Required fields.
    $array->add_rules('taxa_taxon_list_id', 'required');
    $array->add_rules('lat', 'numeric', 'required', 'maximum[90]', 'minimum[-90]');
    $array->add_rules('lon', 'numeric', 'required', 'maximum[180]', 'minimum[-180]');
    $array->add_rules('sensitivity', 'integer', 'required');
    $array->add_rules('date_start', 'date', 'required');
    $array->add_rules('pass_time', 'required');
    $array->add_rules('number_of_bats', 'integer', 'required');
    $array->add_rules('pass_definition_id', 'integer', 'required');
    $array->add_rules('detector_make_id', 'integer', 'required');
    $array->add_rules('detector_model', 'required');
    $array->add_rules('detector_identity', 'required');
    $array->add_rules('linear_feature_adjacent_id', 'integer');
    $array->add_rules('linear_feature_25m_id', 'integer');
    $array->add_rules('anthropogenic_feature_adjacent_id', 'integer');
    $array->add_rules('anthropogenic_feature_25m_id', 'integer');
    $array->add_rules('temperature_c', 'maximum[45]');
    $array->add_rules('rainfall_id', 'integer');
    $array->add_rules('wind_speed_mph', 'integer');
    $array->add_rules('ecobat_occurrence_id', 'integer');
    $array->add_rules('group_id', 'integer');
    $this->unvalidatedFields = array(
      'external_key', 'detector_make_other',
      'detector_height_m', 'roost_within_25m', 'activity_elevated_by_roost',
      'roost_species', 'import_guid', 'processed', 'method_of_classification',
      'analysis_software_used', 'site_name',
    );
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
        'filterIncludesNulls' => TRUE,
      ),
      // Also allow a field to be defined which defines the taxon list to look in when searching for species during a csv upload
      'ecobat_occurrence_pass:fkFilter:taxa_taxon_list:taxon_list_id' => array(
        'display' => 'Species list',
        'description' => 'Select the species checklist which will be used when attempting to match species names.',
        'datatype' => 'lookup',
        'population_call' => 'direct:taxon_list:id:title',
        'linked_to' => 'website_id',
        'linked_filter_field' => 'website_id',
        'filterIncludesNulls' => TRUE,
      ),
      'ecobat_occurrence_pass:pass_definition_id' => array(
        'display' => 'Bat pass definition',
        'description' => 'Select the definition used as a criteria for the number of passes.',
        'datatype' => 'lookup',
        'population_call' => 'report:library/terms/terms_list:termlists_term_id:term:termlist_external_key=ecobat:pass_definitions,termlist_id=',
      ),
      'ecobat_occurrence_pass:sensitivity' => array(
        'display' => 'Sensitivity of data',
        'description' => 'Choose the sensitivity settings for the records.',
        'datatype' => 'lookup',
        'lookup_values' => '1:Public,2:Blur records to 10km grid square,3:Do not publish',
        'default' => '100',
      ),
    );
    return $retVal;
  }

  /**
   * Before submission:
   * * fill in the geom field using the supplied spatial reference, if not already filled in
   * * fill in the day of year which is used for quick date filtering
   */
  protected function preSubmit() {
    $this->preSubmitFillInSensitivity();
    return parent::presubmit();
  }

  private function preSubmitFillInSensitivity() {
    $mappings = array('open' => 1, 'shared' => 1, '10kmbuffer' => 2, 'sensitive' => 3);
    if (array_key_exists('sensitivity', $this->submission['fields']) &&
        !empty($this->submission['fields']['sensitivity']['value']) &&
        !empty($mappings[strtolower(str_replace(' ', '', $this->submission['fields']['sensitivity']['value']))])) {
      $this->submission['fields']['sensitivity']['value'] =
        $mappings[strtolower(str_replace(' ', '', $this->submission['fields']['sensitivity']['value']))];
    }
  }

}
