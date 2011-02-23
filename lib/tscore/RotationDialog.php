<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Displays the rotation for a given regatta
 *
 */
class RotationDialog extends AbstractDialog {

  private $rotation;

  /**
   * Create a new rotation dialog for the given regatta
   *
   * @param Regatta $reg the regatta
   */
  public function __construct(Regatta $reg) {
    parent::__construct("Rotation", $reg);
    $this->rotation = $this->REGATTA->getRotation();
  }

  /**
   * Generates an HTML table for the given division
   *
   * @param Division $div the division
   * @return Rotation $rot
   */
  public function getTable(Division $div) {
    $tab = new Table(array(), array("class"=>"narrow coordinate rotation"));
    $r = new Row(array(Cell::th(),
		       Cell::th()));
    $tab->addHeader($r);

    $races = $this->REGATTA->getRaces($div);
    foreach ($races as $race)
      $r->addCell(Cell::th($race));

    $row = 0;
    foreach ($this->REGATTA->getTeams() as $team) {
      $tab->addRow($r = new Row());
      $r->addAttr("class", "row" . ($row++ % 2));
      $r->addCell(Cell::td($team->school->name), Cell::th($team->name));

      foreach ($races as $race) {
	$sail = $this->rotation->getSail($race, $team);
	$sail = ($sail !== false) ? $sail : "";
	$r->addCell(new Cell($sail));
      }
    }

    return $tab;
  }

  /**
   * Creates a table for each division
   *
   */
  public function fillHTML(Array $args) {
    foreach ($this->REGATTA->getDivisions() as $div) {
      $this->PAGE->addContent($p = new Port(sprintf("Division %s", $div)));
      $p->addChild($this->getTable($div));
    }
  }
}
