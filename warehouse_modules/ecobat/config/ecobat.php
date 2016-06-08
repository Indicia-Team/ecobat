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
 * @package	Modules
 * @subpackage Ecobat
 * @author	Indicia Team
 * @license	http://www.gnu.org/licenses/gpl.html GPL
 * @link 	http://code.google.com/p/indicia/
 */

$config['website_id'] = 2;
$config['survey_id'] = 2;
$config['sample_attrs'] = array(
  'detector_model' => 'smpAttr:21',
  /*'min_temperature_c',
  'precipitation_mm',
  'wind_speed_mph',
  'linear_features',
  'feature_type',
  'roost',
  'roost_taxa_taxon_list_id',
  'habitat_id'*/
);
$config['occurrence_attrs'] = array(
  'passes' => 'occAttr:3',
  'pass_definition_id' => 'occAttr:4'
);