<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

/**
 * Encompasses an RP form block: a thin wrapper around two lists of
 * skippers and crews (one for each division) and their respective
 * RP objects, a representative, and a team name.
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */ 
class RpBlock {
  public $representative;
  public $team;

  public $skipper_A = array();
  public $skipper_B = array();
  public $skipper_C = array();
  public $skipper_D = array();

  public $crew_A = array();
  public $crew_B = array();
  public $crew_C = array();
  public $crew_D = array();
}

?>