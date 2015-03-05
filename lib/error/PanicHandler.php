<?php
/*
 * This file is part of TechScore
 */

/**
 * An error handler in need of a little Hitchhiker's Guide.
 *
 * Specially useful (and possibly only) for unit testing, where every
 * error encountered should throw an exception.
 *
 * @author Dayan Paez
 * @version 2015-03-04
 * @package error
 */
class PanicHandler {
  public static function handleErrors($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
  }
  public static function handleExceptions(Exception $e) {
    throw $e;
  }
  public static function handleFatal() {
    $error = error_get_last();
    if ($error !== null) {
      self::handleErrors($error['type'], $error['message'], $error['file'], $error['line']);
    }
  }
  public static function registerErrors($errors) {
    return set_error_handler("PanicHandler::handleErrors", $errors);
  }
  public static function registerExceptions() {
    return set_exception_handler("PanicHandler::handleExceptions");
  }
  public static function registerFatalHandler() {
    register_shutdown_function("PanicHandler::handleFatal");
  }
  public static function registerAll($errors = E_ALL) {
    self::registerErrors($errors);
    self::registerExceptions();
    self::registerFatalHandler();
  }
}
?>