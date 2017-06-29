<?php


/**
 *
 *
 * @package Client
 * @subpackage PrebuiltForms
 * @todo Provide form description in this comment block.
 * @todo Rename the form class to iform_...
 */
class iform_ecobat_manual_analysis {

  private static $auth;

  /**
   * Return the form metadata. Note the title of this method includes the name of the form file. This ensures
   * that if inheritance is used in the forms, subclassed forms don't return their parent's form definition.
   * @return array The definition of the form.
   * @todo rename this method.
   */
  public static function get_ecobat_manual_analysis_definition() {
    return array(
      'title'=>'Ecobat manual analysis form',
      'category' => 'Ecobat',
      'description'=>'Form for manually specifying the details of a record to feed into an Ecobat analysis.'
    );
  }

  /**
   * Get the list of parameters for this form.
   * @return array List of parameters that this form requires.
   */
  public static function get_parameters() {
    require_once('includes/map.php');
    return array_merge(array(
        array(
        'fieldname'=>'taxon_list_id',
        'label'=>'Species List ',
        'helpText'=>'The species list that species can be selected from.',
        'type'=>'select',
        'table'=>'taxon_list',
        'valueField'=>'id',
        'captionField'=>'title',
        'group'=>'Species',
        'siteSpecific'=>true
      ),
      array(
        'fieldname'=>'default_pass_definition_id',
        'label'=>'Default pass definition ID ',
        'helpText'=>'The ID of the termlist term entry for the default pass definition to use.',
        'type'=>'text_input',
        'siteSpecific'=>true
      )
    ), iform_map_get_map_parameters());
  }

  /**
   * Return the generated form output.
   * @param array $args List of parameter values passed through to the form depending on how the form has been configured.
   * This array always contains a value for language.
   * @param object $nid The Drupal node object's ID.
   * @param array $response When this form is reloading after saving a submission, contains the response from the service call.
   * Note this does not apply when redirecting (in this case the details of the saved object are in the $_GET data).
   * @return Form HTML.
   * @todo: Implement this method
   */
  public static function get_form($args, $nid, $response=null) {
    iform_load_helpers(array(
      'data_entry_helper',
      'map_helper',
      'report_helper'
    ));
    $args = array_merge(array(
      'default_pass_definition_id' => null
    ), $args);
    data_entry_helper::$website_id=$args['website_id'];
    self::$auth = data_entry_helper::get_read_write_auth($args['website_id'], $args['password']);
    if (isset($_POST['input_date']) && (!isset($_POST['runAnalysis']) || $_POST['runAnalysis']!=='no')) {
      if (self::isValidPost())
        return self::reportOutput($args);
      else
        return self::paramsForm($args);
    }
    else {
      return self::paramsForm($args);
    }
  }

  private static function isValidPost() {
    $messages = array();
    if ($_POST['filter_temp']==='1' && !is_numeric($_POST['input_temp'])) {
      $messages[] = 'Please specify the temperature value to filter to in &deg;C.';
    }
    if ($_POST['filter_wind']==='1' && !is_numeric($_POST['input_wind'])) {
      $messages[] = 'Please specify the wind speed to filter to in mph.';
    }
    if (count($messages)) {
      foreach ($messages as $message)
        drupal_set_message('warning', $message);
      return false;
    }
    return true;
  }

  private static function reportOutput($args) {
    hostsite_set_page_title(lang::get('Analysis output'));
    $taxa_taxon_list_external_key = $_POST['input_taxa_taxon_list_external_key'];
    $species = $_POST['input_taxa_taxon_list_external_key:taxon'];
    $passes = $_POST['input_passes'];
    // plurality
    $passesTerm = $_POST['input_passes'] === '1' ? 'pass' : 'passes';
    $isOrAre = $_POST['input_passes'] === '1' ? 'is' : 'are';
    $date = $_POST['input_date'];
    $mapref = $_POST['input_sref'];
    $params = array(
      'input_date' => $date,
      'input_taxa_taxon_list_external_key' => $taxa_taxon_list_external_key,
      'input_passes' => $passes,
      'input_pass_definition_id' => $_POST['input_pass_definition_id'],
      'input_geom' => $_POST['input_sref:geom'],
      'input_temp' => $_POST['input_temp'],
      'input_wind' => $_POST['input_wind'],
      'filter_date' => $_POST['filter_date'],
      'filter_spatial_km' => $_POST['filter_spatial_km'],
      'filter_detector_make' => $_POST['filter_detector_make'],
      'filter_temp' => $_POST['filter_temp'],
      'filter_wind' => $_POST['filter_wind'],
      'filter_pass_definition' => '1'
    );
    $countQuery = report_helper::get_report_data(array(
      'dataSource' => 'specific_surveys/ecobat/reference_output_count',
      'readAuth' => self::$auth['read'],
      'extraParams' => $params
    ));
    $count = $countQuery[0]['filtercount'];
    if ($count==='0') {
      drupal_set_message('There were no records found in the reference range that matched the filter specified. ' .
          'Please alter the reference range filter to select a wider range of records then try again.');
      return self::paramsForm($args);
    }
    $results = report_helper::get_report_data(array(
      'dataSource' => 'specific_surveys/ecobat/reference_output_for_manually_specified_record',
      'readAuth' => self::$auth['read'],
      'extraParams' => $params
    ));
    $percentile = round($results[0]['percentile']);
    $activityLevel = self::percentToActivityTerm($percentile);
    $suffix = self::ordinalSuffix($percentile);
    $filterList = [];
    if ($params['filter_date']==='1')
      $filterList[] = 'within 30 days of the survey date';
    if (!empty($params['filter_spatial_km']))
      $filterList[] = "within $params[filter_spatial_km]km<sup>2</sup> of this location";
    if (!empty($params['filter_detector_make']))
      $filterList[] = "using detectors made by " . self::getTermForId($params['filter_detector_make']);
    if ($params['filter_temp']==='1') {
      $from = $params['input_temp']-2;
      $to = $params['input_temp']+2;
      $filterList[] = "where the temperature at sunset was between $from&deg;C and $to&deg;C";
    }
    if ($params['filter_wind']==='1') {
      $from = $params['input_wind']-2;
      $to = $params['input_wind']+2;
      $filterList[] = "where the wind speed at sunset was between {$from}mph and {$to}mph";
    }
    $filterText = count($filterList) ?
      ' all recorded ' . implode(' and ', $filterList) :
      '';
    $r = <<<ANALYSIS
<fieldset><legend>Analysis output</legend>
<p>The $passes $species $passesTerm recorded on $date at location $mapref
$isOrAre in the {$percentile}$suffix percentile of bat activity for this species within its reference range.
This is regarded as a night of $activityLevel bat activity. This was calculated by comparing this
recording with $count records of $species nightly activity$filterText.</p>
<div class="percent-bar"><div class="value" style="width: {$percentile}%"></div></div>
<div class="percent-bar-label" style="margin-left: {$percentile}%">{$percentile}$suffix percentile</div>
</fieldset>
ANALYSIS;
    if ($count<1000) {
      $r .= <<<SUGGEST
<p>Because the reference range filter returned less than 1000 records, you might like to return to the
analysis form and change the reference range filter so a larger reference range set can be analysed.</p>
SUGGEST;
      $r .= '<form method="POST">';
      foreach ($_POST as $key=>$value) {
        $r .= "<input type=\"hidden\" name=\"$key\" value=\"$value\" />";
      }
      $r .= '<input type="hidden" name="runAnalysis" value="no"/>';
      $r .= '<input type="submit" value="Return to analysis form">';
      $r .= '</form>';
    }
    return $r;
  }

  /**
   * Retrieves the term for a given ID
   * @param $id
   * @return string
   */
  private static function getTermForId($id) {
    $ids = data_entry_helper::get_population_data(array(
      'table' => 'termlists_term',
      'extraParams' => self::$auth['read'] + array('view' => 'cache', 'id' => $id, 'columns' => 'term')
    ));
    return $ids[0]['term'];
  }

  private static function ordinalSuffix($num){
    $num = $num % 100; // protect against large numbers
    if($num < 11 || $num > 13){
      switch($num % 10){
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
      }
    }
    return 'th';
  }

  private static function percentToActivityTerm($percentile) {
    if ($percentile < 20)
      $activityLevel = 'low';
    elseif ($percentile <40)
      $activityLevel = 'low/moderate';
    elseif ($percentile <60)
      $activityLevel = 'moderate';
    elseif ($percentile <80)
      $activityLevel = 'moderate/high';
    else
      $activityLevel = 'high';
    return $activityLevel;
  }

  private static function paramsForm($args) {
    global $indicia_templates;
    if (!empty($_POST))
      data_entry_helper::$entity_to_load = $_POST;
    $col1 = '<p>Fields marked with a <span class="deh-required">*</span> require you to fill in a value.</p>';
    $col1 .= data_entry_helper::date_picker(array(
      'label' => 'Date',
      'fieldname' => 'input_date',
      'class' => 'control-width-4',
      'helpText' => 'Specify the date of the record to compare against the reference range.',
      'validation' => array('required')
    ));
    $col1 .= data_entry_helper::species_autocomplete(array(
      'label' => 'Species',
      'fieldname' => 'input_taxa_taxon_list_external_key',
      'valueField' => 'external_key',
      'class' => 'control-width-4',
      'helpText' => 'Choose the species you are analysing the number of nightly passes for.',
      'validation' => array('required'),
      'speciesIncludeBothNames' => true,
      'speciesNameFilterMode' => 'excludeSynonyms',
      'extraParams' => self::$auth['read'] + array('taxon_list_id' => $args['taxon_list_id'])
    ));
    $col1 .= data_entry_helper::text_input(array(
      'label' => 'Number of passes',
      'fieldname' => 'input_passes',
      'class' => 'control-width-4',
      'helpText' => 'Count of passes in a single night',
      'validation' => array('required')
    ));
    $col1 .= data_entry_helper::select(array(
      'label' => 'Pass definition',
      'fieldname' => 'input_pass_definition_id',
      'table' => 'termlists_term',
      'captionField' => 'term',
      'valueField' => 'id',
      'extraParams' => self::$auth['read'] + array(
        'view' => 'cache',
        'termlist_title' => 'Ecobat pass definitions',
        'preferred' => 't'
      ),
      'blankText' => '<Please select>',
      'validation' => array('required'),
      'default' => $args['default_pass_definition_id']
    ));
    $col1 .= data_entry_helper::sref_and_system(array(
      'label' => 'Map ref',
      'fieldname' => 'input_sref',
      'validation' => array('required')
    ));
    $col1 .= data_entry_helper::text_input(array(
      'label' => 'Temperature',
      'fieldname' => 'input_temp',
      'class' => 'control-width-4',
      'helpText' => 'Temperature at sunset in &deg;C (optional)',
      'afterControl' => '&deg;C'
    ));
    $col1 .= data_entry_helper::text_input(array(
      'label' => 'Wind speed',
      'fieldname' => 'input_wind',
      'class' => 'control-width-4',
      'helpText' => 'Wind speed at sunset in mph (optional)',
      'afterControl' => 'mph'
    ));
    $col2 = map_helper::map_panel(iform_map_get_map_options($args, null), iform_map_get_ol_options($args));
    $firstTab = '<div class="at-panel panel-display two-50 clearfix">' .
      "<div class=\"region region-two-50-first\"><div class=\"region-inner clearfix\">$col1</div></div>" .
      "<div class=\"region region-two-50-second\"><div class=\"region-inner clearfix\">$col2</div></div>" .
      '</div>';
    $firstTab .= data_entry_helper::wizard_buttons(array(
      'divId'=>'controls',
      'page'=>'first'
    ));
    $secondTab = '<fieldset><legend>Date filter:</legend>';
    $secondTab .= '<label class="auto">' . data_entry_helper::checkbox(array(
      'fieldname' => 'filter_date',
      'default' => '1'
    )) . ' Similar date (+/- 30 days)</label>';
    $secondTab .= '<p class="helpText">Limit the records returned to a similar time of year.</p>';
    $secondTab .= '</fieldset>';
    $secondTab .= '<fieldset><legend>Geographic filter:</legend>';
    $secondTab .= data_entry_helper::radio_group(array(
      'fieldname' => 'filter_spatial_km',
      'helpText' => 'Limit the records returned by geographic distance.',
      'lookupValues' => array(
        '' => 'No geographic filter',
        '100' => 'Similar geographic region (within 100km)',
        '200' => 'Similar geographic region (within 200km)'
      ),
      'default' => '100'
    ));
    $secondTab .= '</fieldset>';
    $secondTab .= '<fieldset><legend>Detector filter:</legend>';
    $secondTab .= data_entry_helper::select(array(
      'label' => 'Make',
      'fieldname' => 'filter_detector_make',
      'helpText' => 'Limit the records returned to the detector make you choose here.',
      'blankText' => '<no filter>',
      'table' => 'termlists_term',
      'captionField' => 'term',
      'valueField' => 'id',
      'extraParams' => self::$auth['read'] + array(
          'view' => 'cache',
          'termlist_title' => 'Ecobat detector manufacturers',
          'preferred' => 't'
        ),
    ));
    $secondTab .= '</fieldset>';
    /* $secondTab .= '<fieldset><legend>Environmental conditions filter:</legend>';
    $secondTab .= '<label class="auto">' . data_entry_helper::checkbox(array(
      'fieldname' => 'filter_temp',
      'default' => '1',
    )) . ' Similar temperature (+/- 2&deg;C)</label>';
    $secondTab .= '<p class="helpText">Limit the records returned to a similar temperature.</p>';
    $secondTab .= '<label class="auto">' . data_entry_helper::checkbox(array(
      'fieldname' => 'filter_wind',
      'default' => '0'
    )) . ' Similar wind speed (+/- 2mph)</label>';
    $secondTab .= '<p class="helpText">Limit the records returned to a similar wind speed.</p>';
    $secondTab .= '</fieldset>';*/
    $secondTab .= data_entry_helper::hidden_text(array(
      'fieldname' => 'filter_temp'
    ));
    $secondTab .= data_entry_helper::hidden_text(array(
      'fieldname' => 'filter_wind'
    ));
    $secondTab .= data_entry_helper::wizard_buttons(array(
      'divId'=>'controls',
      'page'=>'last',
      'captionSave' => 'Analyse'
    ));
    $r = '<form method="POST" id="params-form">';
    $r .= '<div id="controls">' . data_entry_helper::tab_header(array(
      'tabs' => array(
        '#input-tab' => 'Input record',
        '#filter-tab' => 'Reference range filter'
      )
    ));
    $r .= '<div id="input-tab">' . $firstTab . '</div>';
    $r .= '<div id="filter-tab">' . $secondTab . '</div>';
    $r .= '</div>';
    $r .= '</form>';
    data_entry_helper::enable_validation('params-form');
    data_entry_helper::enable_tabs(array('divId' => 'controls', 'navButtons' => true));
    return $r;
  }

}
