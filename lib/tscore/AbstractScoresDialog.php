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
  public function __construct($title, Regatta $reg) {
    parent::__construct($title, $reg);

  }
  protected function setupPage() {
    parent::setupPage();

    // Add some menu
    $this->PAGE->addMenu($div = new Div());
    $div->set("class", "menu");
    $div->add($ul = new GenericList());
    $ul->addItems(new XLi(new XA(sprintf("/view/%d/scores",     $this->REGATTA->id()), "All scores")));
    $ul->addItems(new XLi(new XA(sprintf("/view/%d/div-scores", $this->REGATTA->id()), "Divisional")));
    foreach ($this->REGATTA->getDivisions() as $div)
      $ul->addItems(new XLi(new XA(sprintf("/view/%d/scores/%s",$this->REGATTA->id(), $div),
				       "$div Division")));

    // Add meta tag
    $this->PAGE->addHead($p = new GenericElement("meta"));
    $p->set("name", "timestamp");
    $p->set("content", date('Y-m-d H:i:s'));
  }

  /**
   * Prepares the tiebreakers legend element (now a table) and returns it.
   *
   * @param Array $tiebreaker the associative array of symbol => explanation
   * @return GenericElement probably a table
   */
  protected function getLegend($tiebreakers) {
    $tab = new Table();
    array_shift($tiebreakers);
    $tab->addHeader(new Row(array(Cell::th("Sym."), Cell::th("Explanation"))));
    foreach ($tiebreakers as $exp => $ast)
      $tab->addRow(new Row(array(new Cell($ast), new Cell($exp))));
    return $tab;
  }
}

?>