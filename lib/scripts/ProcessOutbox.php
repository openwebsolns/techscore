<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

require_once('AbstractScript.php');

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
      $sent_to_me = false;
      // all
      if ($outbox->recipients == Outbox::R_ALL) {
        foreach (DB::getConferences() as $conf) {
          foreach ($conf->getUsers() as $acc) {
            $this->send($acc, $outbox->subject, $outbox->content);
            if ($acc->id == $outbox->sender->id)
              $sent_to_me = true;
          }
        }
      }
      // conference
      if ($outbox->recipients == Outbox::R_CONF) {
        foreach ($outbox->arguments as $conf) {
          $conf = DB::getConference($conf);
          if ($conf === null)
            throw new RuntimeException("Conference $conf does not exist.");
          foreach ($conf->getUsers() as $acc) {
            $this->send($acc, $outbox->subject, $outbox->content);
            if ($acc->id == $outbox->sender->id)
              $sent_to_me = true;
          }
        }
      }
      // role
      if ($outbox->recipients == Outbox::R_ROLE) {
        foreach ($outbox->arguments as $role) {
          foreach (DB::getAccounts($role) as $acc) {
            $this->send($acc, $outbox->subject, $outbox->content);
            if ($acc->id == $outbox->sender->id)
              $sent_to_me = true;
          }
        }
      }
      // user
      if ($outbox->recipients == Outbox::R_USER) {
        foreach ($outbox->arguments as $user) {
          $acc = DB::getAccount($user);
          if ($acc !== null) {
            $this->send($acc, $outbox->subject, $outbox->content);
            if ($acc->id == $outbox->sender->id)
              $sent_to_me = true;
          }
        }
      }

      // send me a copy?
      if ($outbox->copy_sender > 0 && !$sent_to_me) {
        $this->send($outbox->sender, "COPY OF: ".$outbox->subject, $outbox->content);
        self::errln("Also sent copy to sender {$outbox->sender}");
      }
      $outbox->completion_time = DB::$NOW;
      DB::set($outbox);
    }
    self::errln(sprintf("Processed %d requests, sending %d messages.", $num, $this->sent));
  }

  private function send(Account $to, $subject, $content) {
    DB::queueMessage($to, $this->keywordReplace($to, $subject), $this->keywordReplace($to, $content), true);
    self::errln(sprintf("Sent message to %s.", $to), 2);
    $this->sent++;
  }
  private function keywordReplace(Account $to, $mes) {
    $mes = str_replace('{FULL_NAME}', $to->getName(), $mes);
    $mes = str_replace('{SCHOOL}',    $to->school, $mes);
    return $mes;
  }
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new ProcessOutbox();
  $opts = $P->getOpts($argv);
  if (count($opts) > 0)
    throw new TSScriptException("Invalid argument");
  $P->run();
}
?>