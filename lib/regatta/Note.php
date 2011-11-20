<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package regatta
 */

/**
 * Encapsulates a regatta observation
 *
 * @author Dayan Paez
 * @version   2010-02-22
 */
class Note {

  const FIELDS = "observation.id, observation, observer, race, noted_at";
  const TABLES = "observation";


  public $id;
  public $observation;
  public $observer;

  /**
   * Race $race the race
   */
  public $race;

  /**
   * DateTime $noted_at the time
   */
  public $noted_at;


  /**
   * Returns the observation string
   *
   * @return String $this->observation
   */
  public function __toString() {
    return $this->observation;
  }

  /**
   * Creates a new Note
   *
   */
  public function __construct() {
    $this->noted_at = new DateTime($this->noted_at, new DateTimeZone("America/New_York"));
  }
}
?>