<?php
namespace utils;

use \Account;
use \DB;
use \Sailor;
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

  const STATUS_REGISTERED = 'registered';
  const STATUS_UNREGISTERED = 'unregistered';

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

  public function __construct() {
    $this->query = null;
    $this->gender = null;
    $this->schools = array();
    $this->years = array();
    $this->memberStatus = null;
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
      $condList[] = new DBCondIn('school', $this->schools);
    }

    if ($this->memberStatus == self::STATUS_REGISTERED) {
      $condList[] = new DBCond('icsa_id', null, DBCond::NE);
    }
    elseif ($this->memberStatus == self::STATUS_UNREGISTERED) {
      $condList[] = new DBCond('icsa_id', null);
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

  public function setGender($gender = null) {
    $this->gender = $gender;
  }

  public function addSchool(School $school) {
    if ($school->id !== null) {
      $this->schools[] = $school->id;
    }
  }

  public function setSchools(Array $schools) {
    $this->schools = array();
    foreach ($schools as $school) {
      $this->addSchool($school);
    }
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

  public function setMemberStatus($status = null) {
    $this->memberStatus = $status;
  }

  public function setAccount(Account $account = null) {
    $this->account = $account;
  }
}