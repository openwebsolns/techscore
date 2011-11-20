<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @created 2011-05-14
 * @package tscore-data
 */

/**
 * Encapsulates a finishing team's information in a given division,
 * and is technically only applicable for non-personal regattas. This
 * class is reserved for data analysis, and is not part of the
 * standard set of objects which are used during the course of scoring
 * a regatta.
 *
 * Note that since this field is not associated directly with a
 * regatta, nor should it be, the properties are all flat strings, and
 * not nested objects, except for division, which is a division object
 * upon serialization.
 *
 * @see Dt_Team_Division, under DBObject
 */
class TeamDivision {
  public $id;
  public $team;
  public $division;
  public $rank;

  const FIELDS = 'dt_team_division.id, dt_team_division.team, dt_team_division.division, dt_team_division.rank';
  const TABLES = 'dt_team_division';

  public function __construct() {
    if (!($this->division instanceof Division))
      $this->division = Division::get($this->division);
  }
}
?>