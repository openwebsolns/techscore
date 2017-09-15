<?php
namespace bin;

use \model\StudentProfile;

use \DB;
use \Sailor;
use \Sailor_Season;
use \School;
use \Season;
use \SoterException;
use \STN;
use \UpdateManager;
use \UpdateSailorRequest;
use \UpdateSchoolRequest;

/*
 * Manually transfer student in current season to another school.
 */
require_once(dirname(__DIR__) . '/lib/conf.php');

function usage($mes = null, $exitCode = 0) {
  if ($mes !== null) {
    printf("error: %s\n\n", $mes);
  }
  printf("usage: %s <student-profile-id> <new-school-id>

  1. Updates the student profile to belong to new school
  2. Sets all existing sailor records as inactive
  3. Adds a new active sailor record off the new profile
  4. Adds a new entry in sailor_season

NOTE: Assumes this is a pre-season transfer, thereby removing
the sailor_season entry for the old sailor.
",
         basename(__FILE__)
  );
  exit($exitCode);
}

function updateProfile(StudentProfile $profile, School $school) {
  $profile->school = $school;
  DB::set($profile);
}

function inactivateSailorRecords(StudentProfile $profile, Season $season) {
  foreach ($profile->getSailorRecords() as $sailor) {
    $sailor->active = null;
    DB::set($sailor);

    // queue old-school roster as well.
    UpdateManager::queueSchool($sailor->school, UpdateSchoolRequest::ACTIVITY_ROSTER, $season);

    // NOTE: assume this is a before-season transfer!
    $sailor->removeFromSeason($season);
  }
}

// see users\membership\StudentProfilePane::activateSailorForCurrentSeason()
function createNewSailorRecord(StudentProfile $profile, Season $season) {
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
}

try {
  $profile = DB::$V->reqID($argv, 1, DB::T(DB::STUDENT_PROFILE), "Invalid or missing student profile.");
  $school = DB::$V->reqSchool($argv, 2, "Invalid or missing school to transfer to.");
  $season = Season::forDate(DB::T(DB::NOW));
  if ($season === null) {
    throw new SoterException("No current season exists.");
  }
  if ($profile->school->id === $school->id) {
    throw new SoterException("Profile is already associated with given school.");
  }

  updateProfile($profile, $school);
  inactivateSailorRecords($profile, $season);
  createNewSailorRecord($profile, $season);

} catch (SoterException $e) {
  DB::rollback();
  usage($e->getMessage(), 1);
}