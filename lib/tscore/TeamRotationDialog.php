<?php
use \data\TeamRotationTable;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays all the races in numberical order, and the finishes
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
class TeamRotationDialog extends AbstractDialog {
  /**
   * Create a new dialog
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Rotations", $reg);
  }

  /**
   * Creates the tabular display
   *
   */
  public function fillHTML(Array $args) {
    $this->PAGE->body->set('class', 'tr-rotation-page');
    $this->PAGE->addContent(new XNonprintWarning(
                              array(new XStrong("Hint:"), " to print the sail colors, enable \"Print background colors\" in your printer dialog.")));

    $covered = array();
    foreach ($this->REGATTA->getRounds() as $round) {
      if (!isset($covered[$round->id])) {
        $covered[$round->id] = $round;
        $label = (string)$round;
        if ($round->round_group !== null) {
          foreach ($round->round_group->getRounds() as $i => $other) {
            if ($i > 0) {
              $label .= ", " . $other;
              $covered[$other->id] = $other;
            }
          }
        }

        $this->PAGE->addContent($p = new XPort($label));
        $p->add(new TeamRotationTable($this->REGATTA, $round));
      }
    }
  }
}
?>