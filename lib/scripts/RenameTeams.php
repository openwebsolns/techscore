<?php
namespace scripts;

use \Conf;
use \DB;
use \EditTeamsPane;
use \FullRegatta;
use \TSScriptException;
use \UpdateManager;
use \UpdateRequest;

/**
 * Programatically renames the teams in a given regatta
 *
 * @author Dayan Paez
 * @created 2013-11-26
 */
class RenameTeams extends AbstractScript {

  /**
   * Renames the teams using school preferences
   *
   * @param FullRegatta $reg the regatta whose teams to rename
   */
  public function run(FullRegatta $reg) {
    if (Conf::$USER === null)
      throw new TSScriptException("No user registered.");

    require_once('tscore/EditTeamsPane.php');

    $P = new EditTeamsPane(Conf::$USER, $reg);
    $schools = array();
    foreach ($reg->getTeams() as $team) {
      if (!isset($schools[$team->school->id])) {
        $c = $P->fixTeamNames($team->school);
        if (count($c) > 0) {
          UpdateManager::queueRequest($reg, UpdateRequest::ACTIVITY_TEAM, $team->school->id);
          self::errln(sprintf("Changed name(s) for school %s.", $team->school), 2);
        }
        $schools[$team->school->id] = $team->school;
      }
    }

    self::errln(sprintf("Updated teams for %s %s.", $reg->getSeason(), $reg->name));
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);

    Conf::$USER = DB::getRootAccount();

    $regs = array();
    foreach ($opts as $opt) {
      if ($opt == '--all') {
        $regs = DB::getAll(DB::T(DB::PUBLIC_REGATTA));
        break;
      }
      $reg = DB::getRegatta($opt);
      if ($reg === null) {
        throw new TSScriptException("Invalid regatta ID: $opt");
      }
      $regs[] = $reg;
    }

    if (count($regs) == 0) {
      throw new TSScriptException("No regattas specified.");
    }

    foreach ($regs as $reg) {
      $this->run($reg);
    }
  }

  protected $cli_opts = '<id> [<id> ... ] | --all';
  protected $cli_usage = '  <id>    the specific regatta\
  --all   update all the regattas';
}
