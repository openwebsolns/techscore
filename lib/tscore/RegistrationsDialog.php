<?php
use \data\RegistrationsTable;

/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractDialog.php');

/**
 * Displays a list of all the registered sailors.
 *
 * @author Dayan Paez
 * @version 2010-01-23
 */
class RegistrationsDialog extends AbstractDialog {

  /**
   * Creates a new registrations dialog
   *
   */
  public function __construct(Account $user, FullRegatta $reg) {
    parent::__construct("Record of Participation", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Registrations"));

    if (count($this->REGATTA->getRaces()) == 0) {
      $p->add(new XWarning("There are no races in the regatta."));
      return;
    }

    $p->add(new RegistrationsTable($this->REGATTA));
  }
}
?>
