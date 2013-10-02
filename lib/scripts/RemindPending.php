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
 * Sends mail to users regarding unfinalized regattas
 *
 * @author Dayan Paez
 * @version 2013-10-02
 */
class RemindPending extends AbstractScript {

    private $dry_run = false;

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dry_run = ($flag !== false);
  }

  public function run() {
    $season = Season::forDate(DB::$NOW);
    if ($season === null) {
      self::errln("No current season.");
      return;
    }

    $schools = array();  // map of school ID to list of accounts
    $users = array();    // map of ID to user
    $regattas = array(); // map of user ID to list of regattas

    $threshold = new DateTime('2 days ago');
    foreach ($season->getRegattas() as $reg) {
      if ($reg->end_date < $threshold && $reg->hasFinishes() && $reg->finalized === null) {
        // Notify every account affiliated with the given school
        foreach ($reg->getHosts() as $host) {
          if (!isset($schools[$host->id]))
            $schools[$host->id] = DB::getAccountsForSchool($host, Account::STAT_ACTIVE);
          foreach ($schools[$host->id] as $acc) {
            if (!isset($users[$acc->id])) {
              $users[$acc->id] = $acc;
              $regattas[$acc->id] = array();
            }
            $regattas[$acc->id][] = $reg;
          }
        }
      }
    }

    if (count($regattas) == 0) {
      self::errln("No pending regattas.");
      return;
    }

    foreach ($users as $id => $user) {
      if (!$this->dry_run) {
        DB::mail($user->id,
                 "[Techscore] Please finalize your regattas",
                 $this->getMessage($user, $regattas[$id]));
      }
      self::errln(sprintf("Sent email to %s (%s) regarding %d regatta(s).", $user, $user->id, count($regattas[$id])));
    }
  }

  /**
   * Prepares a message to be sent to a user regarding pending regattas
   *
   * @param Account $user the user
   * @param Array:Regatta $regs the list of regattas
   */
  public function getMessage(Account $user, Array $regs) {
    $body = sprintf("Dear %s,

You are receiving this message because one or more of your regattas are not yet finalized. All official ICSA regattas *must* be finalized in order to be included in reports and on the website.

",
                    $user);
    foreach ($regs as $reg) {
      $body .= sprintf("%s\nhttps://%s/score/%s/finalize\n\n",
                       $reg->name,
                       Conf::$HOME,
                       $reg->id);
    }
    $body .= sprintf("Please take a minute to log in using the links above. If you have any questions, contact Danielle Richards at intersectionals@collegesailing.org.

Thank you for your time,
--
%s Administration",
                     Conf::$NAME);
    return $body;
  }

  protected $cli_opts = '[-n]';
  protected $cli_usage = ' -n, --dry-run  Do not perform deletion';
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new RemindPending();
  $opts = $P->getOpts($argv);
  foreach ($opts as $opt) {
    if ($opt == '-n' || $opt == '--dry-run')
      $P->setDryRun(true);
    else
      throw new TSScriptException("Invalid argument: $opt");
  }
  $P->run();
}
?>