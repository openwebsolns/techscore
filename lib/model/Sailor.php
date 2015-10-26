<?php

/**
 * Encapsulates a sailor, whether registered or not.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class Sailor extends Member {
  public function __construct() {
    $this->role = Member::STUDENT;
  }
  public function db_where() { return new DBCond('role', 'student'); }
}
