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

  public function handleExceptions(Exception $e) {
    throw $e;
  }

}
