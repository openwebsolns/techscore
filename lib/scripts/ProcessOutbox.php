<?php
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
class ProcessOutbox {

  /**
   * @var boolean true to print out information about what's happening
   */
  public static $verbose = false;
  private static $sent = 0;

  /**
   * Do your thang. (i.e. send messages)
   *
   */
  public static function run() {
    self::$sent = 0;
    $num = 0;
    foreach (DB::getPendingOutgoing() as $outbox) {
      $num++;
      $sent_to_me = false;
      // all
      if ($outbox->recipients == 'all') {
	foreach (DB::getConferences() as $conf) {
	  foreach ($conf->getUsers() as $acc) {
	    self::send($acc, $outbox->subject, $outbox->content);
	    if ($acc->id == $outbox->sender)
	      $sent_to_me = true;
	  }
	}
	self::log(sprintf("Successfully sent message from %s to all recipients queued at %s.\n",
			  $outbox->sender, $outbox->queue_time->format('Y-m-d H:i:s')));
      }
      // conference
      if ($outbox->recipients == 'conferences') {
	foreach (explode(',', $outbox->arguments) as $conf) {
	  $conf = DB::getConference($conf);
	  foreach ($conf->getUsers() as $acc) {
	    self::send($acc, $outbox->subject, $outbox->content);
	    if ($acc->id == $outbox->sender)
	      $sent_to_me = true;
	  }
	}
	self::log(sprintf("Successfully sent message from %s to %s queued at %s.\n",
			  $outbox->sender, $outbox->arguments, $outbox->queue_time->format('Y-m-d H:i:s')));
      }
      // role
      if ($outbox->recipients == 'roles') {
	foreach (explode(',', $outbox->arguments) as $role) {
	  foreach (DB::getAccounts($role) as $acc) {
	    self::send($acc, $outbox->subject, $outbox->content);
	    if ($acc->id == $outbox->sender)
	      $sent_to_me = true;
	  }
	}
	self::log(sprintf("Successfully sent message from %s to %s queued at %s.\n",
			  $outbox->sender, $outbox->arguments, $outbox->queue_time->format('Y-m-d H:i:s')));
      }

      // send me a copy?
      if (isset($args['copy-me']) && !$sent_to_me) {
	self::send(DB::getAccount($outbox->sender), "COPY OF: ".$outbox->subject, $outbox->content);
	self::log("Also sent copy to sender {$outbox->sender}\n");
      }
      $outbox->completion_time = DB::$NOW;
      DB::set($outbox);
    }
    self::log(sprintf("Processed %d requests, sending %d messages.\n", $num, self::$sent));
  }

  private static function send(Account $to, $subject, $content) {
    DB::queueMessage($to, self::keywordReplace($to, $subject), self::keywordReplace($to, $content), true);
    self::$sent++;
  }
  private static function keywordReplace(Account $to, $mes) {
    $mes = str_replace('{FULL_NAME}', $to->getName(), $mes);
    $mes = str_replace('{SCHOOL}',    $to->school, $mes);
    return $mes;
  }

  private static function log($mes) {
    if (self::$verbose)
      echo $mes;
  }

  public static function usage($name = 'ProcessOutbox') {
    printf("usage: %s [-vh]

 -h  Print this message
 -v  Be verbose about what you are doing\n", $name);
  }
}

// Run from the command line
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  ini_set('include_path', '.:'.realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $opts = getopt('vh');

  // Help?
  if (isset($opts['h'])) {
    ProcessOutbox::usage($argv[0]);
    exit(1);
  }
  
  if (isset($opts['v'])) {
    ProcessOutbox::$verbose = true;
    unset($opts['v']);
  }
  ProcessOutbox::run();
}
?>