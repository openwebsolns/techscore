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
    $messages = $this->USER->getMessages();

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
      $message = DB::getMessage($args['message']);
      if ($message === null || $message->account != $this->USER) {
        Session::pa(new PA("No such message.", PA::E));
        $this->redirect('inbox');
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
      $form->add(new XA("/inbox", "Close"));
      
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
                        array(new XTD(array('class'=>'left'), new XA("/inbox/{$mes->id}", $sub)),
                              new XTD(array('class'=>'left'), $con),
                              new XTD(array(), $mes->created->format('Y-m-d H:i')))));
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $mes = DB::getMessage($args['delete']);
      if ($mes === null || $mes->account != $this->USER)
        throw new SoterException("Invalid message to delete.");
      DB::deleteMessage($mes);
      Session::pa(new PA("Message deleted."));
      $this->redirect("inbox");
    }

    // ------------------------------------------------------------
    // Reply
    // ------------------------------------------------------------
    if (isset($args['reply'])) {
      $mes = DB::getMessage($args['reply']);
      if ($mes === null || $mes->account != $this->USER)
        throw new SoterException("Invalid message to reply.");
      $text = DB::$V->reqString($args, 'text', 1, 16000, "Empty message body submitted.");
      DB::reply($mes, $text);
      Session::pa(new PA("Reply sent."));
      $this->redirect('inbox');
    }
    return array();
  }
}
?>