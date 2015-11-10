<?php
namespace users\utils\burgees;

use \Account;
use \DB;
use \Burgee;
use \School;
use \UpdateManager;
use \UpdateRequest;
use \UpdateSchoolRequest;

/**
 * Helper to attach burgees to a given school.
 *
 * @author Dayan Paez
 * @version 2015-11-11
 */
class AssociateBurgeesToSchoolHelper {

  private $burgeeProcessor;

  public function setBurgee(Account $user, School $school, $filename) {
    $processor = $this->getBurgeeProcessor();
    $processor->init($filename);

    // resize image to fix in bounding boxes
    $full = $processor->createBurgee(Burgee::FULL_WIDTH, Burgee::FULL_HEIGHT);
    $small = $processor->createBurgee(Burgee::SMALL_WIDTH, Burgee::SMALL_HEIGHT);
    $square = $processor->createBurgee(Burgee::SQUARE_LENGTH, Burgee::SQUARE_LENGTH);
    $processor->cleanup();

    // Update database: first create the burgee, then assign it to the
    // school object (for history control, mostly)
    $full->last_updated = DB::T(DB::NOW);
    $full->school = $school;
    $full->updated_by = $user->id;
    DB::set($full);

    $small->last_updated = DB::T(DB::NOW);
    $small->school = $school;
    $small->updated_by = $user->id;
    DB::set($small);

    $square->last_updated = DB::T(DB::NOW);
    $square->school = $school;
    $square->updated_by = $user->id;
    DB::set($square);
    
    // If this is the first time a burgee is added, then notify all
    // public regattas for which this school has participated so that
    // they can be regenerated!
    require_once('public/UpdateManager.php');
    if ($school->burgee === null) {
      UpdateManager::queueSchool($school, UpdateSchoolRequest::ACTIVITY_DETAILS);

      foreach ($school->getRegattas() as $reg) {
        UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_DETAILS);
      }
    }

    $school->burgee = $full;
    $school->burgee_small = $small;
    $school->burgee_square = $square;
    DB::set($school);
    UpdateManager::queueSchool($school, UpdateSchoolRequest::ACTIVITY_BURGEE);
  }

  /**
   * Inject the processor for burgees.
   *
   * @param BurgeeProcessor $processor the new processor.
   */
  public function setBurgeeProcessor(BurgeeProcessor $processor) {
    $this->burgeeProcessor = $processor;
  }

  private function getBurgeeProcessor() {
    if ($this->burgeeProcessor == null) {
      $this->burgeeProcessor = new GdBurgeeProcessor();
    }
    return $this->burgeeProcessor;
  }

}