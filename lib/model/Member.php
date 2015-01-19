<?php
/*
 * This file is part of Techscore
 */

/**
 * Represents either a student or a coach as a member of a school
 *
 * @author Dayan Paez
 * @version 2012-02-07
 */
class Member extends DBObject {
  protected $school;
  public $last_name;
  public $first_name;
  public $year;
  public $role;
  public $icsa_id;
  public $gender;
  public $url;
  public $active;
  public $regatta_added;
  protected $sync_log;

  const MALE = 'M';
  const FEMALE = 'F';

  const COACH = 'coach';
  const STUDENT = 'student';

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::T(DB::SCHOOL);
    case 'sync_log': return DB::T(DB::SYNC_LOG);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }
  public function db_name() { return 'sailor'; }

  public static function getGenders() {
    return array(self::MALE => "Male", self::FEMALE => "Female");
  }

  public function isRegistered() {
    return $this->icsa_id !== null;
  }

  public function getName() {
    $name = "";
    if ($this->first_name !== null)
      $name = $this->first_name;
    if ($this->last_name !== null) {
      if ($name != "")
        $name .= " ";
      $name .= $this->last_name;
    }
    if ($name == "")
      return "[No Name]";
    return $name;
  }

  public function __toString() {
    $year = "";
    if ($this->role == 'student')
      $year = " '" . (($this->year > 0) ? substr($this->year, -2) : "??");
    $name = $this->getName() . $year;
    if (!$this->isRegistered())
      $name .= " *";
    return $name;
  }

  /**
   * Returns the public URL root for this member
   *
   * This is /sailors/<url>/, where <url> is the "url" property of the
   * sailor.
   *
   * @return String the URL, or null
   */
  public function getURL() {
    if ($this->url === null)
      return null;
    return sprintf('/sailors/%s/', $this->url);
  }

  /**
   * Fetch list of regattas member has participated in
   *
   */
  public function getRegattas($inc_private = false) {
    $cond = new DBCondIn('id', DB::prepGetAll(DB::T(DB::RP_ENTRY), new DBCond('sailor', $this), array('race')));
    return DB::getAll(($inc_private !== false) ? DB::T(DB::REGATTA) : DB::T(DB::PUBLIC_REGATTA),
                      new DBCondIn('id', DB::prepGetAll(DB::T(DB::RACE), $cond, array('regatta'))));
  }

  /**
   * Compares two members based on last name, then first name
   *
   */
  public static function compare(Member $m1, Member $m2) {
    if ($m1->last_name != $m2->last_name)
      return strcmp($m1->last_name, $m2->last_name);
    return strcmp($m1->first_name, $m2->first_name);
  }
}
