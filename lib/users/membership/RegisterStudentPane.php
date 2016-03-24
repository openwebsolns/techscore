<?php
namespace users\membership;

use \users\AbstractUserPane;

/**
 * Allows students to self-register as sailors. This is the entry way
 * to the system as manager of the sailor database.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class RegisterStudentPane extends AbstractUserPane {

  public function __construct() {
    parent::__construct("Register as a sailor");
  }

  protected function fillHTML(Array $args) {

  }

  public function process(Array $args) {

  }

}