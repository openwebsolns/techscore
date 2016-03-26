<?php
/*
 * This file is part of Techscore
 */



/**
 * User editable, DPEditor-enabled, text element
 *
 * @author Dayan Paez
 * @version 2013-03-12
 */
class Text_Entry extends DBObject {
  public $plain;
  public $html;

  const ANNOUNCEMENTS = 'announcements';
  const WELCOME = 'welcome';
  const GENERAL_404 = '404';
  const SCHOOL_404 = 'school404';
  const EULA = 'eula';
  const REGISTER_MESSAGE = 'register';
  const SAILOR_REGISTER_MESSAGE = 'sailor_register';

  /**
   * Fetches list of known sections
   *
   * @return Map
   */
  public static function getSections() {
    return array(self::ANNOUNCEMENTS => "Announcement",
                 self::REGISTER_MESSAGE => "Registration Message",
                 self::SAILOR_REGISTER_MESSAGE => "Student Registration Welcome",
                 self::EULA => "EULA",
                 self::WELCOME => "Public Welcome",
                 self::GENERAL_404 => "404 Page",
                 self::SCHOOL_404 => "School 404 Page");
  }
}
