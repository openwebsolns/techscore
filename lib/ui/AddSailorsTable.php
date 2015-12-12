<?php
namespace ui;

use \xml5\GraduationYearInput;

use \Sailor;

use \XQuickTable;
use \XSelect;
use \XTextInput;

/**
 * A table for adding one (or more) sailors.
 *
 * @author Dayan Paez
 * @version 2015-12-11
 */
class AddSailorsTable extends XQuickTable {

  const ID = 'add-sailors-table';
  const CLASSNAME = 'growable';
  const CLASSNAME_TEMPLATE_ROW = 'growable-template';
  const CLASSNAME_SCHOOL_SELECT = 'school-select no-mselect';

  public function __construct($rootName, $schools) {
    parent::__construct(
      array(
        'class' => self::CLASSNAME,
        'id' => self::ID,
      ),
      array(
        "School",
        "First name",
        "Last name",
        "Year",
        "Gender",
      )
    );
    $this->fill($rootName, $schools);
  }

  private function fill($rootName, $schools) {
    $root = sprintf('%s[0]', $rootName);
    $schoolSelect = XSelect::fromDBM(
      sprintf('%s[school]', $root),
      $schools,
      null,
      array('class' => self::CLASSNAME_SCHOOL_SELECT),
      ''
    );
    $genderSelect = XSelect::fromArray(
      sprintf('%s[gender]', $root),
      Sailor::getGenders()
    );
    $this->addRow(
      array(
        $schoolSelect,
        new XTextInput(sprintf('%s[first_name]', $root), ''),
        new XTextInput(sprintf('%s[last_name]', $root), ''),
        new GraduationYearInput(sprintf('%s[year]', $root), ''),
        $genderSelect,
      ),
      array(
        'class' => self::CLASSNAME_TEMPLATE_ROW,
      )
    );
  }

}