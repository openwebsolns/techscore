<?php
namespace utils;

use \School;

/**
 * Encapsulates some data for initializing panes.
 *
 * @author Dayan Paez
 * @version 2015-03-29
 */
class Context {

  /**
   * @var School
   */
  private $school;

  /**
   * Sets the school in this context.
   *
   * @param School $school
   */
  public function setSchool(School $school = null) {
    $this->school = $school;
  }

  /**
   * Gets the school in context.
   *
   * @return School or null.
   */
  public function getSchool() {
    return $this->school;
  }
}