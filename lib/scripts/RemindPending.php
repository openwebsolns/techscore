<?php
namespace scripts;

use \Account;
use \Conf;
use \DB;
use \DateTime;
use \STN;
use \Season;
use \TSScriptException;

/**
 * Sends mail to users regarding unfinalized regattas
 *
 * 2013-10-07: Include missing RP regattas
 * 2014-10-21: Also send to *participants* with missing RP
 * 2015-11-12: Conditionally bundle missing RP data; only to hosts
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

  public function run(Array $userFilter = array()) {
    if (DB::g(STN::MAIL_UNFINALIZED_REMINDER) === null) {
      self::errln("No e-mail template for unfinalized reminder (MAIL_UNFINALIZED_REMINDER).");
      return;
    }

    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      self::errln("No current season.");
      return;
    }

    $includeMissingRp = (DB::g(STN::INCLUDE_MISSING_RP_IN_UNFINALIZED_REMINDER) !== null);

    $schools = array();  // map of school ID to list of accounts
    $users = array();    // map of ID to user
    $regattas = array(); // map of user ID to list of regattas
    $missing = array();  // map of reg ID to what is missing

    $threshold = new DateTime('2 days ago');
    foreach ($season->getRegattas() as $reg) {
      $notify = 0;
      if ($reg->end_date < $threshold) {
        if ($reg->hasFinishes() && $reg->finalized === null) {
          $notify |= self::PENDING;
        }
        if (!$reg->isRpComplete() && $includeMissingRp) {
          $notify |= self::MISSING_RP;
        }

        if ($notify > 0) {
          // Notify every account affiliated with the given school
          foreach ($reg->getHosts() as $host) {
            if (!isset($schools[$host->id]))
              $schools[$host->id] = $host->getUsers(Account::STAT_ACTIVE);
            foreach ($schools[$host->id] as $acc) {
              if (!array_key_exists($acc->id, $users)) {
                $users[$acc->id] = $acc;
                $regattas[$acc->id] = array();
              }
              $regattas[$acc->id][$reg->id] = $reg;
              $missing[$reg->id] = $notify;
            }
          }
        }
      }
    }

    if (count($regattas) === 0) {
      self::errln("No pending regattas.");
      return;
    }

    $userIdFilter = array();
    foreach ($userFilter as $user) {
      $userIdFilter[] = $user->id;
    }

    foreach ($users as $id => $user) {
      if (count($userIdFilter) === 0 || in_array($id, $userIdFilter)) {
        if (!$this->dry_run) {
          $subject = "[Techscore] Please finalize your regattas";
          if (count($regattas[$id]) === 1) {
            $regIds = array_keys($regattas[$id]);
            $subject = sprintf("[Techscore] Please finalize %s", $regattas[$id][$regIds[0]]->name);
          }
          $mes = str_replace(
            '{BODY}',
            $this->getMessage($user, $regattas[$id], $missing),
            DB::keywordReplace(DB::g(STN::MAIL_UNFINALIZED_REMINDER), $user, $user->getFirstSchool())
          );
          DB::mailAccount($user, $subject, $mes);
        }
        self::errln(sprintf("Sent email to %s (%s) regarding %d regatta(s).", $user, $user->email, count($regattas[$id])));
      }
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
    $body = "";
    foreach (array_values($regs) as $i => $reg) {
      if ($i > 0)
        $body .= "\n\n";
      $body .= sprintf("*%s*\n", $reg->name);
      if ($missing[$reg->id] & self::PENDING)
        $body .= sprintf("\n - Finalize:   https://%s/score/%s/finalize", Conf::$HOME, $reg->id);
      if ($missing[$reg->id] & self::MISSING_RP)
        $body .= sprintf("\n - Missing RP: https://%s/score/%s/missing", Conf::$HOME, $reg->id);
    }
    return $body;
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $users = array();
    while (count($opts) > 0) {
      $opt = array_shift($opts);
      if ($opt == '-n' || $opt == '--dry-run') {
        $this->setDryRun(true);
      }
      elseif ($opt === '--user') {
        if (count($opts) === 0) {
          throw new TSScriptException("Missing e-mail argument for --user");
        }
        $email = array_shift($opts);
        $user = DB::getAccountByEmail($email);
        if ($user === null) {
          throw new TSScriptException("Invalid user e-mail provided: $email");
        }
        $users[$user->id] = $user;
      }
      else {
        throw new TSScriptException("Invalid argument: $opt");
      }
    }
    $this->run($users);
  }

  protected $cli_opts = '[-n] [--user <email>]';
  protected $cli_usage = '
 -n, --dry-run  Do not send mail
 --user <email> E-mail of user to notify (may be specified multiple times)';
}
