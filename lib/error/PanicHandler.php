<?php
namespace error;

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
class PanicHandler extends AbstractErrorHandler {

  public function handleExceptions($e) {
    throw $e;
  }

  public function handleError(
    int $errno,
    string $errstr,
    string $errfile = '',
    int $errline = 0
  ): bool {
    // Honor the @ operator and current error_reporting() configuration.
    // Returning true tells PHP the error was handled (silently ignored).
    if (!(error_reporting() & $errno)) {
        return true;
    }

    parent::handleError($errno, $errstr, $errfile, $errline);
  }
}
