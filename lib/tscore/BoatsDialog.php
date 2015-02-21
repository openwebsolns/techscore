<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Ranks the boats in order
 *
 * @author Dayan Paez
 * @created 2013-11-05
 */
class BoatsDialog extends AbstractScoresDialog {

  private $rotation;

  /**
   * Create a new boats ranking dialog for given regatta
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Boats", $reg);
    $this->rotation = $this->REGATTA->getRotation();
    if (!$this->rotation->isAssigned())
      throw new InvalidArgumentException("Boats dialog only available when using rotations.");
  }

  public function fillHTML(Array $args) {
    $boats = array();
    $sails = array();
    $total = array(); // sail num => total
    $earned = array();
    $races = array();
    foreach ($this->rotation->getRaces() as $race) {
      foreach ($this->REGATTA->getFinishes($race) as $finish) {
        $sail = $this->rotation->getSail($race, $finish->team);
        $id = sprintf('%s %s', $race->boat, $sail);
        if (!isset($sails[$id])) {
          $boats[$id] = $race->boat;
          $sails[$id] = $sail;
          $total[$id] = 0;
          $races[$id] = 0;
          $earned[$id] = 0;
        }
        $total[$id] += $finish->score;
        $races[$id] += 1;
        $earned[$id] += ($finish->earned !== null) ? $finish->earned : $finish->score;
      }
    }

    if (count($sails) == 0) {
      $this->PAGE->addContent(new XWarning("There are no boats to rank."));
      return;
    }
    $this->PAGE->addContent(new XWarning("Note: only boats that have sailed in scored races are shown."));
    asort($total, SORT_NUMERIC);

    $this->PAGE->addContent($tab = new XQuickTable(array('class'=>'boatrank result'),
                                                   array("Rank", "Boat", "Sail", "Race count", "Total score", "Minus penalties")));

    $overall = 0;
    $rank = 0;
    $prevTotal = null;
    foreach ($total as $id => $amt) {
      $overall++;
      if ($prevTotal === null || $amt != $prevTotal)
        $rank = $overall;
      $prevTotal = $amt;
      $tab->addRow(array($rank,
                         $boats[$id],
                         new SailTD($sails[$id]),
                         new XTD(array('class'=>'right'), $races[$id]),
                         new XTD(array('class'=>'total'), $amt),
                         new XTD(array('class'=>'totalcell'), $earned[$id])),
                   array('class'=>'row' . ($overall % 2)));
    }
  }
}
?>