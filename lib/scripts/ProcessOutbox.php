<?php
namespace scripts;

use \Account;
use \DB;
use \Outbox;
use \RuntimeException;
use \School;
use \Season;
use \TSScriptException;

/**
 * This script, to be run from the command line as part of a scheduled
 * task, will process the outgoing request in the 'outbox' table,
 * delivering a mail message to the appropriate users, and adding a
 * message to their TechScore inbox.
 *
 * @author Dayan Paez
 * @version 2011-11-18
 * @package scripts
 */
class ProcessOutbox extends AbstractScript {

  private $sent = 0;

  /**
   * Send queued messages
   *
   */
  public function run() {
    $this->sent = 0;
    $num = 0;
    foreach (DB::getPendingOutgoing() as $outbox) {
      $num++;
      $this->process($outbox);
      self::errln(sprintf("Processed message %s.", $outbox->id), 2);
    }
    self::errln(sprintf("Processed %d requests, sending %d messages.", $num, $this->sent));
  }

  /**
   * Process outbox message, actually sending to users
   *
   */
  public function process(Outbox $outbox) {
    $sent_to_me = false;
    $other_admins = array();
    if ($outbox->copy_admin) {
      foreach (DB::getAdmins() as $admin) {
        if ($admin->id != $outbox->sender->id)
          $other_admins[$admin->id] = $admin;
      }
    }
          
    // all
    if ($outbox->recipients == Outbox::R_ALL) {
      foreach (DB::getConferences() as $conf) {
        foreach ($conf->getUsers(Account::STAT_ACTIVE) as $acc) {
          $this->send($outbox->sender, $acc, $outbox->subject, $outbox->content);
          if ($acc->id == $outbox->sender->id)
            $sent_to_me = true;
          unset($other_admins[$acc->id]);
        }
      }
    }
    // conference
    if ($outbox->recipients == Outbox::R_CONF) {
      foreach ($outbox->arguments as $id) {
        $conf = DB::getConference($id);
        if ($conf === null)
          throw new RuntimeException("Conference $id does not exist.");
        foreach ($conf->getUsers(Account::STAT_ACTIVE) as $acc) {
          $this->send($outbox->sender, $acc, $outbox->subject, $outbox->content);
          if ($acc->id == $outbox->sender->id)
            $sent_to_me = true;
          unset($other_admins[$acc->id]);
        }
      }
    }
    // schools
    if ($outbox->recipients == Outbox::R_SCHOOL) {
      foreach ($outbox->arguments as $id) {
        $school = DB::getSchool($id);
        if ($school === null)
          throw new RuntimeException("School $id does not exist.");
        foreach ($school->getUsers(Account::STAT_ACTIVE, false) as $acc) {
          $this->send($outbox->sender, $acc, $outbox->subject, $outbox->content, $school);
          if ($acc->id == $outbox->sender->id)
            $sent_to_me = true;
          unset($other_admins[$acc->id]);
        }
      }
    }
    // role
    if ($outbox->recipients == Outbox::R_ROLE) {
      foreach ($outbox->arguments as $role) {
        foreach (DB::getAccounts($role) as $acc) {
          $this->send($outbox->sender, $acc, $outbox->subject, $outbox->content);
          if ($acc->id == $outbox->sender->id)
            $sent_to_me = true;
          unset($other_admins[$acc->id]);
        }
      }
    }
    // status
    if ($outbox->recipients == Outbox::R_STATUS) {
      $season = Season::forDate(DB::T(DB::NOW));
      if ($season === null)
        self::errln("No current season for regattas.");
      else {
        $list = array();
        foreach ($season->getRegattas() as $reg) {
          if ($reg->end_date >= DB::T(DB::NOW) || count($reg->getScoredRaces()) == 0)
            continue;

          if (in_array(Outbox::STATUS_PENDING, $outbox->arguments) && $reg->finalized === null) {
            self::errln(sprintf("Adding scorers from regatta %s for pending.", $reg->name), 3);
            $list[] = $reg;
          }
          if (in_array(Outbox::STATUS_MISSING_RP, $outbox->arguments)) {
            if (!$reg->isRpComplete()) {
              self::errln(sprintf("Adding scorers from regatta %s for missing RP.", $reg->name), 3);
              $list[] = $reg;
            }
          }
          if (in_array(Outbox::STATUS_FINALIZED, $outbox->arguments) && $reg->finalized !== null) {
            self::errln(sprintf("Adding scorers from regatta %s for finalized RP.", $reg->name), 3);
            $list[] = $reg;
          }
        }

        $schools = array();  // map of school ID to list of accounts
        $users = array();
        foreach ($list as $reg) {
          foreach ($reg->getHosts() as $host) {
            if (!isset($schools[$host->id])) {
              $schools[$host->id] = $host->getUsers(Account::STAT_ACTIVE);
              foreach ($schools[$host->id] as $acc) {
                $users[$acc->id] = $acc;
              }
            }
          }
        }
        foreach ($users as $acc) {
          $this->send($outbox->sender, $acc, $outbox->subject, $outbox->content);
          if ($acc->id == $outbox->sender->id)
            $sent_to_me = true;
          unset($other_admins[$acc->id]);
        }
      }
    }
    // user
    if ($outbox->recipients == Outbox::R_USER) {
      foreach ($outbox->arguments as $user) {
        $acc = DB::getAccountByEmail($user);
        if ($acc !== null) {
          $this->send($outbox->sender, $acc, $outbox->subject, $outbox->content);
          if ($acc->id == $outbox->sender->id)
            $sent_to_me = true;
        }
      }
    }

    // send me a copy?
    if ($outbox->copy_sender > 0 && !$sent_to_me) {
      $this->send($outbox->sender, $outbox->sender, "COPY OF: ".$outbox->subject, $outbox->content);
      self::errln("Also sent copy to sender {$outbox->sender}");
    }

    // other admins?
    foreach ($other_admins as $admin) {
      $this->send($outbox->sender, $admin, "COPY OF: " . $outbox->subject, $outbox->content);
      self::errln(sprintf("Also sent copy to admin %s", $admin));
    }

    $outbox->completion_time = DB::T(DB::NOW);
    DB::set($outbox);
  }

  private function send(Account $from, Account $to, $subject, $content, School $school = null) {
    if ($school === null)
      $school = $to->getFirstSchool();
    DB::queueMessage($from,
                     $to,
                     DB::keywordReplace($subject, $to, $school),
                     DB::keywordReplace($content, $to, $school), true);
    self::errln(sprintf("Sent message to %s.", $to), 2);
    $this->sent++;
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    if (count($opts) > 0) {
      throw new TSScriptException("Invalid argument");
    }
    $this->run();
  }
}
