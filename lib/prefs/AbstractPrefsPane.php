<?php
/*
 * This file is part of TechScore
 *
 */

require_once('users/AbstractUserPane.php');

/**
 * Parent class for Preferences page. Makes certain that school object
 * exists.
 *
 * @author Dayan Paez
 * @version 2012-03-11
 */
abstract class AbstractPrefsPane extends AbstractUserPane {
  /**
   * Creates a new pane for the given USER. The school will be parsed
   * from the $_GET variable directly, and if permissions do not
   * allow, a redirect will be issued.
   *
   * @param String $title the title of the pane
   * @param Account $user the user
   * @param School $school the school
   */
  public function __construct($title, Account $user) {
    parent::__construct($title, $user);
    try {
      $this->SCHOOL = DB::$V->reqSchool($_GET, 'school', "No school provided for preferences.");
      $schools = $user->getSchools();
      if (!isset($schools[$this->SCHOOL->id]))
	throw new SoterException(sprintf("No permissions to edit school %s.", $this->SCHOOL));
    }
    catch (SoterException $e) {
      Session::pa(new PA($e->getMessage(), PA::E));
      $this->redirect('prefs/' . $user->school->id);
    }
  }
}
?>