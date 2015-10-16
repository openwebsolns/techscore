<?php
use \ui\ProgressDiv;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Parent class for all scores dialog. Sets up the menu with the
 * appropriate links.
 *
 * @author Dayan Paez
 * @version 2010-09-06
 */
abstract class AbstractScoresDialog extends AbstractDialog {
  public function __construct($title, Account $user, FullRegatta $reg) {
    parent::__construct($title, $user, $reg);

  }
  protected function setupPage() {
    parent::setupPage();

    // Add some menu
    $this->PAGE->addContent($prog = new ProgressDiv());
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      $prog->addCompleted("All grids", sprintf('/view/%d/scores',   $this->REGATTA->id));
      $prog->addCompleted("All races", sprintf('/view/%d/races', $this->REGATTA->id));
      $prog->addCompleted("Rankings", sprintf('/view/%d/ranking',  $this->REGATTA->id));
    }
    else {
      $prog->addCompleted("All scores", sprintf('/view/%d/scores', $this->REGATTA->id));
      $prog->addCompleted("Summary", sprintf('/view/%d/div-scores', $this->REGATTA->id));
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
        $prog->addCompleted("All Divisions", sprintf('/view/%d/combined', $this->REGATTA->id));
      }
      else {
        $prog->addCompleted("Rank history", sprintf('/view/%d/chart', $this->REGATTA->id));
      }
      foreach ($this->REGATTA->getDivisions() as $div) {
        $prog->addCompleted("$div Division", sprintf('/view/%d/scores/%s', $this->REGATTA->id, $div));
      }
      $rot = $this->REGATTA->getRotationManager();
      if ($rot->isAssigned()) {
        $prog->addCompleted("Boats rank", sprintf('/view/%d/boats', $this->REGATTA->id));
      }
    }

    // Add meta tag
    $this->PAGE->head->add(new XMeta('timestamp', date('Y-m-d H:i:s')));
  }
}
?>