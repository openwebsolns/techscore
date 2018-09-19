<?php
namespace users\membership;

use \model\StudentProfile;

use \Account;
use \SoterException;

use \XPort;

/**
 * Transfers a student to a new school.
 */
class TransferStudentPane extends AbstractProfilePane {

  public function __construct(Account $user) {
    parent::__construct("Student profile", $user);
  }

  protected function fillProfile(StudentProfile $profile, Array $args) {
    $this->PAGE->addContent($p = new XPort("Transfer to new school"));
  }

  protected function processProfile(StudentProfile $profile, Array $args) {
    throw new SoterException("Not implemented.");
  }
}
