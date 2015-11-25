<?php
namespace scripts;

use \DateTime;
use \DateInterval;
use \DB;
use \DivisionPenalty;
use \Regatta;
use \STN;
use \Team;
use \UpdateManager;
use \UpdateRequest;

use \TSScriptException;

use \data\finalize\AggregatedFinalizeCriteria;
use \data\finalize\FinalizeCriterion;
use \data\finalize\FinalizeStatus;
use \scripts\tools\AutoFinalizeEmailPreparer;

/**
 * Automatically finalize regattas according to settings.
 *
 * @author Dayan Paez
 * @version 2015-10-28
 */
class AutoFinalize extends AbstractScript {

  /**
   * @var boolean true to not take any actions
   */
  private $dry_run = false;

  /**
   * @var DateTime get regattas that ended after this
   * date. Automatically populated if missing.
   */
  private $cutoffDate;

  /**
   * @var boolean true to add Missing RP penalty. Automatically
   * populated from settings if null.
   */
  private $shouldAssessPenalties;

  /**
   * @var FinalizeCriterion the criterion to use to determine if
   * regatta can be finalized. Auto-injected if missing.
   */
  private $finalizeCriterion;

  /**
   * @var String the comments to use for the penalty.
   */
  private $penaltyComments;

  /**
   * @var boolean is the auto-finalize functionality turned on?
   */
  private $isFeatureAllowed;

  /**
   * @var Array list of regattas to consider. Automatically injected.
   */
  private $regattas;

  /**
   * @var String set the template to use.
   */
  private $mailTemplate;

  /**
   * Sets dry run flag
   *
   * @param boolean $flag true to turn on
   */
  public function setDryRun($flag = false) {
    $this->dry_run = ($flag !== false);
  }

  public function setCutoffDate(DateTime $time) {
    $this->cutoffDate = $time;
  }

  private function getCutoffDate() {
    if ($this->cutoffDate == null) {
      $numDaysAgo = DB::g(STN::AUTO_FINALIZE_AFTER_N_DAYS);

      $this->cutoffDate = new DateTime();
      $this->cutoffDate->sub(new DateInterval(sprintf('P%dDT0H', $numDaysAgo)));
    }
    return $this->cutoffDate;
  }

  public function setAssessPenalties($flag) {
    $this->shouldAssessPenalties = ($flag !== false);
  }

  private function shouldAssessPenalties() {
    if ($this->shouldAssessPenalties === null) {
      $this->shouldAssessPenalties = DB::g(STN::AUTO_ASSESS_MRP_ON_AUTO_FINALIZE) !== null;
    }
    return $this->shouldAssessPenalties;
  }

  public function setFinalizeCriterion(FinalizeCriterion $criterion) {
    $this->finalizeCriterion = $criterion;
  }

  private function getFinalizeCriterion() {
    if ($this->finalizeCriterion == null) {
      $this->finalizeCriterion = new AggregatedFinalizeCriteria();
    }
    return $this->finalizeCriterion;
  }

  public function setPenaltyComments($comment) {
    $this->penaltyComments = (string) $comment;
  }

  private function getPenaltyComments() {
    if ($this->penaltyComments == null) {
      // TODO: get from setting?
      $this->penaltyComments = "Missing RP when regatta was finalized.";
    }
    return $this->penaltyComments;
  }

  public function setFeatureAllowed($flag) {
    $this->isFeatureAllowed = ($flag !== false);
  }

  private function isFeatureAllowed() {
    if ($this->isFeatureAllowed == null) {
      $this->isFeatureAllowed = (
        DB::g(STN::ALLOW_AUTO_FINALIZE) !== null
        && DB::g(STN::AUTO_FINALIZE_ENABLED) !== null
      );
    }
    return $this->isFeatureAllowed;
  }

  public function setRegattas(Array $regattas) {
    $this->regattas = $regattas;
  }

  private function getRegattas() {
    if ($this->regattas === null) {
      $cutoffDate = $this->getCutoffDate();
      self::errln("Cutoff date is " . $this->cutoffDate->format('Y-m-d'), 2);

      $this->regattas = DB::getPastRegattasThatEndedAfter($cutoffDate);
    }
    return $this->regattas;
  }

  public function setMailTemplate($template) {
    $this->mailTemplate = $template;
  }

  private function getMailTemplate() {
    if ($this->mailTemplate === null) {
      $this->mailTemplate = DB::g(STN::MAIL_AUTO_FINALIZE_PENALIZED);
    }
    return $this->mailTemplate;
  }

  private function getMailSubject() {
    return "[Techscore] Penalized teams";
  }

  /**
   * Collects all regattas in the past day(s) and finalize them.
   *
   * @throws TSScriptException if the feature is not available.
   */
  public function run() {
    if (!$this->isFeatureAllowed()) {
      self::errln("This feature is not available.");
      return;
    }

    $shouldAssessPenalties = $this->shouldAssessPenalties();
    $emailPreparer = new AutoFinalizeEmailPreparer();

    foreach ($this->getRegattas() as $regatta) {
      if ($regatta->finalized === null) {
        self::errln(sprintf("Regatta \"%s\" is not finalized", $regatta->name));
        if (!$this->canBeFinalized($regatta)) {
          self::errln(sprintf("Regatta \"%s\" does not meet the conditions to be finalized.", $regatta->name));
          continue;
        }

        $this->finalizeRegatta($regatta);

        if ($shouldAssessPenalties) {
          $this->assessPenaltiesForRegatta($regatta, $emailPreparer);
        }
      }
    }

    // Send emails
    $subject = $this->getMailSubject();
    $template = $this->getMailTemplate();
    if (!empty($template)) {
      foreach ($emailPreparer->getAccounts() as $account) {
        $body = $emailPreparer->getEmailBody($account);
        $message = DB::keywordReplace(
          $template,
          $account,
          $account->getFirstSchool()
        );
        $message = str_replace('{BODY}', $body, $message);

        self::errln(sprintf("Emailing %s", $account), 2);
        if (!$this->dry_run) {
          DB::mail($account->email, $subject, $message);
        }
      }
    }
  }

  private function finalizeRegatta(Regatta $regatta) {
    if (!$this->dry_run) {
      $regatta->finalized = DB::T(DB::NOW);
      DB::set($regatta);
    }
  }

  private function assessPenaltiesForRegatta(Regatta $regatta, AutoFinalizeEmailPreparer $emailPreparer) {
    $shouldRerankRegatta = false;

    // For customer obsession, re-calculate RP completeness
    // before assessing penalties
    $rpManager = $regatta->getRpManager();
    foreach ($regatta->getTeams() as $team) {
      if (!$rpManager->resetCacheComplete($team)) {
        self::errln(sprintf("Team \"%s\" is missing RP; assessing penalty.", $team));
        if (!$this->dry_run) {
          $shouldRerankRegatta = true;
          $this->penalizeTeam($regatta, $team);
        }
        $emailPreparer->addPenalizedTeam($team);
      }
      else {
        self::errln(sprintf("Team \"%s\" has completed RP.", $team), 2);
      }
    }

    if ($shouldRerankRegatta) {
      self::errln("Reranked regatta.", 2);
      $regatta->setRanks();

      require_once('public/UpdateManager.php');
      UpdateManager::queueRequest($regatta, UpdateRequest::ACTIVITY_SCORE);
    }
  }

  private function canBeFinalized(Regatta $regatta) {
    $criterion = $this->getFinalizeCriterion();
    if (!$criterion->canApplyTo($regatta)) {
      self::errln("FinalizeCriterion does not apply to regatta", 2);
      return true;
    }
    foreach ($criterion->getFinalizeStatuses($regatta) as $status) {
      if ($status->getType() == FinalizeStatus::ERROR) {
        self::errln(sprintf("ERROR FinalizeStatus: %s", $status->getMessage()), 2);
        return false;
      }
    }
    return true;
  }

  /**
   * Adds a missing RP penalty to given team using given regatta.
   *
   * @param Regatta $regatta the regatta object to use.
   * @param Team $team the team to penalize (should be in given regatta)
   */
  private function penalizeTeam(Regatta $regatta, Team $team) {
    if ($regatta->scoring == Regatta::SCORING_COMBINED) {
      $divisions = array(Division::A());
    }
    else {
      $divisions = $regatta->getDivisions();
    }
    foreach ($divisions as $division) {
      $penalty = new DivisionPenalty();
      $penalty->team = $team;
      $penalty->division = $division;
      $penalty->type = DivisionPenalty::MRP;
      $penalty->comments = $this->getPenaltyComments();
      $regatta->setDivisionPenalty($penalty);
    }
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    while (count($opts) > 0) {
      $opt = array_shift($opts);
      switch ($opt) {
      case '-n':
      case '--dry-run':
        $this->setDryRun(true);
        break;

      case '--turn-on':
        $this->setFeatureAllowed(true);
        break;

      case '--add-penalty':
        $this->setAssessPenalties(true);
        break;

      case '--no-add-penalty':
        $this->setAssessPenalties(false);
        break;

      case '--comment':
        if (count($opts) == 0) {
          throw new TSScriptException("Missing threshold argument.");
        }
        $this->setPenaltyComments(array_shift($opts));
        break;

      case '--mail-template':
        if (count($opts) == 0) {
          throw new TSScriptException("Missing threshold argument.");
        }
        $this->setMailTemplate(array_shift($opts));
        break;

      case '--no-mail':
        $this->setMailTemplate(null);
        break;

      case '--regattas':
        if (count($opts) == 0) {
          throw new TSScriptException("Missing threshold argument.");
        }
        $ids = explode(",", array_shift($opts));
        $regattas = array();
        foreach ($ids as $id) {
          $regatta = DB::getRegatta($id);
          if ($regatta === null) {
            throw new TSScriptException(sprintf("No regatta with ID=%s.", $id));
          }
          $regattas[] = $regatta;
        }
        $this->setRegattas($regattas);
        break;

      case '-t':
        if (count($opts) == 0) {
          throw new TSScriptException("Missing threshold argument.");
        }
        try {
          $threshold = new DateTime(array_shift($opts));
        }
        catch (Exception $e) {
          throw new TSScriptException("Unable to parse date argument to threshold.");
        }
        $this->setCutoffDate($threshold);
        break;

      case '--finalize-criterion':
        if (count($opts) == 0) {
          throw new TSScriptException("Missing threshold argument.");
        }
        $classname = array_shift($opts);
        if (!class_exists($classname)) {
          throw new TSScriptException(sprintf("Class \"%s\" does not exist.", $classname));
        }
        $this->setFinalizeCriterion(new $classname());
        break;

      default:
        throw new TSScriptException("Invalid argument: $opt");
      }
    }

    $this->run();
  }

  protected $cli_opts = '[-n] [--add-penalty] [--no-add-penalty] [-t time]';
  protected $cli_usage = 'Choosing regattas:

 -t time           Threshold to use as date
 --regattas <ids>  Comma-separated list of regatta IDs

Overriding penalties:

 --add-penalty     Assess MRP for teams with missing RP
 --no-add-penalty  Do not assess penalty
 --comment string  The penalty comment to use
 --mail-template template
                   The template to use when sending emails
 --no-mail         Turn off mail sending

Behavior:

 --finalize-criterion classname
                   Full classname of criterion to use

 --turn-on         Forces this feature to be allowed
 -n, --dry-run     Do not actually finalize';
}
