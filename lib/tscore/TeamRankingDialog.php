<?php
use \data\TeamRankingTableCreator;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the overall rankings for a team regatta
 *
 * @author Dayan Paez
 * @version 2013-02-19
 */
class TeamRankingDialog extends AbstractScoresDialog {
  /**
   * Creates a new team ranking dialog
   *
   * @param Account $user the user
   * @param FullRegatta $reg the regatta
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Rankings", $user, $reg);
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Rankings"));
    $maker = new TeamRankingTableCreator($this->REGATTA);
    $p->add($maker->getRankTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $p->add($legend);
    }
  }
}
?>