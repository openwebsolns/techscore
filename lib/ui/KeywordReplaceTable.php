<?php
namespace ui;

use \Account;
use \DB;

use \XQuickTable;

/**
 * A handy table offering fresh replacements.
 *
 * @author Dayan Paez
 * @version 2015-11-11
 */
class KeywordReplaceTable extends XQuickTable {

  const ID = 'keyword-replace';

  private static $keywords = array(
    '{FULL_NAME}' => "Full name of user",
    '{FIRST_NAME}' => "First name of user",
    '{LAST_NAME}' => "Last name of user",
    '{SCHOOL}' => "User's school",
    '{ROLE}' => "Account role (coach, etc)",
  );

  public function __construct(Account $user, School $school = null) {
    parent::__construct(
      array('id' => self::ID),
      array("Keyword", "Description", "Example")
    );

    if ($school === null) {
      $school = $user->getFirstSchool();
    }
    foreach (self::$keywords as $kw => $desc) {
      $this->addRow(
        array(
          $kw,
          $desc,
          DB::keywordReplace(
            $kw,
            $user,
            $school
          )
        )
      );
    }
  }
}