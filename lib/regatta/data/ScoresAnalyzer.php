<?php
/*
 * This file is part of Techscore
 *
 * @author Dayan Paez
 * @version 2011-05-05
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
  public static function getHighFinishers(Array $reg_ids, Division $div, $place, $role = RP2::SKIPPER) {
    $r = new DBBool(array(new DBCondIn('team',
				       DB::prepGetAll(DB::$TEAM_DIVISION,
						      new DBBool(array(new DBCond('rank', $place, DBCond::LE),
								       new DBCond('division', (string)$div))),
						      array('team'))),
			  new DBCondIn('race',
				       DB::prepGetAll(DB::$RACE,
						      new DBBool(array(new DBCondIn('regatta', $reg_ids),
								       new DBCond('division', (string)$div))),
						      array('id'))),
			  new DBCond('boat_role', $role)));

    return DB::getAll(DB::$SAILOR,
		      new DBBool(array(new DBCond('icsa_id', null, DBCond::NE),
				       new DBCondIn('id',
						    DB::prepGetAll(DB::$RP, $r, array('sailor'))))));
  }

  /**
   * Returns a list of high finishes team IDs, in objects which have
   * two properties: 'regatta' (a regatta ID) and 'team' (a regatta
   * team).
   *
   * @return Array:TeamDivision
   */
  public static function getHighFinishingTeams(Regatta $reg, Division $div, $place) {
    return DB::getAll(DB::$TEAM_DIVISION,
		      new DBBool(array(new DBCondIn('team', DB::prepGetAll(DB::$TEAM, new DBCond('regatta', $reg->id()), array('id'))),
				       new DBCond('division', (string)$div),
				       new DBCond('rank', $place, DBCond::LE))));
  }

  /**
   * Gets the TeamDivision (that is the rank) for the given RP
   *
   * @return TeamDivision|null the team division object, if any
   */
  public static function getTeamDivision(Team $team, Division $div) {
    $res = DB::getAll(DB::$TEAM_DIVISION, new DBBool(array(new DBCond('team', $team), new DBCond('division', (string)$div))));
    $r = (count($res) > 0) ? $res[0] : null;
    unset($res);
    return $r;
  }

  /**
   * Returns the place finishes obtained by the given sailor in the
   * given regatta as a string "#D", such as '3A' for "third place in
   * A division"
   *
   * @param Regatta $reg the regatta to consider
   * @param Sailor $sailor the sailor to consider
   * @return Array:TeamDivision the place finishes
   */
  public static function getPlaces(Regatta $reg, Sailor $sailor, $role = RP2::SKIPPER) {
    $list = array();
    $rpm = $reg->getRpManager();
    foreach ($rpm->getParticipation($sailor, $role) as $rp) {
      $td = self::getTeamDivision($rp->team, $rp->division);
      if ($td !== null)
	$list[] = $td;
    }
    return $list;
  }
}
?>