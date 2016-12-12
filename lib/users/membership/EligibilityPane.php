<?php
namespace users\membership;

use \model\StudentProfile;
use \users\AbstractUserPane;
use \xml5\HelpMeLink;

use \Account;
use \Season;
use \Session;

use \FCheckbox;
use \XA;
use \XPort;
use \XQuickTable;
use \XWarning;

/**
 * Set eligibility for a given student profile.
 *
 * @author Dayan Paez
 * @version 2016-04-16
 */
class EligibilityPane extends AbstractUserPane {
  const INPUT_PROFILE = 'id';
  const INPUT_SELECTED_SEASONS = 'seasons';

  private $seasons;
  private $profiles;

  public function __construct(Account $user) {
    parent::__construct("Student eligibility", $user);
    $this->profiles = $this->USER->getStudentProfilesUnderJurisdiction();
    $this->seasons = Season::all();
  }

  protected function fillHTML(Array $args) {
    if (count($this->seasons) === 0) {
      $this->PAGE->addContent(new XWarning(array(
        "There are currently no seasons in the system. Please ",
        new HelpMeLink("contact an administrator"),
        " to proceed."
      )));
      return;
    }

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

  private function fillProfile(StudentProfile $profile, Array $args) {
    $eligibilityBySeason = array();
    foreach ($profile->getEligibilities() as $eligibility) {
      $eligibilityBySeason[$eligibility->season->id] = $eligibility;
    }

    $this->PAGE->addContent($p = new XPort(sprintf("Eligibility for %s", $profile->getName())));
    $p->add($form = $this->createForm());
    $form->add($table = new XQuickTable(array('class' => 'eligibility-selection-table'), array("", "Season", "Notes")));
    foreach ($this->seasons as $season) {
      $isChecked = array_key_exists($season->id, $eligibilityBySeason);
      $notes = "";

      $table->addRow(array(
        new FCheckbox(self::INPUT_SELECTED_SEASONS . '[]', 1, '', $isChecked),
        $season->fullString(),
        $notes,
      ));
    }
    //var_dump($eligibilityBySeason); exit;
  }

  public function process(Array $args) {
    throw new \SoterException("Not yet implemented.");
  }
}