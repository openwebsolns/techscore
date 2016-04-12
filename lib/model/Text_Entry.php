<?php
use \model\AbstractObject;

/**
 * User editable, DPEditor-enabled, text element
 *
 * @author Dayan Paez
 * @version 2013-03-12
 */
class Text_Entry extends AbstractObject {
  public $plain;
  public $html;

  const ANNOUNCEMENTS = 'announcements';
  const WELCOME = 'welcome';
  const GENERAL_404 = '404';
  const SCHOOL_404 = 'school404';
  const EULA = 'eula';
  const SAILOR_EULA = 'sailor_eula';
  const REGISTER_MESSAGE = 'register';
  const SAILOR_REGISTER_MESSAGE = 'sailor_register';

  /**
   * Fetches list of known sections
   *
   * @return Map
   */
  public static function getSections() {
    $allowSailorRegistration = DB::g(STN::ALLOW_SAILOR_REGISTRATION) !== null;
    $list = array(
      self::ANNOUNCEMENTS => "Announcement",
      self::REGISTER_MESSAGE => "Registration Message",
    );
    if ($allowSailorRegistration) {
      $list[self::SAILOR_REGISTER_MESSAGE] = "Student Registration Welcome";
    }
    $list[self::EULA] = "EULA";
    if ($allowSailorRegistration) {
      $list[self::SAILOR_EULA] = "EULA for Students";
    }
    $list[self::WELCOME] = "Public Welcome";
    $list[self::GENERAL_404] = "404 Page";
    $list[self::SCHOOL_404] = "School 404 Page";

    return $list;
  }
}
