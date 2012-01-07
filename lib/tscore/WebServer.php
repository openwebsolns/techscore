<?php
/*
 * This file is part of TechScore
 *
 * @package xml
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
  public static function goBack($default = "/") {
    if (isset($_SERVER['HTTP_REFERER']) &&
	strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) >= 0)
      self::go($_SERVER['HTTP_REFERER']);
    self::go($default);
  }

  /**
   * Moves to the given address, issuing the appropriate location
   * header and exiting.
   *
   * @param String $addr the address to navigate to
   */
  public static function go($addr) {
    /*
      if (preg_match('/^https?:\/\//', $addr)) {
      header("Location: $addr");
      exit;
      }
      header(sprintf("Location: %s/%s", Conf::$HOME, $addr));
    */
    header(sprintf("Location: %s", $addr));
    exit;
  }
}
?>