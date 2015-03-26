<?php
use \data\FleetScoresTableCreator;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the divisional score table, which summarizes the scores
 * for each team by displaying each division's total.
 *
 * @author Dayan Paez
 * @version 2010-09-06
 */
class ScoresDivisionalDialog extends AbstractScoresDialog {

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Account $user the user
   * @param FullRegatta $reg the regatta
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Race results in divisions", $user, $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Team results"));
    if (!$this->REGATTA->hasFinishes()) {
      $p->add(new XWarning("There are no finishes for this regatta."));
      return;
    }

    $maker = new FleetScoresTableCreator($this->REGATTA);
    $p->add($maker->getScoreTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $this->PAGE->addContent($p = new XPort("Legend"));
      $p->add($legend);
    }
  }
}
?>
