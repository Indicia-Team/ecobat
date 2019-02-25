<?php


/**
 *
 *
 * @package Client
 * @subpackage PrebuiltForms
 */
class iform_ecobat_import_analysis {

  private static $auth;

  /**
   * Return the form metadata.
   */
  public static function get_ecobat_import_analysis_definition() {
    return array(
      'title'=>'Ecobat import analysis form',
      'category' => 'Ecobat',
      'description'=>'Form for using an import to feed into an Ecobat analysis.'
    );
  }

  /**
   * Get the list of parameters for this form.
   * @return array List of parameters that this form requires.
   */
  public static function get_parameters() {
    return array();
  }

  /**
   * Return the generated form output.
   * @param array $args List of parameter values passed through to the form depending on how the form has been configured.
   * This array always contains a value for language.
   * @param object $nid The Drupal node object's ID.
   * @param array $response When this form is reloading after saving a submission, contains the response from the service call.
   * Note this does not apply when redirecting (in this case the details of the saved object are in the $_GET data).
   * @return Form HTML.
   */
  public static function get_form($args, $nid, $response=null) {
    if (!isset($_REQUEST['import_guid']))
      return 'Invalid link. The import to analyse needs to be provided in an import_guid parameter in the URL.';
    iform_load_helpers(array(
      'data_entry_helper',
      'map_helper',
      'report_helper'
    ));
    data_entry_helper::$website_id=$args['website_id'];
    self::$auth = data_entry_helper::get_read_write_auth($args['website_id'], $args['password']);
    if (isset($_POST['runAnalysis']) && $_POST['runAnalysis']==='yes') {
      return self::reportOutput($args);
    }
    else {
      return self::paramsForm($args);
    }
  }

  private static function reportOutput($args) {
    hostsite_set_page_title(lang::get('Analysis output'));

    $importValuesQuery = report_helper::get_report_data(array(
      'dataSource' => 'specific_surveys/ecobat/input_params_for_import',
      'readAuth' => self::$auth['read'],
      'extraParams' => array(
        'input_import_guid' => $_REQUEST['import_guid']
      )
    ));
    $importValuesForInput = $importValuesQuery[0];
    foreach ($importValuesForInput as $key => &$value) {
      if ($value === null)
        $value = '';
    }

    // We can only analyse records for a single pass definition
    $pass_definitions = explode(',', $importValuesForInput['pass_definition_ids']);
    if (count($pass_definitions)>1) {
      hostsite_show_message(lang::get('The import is not suitable for analysis because it contains records which use ' .
        'a mixture of pass definitions.'), 'warning');
      hostsite_show_message(var_export($pass_definitions, true));
      return lang::get('Analysis cannot be completed.');
    }

    $params = array(
      'input_doy_from' => $importValuesForInput['doy_from'],
      'input_doy_to' => $importValuesForInput['doy_to'],
      'input_x' => $importValuesForInput['x_avg'],
      'input_y' => $importValuesForInput['y_avg'],
      'input_temp_low' => $importValuesForInput['temperature_c_low'],
      'input_temp_high' => $importValuesForInput['temperature_c_high'],
      'input_wind_low' => $importValuesForInput['wind_speed_mph_low'],
      'input_wind_high' => $importValuesForInput['wind_speed_mph_high'],
      'input_taxa_taxon_list_external_keys' => $importValuesForInput['taxa_taxon_list_external_keys'],
      'input_pass_definition_ids' => $pass_definitions[0],
      'filter_date' => $_POST['filter_date'],
      'filter_spatial_km' => $_POST['filter_spatial_km'],
      'filter_detector_make' => $_POST['filter_detector_make'],
      'filter_temp' => $_POST['filter_temp'],
      'filter_wind' => $_POST['filter_wind'],
      // this filter is forced on
      'filter_pass_definition' => '1',
      // additional parameter to specify which import to work against
      'input_import_guid' => $_REQUEST['import_guid']
    );
    if (!empty($_POST['shiny'])) {
      $url = 'https://ecobat.shinyapps.io/ecobat_analysis?' . http_build_query($params);
      header('Location: ' . $url);
      die();
    }
    if (!empty($_POST['tabular'])) {
      return report_helper::report_grid(array(
        'dataSource' => 'specific_surveys/ecobat/reference_output_for_import',
        'readAuth' => self::$auth['read'],
        'extraParams' => $params,
        'downloadLink' => TRUE
      ));
    } else {
      $r = '<h2>Generating an analysis report</h2>';
      $r .= '<ul class="task-list">';
      $r .= '<li><h3>Step 1</h3><p>Please click this link to download results of your analysis as raw data (essential for step 2).</p>';
      $r .= report_helper::report_download_link(array(
        'dataSource' => 'specific_surveys/ecobat/reference_output_for_import',
        'readAuth' => self::$auth['read'],
        'extraParams' => $params,
        'class' => 'button'
      ));
      $r .= '</li>';
      $r .= '<li><h3>Step 2</h3><p>Upload your results into the analysis app (link below) to generate your personalised analysis report.</p>';
      $r .= '<a href="/advanced-data-analysis">Generate analysis report</a>';
      $r .= '</li>';
      $r .= '</ul>';
      return $r;
    }
  }

  private static function paramsForm($args) {
    global $indicia_templates;
    if (!empty($_POST))
      data_entry_helper::$entity_to_load = $_POST;
    $r = '<form method="POST">';
    if (!empty($_GET['shiny'])) {
      $r .= '<input type="hidden" name="shiny" value="true" />';
    } elseif (!empty($_GET['tabular'])) {
      $r .= '<input type="hidden" name="tabular" value="true" />';
    }
    $r .= '<fieldset><legend>Date filter:</legend>';
    $r .= '<label class="auto">' . data_entry_helper::checkbox(array(
        'fieldname' => 'filter_date',
        'default' => '1',
    )) . ' Similar date (+/- 30 days)</label>';
    $r .= '<p class="helpText">Limit the records returned to a similar time of year. If the import ' .
          'being analysed includes data from multiple nights then the reference range will ' .
          'be filtered to records from 30 days before the first date to 30 days after the last ' .
          'date in the import.</p>';
    $r .= '</fieldset>';
    $r .= '<fieldset><legend>Geographic filter:</legend>';
    $r .= data_entry_helper::radio_group(array(
      'fieldname' => 'filter_spatial_km',
      'helpText' => 'Limit the records returned by geographic distance. If the import ' .
        'being analysed covers more than one map reference then the averaged centre of the import ' .
        'will be used to feed into the analysis.',
      'lookupValues' => array(
        '' => 'No geographic filter',
        '100' => 'Similar geographic region (within 100km)',
        '200' => 'Similar geographic region (within 200km)'
      ),
      'default' => '100'
    ));
    $r .= '</fieldset>';
    $r .= '<fieldset><legend>Detector filter:</legend>';
    $r .= data_entry_helper::select(array(
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
    $r .= '</fieldset>';
    /*$r .= '<fieldset><legend>Environmental conditions filter:</legend>';
    $r .= '<label class="auto">' . data_entry_helper::checkbox(array(
        'fieldname' => 'filter_temp',
        'default' => '1'
    )) . ' Similar temperature (+/- 2&deg;C)</label>';
    $r .= '<p class="helpText">Limit the records returned to a similar temperature. If the import ' .
          'being analysed includes data from multiple nights then the reference range will ' .
          'be filtered to records from 2&deg;C below the lowest temperature to 2&deg;C above ' .
          'the highest.</p>';
    $r .= '<label class="auto">' . data_entry_helper::checkbox(array(
        'fieldname' => 'filter_wind',
        'default' => '0'
    )) . ' Similar wind speed (+/- 2mph)</label>';
    $r .= '<p class="helpText">Limit the records returned to a similar wind speed. If the import ' .
    'being analysed includes data from multiple nights then the reference range will ' .
    'be filtered to records from 2 mph below the lowest speed to 2 mph above ' .
    'the highest.</p>';
    $r .= '</fieldset>';*/
    $r .= data_entry_helper::hidden_text(array(
      'fieldname' => 'filter_temp'
    ));
    $r .= data_entry_helper::hidden_text(array(
      'fieldname' => 'filter_wind'
    ));
    $r .= '<input type="hidden" name="runAnalysis" value="yes" />';
    $r .= '<input type="submit" value="Analyse"/>';
    $r .= '</form>';
    data_entry_helper::enable_tabs(array('divId' => 'controls'));
    return $r;
  }

}
