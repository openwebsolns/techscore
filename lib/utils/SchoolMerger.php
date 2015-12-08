<?php
namespace utils;

use \DB;
use \DBQuery;
use \DBBool;
use \DBCond;
use \DBObject;
use \School;

/**
 * Merge one school's record into another.
 *
 * @author Dayan Paez
 * @version 2015-12-08
 */
class SchoolMerger {

  const DEFAULT_SCHOOL_FIELD = 'school';

  public function transfer(School $target, School $destination) {
    $this->migrateSailors($target, $destination);
    $this->migrateTeams($target, $destination);
    $this->migrateAccounts($target, $destination);
    $this->migrateHosts($target, $destination);
    $this->migrateMergeSailorLog($target, $destination);

    // The following relationships are not migrated over (and will
    // safely self-destroy if $target is deleted):
    //
    //   - Burgees
    //   - School seasons
    //   - Team name prefs
    //   - UpdateSchoolRequest
  }

  private function migrateSailors(School $target, School $destination) {
    $this->updateObject($target, $destination, DB::T(DB::TEAM));
  }

  private function migrateTeams(School $target, School $destination) {
    $this->updateObject($target, $destination, DB::T(DB::TEAM));
  }

  private function migrateAccounts(School $target, School $destination) {
    $this->updateObject($target, $destination, DB::T(DB::ACCOUNT_SCHOOL));
  }

  private function migrateHosts(School $target, School $destination) {
    $this->updateObject($target, $destination, DB::T(DB::HOST_SCHOOL));
  }

  private function migrateMergeSailorLog(School $target, School $destination) {
    $this->updateObject($target, $destination, DB::T(DB::MERGE_SAILOR_LOG));
  }

  private function updateObject(
    School $target,
    School $destination,
    DBObject $obj,
    $schoolField = self::DEFAULT_SCHOOL_FIELD
  ) {
    $query = DB::createQuery(DBQuery::UPDATE);
    $query->values(
      array($schoolField),
      array(DBQuery::A_STR),
      array($destination->id),
      $obj->db_name()
    );
    $query->where(
      new DBCond($schoolField, $target->id)
    );
    DB::query($query);
  }

}