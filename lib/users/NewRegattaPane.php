<?php
/**
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2
 * @date 2010-04-19
 */
require_once('conf.php');

/**
 * Create a new regatta
 *
 */
class NewRegattaPane extends AbstractUserPane {

  /**
   * Create a pane for creating regattas
   *
   * @param User $user the user creating the regatta
   */
  public function __construct(User $user) {
    parent::__construct("New regatta", $user);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Create"));
    $p->addChild($f = new Form("edit/create"));
    
    // $f->add(new FItem("Name:",
  }

  /**
   * Creates the new regatta
   *
   */
  public function process(Array $args) {
    return $args;
  }
}
?>