<?php
namespace model;

use \DBCond;

/**
 * View of StudentProfile with statuses considered active.
 *
 * @author Dayan Paez
 * @version 2017-10-21
 */
class ActiveStudentProfile extends StudentProfile {
  public function db_where() {
    return new DBCond('status', self::STATUS_INACTIVE, DBCond::NE);
  }
}