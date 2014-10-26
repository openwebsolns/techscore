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
    $ref = DB::$V->reqString($_SERVER, 'HTTP_REFERER', 1, 1000, "No referrer found.");
    $date = date('Y-m-d H:i:s');
    $agent = DB::$V->incString($_SERVER, 'HTTP_USER_AGENT', 1, 300, "--");
    $message = DB::$V->reqString($args, 'message', 10, 3000, "Message too short.");
    $mail_link = WS::alink(WS::link('/send-message',
                                    array('list' => array($this->USER->id), 'axis' => 'users')));

    try {
      $sub = '[TS Question] ' . DB::$V->reqString($args, 'subject', 3, 151, "Invalid subject provided.");
      $body = sprintf('------------------------------------------------------------
 User:    %s (%s)
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
                      $this->USER, $this->USER->ts_role,
                      $ref,
                      $date,
                      $agent,
                      $message,
                      $mail_link
      );


      require_once('xml5/TEmailMessage.php');
      $html = new TEmailMessage($sub);
      $html->append(
        new XTable(
          array('style'=>'border:1px solid #ccc; border-collapse:collapse;background:#eee; width: 100%;'),
          array(
            new XTBody(
              array('style' => 'text-align: left'),
              array(
                new XTR(array(), array(new XTH(array(), "User"), new XTD(array(), sprintf("%s (%s)", $this->USER, $this->USER->ts_role)))),
                new XTR(array(), array(new XTH(array(), "Page"), new XTD(array(), $ref))),
                new XTR(array(), array(new XTH(array(), "Time"), new XTD(array(), $date))),
                new XTR(array(), array(new XTH(array(), "Browser"), new XTD(array(), $agent)))
              )
            )
          )
        )
      );
      $html->convertAndAppend($message);
      $html->append(
        new XP(
          array('style'=>'margin-top: 3em'),
          array(new XA($mail_link, "Reply through Techscore", array('style'=>$html->getCSS(TEmailMessage::SUBMIT))))
        )
      );


      $attachments = array();
      if (DB::$V->hasString($file, $args, 'html', 1, 16000)) {
        $file = str_replace('</title>', sprintf('</title><base href="https://%s"/>', Conf::$HOME), $file);
        require_once('mail/StringAttachment.php');
        $attachments[] = new StringAttachment('page.html', 'text/html', $file);
        $message = "For best results, view attached file in its own browser window.";
        $body .= "\n\n" . $message;
        $html->append(new XP(array(), new XEm($message)));
      }
      $res = false;
      foreach (DB::getAdmins() as $admin) {
        if (DB::multipartMail(
              $admin->id,
              $sub,
              array('text/plain' => $body, 'text/html' => $html->toXML()),
              array('Reply-To' => $this->USER->id),
              $attachments
            ))
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