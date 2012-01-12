<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Static utilities
 *
 * @author Dayan Paez
 * @version 2009-12-13
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
}
?>