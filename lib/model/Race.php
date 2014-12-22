<?php
/*
 * This file is part of Techscore
 */



/**
 * Race object: a number and a division
 *
 * @author Dayan Paez
 * @version 2012-01-12
 */
class Race extends DBObject {
  protected $regatta;
  protected $division;
  protected $boat;
  protected $round;
  public $number;
  public $scored_day;
  public $scored_by;

  /**
   * When the regatta scoring is "Team", then these are the two teams
   * that participate in this race
   */
  protected $tr_team1;
  protected $tr_team2;
  /**
   * @var int|null (team racing) ignore the race when creating
   * win-loss record for first team
   */
  public $tr_ignore1;
  /**
   * @var int|null (team racing) ignore the race when creating
   * win-loss record for second team
   */
  public $tr_ignore2;

  public function db_name() { return 'race'; }
  public function db_type($field) {
    switch ($field) {
    case 'division': return DBQuery::A_STR;
    case 'boat': return DB::T(DB::BOAT);
    case 'regatta': return DB::T(DB::REGATTA);
    case 'round': return DB::T(DB::ROUND);
    case 'tr_team1':
    case 'tr_team2':
      return DB::T(DB::TEAM);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_cache() { return true; }
  protected function db_order() {
    return array('number'=>true, 'division'=>true);
  }
  public function &__get($name) {
    if ($name == 'division') {
      if ($this->division === null || $this->division instanceof Division)
        return $this->division;
      $div = Division::get($this->division);
      return $div;
    }
    return parent::__get($name);
  }
  /**
   * Normal behavior is to have number and division, as in "3B".  But
   * if the regatta is combined division (or equivalent), then only
   * the race number is necessary.
   *
   * @return String the representation of the race
   */
  public function __toString() {
    if ($this->regatta !== null &&
        ($this->__get('regatta')->scoring != Regatta::SCORING_STANDARD ||
         count($this->__get('regatta')->getDivisions()) == 1))
      return (string)$this->number;
    return $this->number . $this->division;
  }

  /**
   * Parses the string and returns a Race object with the
   * corresponding division and number. Note that the race object
   * obtained is orphan. If no division is found, "A" is chosen by
   * default. This should suffice for combined scoring regattas and
   * the like.
   *
   * @param String $text the text representation of a race (3A, B12)
   * @return Race a race object
   * @throws InvalidArgumentException if unable to parse
   */
  public static function parse($text) {
    $race = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$text));
    $len = strlen($race);
    if ($len == 0)
      throw new InvalidArgumentException("Race missing number.");

    // ASCII: A = 65, D = 68, Z = 90
    $first = ord($race[0]);
    $last = ord($race[$len - 1]);

    $div = Division::A();
    if ($first >= 65 && $first <= 90) {
      if ($last > 68)
        throw new InvalidArgumentException(sprintf("Invalid division (%s).", $race[0]));
      $div = Division::get($race[0]);
      $race = substr($race, 1);
    }
    elseif ($last >= 65 && $last <= 90) {
      if ($last > 68)
        throw new InvalidArgumentException(sprintf("Invalid division (%s).", $race[$len - 1]));
      $div = Division::get($race[$len - 1]);
      $race = substr($race, 0, $len - 1);
    }

    if (!is_numeric($race))
      throw new InvalidArgumentException("Missing number for race.");

    $r = new Race();
    $r->division = $div;
    $r->number = (int)$race;
    return $r;
  }

  /**
   * Compares races by number, then division.
   *
   * @param Race $r1 the first race
   * @param Race $r2 the second race
   * @return negative should $r1 have a lower number, or failing that, a
   * lower division than $r2; positive if the opposite is true; 0 if they
   * are equal
   */
  public static function compareNumber(Race $r1, Race $r2) {
    $diff = $r1->number - $r2->number;
    if ($diff != 0) return $diff;
    return ord((string)$r1->division) - ord((string)$r2->division);
  }
}
