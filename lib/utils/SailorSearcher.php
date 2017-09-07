<?php
namespace utils;

use \Account;
use \DB;
use \Sailor;
use \Season;
use \School;
use \Member;

use \DBCond;
use \DBCondIn;
use \DBBool;

/**
 * Like a DAO, for sailors.
 *
 * @author Dayan Paez
 * @version 2015-11-20
 * @see RegattaSearcher
 */
class SailorSearcher {

  const FIELD_QUERY = 'q';
  const FIELD_GENDER = 'gender';
  const FIELD_SCHOOL = 'school';
  const FIELD_YEAR = 'year';
  const FIELD_MEMBER_STATUS = 'status';
  const FIELD_ELIGIBILITY_SEASON = 'season';

  /**
   * @var String Search term?
   */
  private $query;
  /**
   * @var const gender to filter by (null possible).
   */
  private $gender;
  /**
   * @var Array:School the school to limit list to.
   */
  private $schools;
  /**
   * @var Array:String the graduation year.
   */
  private $years;
  /**
   * @var const whether active or inactive (null to ignore).
   */
  private $memberStatus;
  /**
   * @var Account limit sailors to whose in this account's jurisdiction.
   */
  private $account;
  /**
   * @var Season limit to those eligible to sail given season.
   */
  private $season;

  public function __construct() {
    $this->account = null;
    $this->query = null;
    $this->gender = null;
    $this->schools = array();
    $this->years = array();
    $this->memberStatus = null;
    $this->season = null;
  }

  /**
   * Performs the search based on settings.
   *
   * @return Array:Sailor
   */
  public function doSearch() {
    $condList = array();

    if ($this->query !== null) {
      $qry = '%' . trim($this->query) . '%';
      $condList[] = new DBBool(
        array(
          new DBCond('first_name', $qry, DBCond::LIKE),
          new DBCond('last_name', $qry, DBCond::LIKE),
          new DBCond('concat(first_name, " ", last_name)', $qry, DBCond::LIKE),
        ),
        DBBool::mOR
      );
    }

    if ($this->account !== null) {
      $cond = new DBCondIn(
        'school',
        DB::prepGetAll(
          DB::T(DB::ACTIVE_SCHOOL),
          $this->account->getSchoolsDBCond(),
          array('id')
        )
      );
      if ($cond !== null) {
        $condList[] = $cond;
      }
    }

    if ($this->gender !== null) {
      $condList[] = new DBCond('gender', $this->gender);
    }

    if (count($this->years) > 0) {
      $condList[] = new DBCondIn('year', $this->years);
    }

    if (count($this->schools) > 0) {
      $condList[] = new DBCondIn('school', $this->getSchoolsIds());
    }

    if ($this->memberStatus !== null) {
      $condList[] = new DBCond('register_status', $this->memberStatus);
    }

    if ($this->season !== null) {
      $condList[] = new DBCondIn(
        'student_profile',
        DB::prepGetAll(
          DB::T(DB::ELIGIBILITY),
          new DBCond('season', $this->season),
          array('student_profile')
        )
      );
    }

    $cond = null;
    if (count($condList) > 0) {
      $cond = new DBBool($condList);
    }
    return DB::getAll(DB::T(DB::SAILOR), $cond);
  }

  public function setQuery($qry = null) {
    $this->query = $qry;
  }

  public function getQuery() {
    return $this->query;
  }

  public function setGender($gender = null) {
    $this->gender = $gender;
  }

  public function getGender() {
    return $this->gender;
  }

  public function addSchool(School $school) {
    if ($school->id !== null) {
      $this->schools[] = $school;
    }
  }

  public function setSchools(Array $schools) {
    $this->schools = array();
    foreach ($schools as $school) {
      $this->addSchool($school);
    }
  }

  public function getSchools() {
    return $this->schools;
  }

  public function getSchoolsIds() {
    $list = array();
    foreach ($this->schools as $school) {
      $list[] = $school->id;
    }
    return $list;
  }

  public function addYear($year) {
    if ($year !== null) {
      $this->years[] = $year;
    }
  }

  public function setYears(Array $years) {
    $this->years = array();
    foreach ($years as $year) {
      $this->addYear($year);
    }
  }

  public function getYears() {
    return $this->years;
  }

  public function setMemberStatus($status = null) {
    $this->memberStatus = $status;
  }

  public function getMemberStatus() {
    return $this->memberStatus;
  }

  public function setAccount(Account $account = null) {
    $this->account = $account;
  }

  public function getAccount() {
    return $this->account;
  }

  public function setEligibilitySeason(Season $season = null) {
    $this->season = $season;
  }

  public function getEligibilitySeason() {
    return $this->season;
  }

  /**
   * Create a suitable map consistent with fromArgs.
   *
   * @return Array associative map.
   */
  public function toArgs() {
    $season = ($this->season === null) ? null : $this->season->id;
    return array(
      self::FIELD_QUERY => $this->getQuery(),
      self::FIELD_MEMBER_STATUS => $this->getMemberStatus(),
      self::FIELD_GENDER => $this->getGender(),
      self::FIELD_SCHOOL => $this->getSchoolsIds(),
      self::FIELD_YEAR => $this->getYears(),
      self::FIELD_ELIGIBILITY_SEASON => $season,
    );
  }

  /**
   * Creates a new SailorSearcher from given GET/POST arguments.
   *
   * @param Account $account owner to determine jurisdiction.
   * @param Array $args the arguments.
   * @return SailorSearcher a new searcher.
   */
  public static function fromArgs(Account $account, Array $args) {
    $status = DB::$V->incKey(
      $args,
      self::FIELD_MEMBER_STATUS,
      Sailor::getRegisterStatuses()
    );
    $query = DB::$V->incString($args, self::FIELD_QUERY, 1, 256);
    $gender = DB::$V->incKey(
      $args,
      self::FIELD_GENDER,
      Member::getGenders()
    );

    $years = array();
    if (array_key_exists(self::FIELD_YEAR, $args)) {
      if (is_array($args[self::FIELD_YEAR])) {
        $yearsArgs = DB::$V->incList($args, self::FIELD_YEAR);
      }
      else {
        $yearsArgs = array(DB::$V->incInt($args, self::FIELD_YEAR));
      }
      foreach ($yearsArgs as $i => $year) {
        $years[] = DB::$V->incInt($yearsArgs, $i, 1970, 3001, null);
      }
    }

    // Null entries are ignored anyways.
    $schools = array();
    if (array_key_exists(self::FIELD_SCHOOL, $args)) {
      if (is_array($args[self::FIELD_SCHOOL])) {
        $schoolArgs = DB::$V->incList($args, self::FIELD_SCHOOL);
      }
      else {
        $schoolArgs = array(DB::$V->incString($args, self::FIELD_SCHOOL));
      }
      foreach ($schoolArgs as $i => $id) {
        $school = DB::$V->incSchool($schoolArgs, $i);
        if ($school !== null && $account->hasSchool($school)) {
          $schools[] = $school;
        }
      }
    }

    $season = DB::$V->incID($args, self::FIELD_ELIGIBILITY_SEASON, DB::T(DB::SEASON));

    $searcher = new SailorSearcher();
    $searcher->setAccount($account);
    $searcher->setQuery($query);
    $searcher->setMemberStatus($status);
    $searcher->setGender($gender);
    $searcher->setYears($years);
    $searcher->setSchools($schools);
    $searcher->setEligibilitySeason($season);

    return $searcher;
  }

}