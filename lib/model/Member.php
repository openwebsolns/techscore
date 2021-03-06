<?php
use \model\AbstractObject;
use \model\PublicData;
use \model\Publishable;

/**
 * Represents either a student as a member of a school.
 *
 * While this abstraction used to be more relevant when coaches were
 * also tracked, it has been kept around for potential future
 * development.
 *
 * @author Dayan Paez
 * @version 2012-02-07
 */
class Member extends AbstractObject implements Publishable {

  const STATUS_REGISTERED = 'registered';
  const STATUS_UNREGISTERED = 'unregistered';
  const STATUS_REQUESTED = 'requested';

  protected $school;
  protected $student_profile;
  public $register_status;
  public $last_name;
  public $first_name;
  public $year;
  public $role;
  public $external_id;
  public $gender;
  public $url;
  public $active;
  public $regatta_added;
  protected $sync_log;

  const MALE = 'M';
  const FEMALE = 'F';

  const STUDENT = 'student';

  public function db_type($field) {
    switch ($field) {
    case 'school': return DB::T(DB::SCHOOL);
    case 'sync_log': return DB::T(DB::SYNC_LOG);
    case 'student_profile': return DB::T(DB::STUDENT_PROFILE);
    default:
      return parent::db_type($field);
    }
  }
  protected function db_order() { return array('last_name'=>true, 'first_name'=>true); }
  public function db_name() { return 'sailor'; }

  public static function getGenders() {
    return array(self::MALE => "Male", self::FEMALE => "Female");
  }

  public static function getGender($gender) {
    return self::getGenders()[$gender];
  }

  public function isRegistered() {
    return $this->register_status != self::STATUS_UNREGISTERED;
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
    if ($this->url === null || !$this->isRegistered())
      return null;
    return sprintf('/sailors/%s/', $this->url);
  }

  public function getPublicData() {
    return (new PublicData(PublicData::V1))
      ->with('external_id', $this->external_id)
      ->with('first_name', $this->first_name)
      ->with('gender', $this->gender)
      ->with('id', $this->id)
      ->with('last_name', $this->last_name)
      ->with('name', $this->getName())
      ->with('school', $this->__get('school'))
      ->with('year', $this->year)
      ;
  }

  /**
   * Return sailor (HTML) representation, as link to profile, if supported.
   *
   * @param boolean $public true to include URL.
   * @return Xmlable or text.
   */
  public function toView($public = false) {
    $result = (string)$this;

    $url = $this->getURL();
    if ($public !== false && $url !== null && DB::g(STN::SAILOR_PROFILES)) {
      $result = new XA($url, $result);
    }

    return $result;
  }

  /**
   * Fetch list of regattas member has participated in
   *
   * @param Season $season non-null to filter by given season only
   * @return FullRegatta
   */
  public function getRegattas(Season $season = null) {
    $cond = new DBBool(
      array(
        new DBCondIn(
          'id',
          DB::prepGetAll(
            DB::T(DB::RACE),
            new DBCondIn(
              'id',
              DB::prepGetAll(
                DB::T(DB::RP_ENTRY),
                new DBCondIn(
                  'attendee',
                  DB::prepGetAll(
                    DB::T(DB::ATTENDEE),
                    new DBCond('sailor', $this),
                    array('id')
                  )
                ),
                array('race')
              )
            ),
            array('regatta')
          )
        ),
      )
    );
    if ($season !== null) {
      $cond->add(new DBCond('dt_season', $season));
    }
    return DB::getAll(DB::T(DB::PUBLIC_REGATTA), $cond);
  }

  /**
   * Fetch list of seasons the sailor was "active" in.
   *
   * @return Array:Season
   */
  public function getSeasonsActive() {
    return DB::getAll(
      DB::T(DB::SEASON),
      new DBCondIn(
        'id',
        DB::prepGetAll(
          DB::T(DB::SAILOR_SEASON),
          new DBCond('sailor', $this),
          array('season')
        )
      )
    );
  }

  public function removeFromSeason(Season $season) {
    DB::removeAll(
      DB::T(DB::SAILOR_SEASON),
      new DBBool(
        array(
          new DBCond('sailor', $this),
          new DBCond('season', $season)
        )
      )
    );
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

  public static function getRegisterStatuses() {
    return array(
      self::STATUS_REGISTERED => "Registered",
      self::STATUS_UNREGISTERED => "Unregistered",
      self::STATUS_REQUESTED => "Requested",
    );
  }
}
