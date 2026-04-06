<?php
namespace scripts;

use \DB;
use \DBConnection;
use \MySQLi_Result;

/**
 * Provides access to run database commands.
 *
 * @author Dayan Paez
 * @created 2026-04-05 (Easter)
 */
class ExecDbQuery extends AbstractScript {

  public function __construct() {
    parent::__construct();
    $this->cli_opts = '[-v] <command>';
    $this->cli_usage = 'SQL commands to execute';
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    // ensure always some output
    ExecDbQuery::setVerbosity(max(1, ExecDbQuery::getVerbosity()));

    $this->run($opts);
  }

  /**
   * Execute the given queries.
   *
   * This is cribbed directly from PHP's own example documentation.
   */
  private function run(Array $queries) {
    $con = DB::connection();
    $con->commit();
    $con->multi_query(implode(' ', $queries));

    do {
      // store the result set in PHP
      if ($result = $con->store_result()) {
        $this->printTable($result);
        $result->free();
      }

      // print divider
      if ($con->more_results()) {
        self::errln("-----------------");
      }
    } while ($con->next_result());
  }

  /**
   * Pretty-print a table using the given results.
   *
   * @param $result with fields and rows
   */
  private function printTable(MySQLi_Result $result) {
    foreach ($result->fetch_fields() as $field) {
      $headers[] = $field->name;
    }
    self::errln(implode("\t", $headers));

    while ($row = $result->fetch_row()) {
      self::errln(implode("\t", $row));
    }
  }
}
