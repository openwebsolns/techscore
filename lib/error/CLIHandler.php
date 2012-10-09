<?php
/*
 * This file is part of TechScore
 */

/**
 * An error handler which prints the error and its backtrace directly
 * to standard output as a nice tree.
 *
 * @author Dayan Paez
 * @version 2012-01-28
 * @package error
 */
class CLIHandler {
  public static function handleErrors($errno, $errstr, $errfile, $errline) {
    printf("(EE) + %s\n", str_replace("\n", "\n     | ", wordwrap($errstr)));

    $fmt = "     | %8s: %s\n";
    printf($fmt, "Time",   date('Y-m-d H:i:s'));
    printf($fmt, "Number", $errno);
    printf($fmt, "File",   $errfile);
    printf($fmt, "Line",   $errline);
    foreach (debug_backtrace() as $list) {
      echo "     +--------------------\n";
      foreach (array('file', 'line', 'class', 'function') as $index) {
        if (isset($list[$index]))
          printf($fmt, ucfirst($index), $list[$index]);
      }
    }
    DB::rollback();
    exit;
  }
  public static function handleExceptions(Exception $e) {
    printf("(EX) + %s\n", $e->getMessage());
    $fmt = "     | %8s: %s\n";
    printf($fmt, "Time", date('Y-m-d H:i:s'));
    printf($fmt, "Number", $e->getCode());
    printf($fmt, "File", $e->getFile());
    printf($fmt, "Line", $e->getLine());
    foreach ($e->getTrace() as $i => $trace) {
      echo "     +--------------------\n";
      foreach (array('file', 'line', 'class', 'function') as $index) {
        if (isset($list[$index]))
          printf($fmt, ucfirst($index), $list[$index]);
      }
    }
    DB::rollback();
    exit;
  }
  public static function registerErrors($errors) {
    return set_error_handler("CLIHandler::handleErrors", $errors);
  }
  public static function registerExceptions() {
    return set_exception_handler("CLIHandler::handleExceptions");
  }
}
?>