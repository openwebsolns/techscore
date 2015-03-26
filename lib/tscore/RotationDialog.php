<?php
use \data\RotationTable;

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
   * @param Account $user the user
   * @param FullRegatta $reg the regatta
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Rotation", $user, $reg);
    $this->rotation = $this->REGATTA->getRotation();
  }

  /**
   * Creates a table for each division
   *
   */
  public function fillHTML(Array $args) {
    foreach ($this->REGATTA->getDivisions() as $div) {
      $this->PAGE->addContent($p = new XPort(sprintf("Division %s", $div)));
      $p->add(new RotationTable($this->REGATTA, $div));
    }
  }
}
