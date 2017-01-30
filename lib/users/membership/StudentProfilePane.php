<?php
namespace users\membership;

use \model\StudentProfile;

use \Account;
use \DB;
use \Metric;
use \Sailor;
use \Session;
use \SoterException;

use \XP;
use \XPort;
use \XHiddenInput;
use \XQuickTable;
use \XSubmitInput;

/**
 * Edit a student profile.
 *
 * @author Dayan Paez
 * @version 2016-04-16
 */
class StudentProfilePane extends AbstractProfilePane {

  const INPUT_SAILOR = 'sailor';
  const METRIC_EXACT_STUDENT_PROFILE_SAILOR_MATCH = 'exact-student-profile-sailor-match';
  const SUBMIT_REGISTER_EXISTING_SAILOR = 'register-existing-sailor';
  const SUBMIT_REGISTER_NEW_SAILOR = 'register-new-sailor';

  public function __construct(Account $user) {
    parent::__construct("Student profile", $user);
  }

  protected function fillProfile(StudentProfile $profile, Array $args) {
    $sailorRecords = $profile->getSailorRecords();
    if (count($sailorRecords) === 0) {
      $this->fillRegisterSailor($profile, $args);
      return;
    }

    $this->PAGE->addContent($p = new XPort("Sailor record"));
    $p->add($table = new XQuickTable(
      array('class' => 'sailor-records'),
      array("First name", "Last name", "Gender", "Graduation year", "# of regattas")
    ));
    foreach ($sailorRecords as $sailor) {
      $table->addRow(array(
        $sailor->first_name,
        $sailor->last_name,
        $sailor->gender,
        $sailor->year,
        count($sailor->getRegattas()),
      ));
    }
  }

  private function fillRegisterSailor(StudentProfile $profile, Array $args) {
    $this->PAGE->addContent($p = new XPort("Create/transfer sailor record"));
    $sailors = $profile->school->getSailors();
    $p->add(new XP(array(), "To finish registration, we need to match you with our records. Click \"This is me!\" next to the sailor record below that belongs to you. If none match, use the \"I'm new!\" button at the bottom to proceed."));

    $rows = array();
    $exactMatches = array();
    foreach ($sailors as $sailor) {
      if ($sailor->student_profile === null) {
        $form = $this->createProfileForm($profile);
        $form->add(new XSubmitInput(self::SUBMIT_REGISTER_EXISTING_SAILOR, "This is me!", array('class' => 'secondary')));
        $form->add(new XHiddenInput(self::INPUT_SAILOR, $sailor->id));
        $row = array(
          $sailor->first_name,
          $sailor->last_name,
          $sailor->year,
          $form,
        );

        if ($this->isExactMatch($profile, $sailor)) {
          $exactMatches[] = $row;
        } else {
          $rows[] = $row;
        }
      }
    }

    if (count($rows) + count($exactMatches) > 0) {
      $p->add($table = new XQuickTable(
        array('class' => 'sailor-records'),
        array("First name", "Last name", "Graduation year", "")
      ));

      foreach ($exactMatches as $row) {
        $table->addRow($row, array('class' => 'exact-match'));
      }
      foreach ($rows as $row) {
        $table->addRow($row);
      }
    }

    $p->add($form = $this->createProfileForm($profile));
    $form->add(new XP(array(), array(
      "I can't find my record. ",
      new XSubmitInput(self::SUBMIT_REGISTER_NEW_SAILOR, "I'm new!")
    )));
  }

  protected function processProfile(StudentProfile $profile, Array $args) {
    if (array_key_exists(self::SUBMIT_REGISTER_EXISTING_SAILOR, $args)) {
      $sailor = DB::$V->reqID($args, self::INPUT_SAILOR, DB::T(DB::SAILOR), "Invalid sailor record chosen.");
      if ($sailor->student_profile !== null) {
        throw new SoterException("Chosen sailor record already belongs to another account.");
      }

      $sailor->student_profile = $profile;
      DB::set($sailor);
      Session::info(sprintf("Added existing sailor record for \"%s\" to your profile.", $sailor));

      if ($this->isExactMatch($profile, $sailor)) {
        Metric::publish(self::METRIC_EXACT_STUDENT_PROFILE_SAILOR_MATCH);
      }
    }
  }

  private function isExactMatch(StudentProfile $profile, Sailor $sailor) {
    return (
      strcasecmp($sailor->first_name, $profile->first_name) === 0
      && strcasecmp($sailor->last_name, $profile->last_name) === 0
    );
  }
}