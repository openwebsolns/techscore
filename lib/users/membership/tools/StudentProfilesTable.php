<?php
namespace users\membership\tools;

use \Member;
use \XQuickTable;

/**
 * Table displaying student profiles with links to edit them.
 */
class StudentProfilesTable extends XQuickTable {

  const CLASSNAME = 'student-profiles';

  /**
   * Creates a new table using given non-zero list of profiles.
   *
   * @param Array:StudentProfile $profiles list of profiles
   * @param function $nameTransformer function which takes in the
   *   profile's name and profile object, and returns Xmlable field to
   *   embed. Use this, for example, to wrap the name in a link to
   *   edit the profile.
   */
  public function __construct($profiles, $nameTransformer) {
    parent::__construct(
      array('class' => self::CLASSNAME),
      array(
        "Name",
        "Gender",
        "Birth date",
        "School",
        "Graduation Year",
        "Status",
      )
    );
    foreach ($profiles as $profile) {
      $name = $profile->getName();
      if ($nameTransformer !== null) {
        $name = $nameTransformer($name, $profile);
      }
      $this->addRow(
        array(
          $name,
          Member::getGender($profile->gender),
          $profile->birth_date ? $profile->birth_date->format('Y-m-d') : 'N/A',
          $profile->graduation_year,
          $profile->school,
          $profile->status,
        )
      );
    }
  }
}