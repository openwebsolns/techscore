<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package xml
 */

require_once('xml/XmlLibrary.php');
require_once('xml5/TS.php');

/**
 * Encapsulates an announcement, which constitutes a message and a
 * message type.
 *
 */
class Announcement extends Para {

  private $message;
  private $type;

  const VALID = "valid";
  const ERROR = "error";
  const WARNING = "warning";

  public function __construct($message, $type = null) {
    parent::__construct("");
    if (!$type) $type = self::VALID;
    $img = null;
    switch ($type) {
    case "valid":
      $img = new XImg("/img/check.png", "Check!");
      break;

    case "error":
      $img = new XImg("/img/error.png", "Error!");
      break;

    default:
      $img = new XImg("/img/warn.png",  "Warning");
    }
    $this->addChild($img);
    $this->addChild(new XText($message));
    $this->message = $message;
    $this->type    = $type;
    $this->addAttr('class', $type);
  }

  /**
   * Returns the string representation of this announcement
   *
   * @return String string representation
   */
  public function __toString() {
    return sprintf("%s:%s", $this->type, $this->message);
  }

  /**
   * Parses the string representation of announcement and returns a
   * new Announcement object
   *
   * @param String $string representation of announcement
   * @return Announcement an announcement object (of type valid by
   * default)
   */
  public static function parse($str) {
    $val = explode(":", $str);
    if (count($val) == 1) {
      return new Announcement($val[0], Announcement::VALID);
    }
    switch (strtolower($val[0])) {
    case Announcement::ERROR:
      return new Announcement(trim($val[1]), Announcement::ERROR);

    case Announcement::WARNING:
      return new Announcement(trim($val[1]), Announcement::WARNING);

    default:
      return new Announcement(trim($val[1]), Announcement::VALID);
    }
  }
}

?>