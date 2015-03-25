<?php
namespace data;

/**
 * Provides useful functionality for score table creation.
 *
 * @author Dayan Paez
 * @version 2015-03-24
 */
class Utils {

  /**
   * Create a basic tiebreaker map from entries in ranks.
   *
   * @param Array:Rank the list of ranks.
   * @return Array map of explanation => key in legend.
   */
  public static function createTiebreakerMap($ranks) {
    $tiebreakers = array("" => "");

    foreach ($ranks as $rank) {
      if (!empty($rank->explanation) && !isset($tiebreakers[$rank->explanation])) {
        $count = count($tiebreakers);
        switch ($count) {
        case 1:
          $tiebreakers[$rank->explanation] = "*";
          break;
        case 2:
          $tiebreakers[$rank->explanation] = "**";
          break;
        default:
          $tiebreakers[$rank->explanation] = chr(95 + $count);
        }
      }
    }

    return $tiebreakers;
  }
}