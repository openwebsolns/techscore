<?php
namespace data;

use \InvalidArgumentException;

use \Dt_Team_Division;
use \Team;
use \Rank;

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
   * @param Array of either RankedTeam or Ranks.
   * @return Array map of explanation => key in legend.
   */
  public static function createTiebreakerMap($ranks) {
    $tiebreakers = array("" => "");

    foreach ($ranks as $rank) {
      $explanation = null;
      if ($rank instanceof Team) {
        $explanation = $rank->dt_explanation;
      }
      elseif ($rank instanceof Rank) {
        $explanation = $rank->explanation;
      }
      elseif ($rank instanceof Dt_Team_Division) {
        $explanation = $rank->explanation;
      }
      else {
        throw new InvalidArgumentException("Invalid object provided: " . get_class($rank));
      }

      if (!empty($explanation) && !isset($tiebreakers[$explanation])) {
        $count = count($tiebreakers);
        switch ($count) {
        case 1:
          $tiebreakers[$explanation] = "*";
          break;
        case 2:
          $tiebreakers[$explanation] = "**";
          break;
        default:
          $tiebreakers[$explanation] = chr(95 + $count);
        }
      }
    }

    return $tiebreakers;
  }
}