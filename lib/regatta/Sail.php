<?php
/*
 * This class is part of TechScore
 *
 * @version 2.0
 * @author Dayan Paez
 * @package regatta
 */

require_once('conf.php');

/**
 * Encapsulates a sail
 *
 */
class Sail {

  // Parameters
  public $sail;
  public $race;
  public $team;

  /**
   * Returns just the sail number
   *
   * @return String the sail number
   */
  public function __toString() {
    return (string)$this->sail;
  }
}

?>