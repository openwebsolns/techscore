<?php
/**
 * Creates objects for use in unit tests.
 *
 * @author Dayan Paez
 * @version 2015-12-02
 */
class DataCreator {

  private static $schoolIndex = 0;

  public function createSchool() {
    self::$schoolIndex++;
    $school = new School();
    $school->id = 'ID' . self::$schoolIndex;
    $school->name = "School " . self::$schoolIndex;
    return $school;
  }

}