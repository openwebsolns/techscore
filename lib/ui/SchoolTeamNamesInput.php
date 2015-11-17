<?php
namespace ui;

use \School;
use \Team_Name_Prefs;

use \XQuickTable;
use \XTextInput;

/**
 * Displays and allows input the set of given school's team names.
 *
 * Current implementation uses a table.
 *
 * @author Dayan Paez
 * @version 2015-11-16
 */
class SchoolTeamNamesInput extends XQuickTable {

  const COUNT_MINIMUM = 2;

  const CLASSNAME = 'team-names-input-table growable';
  const CLASSNAME_PRIMARY = 'team-names-input-table-primary';
  const CLASSNAME_NON_PRIMARY = 'growable-template';

  /**
   * Create a new table for given school.
   *
   * @param School $school the school whose team to display.
   * @param int $count minimum number of entries to allow.
   */
  public function __construct(School $school, $count = self::COUNT_MINIMUM) {
    parent::__construct(
      array('class' => self::CLASSNAME),
      array("", "Name")
    );
    $this->fill($school, $count);
  }

  private function fill(School $school, $maxCount) {
    $names = $school->getTeamNames();
    $count = count($names);
    $maxCount += $count;

    $commonInputAttrs = array(
      'maxlength' => 20,
      'pattern' => Team_Name_Prefs::REGEX_NAME,
      'title' => "Name must not end in a number",
    );

    for ($i = 0; $i < $maxCount; $i++) {
      $name = ($i < $count) ? $names[$i] : '';
      $label = '';
      $attrs = array('class' => self::CLASSNAME_NON_PRIMARY);
      $inputAttrs = $commonInputAttrs;
      if ($i == 0) {
        $label = "Primary";
        $attrs = array('class' => self::CLASSNAME_PRIMARY);
        $inputAttrs['required'] = 'required';
      }

      $this->addRow(
        array(
          $label,
          new XTextInput('name[]', $name, $inputAttrs)
        ),
        $attrs
      );
    }
  }

}