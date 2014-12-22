<?php
/*
 * This file is part of Techscore
 */



class Dt_Rp extends DBObject {
  const SKIPPER = 'skipper';
  const CREW = 'crew';

  protected $team_division;
  protected $race_nums;
  protected $sailor;
  public $boat_role;
  public $rank;
  public $explanation;

  public function db_type($field) {
    if ($field == 'sailor') return DB::T(DB::MEMBER);
    if ($field == 'race_nums') return array();
    if ($field == 'team_division') return DB::T(DB::DT_TEAM_DIVISION);
    return parent::db_type($field);
  }
  protected function db_order() { return array('race_nums'=>true); }
}
