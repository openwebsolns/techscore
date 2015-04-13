<?php
/*
 * This file is part of Techscore
 */

/**
 * Penalty for a team in a division
 *
 * @author Dayan Paez
 * @version 2012-01-13
 */
class DivisionPenalty extends DBObject {
  // Constants
  const PFD = "PFD";
  const LOP = "LOP";
  const MRP = "MRP";
  const GDQ = "GDQ";

  public static function getList() {
    return array(DivisionPenalty::PFD=>"PFD: Illegal lifejacket",
                 DivisionPenalty::LOP=>"LOP: Missing pinnie",
                 DivisionPenalty::MRP=>"MRP: Missing RP info",
                 DivisionPenalty::GDQ=>"GDQ: General disqualification");
  }

  protected $team;
  protected $division;
  public $type;
  public $comments;

  public function db_name() { return 'penalty_division'; }
  public function db_type($field) {
    switch ($field) {
    case 'team': return DB::T(DB::TEAM);
    case 'division': return DBQuery::A_STR;
    default:
      return parent::db_type($field);
    }
  }

  public function &__get($name) {
    if ($name == 'division') {
      $div = Division::get($this->division);
      return $div;
    }
    return parent::__get($name);
  }
  public function __set($name, $value) {
    if ($name == 'division')
      $this->division = (string)$value;
    else
      parent::__set($name, $value);
  }

  /**
   * String representation, really useful for debugging purposes
   *
   * @return String string representation
   */
  public function __toString() {
    return sprintf("%s|%s|%s|%s",
                   $this->team,
                   $this->division,
                   $this->type,
                   $this->comments);
  }
}
