<?php
namespace scripts;

use \Account;
use \Conf;
use \DateTime;
use \DB;
use \Season;
use \STN;
use \TSScriptException;

/**
 * Sends mail to users whose team(s) are missing RP information.
 *
 * 2015-11-12: Send message for regattas that end today.
 *
 * @author Dayan Paez
 * @version 2014-10-21
 */
class RemindMissingRP extends AbstractScript {

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
    if (DB::g(STN::MAIL_MISSING_RP_REMINDER) === null) {
      self::errln("No e-mail template for missing RP reminder (MAIL_MISSING_RP_REMINDER).");
      return;
    }

    $season = Season::forDate(DB::T(DB::NOW));
    if ($season === null) {
      self::errln("No current season.");
      return;
    }

    $schools = array();  // cache of map of school ID to list of accounts
    $users = array();    // map of ID to user
    $regattas = array(); // map of reg ID to regatta
    $missing = array();  // map of user ID to (map of reg ID to list
                         // of missing team names)

    $threshold = new DateTime();
    $threshold = $threshold->format('Y-m-d');
    foreach ($season->getRegattas() as $reg) {
      if ($reg->end_date->format('Y-m-d') == $threshold) {
        foreach ($reg->getTeamsMissingRpComplete() as $team) {
          $school = $team->school;

          if (!isset($schools[$school->id]))
            $schools[$school->id] = $school->getUsers(Account::STAT_ACTIVE, false);
          foreach ($schools[$school->id] as $acc) {
            if (!isset($missing[$acc->id])) {
              $users[$acc->id] = $acc;
              $missing[$acc->id] = array();
            }
            if (!isset($missing[$acc->id][$reg->id])) {
              $missing[$acc->id][$reg->id] = array();
              $regattas[$reg->id] = $reg;
            }

            $missing[$acc->id][$reg->id][] = $team;
          }
        }
      }
    }

    if (count($missing) == 0) {
      self::errln("No regattas missing RP.");
      return;
    }

    foreach ($missing as $user_id => $list) {
      $user = $users[$user_id];
      if (!$this->dry_run) {
        $subject = sprintf("[%s] Please enter RP for your teams' regattas", DB::g(STN::APP_NAME));
        $mes = str_replace('{BODY}',
                           $this->getMessage($user, $regattas, $list),
                           DB::keywordReplace(DB::g(STN::MAIL_MISSING_RP_REMINDER), $user, $user->getFirstSchool()));
        DB::mailAccount($user, $subject, $mes);
      }
      self::errln(sprintf("Sent email to %s (%s) regarding %d regatta(s).", $user, $user->email, count($list)));
    }
  }

  /**
   * Prepares a message to be sent to a user regarding pending regattas
   *
   * @param Account $user the user
   * @param Array:Regatta $regs the list of regattas, indexed by ID
   * @param Array:Const $missing look-up table of what is missing for
   * each regatta, indexed by regatta ID
   */
  private function getMessage(Account $user, Array $regs, Array $missing) {
    $body = "";
    $i = 0;
    foreach ($missing as $reg_id => $teams) {
      if ($i > 0)
        $body .= "\n\n";
      $body .= sprintf("*%s* https://%s/score/%s/missing\n", $regs[$reg_id]->name, Conf::$HOME, $reg_id);
      foreach ($teams as $team)
        $body .= sprintf("\n - %s", $team);

      $i++;
    }
    return $body;
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    foreach ($opts as $opt) {
      if ($opt == '-n' || $opt == '--dry-run') {
        $this->setDryRun(true);
      }
      else {
        throw new TSScriptException("Invalid argument: $opt");
      }
    }
    $this->run();
  }

  protected $cli_opts = '[-n]';
  protected $cli_usage = ' -n, --dry-run  Do not send mail';
}
