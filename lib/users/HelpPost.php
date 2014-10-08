<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('users/AbstractUserPane.php');


/**
 * Controller to send a help message on behalf of user
 *
 * @author Dayan Paez
 * @created 2014-04-28
 */
class HelpPost extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("Help", $user);
  }

  protected function fillHTML(Array $args) {
    Session::pa(new PA("The help page is only available via POST.", PA::E));
    WS::goBack('/');
  }

  public function process(Array $args) {
    $response = array('error'=>0, 'message'=>'');
    $api = isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json';
    try {
      $sub = '[TS Question] ' . DB::$V->reqString($args, 'subject', 3, 151, "Invalid subject provided.");
      $body = sprintf('------------------------------------------------------------
User:    %s
Page:    %s
Time:    %s
Browser: %s
------------------------------------------------------------

%s

Reply through Techscore: %s
-- 
Techscore

This is a help message generated by Techscore on behalf of given
user.',
                      $this->USER,
                      DB::$V->reqString($_SERVER, 'HTTP_REFERER', 1, 1000, "No referrer found."),
                      date('Y-m-d H:i:s'),
                      DB::$V->incString($_SERVER, 'HTTP_USER_AGENT', 1, 300, "--"),
                      DB::$V->reqString($args, 'message', 10, 3000, "Message too short."),
                      WS::alink(WS::link('/send-message',
                                         array('list' => array($this->USER->id), 'axis' => 'users')))
      );

      $res = false;
      foreach (DB::getAdmins() as $admin) {
        if (DB::mail($admin->id, $sub, $body, true, array('Reply-To' => $this->USER->id)))
          $res = true;
      }
      if (!$res)
        throw new SoterException("Unable to send mail at this time. Please try again later.");

      $response['message'] = "Message successfully sent. Please give us time to review your request and get back to you.";
    }
    catch (SoterException $e) {
      if (!$api)
        throw $e;

      $response['error'] = 1;
      $response['message'] = $e->getMessage();
    }

    if ($api) {
      header('Content-Type: application/json');
      echo json_encode($response);
      exit;
    }
    else
      Session::pa(new PA($response['message']));
  }
}
?>