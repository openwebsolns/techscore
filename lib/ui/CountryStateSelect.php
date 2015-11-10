<?php
namespace ui;

use \XOption;
use \XOptionGroup;
use \XSelect;

/**
 * Convenience class for choosing from the known states and provinces
 * of certain countries.
 *
 * @author Dayan Paez
 * @version 2015-11-10
 */
class CountryStateSelect extends XSelect {

  const US = "United States";
  const CA = "Canada";

  public function __construct($name, $chosen = null) {
    parent::__construct($name);
    $this->add(new XOption("", array(), ""));
    foreach (self::$data as $country => $list) {
      $this->add($group = new XOptionGroup($country));
      foreach ($list as $value => $label) {
        $option = new XOption($value, array(), $label);
        if ($value == $chosen) {
          $option->set('selected', 'selected');
        }
        $group->add($option);
      }
    }
  }

  public static function getKeyValuePairs() {
    $values = array();
    foreach (self::$data as $list) {
      foreach ($list as $key => $value) {
        $values[$key] = $value;
      }
    }
    return $values;
  }

  private static $data = array(
    self::US => array(
      'AL' => 'AL - ALABAMA',
      'AK' => 'AK - ALASKA',
      'AS' => 'AS - AMERICAN SAMOA',
      'AZ' => 'AZ - ARIZONA',
      'AR' => 'AR - ARKANSAS',
      'CA' => 'CA - CALIFORNIA',
      'CO' => 'CO - COLORADO',
      'CT' => 'CT - CONNECTICUT',
      'DE' => 'DE - DELAWARE',
      'DC' => 'DC - DISTRICT OF COLUMBIA',
      'FL' => 'FL - FLORIDA',
      'GA' => 'GA - GEORGIA',
      'GU' => 'GU - GUAM',
      'HI' => 'HI - HAWAII',
      'ID' => 'ID - IDAHO',
      'IL' => 'IL - ILLINOIS',
      'IN' => 'IN - INDIANA',
      'IA' => 'IA - IOWA',
      'KS' => 'KS - KANSAS',
      'KY' => 'KY - KENTUCKY',
      'LA' => 'LA - LOUISIANA',
      'ME' => 'ME - MAINE',
      'MH' => 'MH - MARSHALL ISLANDS',
      'MD' => 'MD - MARYLAND',
      'MA' => 'MA - MASSACHUSETTS',
      'MI' => 'MI - MICHIGAN',
      'MN' => 'MN - MINNESOTA',
      'MS' => 'MS - MISSISSIPPI',
      'MO' => 'MO - MISSOURI',
      'MT' => 'MT - MONTANA',
      'NE' => 'NE - NEBRASKA',
      'NV' => 'NV - NEVADA',
      'NH' => 'NH - NEW HAMPSHIRE',
      'NJ' => 'NJ - NEW JERSEY',
      'NM' => 'NM - NEW MEXICO',
      'NY' => 'NY - NEW YORK',
      'NC' => 'NC - NORTH CAROLINA',
      'ND' => 'ND - NORTH DAKOTA',
      'MP' => 'MP - NORTHERN MARIANA ISLANDS',
      'OH' => 'OH - OHIO',
      'OK' => 'OK - OKLAHOMA',
      'OR' => 'OR - OREGON',
      'PW' => 'PW - PALAU',
      'PA' => 'PA - PENNSYLVANIA',
      'PR' => 'PR - PUERTO RICO',
      'RI' => 'RI - RHODE ISLAND',
      'SC' => 'SC - SOUTH CAROLINA',
      'SD' => 'SD - SOUTH DAKOTA',
      'TN' => 'TN - TENNESSEE',
      'TX' => 'TX - TEXAS',
      'UT' => 'UT - UTAH',
      'VT' => 'VT - VERMONT',
      'VI' => 'VI - VIRGIN ISLANDS',
      'VA' => 'VA - VIRGINIA',
      'WA' => 'WA - WASHINGTON',
      'WV' => 'WV - WEST VIRGINIA',
      'WI' => 'WI - WISCONSIN',
      'WY' => 'WY - WYOMING',
    ),
    self::CA => array(
      'AB' => 'AB - ALBERTA',
      'BC' => 'BC - BRITISH COLUMBIA',
      'MB' => 'MB - MANITOBA',
      'NB' => 'NB - NEW BRUNSWICK',
      'NL' => 'NL - NEWFOUNDLAND AND LABRADOR',
      'NS' => 'NS - NOVA SCOTIA',
      'NT' => 'NT - NORTHWEST TERRITORIES',
      'NU' => 'NU - NUNAVUT',
      'ON' => 'ON - ONTARIO',
      'PE' => 'PE - PRINCE EDWARD ISLAND',
      'QC' => 'QC - QUEBEC',
      'SK' => 'SK - SASKATCHEWAN',
      'YT' => 'YT - YUKON',
    ),
  );
}