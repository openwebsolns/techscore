<?php
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
    $this->PAGE->addMenu(new XDiv(array('class'=>'menu'), array($ul = new XUl())));
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      $ul->add(new XLi(new XA(sprintf('/view/%d/scores',   $this->REGATTA->id), "All grids")));
      $ul->add(new XLi(new XA(sprintf('/view/%d/races', $this->REGATTA->id), "All races")));
      $ul->add(new XLi(new XA(sprintf('/view/%d/ranking',  $this->REGATTA->id), "Rankings")));
    }
    else {
      $ul->add(new XLi(new XA(sprintf('/view/%d/scores',     $this->REGATTA->id), "All scores")));
      $ul->add(new XLi(new XA(sprintf('/view/%d/div-scores', $this->REGATTA->id), "Summary")));
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
        $ul->add(new XLi(new XA(sprintf('/view/%d/combined', $this->REGATTA->id), "All Divisions")));
      else
        $ul->add(new XLi(new XA(sprintf('/view/%d/chart', $this->REGATTA->id), "Rank history")));
      foreach ($this->REGATTA->getDivisions() as $div)
        $ul->add(new XLi(new XA(sprintf('/view/%d/scores/%s', $this->REGATTA->id, $div),
                                "$div Division")));
      $rot = $this->REGATTA->getRotation();
      if ($rot->isAssigned())
        $ul->add(new XLi(new XA(sprintf('/view/%d/boats', $this->REGATTA->id), "Boats rank")));
    }

    // Add meta tag
    $this->PAGE->head->add(new XMeta('timestamp', date('Y-m-d H:i:s')));
  }

  /**
   * Prepares the tiebreakers legend element (now a table) and returns it.
   *
   * @param Array $tiebreaker the associative array of symbol => explanation
   * @param Array $outside_schools optional list of outside schools
   * @return XElem probably a table
   */
  protected function getLegend(Array $tiebreakers, Array $outside_schools = array()) {
    $tab = new XQuickTable(array('class'=>'tiebreaker'), array("Sym.", "Explanation"));
    array_shift($tiebreakers);
    foreach ($tiebreakers as $exp => $ast)
      $tab->addRow(array($ast, new XTD(array('class'=>'explanation'), $exp)));
    foreach ($outside_schools as $exp => $ast)
      $tab->addRow(array($ast, new XTD(array('class'=>'explanation'), sprintf("%s sailor", $exp))));
    return $tab;
  }
}
?>