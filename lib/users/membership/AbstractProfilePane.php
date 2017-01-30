<?php
namespace users\membership;

use \model\StudentProfile;
use \users\AbstractUserPane;
use \users\membership\tools\StudentProfilesTable;

use \Account;
use \DB;
use \Metric;
use \Session;
use \SoterException;

use \XA;
use \XForm;
use \XHiddenInput;
use \XPort;
use \XQuickTable;
use \XWarning;

/**
 * Parent pane to handle multi-profile selection per account.
 *
 * @author Dayan Paez
 * @version 2017-01-29
 */
abstract class AbstractProfilePane extends AbstractUserPane {
  const INPUT_PROFILE = 'id';

  private $profiles;

  public function __construct($title, Account $user) {
    parent::__construct($title, $user);
    $this->profiles = $this->USER->getStudentProfilesUnderJurisdiction();
  }

  protected function fillHTML(Array $args) {
    if (count($this->profiles) === 0) {
      $this->PAGE->addContent(new XWarning(array("There are no student profiles associated with this account. Please visit the ", new XA($this->linkTo('users\membership\RegisterStudentPane'), "sailor registration page"), ".")));
      return;
    }

    if (count($this->profiles) === 1) {
      $this->fillProfile($this->profiles[0], $args);
      return;
    }

    if (array_key_exists(self::INPUT_PROFILE, $args)) {
      foreach ($this->profiles as $profile) {
        if ($profile->id === $args[self::INPUT_PROFILE]) {
          unset($args[self::INPUT_PROFILE]);
          $this->fillProfile($profile, $args);
          return;
        }
      }
      Session::error(sprintf("Invalid profile requested \"%s\".", $args[self::INPUT_PROFILE]));
    }

    // Choose profile to edit
    $this->PAGE->addContent($p = new XPort("Choose profile to edit"));
    $p->add(new StudentProfilesTable(
      $this->profiles,
      function($name, StudentProfile $profile) {
        return new XA(
          $this->linkTo(null, array(self::INPUT_PROFILE => $profile->id)),
          $name
        );
      }
    ));
  }

  public function process(Array $args) {
    $profile = DB::$V->reqID($args, self::INPUT_PROFILE, DB::T(DB::STUDENT_PROFILE), "Invalid or missing profile to edit.");
    if ($profile->owner->id !== $this->USER->id) {
      Metric::publish(Metric::UNEXPECTED_POST_ARGUMENT);
      throw new SoterException("Invalid student profile requested.");
    }

    unset($args[self::INPUT_PROFILE]);
    $this->processProfile($profile, $args);
  }

  protected function createProfileForm(StudentProfile $profile, $method = XForm::POST) {
    $form = parent::createForm($method);
    $form->add(new XHiddenInput(self::INPUT_PROFILE, $profile->id));
    return $form;
  }

  abstract protected function fillProfile(StudentProfile $profile, Array $args);
  abstract protected function processProfile(StudentProfile $profile, Array $args);
}