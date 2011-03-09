<?php
/**
 * This file is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Static utilities
 *
 * @author Dayan Paez
 * @created 2009-12-13
 */
class Utilities {

  /**
   * Creates array of range from string. On fail, return null. Expects
   * argument to contain only spaces, commas, dashes and numbers,
   * greater than 0
   *
   * @param String $str the range to parse
   * @return Array the numbers in the string in numerical order
   */
  public static function parseRange($str) {
    // Check for valid characters
    if (preg_match('/[^0-9 ,-]/', $str) == 1)
      return null;

    // Remove leading and trailing spaces, commasn and hyphens
    $str = preg_replace('/^[ ,-]*/', '', $str);
    $str = preg_replace('/[ ,-]*$/', '', $str);
    $str = preg_replace('/ +/', ' ', $str);

    // Squeeze spaces
    $str = preg_replace('/ +/', ' ', $str);

    // Make interior spaces into commas, and squeeze commas
    $str = str_replace(" ", ",", $str);
    $str = preg_replace('/,+/', ',', $str);

    // Squeeze hyphens
    $str = preg_replace('/-+/', '-', $str);

    $sub = explode(",", $str);
    $list = array();
    foreach ($sub as $s) {
      $delims = explode("-", $s);
      $start  = $delims[0];
      $end    = $delims[count($delims)-1];
    
      // Check limits
      if ($start > $end) // invalid range
	return null;
      for ($i = $start; $i <= $end; $i++)
	$list[] = (int)$i;
    }
    
    return array_unique($list);
  }

  /**
   * Creates a string representation of the integers in the list
   *
   * @param Array<int> $list the numbers to be made into a range
   * @return String the range as a string
   */
  public static function makeRange(Array $list) {
    // Must be unique
    $list = array_unique($list);
    if (count($list) == 0)
      return "";

    // and sorted
    sort($list, SORT_NUMERIC);
  
    $mid_range = false;
    $last  = $list[0];
    $range = $last;
    for ($i = 1; $i < count($list); $i++) {
      if ($list[$i] == $last + 1)
	$mid_range = true;
      else {
	$mid_range = false;
	if ($last != substr($range,-1))
	  $range .= "-$last";
	$range .= ",$list[$i]";
      }
      $last = $list[$i];
    }
    if ( $mid_range )
      $range .= "-$last";

    return $range;
  }

  /**
   * Returns a list of the unscored race numbers common to all the
   * divisions passed in the parameter
   *
   * @param Regatta $reg the regatta
   * @param Array<div> $divs a list of divisions
   * @return a list of race numbers
   */
  public static function getUnscoredRaceNumbers(Regatta $reg, Array $divisions) {
    $common_nums = null;
    foreach ($divisions as $div) {
      $races = $reg->getUnscoredRaces($div);
      $nums  = array();
      foreach ($races as $race) {
	$nums[] = $race->number;
      }

      if ($common_nums == null) {
	$common_nums = $nums;
      }
      else {
	$common_nums = array_intersect($common_nums, $nums);
      }
    }
    return $common_nums;
  }

  /**
   * Creates a short, filesystem safe nick name from a given string
   *
   * @param String $name the name to modify
   * @return String the modified name
   */
  public static function createNick($name) {
    $name = strtolower((string)$name);

    // Remove 's from
    $name = preg_replace('/\'s/', '', $name);

    // White list permission
    $name = preg_replace('/[^0-9a-z\s-_+]+/', '', $name);

    // Remove '80th'
    $name = preg_replace('/[0-9]+th/', '', $name);
    $name = preg_replace('/[0-9]*1st/', '', $name);
    $name = preg_replace('/[0-9]*2nd/', '', $name);
    $name = preg_replace('/[0-9]*3rd/', '', $name);

    // Trim spaces
    $name = trim($name);
    $name = preg_replace('/\s+/', '-', $name);

    $tokens = explode("-", $name);
    $blacklist = array("the", "of", "for", "and", "an", "in", "is", "at",
		       "trophy", "championship", "intersectional",
		       "college", "university", "regatta",
		       "professor");
    $tok_copy = $tokens;
    foreach ($tok_copy as $i => $t)
      if (in_array($t, $blacklist))
	unset($tokens[$i]);
    $name = implode("-", $tokens);

    // eastern -> east
    $name = str_replace("eastern", "east", $name);
    $name = str_replace("western", "west", $name);
    $name = str_replace("northern", "north", $name);
    $name = str_replace("southern", "south", $name);

    // semifinals -> semis
    $name = str_replace("semifinals", "semis", $name);
    $name = str_replace("semifinal",  "semis", $name);

    $i = 1;
    $new_name = $name;
    return $new_name;
  }
}
?>