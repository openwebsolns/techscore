<?php
/*
 * This file is part of TechScore
 */

/**
 * Handles errors and exceptions by sending mail and providing a
 * generic message to the user
 *
 * @author Dayan Paez
 * @version 2012-01-28
 * @package error
 */
class MailHandler {
  public static $CENSORED_POST = array('userid', 'pass');
  public static function handleErrors($errno, $errstr, $errfile, $errline) {
    $fmt = "%6s: %s\n";
    $body  = sprintf($fmt, "Time",   date('Y-m-d H:i:s'));
    $body .= sprintf($fmt, "Number", $errno);
    $body .= sprintf($fmt, "String", $errstr);
    $body .= sprintf($fmt, "File",   $errfile);
    $body .= sprintf($fmt, "Line",   $errline);
    $body .= @sprintf($fmt, "Request", $_SERVER['REQUEST_URI']);
    foreach (debug_backtrace() as $list) {
      $body .= "--------------------\n";
      foreach (array('file', 'line', 'class', 'function') as $index) {
        if (isset($list[$index]))
          $body .= sprintf($fmt, ucfirst($index), $list[$index]);
      }
    }
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
      $body .= "--------------------\n";
      $body .= "Post:\n";
      
      foreach ($_POST as $key => $val) {
        if (in_array($key, self::$CENSORED_POST))
          $val = str_repeat("*", mb_strlen($val));
        elseif (is_array($val))
          $val = serialize($val);
        $body .= sprintf("%30s: %s\n", $key, $val);
      }
    }
    require_once('regatta/DB.php');
    DB::mail(Conf::$ADMIN_MAIL, sprintf("[Techscore Error] %s", $errstr), $body);

    // Prepare XHTML
    require_once('xml5/TScorePage.php');
    $P = new TScorePage("Server error", Conf::$USER);
    $P->addContent(new XPageTitle("Server error"));
    $P->addContent(new XP(array(), "There was an error while handling your request. Administrators have been notified of the problem and it will be addressed as soon as possible."));
    $P->addContent(new XP(array(), "Sorry for the inconvenience."));
    http_response_code(500);
    $P->printXML();
    DB::rollback();
    exit;
  }
  public static function handleExceptions(Exception $e) {
    $fmt = "%6s: %s\n";
    $body  = sprintf($fmt, "Time",   date('Y-m-d H:i:s'));
    $body .= sprintf($fmt, "Number", $e->getCode());
    $body .= sprintf($fmt, "String", $e->getMessage());
    $body .= sprintf($fmt, "File",   $e->getFile());
    $body .= sprintf($fmt, "Line",   $e->getLine());
    $body .= sprintf($fmt, "Request", $_SERVER['REQUEST_URI']);
    $body .= "--------------------\n";
    $body .= sprintf($fmt, "Trace",  $e->getTraceAsString());
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
      $body .= "--------------------\n";
      $body .= "Post:\n";
      
      foreach ($_POST as $key => $val) {
        if (in_array($key, self::$CENSORED_POST))
          $val = str_repeat("*", mb_strlen($val));
        elseif (is_array($val))
          $val = serialize($val);
        $body .= sprintf("%30s: %s\n", $key, $val);
      }
    }
    require_once('regatta/DB.php');
    DB::mail(Conf::$ADMIN_MAIL, sprintf("[Techscore Exception] %s", $e->getMessage()), $body);

    // Prepare XHTML
    require_once('xml5/TScorePage.php');
    $P = new TScorePage("Server error", Conf::$USER);
    $P->addContent(new XPageTitle("Server error"));
    $P->addContent(new XP(array(), "There was an error while handling your request. Administrators have been notified of the problem and it will be addressed as soon as possible."));
    $P->addContent(new XP(array(), "Sorry for the inconvenience."));
    http_response_code(500);
    $P->printXML();
    DB::rollback();
    exit;
  }
  public static function registerErrors($errors) {
    return set_error_handler("MailHandler::handleErrors", $errors);
  }
  public static function registerExceptions() {
    return set_exception_handler("MailHandler::handleExceptions");
  }
}
?>