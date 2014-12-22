<?php
/*
 * This file is part of Techscore
 */



/**
 * Encapsulates a conference
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Conference extends DBObject {
  public $name;
  public $url;
  protected $mail_lists;
  public function __toString() {
    return $this->id;
  }
  protected function db_cache() { return true; }

  public function db_type($field) {
    if ($field == 'mail_lists')
      return array();
    return parent::db_type($field);
  }

  /**
   * Returns a list of users from this conference
   *
   * @param String|null $status a possible Account status
   * @param boolean $active_schools true (default) to limit
   * @return Array:Account list of users
   */
  public function getUsers($status = null, $active_schools = true) {
    require_once('regatta/Account.php');
    $obj = ($active_schools) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    $cond = new DBCondIn(
      'id',
      DB::prepGetAll(
        DB::T(DB::ACCOUNT_SCHOOL),
        new DBCondIn(
          'school',
          DB::prepGetAll(
            $obj,
            new DBCond('conference', $this),
            array('id')
          )
        ),
        array('account')
      )
    );
    if ($status !== null) {
      $statuses = Account::getStatuses();
      if (!isset($statuses[$status]))
        throw new InvalidArgumentException("Invalid status provided: $status.");
      $cond = new DBBool(array($cond, new DBCond('status', $status)));
    }
    return DB::getAll(DB::T(DB::ACCOUNT), $cond);
  }

  /**
   * Returns a list of school objects which are in the specified
   * conference.
   *
   * @param boolean $active true (default) to return only active
   * @return a list of schools in the conference
   */
  public function getSchools($active = true) {
    $obj = ($active) ? DB::T(DB::ACTIVE_SCHOOL) : DB::T(DB::SCHOOL);
    return DB::getAll($obj, new DBCond('conference', $this));
  }

  /**
   * Creates the full URL to this conference's public summary page
   *
   * The URL is built from /<STN::CONFERENCE_URL>/<url>/, where <url>
   * is the lowercase version of the ID
   *
   * @return String the full URL
   */
  public function createUrl() {
    if ($this->id === null)
      throw new InvalidArgumentException("No ID exists for this conference.");
    return sprintf('/%s/%s/', DB::g(STN::CONFERENCE_URL), strtolower($this->id));
  }
}
