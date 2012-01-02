<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

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
    $t = new XTable(array('class'=>'coordinate rotation'),
		    array(new XTHead(array(),
				     array($r = new XTR(array(), array(new XTH(), new XTH())))),
			  $tab = new XTBody()));

    $races = $this->REGATTA->getRaces($div);
    foreach ($races as $race)
      $r->add(new XTH(array(), $race));

    $row = 0;
    foreach ($this->REGATTA->getTeams() as $team) {
      $tab->add($r = new XTR(array('class'=>'row'.($row++ % 2)),
			     array(new XTD(array(), $team->school->name),
				   new XTD(array(), $team->name))));

      foreach ($races as $race) {
	$sail = $this->rotation->getSail($race, $team);
	$sail = ($sail !== false) ? $sail : "";
	$r->add(new XTD(array(), $sail));
      }
    }

    return $t;
  }

  /**
   * Creates a table for each division
   *
   */
  public function fillHTML(Array $args) {
    foreach ($this->REGATTA->getDivisions() as $div) {
      $this->PAGE->addContent($p = new XPort(sprintf("Division %s", $div)));
      $p->add($this->getTable($div));
    }
  }
}
