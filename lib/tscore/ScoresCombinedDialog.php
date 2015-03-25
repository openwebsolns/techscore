<?php
use \data\CombinedScoresTableCreator;

/**
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the scores table à la Division dialog, but tailored for
 * combined division score (uses *SpecialCombinedRanker).
 *
 * @author Dayan Paez
 * @version 2010-02-01
 */
class ScoresCombinedDialog extends AbstractScoresDialog {

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param FullRegatta $reg the regatta
   * @throws InvalidArgumentException if not combined division
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Race results", $reg);
    if ($reg->scoring != Regatta::SCORING_COMBINED)
      throw new InvalidArgumentException("Dialog only available to combined division scoring.");
  }

  /**
   * Create and display the score table
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Division results: combined"));
    if (!$this->REGATTA->hasFinishes()) {
      $p->add(new XWarning("There are no finishes for this regatta."));
      return;
    }

    $maker = new CombinedScoresTableCreator($this->REGATTA);
    $p->add($maker->getScoreTable());
    $legend = $maker->getLegendTable();
    if ($legend !== null) {
      $p->add(new XHeading("Tiebreaker legend"));
      $p->add($legend);
    }
  }
}
