<?php
/*
 * This file is part of Techscore
 */



/**
 * Active schools are useful when creating a new regatta (or one for
 * the current season), so that users are only choosing from active
 * schools for regatta participation.
 *
 * @author Dayan Paez
 * @version 2012-04-01
 */
class Active_School extends School {
  public function db_where() { return new DBCond('inactive', null); }
}
