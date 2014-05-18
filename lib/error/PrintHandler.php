<?php
/*
 * This file is part of TechScore
 */

/**
 * An error handler which prints the error and its backtrace directly
 * to the screen as an XHTML page.
 *
 * @author Dayan Paez
 * @version 2012-01-28
 * @package error
 */
class PrintHandler {
  public static function handleErrors($errno, $errstr, $errfile, $errline) {
    require_once('xml5/TScorePage.php');
    $P = new TScorePage("Error");
    $P->addContent(new XPageTitle("Error!"));
    $P->addContent(new XUl(array(),
                           array(new XLi("$errno: $errstr"),
                                 new XLi("File: $errfile"),
                                 new XLi("Line: $errline"))));
    $P->addContent(new XHeading("Backtrace"));
    $P->addContent($tab = new XQuickTable(array(), array("No.", "Function", "File", "Line")));
    $tab->addRow(array($errno, $errstr, $errfile, $errline));
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $i => $trace) {
      $func = (isset($trace['function'])) ? $trace['function'] : 'N/A';
      $file = (isset($trace['file'])) ? $trace['file'] : 'N/A';
      $line = (isset($trace['line'])) ? $trace['line'] : 'N/A';
      $tab->addRow(array($i + 1, $func, $file, $line));
    }
    $P->printXML();
    DB::rollback();
    exit;
  }
  public static function handleExceptions(Exception $e) {
    require_once('xml5/TScorePage.php');
    $P = new TScorePage("Exception");
    $P->addContent(new XPageTitle("Exception!"));
    $P->addContent(new XUl(array(),
                           array(new XLi("Number: " . $e->getCode()),
                                 new XLi("Message: " . $e->getMessage()),
                                 new XLi("File: " . $e->getFile()),
                                 new XLi("Line: " . $e->getLine()))));
    $P->addContent($tab = new XQuickTable(array(), array("No.", "Function", "File", "Line")));
    foreach ($e->getTrace() as $i => $trace) {
      $func = (isset($trace['function'])) ? $trace['function'] : 'N/A';
      $file = (isset($trace['file'])) ? $trace['file'] : 'N/A';
      $line = (isset($trace['line'])) ? $trace['line'] : 'N/A';
      $tab->addRow(array(($i + 1), $func, $file, $line));
    }
    $P->printXML();
    DB::rollback();
    exit;
  }
  public static function handleFatal() {
    $error = error_get_last();
    if ($error !== null) {
      self::handleErrors($error['type'], $error['message'], $error['file'], $error['line']);
    }
  }
  public static function registerErrors($errors) {
    return set_error_handler("PrintHandler::handleErrors", $errors);
  }
  public static function registerExceptions() {
    return set_exception_handler("PrintHandler::handleExceptions");
  }
  public static function registerFatalHandler() {
    register_shutdown_function("PrintHandler::handleFatal");
  }
  public static function registerAll($errors) {
    self::registerErrors($errors);
    self::registerExceptions();
    self::registerFatalHandler();
  }
}
?>