<?php
/*
 * This file is part of Techscore
 */



/**
 * A coach (a sailor with role=coach)
 *
 * @author Dayan Paez
 * @version 2012-01-08
 */
class Coach extends Member {
  public function db_where() { return new DBCond('role', 'coach'); }
  public function __construct() {
    $this->role = Member::COACH;
  }
}
