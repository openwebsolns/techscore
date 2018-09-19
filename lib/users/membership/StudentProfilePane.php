<?php
namespace users\membership;

use \model\StudentProfile;

use \xml5\XExternalA;

use \Account;
use \Conf;
use \DB;
use \Metric;
use \Sailor;
use \Sailor_Season;
use \School;
use \Season;
use \Session;
use \SoterException;
use \STN;
use \UpdateManager;
use \UpdateSailorRequest;
use \UpdateSchoolRequest;

use \FItem;
use \FOption;
use \FOptionGroup;
use \FReqItem;
use \XA;
use \XP;
use \XPort;
use \XHiddenInput;
use \XQuickTable;
use \XSelect;
use \XSubmitInput;
use \XWarning;

/**
 * Edit a student profile and allow selecting existing sailor record.
 *
 * @author Dayan Paez
 * @version 2016-04-16
 */
class StudentProfilePane extends AbstractProfilePane {

  const METRIC_EXACT_STUDENT_PROFILE_SAILOR_MATCH = 'StudentProfilePane-exact-sailor-match';
  const METRIC_STUDENT_PROFILE_SAILOR_MATCH = 'StudentProfilePane-sailor-match';
  const METRIC_STUDENT_PROFILE_NEW_SAILOR = 'StudentProfile-new-sailor';

  const INPUT_SAILOR = 'sailor';
  const INPUT_SCHOOL = 'school';
  const INPUT_PRE_SEASON_TRANSFER = 'pre-season-transfer';
  const SUBMIT_REGISTER_EXISTING_SAILOR = 'register-existing-sailor';
  const SUBMIT_REGISTER_NEW_SAILOR = 'register-new-sailor';
  const SUBMIT_TRANSFER = 'transfer-schools';

  public function __construct(Account $user) {
    parent::__construct("Student profile", $user);
  }

  protected function fillProfile(StudentProfile $profile, Array $args) {
    $sailorRecords = $profile->getSailorRecords();
    if (count($sailorRecords) === 0) {
      $this->fillRegisterSailor($profile, $args);
      return;
    }

    $this->PAGE->addContent($p = new XPort("Profile"));
    $p->add(new FItem("Name:", $profile->getName()));
    $p->add(new FItem("School:", $profile->school));
    $p->add(new FItem("Graduation Year:", $profile->graduation_year));

    $this->PAGE->addContent($p = new XPort("School transfer"));
    $p->add(new XP(array(), "When you transfer to a new school, your profile is updated and a new sailor record associated with the new school is created in your profile. This preserves your existing sailor history in your existing school."));
    $p->add($f = $this->createProfileForm($profile));
    $f->add(new FReqItem("New school:", $this->getSchoolSelect($profile->school)));
    $f->add(new XSubmitInput(self::SUBMIT_TRANSFER, "Transfer"));

    $this->PAGE->addContent($p = new XPort("Sailor record"));
    $p->add($table = new XQuickTable(
      array('class' => 'sailor-records'),
      array("First name", "Last name", "Gender", "School", "Graduation year", "# of regattas")
    ));

    foreach ($sailorRecords as $sailor) {
      $numRegattas = count($sailor->getRegattas());
      if (DB::g(STN::SAILOR_PROFILES) !== null) {
        $numRegattas = new XExternalA(sprintf('http://%s%s', Conf::$PUB_HOME, $sailor->getURL()), $numRegattas);
      }

      $table->addRow(array(
        $sailor->first_name,
        $sailor->last_name,
        $sailor->gender,
        $sailor->school,
        $sailor->year,
        $numRegattas,
      ));
    }
    $p->add(new XP(array(), "More functionality coming soon..."));
  }

  private function fillRegisterSailor(StudentProfile $profile, Array $args) {
    $this->PAGE->addContent($p = new XPort("Create/transfer sailor record"));

    $currentSeason = null;
    $nextSeason = null;
    foreach (Season::all() as $season) {
      if ($season->isCurrent(DB::T(DB::NOW))) {
        $currentSeason = $season;
        break;
      }
      if ($season->end_date < DB::T(DB::NOW)) {
        break;
      }
      $nextSeason = $season;
    }
    if ($currentSeason === null) {
      $comeBackOn = 'later';
      if ($nextSeason !== null) {
        $comeBackOn = sprintf('after %s', $nextSeason->start_date->format('F j, Y'));
      }
      $p->add(new XWarning(sprintf("You're not done yet! However, there is no active season at this time. Please come back %s to finish registration.", $comeBackOn)));
      return;
    }

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
    // Transfer
    if (array_key_exists(self::SUBMIT_TRANSFER, $args)) {
      $school = DB::$V->reqSchool($args, self::INPUT_SCHOOL, "Invalid or missing school to transfer to.");
      if ($school->id === $profile->school->id) {
        throw new SoterException("Transfer school same as current school.");
      }

      // update profile
      $profile->school = $school;
      DB::set($profile);

      // inactivate previous sailor records
      $season = Season::forDate(DB::T(DB::NOW));
      foreach ($profile->getSailorRecords() as $sailor) {
        $sailor->active = null;
        DB::set($sailor);

        // queue old-school roster as well.
        UpdateManager::queueSchool($sailor->school, UpdateSchoolRequest::ACTIVITY_ROSTER, $season);

        // remove sailor record from current season if there is no attendance
        if ($season !== null && count($sailor->getRegattas($season)) === 0) {
          $sailor->removeFromSeason($season);
        }
      }

      // create new sailor record
      $sailor = Sailor::fromStudentProfile($profile);
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

      $profile->addSailorRecord($sailor);
      Session::info(sprintf("Profile transferred to %s.", $school));
    }

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
      $sailor->active = 1; // TODO: deprecate
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

  private function getSchoolSelect(School $ignore) {
    $aff = new XSelect(self::INPUT_SCHOOL);
    $aff->add(new FOption('', "[Choose one]"));
    foreach (DB::getConferences() as $conf) {
      $aff->add($opt = new FOptionGroup($conf));
      foreach ($conf->getSchools() as $school) {
        if ($school->id !== $ignore->id) {
          $opt->add(new FOption($school->id, $school->name));
        }
      }
    }
    return $aff;
  }
}
