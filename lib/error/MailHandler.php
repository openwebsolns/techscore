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
    require_once('regatta/DB.php');
    DB::mail(Conf::$ADMIN_MAIL, sprintf("[%s Error]", Conf::$NAME), $body);

    // Prepare XHTML
    require_once('xml/TScorePage.php');
    $P = new TScorePage("Server error", Conf::$USER);
    $P->addContent(new XPageTitle("Server error"));
    $P->addContent(new XP(array(), "There was an error while handling your request. Administrators have been notified of the problem and it will be addressed as soon as possible."));
    $P->addContent(new XP(array(), "Sorry for the inconvenience."));
    $P->printXML();
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
    $body .= "====================\n";
    require_once('regatta/DB.php');
    DB::mail(Conf::$ADMIN_MAIL, sprintf("[%s Exception]", Conf::$NAME), $body);

    // Prepare XHTML
    require_once('xml/TScorePage.php');
    $P = new TScorePage("Server error", Conf::$USER);
    $P->addContent(new XPageTitle("Server error"));
    $P->addContent(new XP(array(), "There was an error while handling your request. Administrators have been notified of the problem and it will be addressed as soon as possible."));
    $P->addContent(new XP(array(), "Sorry for the inconvenience."));
    $P->printXML();
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