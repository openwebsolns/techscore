<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Displays and controls the display of messages for the given user
 *
 * @author Dayan Paez
 * @version   2010-04-12
 */
class MessagePane extends AbstractUserPane {

  const NUM_PER_AGE = 10;

  public function __construct(Account $user) {
    parent::__construct("Messages", $user);
  }

  protected function fillHTML(Array $args) {
    $messages = DB::getMessages($this->USER);

    // ------------------------------------------------------------
    // No messages
    // ------------------------------------------------------------
    if (count($messages) == 0) {
      $this->PAGE->addContent($p = new XPort("Inbox"));
      $p->add(new XP(array(), "You have no messages."));
      return;
    }

    // ------------------------------------------------------------
    // Chosen message
    // ------------------------------------------------------------
    if (isset($args['message'])) {
      $message = Preferences::getObjectWithProperty($messages, "id", $args['message']);
      if ($message === null) {
	Session::pa(new PA("No such message.", PA::E));
	$this->redirect("../inbox");
      }

      $sub = (empty($message->subject)) ? "[No subject]" : $message->subject;
      $this->PAGE->addContent($p = new XPort($sub));
      $p->add(new XDiv(array('class'=>'email-message'),
		       array(new XPre(wordwrap($message->content, 90)))));
      $p->add($form = new XForm("/inbox-edit", XForm::POST));

      // Fill out form
      $form->add(new XButton(array("name" =>"delete",
				   "type"=>"submit",
				   "value"=>$message->id),
			     array("Delete")));
      $form->add(new XText(" "));
      $form->add(new XA("inbox", "Close"));
      
      $p->add($form = new XForm("/inbox-edit", XForm::POST));
      $form->add(new XTextArea("text", "", array("style"=>"width: 100%", "rows" =>"3")));
      $form->add(new XButton(array("name" =>"reply",
				   "type" =>"submit",
				   "value"=>$message->id),
			     array("Reply")));

      // Mark the message as read
      DB::markRead($message);
    }

    // ------------------------------------------------------------
    // Message browser
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("All messages"));
    $p->add(new XTable(array('class'=>'left', 'style'=>'width:100%;'),
		       array(new XTHead(array(),
					array(new XTR(array(),
						      array(new XTH(array('width'=>'20%'), "Subject"),
							    new XTH(array('width'=>'60%'), "Content"),
							    new XTH(array('width'=>'20%'), "Sent"))))),
			     $tab = new XTBody())));
    foreach ($messages as $mes) {
      $sub = (empty($mes->subject)) ? "[No subject]" : $mes->subject;
      $con = (strlen($mes->content) > 50) ?
	substr($mes->content, 0, 50) . "..." :
	$mes->content;

      $attrs = ($mes->read_time === null) ? array('class'=>'strong') : array();
      $tab->add(new XTR($attrs,
			array(new XTD(new XA("/inbox/{$mes->id}", $sub)),
			      new XTD($con),
			      new XTD($mes->created->format('Y-m-d H:i')))));
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $messages = DB::getMessages($this->USER);
      $mes = Preferences::getObjectWithProperty($messages, "id", $args['delete']);
      if ($mes === null) {
	Session::pa(new PA("Invalid message to delete.", PA::E));
	$this->redirect();
      }
      DB::deleteMessage($mes);
      Session::pa(new PA("Message deleted."));
      $this->redirect("inbox");
    }

    // ------------------------------------------------------------
    // Reply
    // ------------------------------------------------------------
    if (isset($args['reply'])) {
      $messages = DB::getMessages($this->USER);
      $mes = Preferences::getObjectWithProperty($messages, "id", $args['reply']);
      if ($mes === null) {
	Session::pa(new PA("Invalid message to reply.", PA::E));
	$this->redirect();
      }
      if (empty($args['text'])) {
	Session::pa(new PA("Empty message not sent.", PA::I));
	$this->redirect();
      }
      DB::reply($mes, (string)$args['text']);
      Session::pa(new PA("Reply sent."));
      $this->redirect("inbox");
    }
    return $args;
  }
}
?>