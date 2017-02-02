<?php
namespace users\membership;

use \model\StudentProfile;

use \Account;
use \DB;
use \Metric;
use \Sailor;
use \Sailor_Season;
use \Season;
use \Session;
use \SoterException;
use \STN;
use \UpdateManager;
use \UpdateSailorRequest;
use \UpdateSchoolRequest;

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

  const METRIC_EXACT_STUDENT_PROFILE_SAILOR_MATCH = 'StudentProfilePane-exact-sailor-match';
  const METRIC_STUDENT_PROFILE_SAILOR_MATCH = 'StudentProfilePane-sailor-match';
  const METRIC_STUDENT_PROFILE_NEW_SAILOR = 'StudentProfile-new-sailor';

  const INPUT_SAILOR = 'sailor';
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
    // Existing
    if (array_key_exists(self::SUBMIT_REGISTER_EXISTING_SAILOR, $args)) {
      $sailor = DB::$V->reqID($args, self::INPUT_SAILOR, DB::T(DB::SAILOR), "Invalid sailor record chosen.");
      if ($sailor->student_profile !== null) {
        throw new SoterException("Chosen sailor record already belongs to another account.");
      }

      $this->backfillEligibilityFromAttendance($profile, $sailor);
      $this->addCurrentSeasonElibility($profile);
      $this->activateSailorForCurrentSeason($sailor);
      $profile->addSailorRecord($sailor);

      Session::info(sprintf("Associated existing sailor record for \"%s\" to your profile.", $sailor));

      if ($this->isExactMatch($profile, $sailor)) {
        Metric::publish(self::METRIC_EXACT_STUDENT_PROFILE_SAILOR_MATCH);
      }
      Metric::publish(self::METRIC_STUDENT_PROFILE_SAILOR_MATCH);
    }

    // New
    if (array_key_exists(self::SUBMIT_REGISTER_NEW_SAILOR, $args)) {
      $sailor = Sailor::fromStudentProfile($profile);
      $this->activateSailorForCurrentSeason($sailor);
      $this->backfillEligibilityFromAttendance($profile, $sailor);
      $this->addCurrentSeasonElibility($profile);
      $profile->addSailorRecord($sailor);

      Session::info("Created new sailor record.");
      Metric::publish(self::METRIC_STUDENT_PROFILE_NEW_SAILOR);
    }
  }

  private function isExactMatch(StudentProfile $profile, Sailor $sailor) {
    return (
      strcasecmp($sailor->first_name, $profile->first_name) === 0
      && strcasecmp($sailor->last_name, $profile->last_name) === 0
    );
  }

  /**
   * Fill-in profile's eligibility based on every season that the given sailor participated.
   */
  private function backfillEligibilityFromAttendance(StudentProfile $profile, Sailor $sailor) {
    $seasons = array();
    foreach ($sailor->getRegattas() as $regatta) {
      $season = $regatta->getSeason();
      $seasonId = $season->shortString();
      if (!array_key_exists($seasonId, $seasons)) {
        $seasons[$seasonId] = $season;
      }
    }

    foreach ($seasons as $season) {
      $profile->addEligibility(
        $season,
        "Backfilled as part of sailor record registration."
      );
    }
  }

  /**
   * Automatically pre-select eligibility for current season, if it exists.
   *
   * This is intended to ease migration. May need revisit?
   */
  private function addCurrentSeasonElibility(StudentProfile $profile) {
    $season = Season::forDate(DB::T(DB::NOW));
    if ($season !== null) {
      $profile->addEligibility(
        $season,
        "Auto-selected as part of sailor record registration."
      );
    }
  }

  private function activateSailorForCurrentSeason(Sailor $sailor) {
    $season = Season::forDate(DB::T(DB::NOW));
    if ($season !== null) {
      $sailor->active = 1;
      $sailor->register_status = Sailor::STATUS_REGISTERED;
      DB::set(Sailor_Season::create($sailor, $season));

      // URL
      if (DB::g(STN::SAILOR_PROFILES)) {
        $old_url = $sailor->getURL();
        $sailor->url = DB::createUrlSlug(
          $sailor->getUrlSeeds(),
          function ($slug) use ($sailor) {
            $other = DB::getSailorByUrl($slug);
            return ($other === null || $other->id == $sailor->id);
          }
        );

        UpdateManager::queueSailor($sailor, UpdateSailorRequest::ACTIVITY_URL, $season, $old_url);

        // queue school DETAILS as well, if entirely new URL. This will
        // cause all the seasons to be regenerated, without affecting
        // the regattas.
        UpdateManager::queueSchool($sailor->school, UpdateSchoolRequest::ACTIVITY_DETAILS, $season);
      }
    }
  }
}