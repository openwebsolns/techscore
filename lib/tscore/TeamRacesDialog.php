<?php
use \data\TeamRacesTable;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays all the races in numerical order, and the finishes
 *
 * The columns are:
 *
 *   - race number
 *   - first team to finish
 *   - second team to finish
 *     ...
 *
 * @author Dayan Paez
 * @version 2013-02-18
 */
class TeamRacesDialog extends AbstractScoresDialog {
  /**
   * Create a new dialog
   *
   * @param Account $user the user
   * @param FullRegatta $reg the regatta
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("All races", $user, $reg);
  }

  /**
   * Creates the tabular display
   *
   */
  public function fillHTML(Array $args) {
    if (count($this->REGATTA->getRaces()) == 0) {
      $this->PAGE->addContent(new XWarning("There are no races for this regatta."));
      return;
    }
    $this->PAGE->addContent(new TeamRacesTable($this->REGATTA));
  }
}
?>