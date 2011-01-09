<?php
/**
 * This file is part of TechScore
 *
 */

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
    $div->addAttr("class", "menu");
    $div->addChild($ul = new GenericList());
    $ul->addItems(new LItem(new Link(sprintf("/view/%d/scores",     $this->REGATTA->id()), "All scores")));
    $ul->addItems(new LItem(new Link(sprintf("/view/%d/div-scores", $this->REGATTA->id()), "Divisional")));
    foreach ($this->REGATTA->getDivisions() as $div)
      $ul->addItems(new LItem(new Link(sprintf("/view/%d/scores/%s",$this->REGATTA->id(), $div),
				       "$div Division")));

    // Add meta tag
    $this->PAGE->addHead($p = new GenericElement("meta"));
    $p->addAttr("name", "timestamp");
    $p->addAttr("content", date('Y-m-d H:i:s'));
  }
}

?>