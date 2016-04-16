<?php
namespace users\membership;

use \model\StudentProfile;
use \users\AbstractUserPane;
use \Account;

/**
 * Edit a student profile.
 *
 * @author Dayan Paez
 * @version 2016-04-16
 */
class StudentProfilePane extends AbstractUserPane {
  public function __construct(Account $user) {
    parent::__construct("Edit student profile", $user);
  }

  protected function fillHTML(Array $args) {

  }

  public function process(Array $args) {
    
  }
}