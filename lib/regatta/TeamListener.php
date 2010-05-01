<?php
/**
 * This file is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Interface for listening to changes in team propertise
 *
 */
interface TeamListener {
  /**
   * Notifies of a change to a team
   *
   * @param Team $team the team object which changed
   */
  public function changedTeam(Team $team);
}
?>