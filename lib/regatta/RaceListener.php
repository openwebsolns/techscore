<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Interface for listening to changes in race properties
 *
 */
interface RaceListener {
  /**
   * Notifies of a change to a race
   *
   * @param Race $race the race object which changed
   */
  public function changedRace(Race $race);
}
?>