<?php
use \data\FullScoresTableCreator;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the full scores table for a given regatta. When there's
 * only one division, omits the division column.
 *
 */
class ScoresFullDialog extends AbstractScoresDialog {

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Race results", $reg);
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Team results"));

    $maker = new FullScoresTableCreator($this->REGATTA);
    $p->add($maker->getScoreTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $this->PAGE->addContent($p = new XPort("Legend"));
      $p->add($legend);
    }
  }

}
?>
