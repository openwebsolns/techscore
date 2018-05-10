<?php
namespace scripts;

use \DateTime;
use \DB;
use \DBCond;
use \Exception;
use \Metric;
use \TSScriptException;

/**
 * Removes "old" metric entries.
 *
 * @author Dayan Paez
 * @version 2018-05-10
 */
class CleanupMetrics extends AbstractScript {

  const DEFAULT_THRESHOLD_IN_DAYS = 90;

  /**
   * @var DateTime Any sessions PRIOR to this date will be deleted.
   */
  private $latestDateToKeep;

  public function run() {
    $threshold = $this->getLatestDateToKeep();
    DB::removeAll(
      new Metric(),
      new DBCond('published_on', $threshold, DBCond::LT)
    );
    $this->errln(sprintf("Deleted metrics prior to %s.", $threshold->format('r')));
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    while (count($opts) > 0) {
      $opt = array_shift($opts);
      switch ($opt) {
      case '--date':
      case '-d':
        if (count($opts) == 0) {
          throw new TSScriptException("Missing threshold date.");
        }
        try {
          $this->setLatestDateToKeep(new DateTime(array_shift($opts)));
        }
        catch (Exception $e) {
          throw new TSScriptException($e->getMessage());
        }
        break;

      default:
        throw new TSScriptException("Unknown argument: $opt.");
      }
    }
    $this->run();
  }

  public function setLatestDateToKeep(DateTime $date) {
    $this->latestDateToKeep = $date;
  }

  private function getLatestDateToKeep() {
    if ($this->latestDateToKeep === null) {
      $this->latestDateToKeep = new DateTime(sprintf("%d days ago", self::DEFAULT_THRESHOLD_IN_DAYS));
    }
    return $this->latestDateToKeep;
  }

  protected $cli_opts = '[--date]';
  protected $cli_usage = ' -d, --date <date>   delete anything older than this date.';
}
