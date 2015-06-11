<?php
namespace tscore\utils;

use \InvalidArgumentException;

use \DB;
use \Division;
use \FullRegatta;
use \RpManager;

/**
 * Help parse some of the display settings for RpPanes.
 *
 * @author Dayan Paez
 * @version 2015-05-12
 */
class RpPaneParams {

  public $chosenTeam;
  public $participatingSailorsById;

  public $participatingSchoolsById;
  public $requestedSchoolsById;
  public $schoolsById;

  /**
   * Map sorted by division, then role, pointing to a list of RP objects.
   */
  public $rps;

  /**
   * @var Array map of school name to array of sailor ID to sailor name.
   */
  public $sailorOptions;
  public $attendeeOptions;

  public function __set($name, $value) {
    throw new InvalidArgumentException("No such property $name.");
  }
  public function __get($name) {
    throw new InvalidArgumentException("No such property $name.");
  }
}