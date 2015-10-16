<?php
namespace error;

use \ErrorException;
use \Exception;

/**
 * Interface expected of all error handlers.
 *
 * @author Dayan Paez
 * @created 2015-10-15
 */
abstract class AbstractErrorHandler {

  /**
   * Handler for exceptions. Should exit afterwards.
   *
   * @param Exception $e the generic exception to handle.
   */
  abstract public function handleExceptions(Exception $e);

  /**
   * Handler for PHP errors. Default implementation: rethrow as
   * ErrorException.
   *
   * @param int $errno the type of error.
   * @param String $errstr the message.
   * @param String $errfile the file location.
   * @param String $errline the line location in the file.
   */
  public function handleErrors($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
  }

  /**
   * Handle fatal errors, if any. Default: forward to handleErrors.
   */
  public function handleFatal() {
    $error = error_get_last();
    if ($error !== null) {
      $this->handleErrors($error['type'], $error['message'], $error['file'], $error['line']);
    }
  }

  /**
   * Register this class' handleErrors method.
   *
   * @param int $errors the bitmask of error types to handle.
   */
  public function registerErrors($errors) {
    return set_error_handler(array($this, 'handleErrors'), $errors);
  }

  /**
   * Register this class' handleExceptions method.
   *
   */
  public function registerExceptions() {
    return set_exception_handler(array($this, 'handleExceptions'));
  }

  /**
   * Register this class' handleFatal method.
   *
   */
  public function registerFatalHandler() {
    register_shutdown_function(array($this, 'handleFatal'));
  }

  /**
   * Convenience method to register all handlers at once.
   *
   * @param int $errors the bitmask for the error handler.
   */
  public function registerAll($errors = null) {
    if ($errors === null) {
      $errors = E_ALL | E_STRICT | E_NOTICE;
    }
    $this->registerErrors($errors);
    $this->registerExceptions();
    $this->registerFatalHandler();
  }
}