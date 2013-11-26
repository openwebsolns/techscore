<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2010-09-18
 * @package scripts
 */

require_once('AbstractScript.php');

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
    require_once('public/UpdateManager.php');

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

  protected $cli_opts = '<id> [<id> ... ] | --all';
  protected $cli_usage = '  <id>    the specific regatta\
  --all   update all the regattas';
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');
  require_once('regatta/Regatta.php');

  $P = new RenameTeams();
  $opts = $P->getOpts($argv);

  // Get admin user
  $admins = DB::getAdmins();
  if (count($admins) == 0)
    throw new TSScriptException("No admin users exist in the system.");
  Conf::$USER = $admins[0];

  $regs = array();
  foreach ($opts as $opt) {
    if ($opt == '--all') {
      $regs = DB::getAll(DB::$PUBLIC_REGATTA);
      break;
    }
    $reg = DB::getRegatta($opt);
    if ($reg === null)
      throw new TSScriptException("Invalid regatta ID: $opt");
    $regs[] = $reg;
  }

  if (count($regs) == 0)
    throw new TSScriptException("No regattas specified.");

  foreach ($regs as $reg)
    $P->run($reg);
}
?>