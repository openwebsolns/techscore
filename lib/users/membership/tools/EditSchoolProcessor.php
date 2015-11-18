<?php
namespace users\membership\tools;

use \ui\CountryStateSelect;
use \users\utils\burgees\AssociateBurgeesToSchoolHelper;

use \DB;
use \Conf;
use \School;
use \SoterException;
use \STN;

/**
 * Processes a request to edit a school.
 *
 * @author Dayan Paez
 * @version 2015-11-10
 */
class EditSchoolProcessor {

  private $burgeeHelper;

  /**
   * Edits only the editable fields of the given school, from args.
   *
   * @param Array $args the POST parameters.
   * @param School $school the School to edit.
   * @param Array $editableFields the list of fields that may be edited.
   * @return Array:String list of fields that were changed.
   * @throws SoterException with invalid input.
   */
  public function process(Array $args, School $school, Array $editableFields) {
    $changed = array();

    if (in_array(EditSchoolForm::FIELD_NAME, $editableFields)) {
      if ($this->processName($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_NAME;
      }
    }
    if (in_array(EditSchoolForm::FIELD_NICK_NAME, $editableFields)) {
      if ($this->processNickName($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_NICK_NAME;
      }
    }
    if (in_array(EditSchoolForm::FIELD_URL, $editableFields)) {
      if ($this->processUrl($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_URL;
      }
    }
    if (in_array(EditSchoolForm::FIELD_CONFERENCE, $editableFields)) {
      if ($this->processConference($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_CONFERENCE;
      }
    }
    if (in_array(EditSchoolForm::FIELD_CITY, $editableFields)) {
      if ($this->processCity($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_CITY;
      }
    }
    if (in_array(EditSchoolForm::FIELD_STATE, $editableFields)) {
      if ($this->processState($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_STATE;
      }
    }
    if (in_array(EditSchoolForm::FIELD_ID, $editableFields)) {
      if ($this->processId($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_ID;
      }
    }
    if (in_array(EditSchoolForm::FIELD_BURGEE, $editableFields)) {
      if ($this->processBurgee($school, $args)) {
        $changed[] = EditSchoolForm::FIELD_BURGEE;
      }
    }

    // Changes in ID automagically update the record.
    if (count($changed) > 0) {
      DB::set($school);
    }

    return $changed;
  }

  private function processName(School $school, Array $args) {
    $name = DB::$V->reqString($args, EditSchoolForm::FIELD_NAME, 1, 51, "Invalid name provided.");
    if ($name == $school->name) {
      return false;
    }

    // Is name unique?
    $othersWithName = DB::getSchoolsByName($name);
    if (count($othersWithName) > 0) {
      throw new SoterException(sprintf("Name \"%s\" is already assigned to another active school.", $name));
    }

    $school->name = $name;
    return true;
  }

  private function processNickName(School $school, Array $args) {
    $nickName = DB::$V->reqString($args, EditSchoolForm::FIELD_NICK_NAME, 1, 21, "Invalid short name provided.");
    if ($nickName == $school->nick_name) {
      return false;
    }

    $school->nick_name = $nickName;
    return true;
  }

  private function processUrl(School $school, Array $args) {
    $matches = DB::$V->reqRE(
      $args,
      EditSchoolForm::FIELD_URL,
      DB::addRegexDelimiters(EditSchoolForm::REGEX_URL),
      "Nonconformant URL provided."
    );

    $url = $matches[0];
    if ($url == $school->url) {
      return false;
    }

    $otherSchool = DB::getSchoolByUrl($url);
    if ($otherSchool !== null) {
      throw new SoterException(sprintf("Requested URL is already being used for a different school.", $otherSchool));
    }

    $school->url = $url;
    return true;
  }

  private function processConference(School $school, Array $args) {
    $conference = DB::$V->reqID(
      $args,
      EditSchoolForm::FIELD_CONFERENCE,
      DB::T(DB::CONFERENCE),
      sprintf("Invalid %s provided.", DB::g(STN::CONFERENCE_TITLE))
    );

    if ($school->conference == $conference) {
      return false;
    }

    $school->conference = $conference;
    return true;
  }

  private function processCity(School $school, Array $args) {
    $city = DB::$V->reqString(
      $args,
      EditSchoolForm::FIELD_CITY,
      1,
      31,
      "Invalid city argument provided."
    );

    if ($city == $school->city) {
      return false;
    }

    $school->city = $city;
    return true;
  }

  private function processState(School $school, Array $args) {
    $state = DB::$V->reqKey(
      $args,
      EditSchoolForm::FIELD_STATE,
      CountryStateSelect::getKeyValuePairs(),
      "Invalid state argument provided."
    );

    if ($state == $school->state) {
      return false;
    }

    $school->state = $state;
    return true;
  }

  private function processBurgee(School $school, Array $args) {
    $file = DB::$V->incFile($args, EditSchoolForm::FIELD_BURGEE, 1, 200000);
    if ($file === null) {
      return false;
    }

    $helper = $this->getAssociateBurgeesHelper();
    $helper->setBurgee(
      Conf::$USER,
      $school,
      $file['tmp_name']
    );
    return true;
  }

  private function processId(School $school, Array $args) {
    $matches = DB::$V->reqRE(
      $args,
      EditSchoolForm::FIELD_ID,
      DB::addRegexDelimiters(EditSchoolForm::REGEX_ID),
      "Invalid ID format provided."
    );

    $id = $matches[0];
    if ($id == $school->id) {
      return false;
    }

    $otherSchool = DB::getSchool($id);
    if ($otherSchool !== null) {
      throw new SoterException(
        sprintf("Chosen ID already belongs to another school (%s).", $otherSchool)
      );
    }

    if ($school->id !== null) {
      DB::reID($school, $id);
    }
    else {
      $school->id = $id;
      DB::set($school);
    }
    return true;
  }

  /**
   * Inject the processor for burgees.
   *
   * @param BurgeeProcessor $processor the new processor.
   */
  public function setAssociateBurgeesHelper(AssociateBurgeesToSchoolHelper $processor) {
    $this->burgeeHelper = $processor;
  }

  private function getAssociateBurgeesHelper() {
    if ($this->burgeeHelper == null) {
      $this->burgeeHelper = new AssociateBurgeesToSchoolHelper();
    }
    return $this->burgeeHelper;
  }

}