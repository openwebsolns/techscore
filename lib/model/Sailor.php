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
  public function db_where() {
    return new DBCond('role', 'student');
  }
  public function getUrlSeeds() {
    $name = $this->getName();
    $seeds = array($name);
    if ($this->year > 0) {
      $seeds[] = $name . " " . $this->year;
    }
    $seeds[] = $name . " " . $this->school->nick_name;
    return $seeds;
  }
}
