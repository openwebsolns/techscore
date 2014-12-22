<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2014-10-24
 * @package scripts
 */

require_once('AbstractScript.php');

/**
 * Sends mail to users whose team(s) are participating in future regatta
 *
 * @author Dayan Paez
 * @version 2014-10-24
 */
class RemindUpcoming extends AbstractScript {

  private $dry_run = false;

  /**
   * @var DateTime how far in the future to notify about
   */
  private $threshold;

  public function __construct() {
    parent::__construct();
    $this->setThreshold(new DateTime('2 days'));
  }

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dry_run = ($flag !== false);
  }

  public function setThreshold(DateTime $date) {
    if ($date <= DB::T(DB::NOW))
      throw new TSScriptException("Threshold date must be in the future");
    $this->threshold = $date;
  }

  public function run() {
    if (DB::g(STN::MAIL_UPCOMING_REMINDER) === null) {
      self::errln("No e-mail template for upcoming regatta reminder (MAIL_UPCOMING_REMINDER).");
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

    foreach ($season->getRegattas() as $reg) {
      if ($reg->start_time > DB::T(DB::NOW) && $reg->start_time < $this->threshold) {
        if ($reg->dt_status == Regatta::STAT_SCHEDULED) {
          self::errln(sprintf("Skipping regatta \"%s\" (%s) because it is in scheduled state.",
                              $reg->name, $reg->id), 2);
          continue;
        }
        if ($reg->finalized !== null) {
          self::errln(sprintf("Skipping regatta \"%s\" (%s) because it is finalized.",
                              $reg->name, $reg->id), 2);
          continue;
        }

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
        $subject = sprintf("[%s] Please enter RP for upcoming regattas", DB::g(STN::APP_NAME));
        $mes = str_replace('{BODY}',
                           $this->getMessage($user, $regattas, $list),
                           DB::keywordReplace(DB::g(STN::MAIL_UPCOMING_REMINDER), $user, $user->getFirstSchool()));
        DB::mail($user->email, $subject, $mes);
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
      $reg = $regs[$reg_id];

      if ($i > 0)
        $body .= "\n\n";

      $body .= sprintf("*%s* https://%s/score/%s/rp\n", $reg->name, Conf::$HOME, $reg_id);
      $body .= sprintf("\n - Date: %s", $reg->start_time->format('F j, Y \a\t H:i'));
      $body .= sprintf("\n - Host: %s", $reg->getHostVenue());
      if (count($teams) > 1)
        $body .= sprintf("\n - %d teams participating", count($teams));

      $i++;
    }
    return $body;
  }

  protected $cli_opts = '[-n] [-t time]';
  protected $cli_usage = ' -t time        Threshold to use as a date
 -n, --dry-run  Do not send mail';
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new RemindUpcoming();
  $opts = $P->getOpts($argv);
  $threshold = null;

  while (count($opts) > 0) {
    $opt = array_shift($opts);
    if ($opt == '-n' || $opt == '--dry-run')
      $P->setDryRun(true);
    elseif ($opt == '-t') {
      if (count($opts) == 0)
        throw new TSScriptException("Missing threshold argument");
      try {
        $threshold = new DateTime(array_shift($opts));
      }
      catch (Exception $e) {
        throw new TSScriptException("Unable to parse date argument to threshold");
      }
    }
    else
      throw new TSScriptException("Invalid argument: $opt");
  }

  if ($threshold !== null)
    $P->setThreshold($threshold);
  $P->run();
}
?>
