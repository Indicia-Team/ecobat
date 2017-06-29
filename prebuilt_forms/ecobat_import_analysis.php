<?php


/**
 *
 *
 * @package Client
 * @subpackage PrebuiltForms
 * @todo Provide form description in this comment block.
 * @todo Rename the form class to iform_...
 */
class iform_ecobat_import_analysis {

  private static $auth;

  /**
   * Return the form metadata. Note the title of this method includes the name of the form file. This ensures
   * that if inheritance is used in the forms, subclassed forms don't return their parent's form definition.
   * @return array The definition of the form.
   * @todo rename this method.
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
   * @todo: Implement this method
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
   * @todo: Implement this method
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
      'extraParams' => array('input_import_guid' => $_REQUEST['import_guid'])
    ));
    $importValuesForInput = $importValuesQuery[0];

    // We can only analyse records for a single pass definition
    $pass_definitions = explode(',', $importValuesForInput['pass_definition_ids']);
    if (count($pass_definitions)>1) {
      hostsite_show_message(lang::get('The import is not suitable for analysis because it contains records which use ' .
          'a mixture of pass definitions.'), 'warning');
      drupal_set_message(var_export($pass_definitions, true));
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
      'filter_pass_definition' => '1'
    );

    $countQuery = report_helper::get_report_data(array(
      'dataSource' => 'specific_surveys/ecobat/reference_output_count_for_range',
      'readAuth' => self::$auth['read'],
      'extraParams' => $params
    ));

    if (count($countQuery) === '0') {
      drupal_set_message('There were no records found in the reference range that matched the filter specified. ' .
        'Please alter the reference range filter to select a wider range of records then try again.');
      return self::paramsForm($args);
    }

    // create a simple array for looking up the reference set size per species
    $referenceCounts = [];
    foreach($countQuery as $record)
      $referenceCounts[$record['external_key']] = $record['filtercount'];

    // additional parameter to specify which import to work against
    $params['input_import_guid'] = $_REQUEST['import_guid'];
    $results = report_helper::get_report_data(array(
      'dataSource' => 'specific_surveys/ecobat/reference_output_for_import',
      'readAuth' => self::$auth['read'],
      'extraParams' => $params
    ));
    return self::showOutput($results, $params, $referenceCounts);
  }

  private static function showOutput($results, $params, $referenceCounts) {
    // chunk the results up by species to output separate results for each
    helper_base::add_resource('jqplot');
    helper_base::add_resource('jqplot_bar');
    $taxaDefs = array();
    $currentTaxon = '';
    $currentTaxonResults = [];
    $currentTaxonLabel = '';
    foreach ($results as $result) {
      if ($currentTaxon !== $result['taxon']) {
        if (!empty($currentTaxonResults))
          $taxaDefs[$currentTaxonLabel] = array($currentTaxonLabel, $currentTaxonResults);
        $currentTaxonResults = [];
        $currentTaxon = $result['taxon'];
        $currentTaxonLabel = self::species_label($result);
      }
      $currentTaxonResults[] = $result;
    }
    if (!empty($currentTaxonResults))
      $taxaDefs[$currentTaxonLabel] = array($currentTaxonLabel, $currentTaxonResults);
    $r = '';
    foreach ($taxaDefs as $taxon => $taxonDef)
      $r .= self::showOutputForTaxon($taxonDef[0], $taxonDef[1], $params, $referenceCounts);
    return $r;
  }

  private static function showOutputForTaxon($taxon, $results, $params, $referenceCounts) {
    $r = "<fieldset><legend>Results for $taxon</legend>";
    $referenceCount = $referenceCounts[$results[0]['external_key']];
    $r .= self::showSummaryOutput($results, $params, $referenceCount);
    if (count($results)<=3) {
      foreach($results as $night)
        $r .= self::showNightOutput($night, $params, $referenceCount);
    } else {
      $r .= lang::get('Nightly breakdowns are only available when analysing 3 nights or less at a time.');
    }
    $r .= '</fieldset>';
    return $r;
  }

  private static function showNightOutput($night, $params, $referenceCount) {
    $percentile = round($night['percentile']);
    $activityLevel = self::percentToActivityTerm($percentile);
    $suffix = self::ordinalSuffix($percentile);
    $filterText = self::getFilterText($params);
    $passes = $night['passes_summed'];
    $species = self::species_label($night, true);
    $date = $night['date'];
    $mapref = $night['entered_sref'];
    $passesTerm = $passes === '1' ? 'pass' : 'passes';
    $isOrAre = $passes === '1' ? 'is' : 'are';
    $r = <<<ANALYSIS
<fieldset><legend>Nightly output for $date</legend>
<p>The $passes $species $passesTerm recorded on $date at location $mapref
$isOrAre in the {$percentile}$suffix percentile of bat activity for this species within its reference range.
This is regarded as a night of $activityLevel bat activity. This was calculated by comparing this
recording with $referenceCount records of $species nightly activity$filterText.</p>
<div class="percent-bar">
<div class="value" style="width: {$percentile}%"></div>
</div>
<div class="percent-bar-label" style="margin-left: {$percentile}%">{$percentile}$suffix percentile</div>
</fieldset>
ANALYSIS;
    return $r;
  }

  private static function species_label($record, $simple = false) {
    if ($record['common_name'] !== $record['taxon'] && !empty($record['common_name'])) {
      $r = "$record[common_name]";
      if (!$simple)
        $r .= " (<em>$record[taxon]</em>)";
      return $r;
    } else
      return "<em>$record[taxon]</em>";
  }

  private static function getFilterText($params) {
    $filterList = [];
    if ($params['filter_date']==='1')
      $filterList[] = 'within 30 days of the range of surveyed dates';
    if (!empty($params['filter_spatial_km']))
      $filterList[] = "within $params[filter_spatial_km]km<sup>2</sup> of this location";
    if (!empty($params['filter_detector_make']))
      $filterList[] = "using detectors made by " . self::getTermForId($params['filter_detector_make']);
    if ($params['filter_temp']==='1') {
      $from = $params['input_temp_low']-2;
      $to = $params['input_temp_high']+2;
      $filterList[] = "where the temperature at sunset was between $from&deg;C and $to&deg;C";
    }
    if ($params['filter_wind']==='1') {
      $from = $params['input_wind_low']-2;
      $to = $params['input_wind_high']+2;
      $filterList[] = "where the wind speed at sunset was between {$from}mph and {$to}mph";
    }
    if ($params['filter_pass_definition']==='1')
      $filterList[] = 'using the same pass definition';
    if (count($filterList)) {
      $last = array_pop($filterList);
      return ' all recorded ' . implode(', ', $filterList) . " and $last";
    }
    return '';
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

  private static function showSummaryOutput($results, $params, $referenceCount) {
    $levels = [
      'high' => 0,
      'moderate/high' => 0,
      'moderate' => 0,
      'low/moderate' => 0,
      'low' => 0
    ];
    $i = 0;
    $percentiles = [];
    $highestPasses = 0;
    $lowestPasses = 9999;

    foreach ($results as $night) {
      $activityTerm = self::percentToActivityTerm($night['percentile']);
      $levels[$activityTerm] = $levels[$activityTerm] + 1;
      $percentiles[] = $night['percentile'];
      if ($night['passes_summed'] > $highestPasses) {
        $highestPasses = $night['passes_summed'];
      }
      if ($night['passes_summed'] < $lowestPasses) {
        $lowestPasses = $night['passes_summed'];
      }
      $i++;
    }
    sort($percentiles);
    $highestPercentile = $percentiles[count($percentiles) - 1];
    $highestPercentileSuffix = self::ordinalSuffix($highestPercentile);
    // correct pluralisation
    $es = $highestPasses === '1' ? '' : 'es';
    $activityPhrases = [];
    // we'll treat things differently if all the activity values were the same
    $allSameActivity = $highestPasses === $lowestPasses;
    // we'll treat things differently if all the nights had the same broad level
    $allSameLevel = FALSE;
    foreach ($levels as $level => $levelCount) {
      if ($levelCount > 0) {
        $allSameLevel = $allSameLevel || $levelCount === count($percentiles);
        $countIndication = $allSameLevel ? 'all' : $levelCount;
        $phrase = "$countIndication of which were classed as $level activity";
        /*// simplify things if all values are the same
        if ($allSameLevel) {
          $phrase .= " with $highestPasses pass$es and a percentile of $percentiles[0]";
        }*/
        $activityPhrases[] = $phrase;
      }
    }
    $lastPhrase = array_pop($activityPhrases);
    $commaJoinedPhrases = implode(', ', $activityPhrases);
    $activitySummary = empty($commaJoinedPhrases) ? $lastPhrase : "$commaJoinedPhrases and $lastPhrase";
    $count = count($percentiles);
    $median = self::getMedian($percentiles);
    $medianSuffix = self::ordinalSuffix($median);

    if ($allSameActivity) {
      $breakdown = "All the nights had $highestPasses pass$es and were on the " .
        "$highestPercentile$highestPercentileSuffix percentile.";
      $bar = <<<SIMPLE
<div class="percent-bar">
<div class="value" style="width: {$highestPercentile}%"></div>
</div>
<div class="percent-bar-label" style="margin-left: {$highestPercentile}%">{$highestPercentile}$highestPercentileSuffix %ile</div>
SIMPLE;
    } else {
      // Confidence interval calculation
      // @see https://www.ucl.ac.uk/ich/short-courses-events/about-stats-courses/stats-rm/Chapter_8_Content/confidence_interval_single_median
      $confidenceRangeWidth = 1.96 * sqrt($count) / 2;
      $confidenceRangeFrom = round($count / 2 - $confidenceRangeWidth - 1);
      $confidenceRangeTo = round($count / 2 + $confidenceRangeWidth);
      if ($confidenceRangeFrom >= 0) {
        $confidenceFrom = $percentiles[$confidenceRangeFrom];
        $confidenceTo = $percentiles[$confidenceRangeTo];
        $confidenceRangeText = "(95% confidence interval: $confidenceFrom to $confidenceTo)";
      }
      else {
        $from = $percentiles[0];
        $to = $percentiles[count($percentiles) - 1];
        $confidenceRangeText = "(range $from to $to)";
      }
      $breakdown = <<<BREAKDOWN
The median percentile was $median $confidenceRangeText. The highest night of activity contained $highestPasses pass$es, 
this was on the $highestPercentile$highestPercentileSuffix percentile.
BREAKDOWN;
      $t = '';
      if ($confidenceRangeFrom >= 0) {
        $tWidth = $confidenceTo - $confidenceFrom;
        $t = <<<TBAR
<div class="percent-bar-t-horiz" style="left: {$confidenceFrom}%; width: $tWidth%;"></div>
<div class="percent-bar-t-vert" style="left: {$confidenceFrom}%; width: $tWidth%;"></div>
TBAR;
      }
      $t .= <<<HIGHEST
<div class="highest-percentile" style="width: {$highestPercentile}%"></div>
HIGHEST;
      $bar = <<<ADV
<div class="percent-bar">
$t
<div class="value" style="width: {$median}%"></div>
</div>
<div class="percent-bar-label" style="margin-left: {$median}%">{$median}$medianSuffix percentile</div>
ADV;
    }
    $filterText = self::getFilterText($params);
    $r = <<<ANALYSIS
<p>There were $count nights of surveying, $activitySummary. 
 $breakdown
 This was calculated by comparing this import with $referenceCount records of nightly activity$filterText.
</p>
$bar
ANALYSIS;

    return "<fieldset><legend>Summary analysis</legend>$r</fieldset>\n";
  }

  /**
   * @param $num
   * @return string
   * @todo Shared code with other form
   */
  private static function ordinalSuffix($num){
    $num = $num % 100; // protect against large numbers
    if ($num < 11 || $num > 13) {
      switch ($num % 10){
        case 1: return 'st';
        case 2: return 'nd';
        case 3: return 'rd';
      }
    }
    return 'th';
  }

  /**
   * Returns the median of an array
   * @param $arr
   */
  private static function getMedian($arr) {
    $count = count($arr);
    if ($count % 2 === 1) {
      // odd number
      return $arr[($count-1) / 2];
    } else {
      // get average of 2 values adjacent to the midpoint
      return ($arr[$count / 2 - 1] + $arr[$count / 2]) / 2;
    }
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
    $r = '<form method="POST">';
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
