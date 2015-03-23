<?php
use \data\TeamRegistrationsTable;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Sailors in grid format for team racing
 *
 * @author Dayan Paez
 * @created 2013-03-21
 */
class TeamRegistrationsDialog extends AbstractDialog {

  public function __construct(FullRegatta $reg) {
    parent::__construct("Record of Participation", $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent(new XWarning("Only scored races are displayed."));
    $rounds = $this->REGATTA->getScoredRounds();
    foreach ($rounds as $round) {
      $this->PAGE->addContent($p = new XPort("Round $round"));
      $p->add(new TeamRegistrationsTable($this->REGATTA, $round));
    }
  }
}
?>