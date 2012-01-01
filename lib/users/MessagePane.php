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

  public function __construct(User $user) {
    parent::__construct("Messages", $user);
  }

  protected function fillHTML(Array $args) {
    $messages = Preferences::getMessages($this->USER->asAccount());

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
	$this->announce(new Announcement("No such message.", Announcement::ERROR));
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
      Preferences::markRead($message);
    }

    // ------------------------------------------------------------
    // Message browser
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("All messages"));
    $p->add($tab = new Table());
    $tab->set("style", "width: 100%;");
    $tab->set("class", "left");
    $tab->addHeader(new Row(array(new Cell("Subject", array("width"=>"20%"), 1),
				  new Cell("Content", array("width"=>"60%"), 1),
				  new Cell("Sent",    array("width"=>"20%"), 1))));
    foreach ($messages as $mes) {
      $sub = (empty($mes->subject)) ? "[No subject]" : $mes->subject;
      $con = (strlen($mes->content) > 50) ?
	substr($mes->content, 0, 50) . "..." :
	$mes->content;

      if (!$mes->read_time)
	$tab->addRow(new Row(array(new Cell(new XStrong(new XA("inbox/" . $mes->id, $sub))),
				   new Cell(new XStrong($con)),
				   new Cell(new XStrong($mes->created->format('Y-m-d H:i'))))));
      else
	$tab->addRow(new Row(array(new Cell(new XA("inbox/" . $mes->id, $sub)),
				   new Cell($con),
				   new Cell($mes->created->format('Y-m-d H:i')))));
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $messages = Preferences::getMessages($this->USER->asAccount());
      $mes = Preferences::getObjectWithProperty($messages, "id", $args['delete']);
      if ($mes === null) {
	$this->announce(new Announcement("Invalid message to delete.", Announcement::ERROR));
	$this->redirect();
      }
      Preferences::deleteMessage($mes);
      $this->announce(new Announcement("Message deleted."));
      $this->redirect("inbox");
    }

    // ------------------------------------------------------------
    // Reply
    // ------------------------------------------------------------
    if (isset($args['reply'])) {
      $messages = Preferences::getMessages($this->USER->asAccount());
      $mes = Preferences::getObjectWithProperty($messages, "id", $args['reply']);
      if ($mes === null) {
	$this->announce(new Announcement("Invalid message to reply.", Announcement::ERROR));
	$this->redirect();
      }
      if (empty($args['text'])) {
	$this->announce(new Announcement("Empty message not sent.", Announcement::WARNING));
	$this->redirect();
      }
      Preferences::reply($mes, (string)$args['text']);
      $this->announce(new Announcement("Reply sent."));
      $this->redirect("inbox");
    }
    return $args;
  }
}
?>