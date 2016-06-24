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

$config['website_id'] = 2;//126;
$config['survey_id'] = 2;//260;
$config['sample_attrs'] = array(
  'detector_make_id' => 'smpAttr:765',
  'detector_make_other' => 'smpAttr:765',
  'detector_model' => 'smpAttr:763',
  'detector_height_m' => 'smpAttr:',
  'roost_within_25m' => 'smpAttr:',
  'activity_elevated_by_roost' => 'smpAttr:',
  'linear_feature_adjacent_id' => 'smpAttr:',
  'linear_feature_25m_id' => 'smpAttr:',
  'anthropogenic_feature_adjacent_id' => 'smpAttr:',
  'anthropogenic_feature_25m_id' => 'smpAttr:',
  'temperature_c' => 'smpAttr:764',
  'rainfall_id' => 'smpAttr:',
  'wind_speed' => 'smpAttr:',
  'wind_speed_unit_id' => 'smpAttr:'
);
$config['occurrence_attrs'] = array(
  'passes' => 'occAttr:372',
  'pass_definition_id' => 'occAttr:373'
);