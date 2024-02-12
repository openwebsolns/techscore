<?php
namespace error;

use \DB;

/**
 * An error handler which prints the error and its backtrace directly
 * to standard output as a nice tree.
 *
 * @author Dayan Paez
 * @version 2012-01-28
 * @package error
 */
class CLIHandler extends AbstractErrorHandler {

  public function handleExceptions($e) {
    printf("(EX) + %s\n", $e->getMessage());
    $fmt = "     | %8s: %s\n";
    printf($fmt, "Time", date('Y-m-d H:i:s'));
    printf($fmt, "Number", $e->getCode());
    printf($fmt, "File", $e->getFile());
    printf($fmt, "Line", $e->getLine());
    foreach ($e->getTrace() as $i => $list) {
      echo "     +--------------------\n";
      foreach (array('file', 'line', 'class', 'function') as $index) {
        if (isset($list[$index]))
          printf($fmt, ucfirst($index), $list[$index]);
      }
    }
    DB::rollback();
    exit(3);
  }
}
