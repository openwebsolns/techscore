<?php
/**
 * This file is part of Techscore
 *
 * @author Dayan Paez
 * @created 2011-05-05
 * @package regatta
 */

/**
 * Analyzes scores, like for the All American report, for instance, by
 * providing static methods
 *
 * @author Dayan Paez
 * @version 2011-05-05
 */
class ScoresAnalyzer {

  /**
   * Gets all the sailors which have placed AT LEAST as high as the
   * given place finish in the given division
   *
   * @param Array $reg_ids list of the regatta IDs to consider
   * @param Division $div the division to consider
   * @param int $place the lowest place finish (inclusive) to consider
   * @param Const $role either 'skipper' (default) or 'crew'
   * @return Array:Sailor
   */
  public static function getHighFinishers(Array $reg_ids, Division $div, $place, $role = 'skipper') {
    $q = sprintf('select distinct %s from %s where icsa_id is not null and id in (select sailor from rp where team in (select team from dt_team_division where rank <= %d and division = "%s") and race in (select id from race where regatta in (%s) and division = "%s") and boat_role = "%s")',
		 Sailor::FIELDS, Sailor::TABLES, $place, $div, implode(',', $reg_ids), $div, $role);
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("Sailor"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Returns a list of high finishes team IDs, in objects which have
   * two properties: 'regatta' (a regatta ID) and 'team' (a regatta
   * team).
   *
   * @see getHighFinishers
   * @return Array:TeamDivision
   */
  public static function getHighFinishingTeams(Regatta $reg, Division $div, $place) {
    $q = sprintf('select %s from %s where team in (select id from team where regatta = %d) and rank <= %d and division = "%s"',
		 TeamDivision::FIELDS, TeamDivision::TABLES, $reg->id(), $place, $div);
    $q = Preferences::query($q);
    $list = array();
    while ($obj = $q->fetch_object("TeamDivision"))
      $list[] = $obj;
    return $list;
  }

  /**
   * Gets the TeamDivision (that is the rank) for the given RP
   *
   */
  public static function getTeamDivision(Team $team, Division $div) {
    $q = sprintf('select %s form %s where team = "%s" and division = "%s"',
		 TeamDivision::FIELDS, TeamDivision::TABLES, $team->id, $div);
    $q = Preferences::query($q);
    if ($q->num_rows == 0)
      return null;
    $team = $q->fetch_object("TeamDivision");
    $q->free();
    return $team;
  }

  /**
   * Returns the place finishes obtained by the given sailor in the
   * given regatta as a string "#D", such as '3A' for "third place in
   * A division"
   *
   * @param Regatta $reg the regatta to consider
   * @param Sailor $sailor the sailor to consider
   * @return Array the place finishes
   */
  public static function getPlaces(Regatta $reg, Sailor $sailor) {
    $list = array();
    foreach ($reg->getDivisions() as $div) {
      $q = sprintf('select rank from dt_team_division where team in (select id from team where regatta = %d) and division = "%s" and team in (select team from rp where sailor = "%s" and race in (select id from race where division = "%s"))',
		   $reg->id(), $div, $sailor->id, $div);
      $q = Preferences::query($q);
      if ($q->num_rows > 0) {
	$rank = $q->fetch_object();
	$list[] = sprintf('%d%s', $rank->rank, $div);
      }
    }
    return $list;
  }
}
?>