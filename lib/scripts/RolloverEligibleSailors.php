<?php
namespace scripts;

use \DateTime;
use \InvalidArgumentException;

use \DB;
use \Sailor;
use \Sailor_Season;
use \Season;
use \TSScriptException;

use \eligibility\EligibilityCalculatorFactory;

/**
 * For each sailor in given season (default = current), determine if
 * they are eligible to sail "next" season, and add them to that
 * season if so.
 */
class RolloverEligibleSailors extends AbstractScript {

  private $dryRun = false;

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dryRun = ($flag !== false);
  }

  /**
   * Perform auto rollover
   *
   * @param Season $season usually the current season
   */
  public function run(Season $season) {
    $previousSeason = $season->previousSeason();
    if ($previousSeason === null) {
      throw new TSScriptException("No previous season exists.");
    }

    $calculator = EligibilityCalculatorFactory::build();
    foreach ($previousSeason->getRegisteredSailors() as $sailor) {
      $result = $calculator->checkEligibility($sailor, $season);
      self::errln(sprintf('%7d %s: %s (%s)', $sailor->id, $sailor, $result->isEligible() ? "yes" : "no", $result->getReason()), 2);
      if (!$this->dryRun) {
        if ($result->isEligible()) {
          $entry = Sailor_Season::create($sailor, $season);
          DB::set($entry);
        }
      }
    }
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $season = null;
    while (count($opts) > 0) {
      $opt = array_shift($opts);
      switch ($opt) {
      case '-n':
      case '--dry-run':
        $this->setDryRun(true);
        break;

      case '--auto':
        $today = new DateTime();
        $season = Season::forDate($today);
        if ($season === null) {
          throw new TSScriptException("No current season.");
        }

        if ($today->format('Y-m-d') !== $season->start_date->format('Y-m-d')) {
          self::errln('Today is not the start of the current season.');
          return;
        }

        try {
          // do not penalize unconfigured calculator
          EligibilityCalculatorFactory::build();
        } catch (InvalidArgumentException $e) {
          self::errln($e->getMessage());
          return;
        }

        break;

      case '--season':
        if (count($opts) === 0) {
          throw new TSScriptException("Missing argument for season.");
        }
        $id = array_shift($opts);
        $season = DB::getSeason($id);
        if ($season === null) {
          throw new TSScriptException(sprintf("Invalid season ID provided '%s'", $id));
        }
        break;

      default:
        throw new TSScriptException("Invalid argument: $opt");
      }
    }

    if ($season === null) {
      throw new TSScriptException("No season specified.");
    }
    $this->run($season);
  }

  protected $cli_opts = '[-n] [--season season|--auto]';
  protected $cli_usage = 'Registers existing sailors for next season based on eligibility criteria.

If run with --auto, it will glean the season from the current date,
and then ONLY run if it is the firt day of that season. Otherwise,
the --season argument is required.

  --auto           Suitable for a cron-job to prepare new season
  --season season  Calculate eligbility for given season
  -n, --dry-run    Simulate actual run; implies -v
';
}
