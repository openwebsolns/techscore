<?php
namespace users\membership\tools;

use \ui\CountryStateSelect;

use \DB;
use \School;
use \STN;

use \FReqItem;
use \FItem;
use \XFileForm;
use \XHiddenInput;
use \XSelect;
use \XStrong;
use \XTextInput;

/**
 * Adds input elements to a given form used for editing schools.
 *
 * @author Dayan Paez
 * @version 2015-11-10
 */
class EditSchoolForm extends XFileForm {

  const FIELD_ID = 'id';
  const FIELD_NAME = 'name';
  const FIELD_NICK_NAME = 'nick_name';
  const FIELD_URL = 'url';
  const FIELD_CONFERENCE = 'conference';
  const FIELD_CITY = 'city';
  const FIELD_STATE = 'state';

  const REGEX_ID = '^[A-Za-z0-9-]+$';
  const REGEX_URL = '^[a-z0-9]+[a-z0-9-]*[a-z0-9]+$';

  /**
   * Creates a new form for editing schools.
   *
   * @param String $action where the form will be posted.
   * @param School $school the school to edit.
   * @param Array $editableFields list of FIELD_* constants that can
   *   be edited.
   */
  public function __construct($action, School $school, Array $editableFields) {
    parent::__construct($action);
    $this->fill($school, $editableFields);
  }

  private function fill(School $school, Array $editableFields) {
    $this->add(new XHiddenInput('original-id', $school->id));

    $this->fillId($school, in_array(self::FIELD_ID, $editableFields));
    $this->fillName($school, in_array(self::FIELD_NAME, $editableFields));
    $this->fillNickName($school, in_array(self::FIELD_NICK_NAME, $editableFields));
    $this->fillUrl($school, in_array(self::FIELD_URL, $editableFields));
    $this->fillConference($school, in_array(self::FIELD_CONFERENCE, $editableFields));
    $this->fillCity($school, in_array(self::FIELD_CITY, $editableFields));
    $this->fillState($school, in_array(self::FIELD_STATE, $editableFields));
  }

  private function fillId(School $school, $editable) {
    if ($editable) {
      $this->add(
        new FReqItem(
          "ID:",
          new XTextInput('id', $school->id, array('pattern' => self::REGEX_ID)),
          "Must be unique. Allowed characters: A-Z, 0-9, and hyphens (-)."
        )
      );
    }
    else {
      $this->add(new FReqItem("ID:", new XStrong($school->id)));
    }
  }

  private function fillName(School $school, $editable) {
    if ($editable) {
      $this->add(
        new FReqItem(
          "Name:",
          new XTextInput('name', $school->name, array('maxlength' => 50)),
          "Should be unique to avoid user confusion."
        )
      );
    }
    else {
      $this->add(new FReqItem("Name:", new XStrong($school->name)));
    }
  }

  private function fillNickName(School $school, $editable) {
    if ($editable) {
      $this->add(
        new FReqItem(
          "Short name:",
          new XTextInput(self::FIELD_NICK_NAME, $school->nick_name, array('maxlength' => 20)),
          "Short version of the name limited to 20 characters."
        )
      );
    }
    else {
      $this->add(new FReqItem("Short name:", new XStrong($school->nick_name)));
    }
  }

  private function fillUrl(School $school, $editable) {
    if ($editable) {
      $this->add(
        new FReqItem(
          "URL slug:",
          new XTextInput('url', $school->url, array('pattern' => self::REGEX_URL)),
          "Must be lowercase letters, numbers, and hyphens (-)."
        )
      );
    }
    else {
      $this->add(new FReqItem("URL slug:", new XStrong($school->url)));
    }
  }

  private function fillConference(School $school, $editable) {
    $label = DB::g(STN::CONFERENCE_TITLE) . ":";
    if ($editable) {
      $this->add(new FReqItem($label, XSelect::fromDBM('conference', DB::getConferences(), $school->conference)));
    }
    else {
      $this->add(new FReqItem($label, new XStrong($school->conference)));
    }
  }

  private function fillCity(School $school, $editable) {
    if ($editable) {
      $this->add(new FItem("City:", new XTextInput('city', $school->city, array('maxlength' => 30))));
    }
    else {
      $this->add(new FItem("City:", new XStrong($school->city)));
    }
  }

  private function fillState(School $school, $editable) {
    if ($editable) {
      $this->add(new FItem("State:", new CountryStateSelect('state', $school->state)));
    }
    else {
      $this->add(new FReqItem("State:", new XStrong($school->state)));
    }
  }
}