<?php
namespace users\membership\tools;

use \xml5\XExternalA;
use \xml5\GraduationYearInput;

use \DB;
use \Conf;
use \Sailor;
use \STN;

use \XFileForm;
use \XHiddenInput;
use \FReqItem;
use \XTextInput;
use \FItem;
use \XSelect;
use \XSpan;

/**
 * Form for editing or adding a new sailor.
 *
 * @author Dayan Paez
 * @version 2015-12-09
 */
class EditSailorForm extends XFileForm {

  const FIELD_ID = 'id';
  const FIELD_FIRST_NAME = 'first_name';
  const FIELD_LAST_NAME = 'last_name';
  const FIELD_YEAR = 'year';
  const FIELD_GENDER = 'gender';
  const FIELD_URL = 'url';

  const REGEX_URL = '^[a-z0-9]+[a-z0-9-]*[a-z0-9]+$';

  public function __construct($action, Sailor $sailor, $editable = true) {
    parent::__construct($action);
    if ($editable) {
      $this->fill($sailor);
    } else {
      $this->showOnly($sailor);
    }
  }

  private function fill(Sailor $sailor) {
    if ($sailor->id !== null) {
      $this->add(new XHiddenInput(self::FIELD_ID, $sailor->id));
    }
    $this->add(new FItem("School:", new XSpan($sailor->school)));
    $this->add(
      new FReqItem(
        "First name:",
        new XTextInput(self::FIELD_FIRST_NAME, $sailor->first_name)
      )
    );
    $this->add(
      new FReqItem(
        "Last name:",
        new XTextInput(self::FIELD_LAST_NAME, $sailor->last_name)
      )
    );
    $this->add(
      new FReqItem(
        "Graduation year:",
        new GraduationYearInput(self::FIELD_YEAR, $sailor->year)
      )
    );

    $genders = array('' => '');
    foreach (Sailor::getGenders() as $key => $val) {
      $genders[$key] = $val;
    }
    $this->add(
      new FReqItem(
        "Gender:",
        XSelect::fromArray(
          self::FIELD_GENDER,
          $genders,
          $sailor->gender
        )
      )
    );

    if ($sailor->isRegistered() && DB::g(STN::SAILOR_PROFILES) !== null) {
      $this->add(
        new FItem(
          "URL slug:",
          new XTextInput('url', $sailor->url, array('pattern' => self::REGEX_URL)),
          "Must be lowercase letters, numbers, and hyphens (-). Leave blank to auto-generate a URL based on sailor name."
        )
      );
    }
  }

  /**
   * Non-editable version of above.
   */
  private function showOnly(Sailor $sailor) {
    $this->add(new FItem("School:", new XSpan($sailor->school)));
    $this->add(new FReqItem("First name:", new XSpan($sailor->first_name)));
    $this->add(new FReqItem("Last name:", new XSpan($sailor->last_name)));
    $this->add(new FReqItem("Graduation year:", new XSpan($sailor->year)));
    $this->add(new FReqItem("Gender:", new XSpan(Sailor::getGender($sailor->gender))));
    if ($sailor->isRegistered() && DB::g(STN::SAILOR_PROFILES) !== null) {
      $this->add(new FItem("URL slug:", new XExternalA(sprintf('http://%s%s', Conf::$PUB_HOME, $sailor->getURL()), $sailor->url)));
    }
  }

}
