<?php
/**
 * This file is part of TechScore
 *
 */

/**
 * This class provides a set of tools for dealing with the server,
 * such as redirecting to previous page, if one exists, for example.
 * This has the advantage of delegating all the server-related
 * commands to one central location.
 *
 * @author Dayan Paez
 * @version 2010-07-24
 */
class WebServer {

  /**
   * Moves to the referer, or to the given default page
   *
   * @param String $default the page to move if no valid referer
   * found
   */
  public static function goBack($default = ".") {
    if (isset($_SERVER['HTTP_REFERER']))
      self::go($_SERVER['HTTP_REFERER']);
    self::go($default);
  }

  /**
   * Moves to the given address relative to HOME, unless a full URL
   * (starting with http/s) is given.
   *
   * @param String $addr the address to navigate to
   */
  public static function go($addr) {
    if (preg_match('/^https?:\/\//', $addr)) {
      header("Location: $addr");
      exit;
    }
    header(sprintf("Location: %s/%s", HOME, $addr));
    exit;
  }
}
?>