<?php

class iform_ecobat_import_within_night_analysis {

  private static $auth;

  /**
   * Return the form metadata.
   */
  public static function get_ecobat_import_within_night_analysis_definition() {
    return array(
      'title'=>'Ecobat import within night analysis form',
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
      'report_helper',
    ));
    data_entry_helper::$website_id=$args['website_id'];
    self::$auth = data_entry_helper::get_read_write_auth($args['website_id'], $args['password']);
    return self::reportOutput($args);
  }

  private static function reportOutput($args) {
    hostsite_set_page_title(lang::get('Analysis output'));
    $params = array(
      // additional parameter to specify which import to work against
      'input_import_guid' => $_REQUEST['import_guid'],
    );
    if (!empty($_POST['shiny'])) {
      $url = 'https://ecobat.shinyapps.io/Nightly-Analysis?' . http_build_query($params);
      header('Location: ' . $url);
      die();
    }
    if (!empty($_POST['tabular'])) {
      return report_helper::report_grid(array(
        'dataSource' => 'specific_surveys/ecobat/within_night_output_for_import',
        'readAuth' => self::$auth['read'],
        'extraParams' => $params,
        'downloadLink' => TRUE,
      ));
    } else {
      $r = '<h2>Generating an analysis report</h2>';
      $r .= '<ul class="task-list">';
      $r .= '<li><h3>Step 1</h3><p>Please click this link to download results of your analysis as raw data (essential for step 2).</p>';
      $r .= report_helper::report_download_link(array(
        'dataSource' => 'specific_surveys/ecobat/within_night_output_for_import',
        'readAuth' => self::$auth['read'],
        'extraParams' => $params,
        'class' => 'button',
      ));
      $r .= '</li>';
      $r .= '<li><h3>Step 2</h3><p>Upload your results into the analysis app (link below) to generate your personalised analysis report.</p>';
      $r .= '<a href="/analyse/within-night-output">Generate analysis report</a>';
      $r .= '</li>';
      $r .= '</ul>';
      return $r;
    }
  }

}
