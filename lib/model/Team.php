<?php
/*
 * This file is part of Techscore
 */



/**
 * Encapsulates a team, or a linking table between schools and regattas
 *
 * @author Dayan Paez
 * @version 2012-01-10
 */
class Team extends DBObject {
  public $name;
  protected $school;
  protected $regatta; // change to protected when using DBM

  public $rank_group;
  public $lock_rank;

  public $dt_rank;
  public $dt_explanation;
  public $dt_score;
  public $dt_wins;
  public $dt_losses;
  public $dt_ties;
  public $dt_complete_rp;

  public function db_name() { return 'team'; }
  protected function db_order() { return array('school'=>true, 'id'=>true); }
  protected function db_cache() { return true; }
  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::T(DB::SCHOOL);
    case 'regatta': return DB::T(DB::REGATTA);
    default:
      return parent::db_type($field);
    }
  }
  public function &getQualifiedName() {
    return $this->name;
  }
  public function __toString() {
    return $this->__get('school')->nick_name . ' ' . $this->getQualifiedName();
  }

  /**
   * Gets the team's "team racing win percentage"
   */
  public function getWinPercentage() {
    $total = $this->dt_wins + $this->dt_losses + $this->dt_ties;
    if ($total == 0)
      return 0;
    return $this->dt_wins / $total;
  }

  /**
   * Display this team's "team racing record": wins-losses
   */
  public function getRecord() {
    $txt = sprintf('%d-%d', $this->dt_wins, $this->dt_losses);
    if ($this->dt_ties > 0)
      $txt .= sprintf('-%d', $this->dt_ties);
    return $txt;
  }

  /**
   * Returns this team's rank within the given division, if one exists
   *
   * @param String $division the possible division
   * @return Dt_Team_Division|null the rank
   */
  public function getRank(Division $division) {
    $r = DB::getAll(DB::T(DB::DT_TEAM_DIVISION), new DBBool(array(new DBCond('team', $this),
                                                            new DBCond('division', $division))));
    $b;
    if (count($r) == 0) $b = null;
    else $b = $r[0];
    unset($r);
    return $b;
  }

  // ------------------------------------------------------------
  // RP
  // ------------------------------------------------------------

  /**
   * Gets the Dt_RP for this team in the given division and role
   *
   * @param String $div the division, or null for all divisions
   * @param String $role 'skipper', or 'crew'
   * @return Array:Dt_RP the rp for that team
   */
  public function getRpData(Division $div = null, $role = Dt_Rp::SKIPPER) {
    if ($div !== null) {
      $rank = $this->getRank($div);
      if ($rank === null)
        return array();
      return $rank->getRP($role);
    }
    $q = DB::prepGetAll(DB::T(DB::DT_TEAM_DIVISION), new DBCond('team', $this->id), array('id'));
    return DB::getAll(DB::T(DB::DT_RP), new DBBool(array(new DBCond('boat_role', $role),
                                                   new DBCondIn('team_division', $q))));
  }

  /**
   * Removes all Dt_RP entries for this team from the database
   *
   * @param Division $div the division whose RP info to reset
   */
  public function resetRpData(Division $div) {
    $q = DB::prepGetAll(DB::T(DB::DT_TEAM_DIVISION),
                        new DBBool(array(new DBCond('team', $this->id), new DBCond('division', $div))),
                        array('id'));
    foreach (DB::getAll(DB::T(DB::DT_RP), new DBCondIn('team_division', $q)) as $rp)
      DB::remove($rp);
  }

  // Comparators

  /**
   * Sorts the teams based on school's full name
   *
   * @param Team $t1 the first team
   * @param Team $t2 the second team
   * @return int < 0 if $t1 comes before $t2...
   */
  public static function compare(Team $t1, Team $t2) {
    $diff = strcmp($t1->__get('school')->name, $t2->__get('school')->name);
    if ($diff == 0)
      return $t1->id - $t2->id;
    return $diff;
  }
}
