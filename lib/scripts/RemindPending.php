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
 * 2013-10-07: Include missing RP regattas
 *
 * @author Dayan Paez
 * @version 2013-10-02
 */
class RemindPending extends AbstractScript {

  const PENDING = 1;
  const MISSING_RP = 2;

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
    $missing = array();  // map of reg ID to what is missing

    $threshold = new DateTime('2 days ago');
    foreach ($season->getRegattas() as $reg) {
      $notify = 0;
      if ($reg->end_date < $threshold && $reg->hasFinishes() && $reg->finalized === null)
        $notify |= self::PENDING;
      $rp = $reg->getRpManager();
      if (!$rp->isComplete())
        $notify |= self::MISSING_RP;

      if ($notify > 0) {
        // Notify every account affiliated with the given school
        foreach ($reg->getHosts() as $host) {
          if (!isset($schools[$host->id]))
            $schools[$host->id] = $host->getUsers(Account::STAT_ACTIVE);
          foreach ($schools[$host->id] as $acc) {
            if (!isset($users[$acc->id])) {
              $users[$acc->id] = $acc;
              $regattas[$acc->id] = array();
            }
            $regattas[$acc->id][] = $reg;
            $missing[$reg->id] = $notify;
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
        $subject = (count($regattas[$id]) == 1) ?
          sprintf("[Techscore] Please finalized %s", $regattas[$id][0]->name) :
          "[Techscore] Please finalize your regattas";
        DB::mail($user->id,
                 $subject,
                 $this->getMessage($user, $regattas[$id], $missing));
      }
      self::errln(sprintf("Sent email to %s (%s) regarding %d regatta(s).", $user, $user->id, count($regattas[$id])));
    }
  }

  /**
   * Prepares a message to be sent to a user regarding pending regattas
   *
   * @param Account $user the user
   * @param Array:Regatta $regs the list of regattas
   * @param Array:Const $missing look-up table of what is missing for
   * each regatta
   */
  private function getMessage(Account $user, Array $regs, Array $missing) {
    $body = sprintf("Dear %s,

You are receiving this message because one or more of your regattas are not yet finalized. All official regattas *must* be finalized, and all RP information must be accounted for, in order to be included in reports and on the website.

Below is a list of regattas that need your attention, as well as an indication of what needs to be addressed:

",
                    $user);
    foreach ($regs as $reg) {
      $body .= sprintf("%s\n", $reg->name);
      if ($missing[$reg->id] & self::PENDING)
        $body .= sprintf("Finalize:   https://%s/score/%s/finalize\n", Conf::$HOME, $reg->id);
      if ($missing[$reg->id] & self::MISSING_RP)
        $body .= sprintf("Missing RP: https://%s/score/%s/missing\n", Conf::$HOME, $reg->id);
      $body .= "\n";
    }
    $body .= sprintf("Please take a minute to log in using the links above. If you have any questions, contact Danielle Richards at intersectionals@collegesailing.org.

Thank you for your time,
--
%s Administration",
                     DB::g(STN::APP_NAME));
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
