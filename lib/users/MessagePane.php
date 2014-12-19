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

      require_once('xml5/TSEditor.php');
      $parser = new TSEditor();

      $sub = (empty($message->subject)) ? "[No subject]" : $message->subject;
      $this->PAGE->addContent($p = new XPort($sub));
      $p->add(new XDiv(array('class'=>'email-message'), $parser->parse($message->content)));

      // Fill out form
      $p->add($form = $this->createForm());
      $form->add($xp = new XSubmitP('delete', "Delete"));
      $xp->add(new XHiddenInput('message[]', $message->id));
      $xp->add(new XA(WS::link('/inbox'), "Close"));

      if ($message->sender !== null) {
        $p->add($form = $this->createForm());
        $form->add(new XTextArea('text', "", array('style'=>'width:100%;box-sizing:border-box;', 'rows' =>'5')));
        $form->add($xp = new XSubmitP('reply-message', "Reply"));
        $xp->add(new XHiddenInput('reply', $message->id));
      }

      // Mark the message as read
      DB::markRead($message);
    }

    // ------------------------------------------------------------
    // Message browser
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/SelectAllTableCheckboxes.js')));
    $this->PAGE->head->add(new XScript('text/javascript', null,
                                       'window.addEventListener("load",function(e){new SelectAllTableCheckboxes("message[]","message-table-checkall");},false);'));
    $this->PAGE->addContent($p = new XPort("All messages"));
    $p->add($f = $this->createForm());
    $f->add(new XTable(
              array('class'=>'full', 'id'=>'message-table'),
              array(new XTHead(
                      array(),
                      array(new XTR(
                              array(),
                              array(
                                new XTH(array('id'=>'message-table-checkall'), ""),
                                new XTH(array('width'=>'20%'), "Subject"),
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
                        array(
                          new XTD(array(), new FCheckbox('message[]', $mes->id)),
                          new XTD(array('class'=>'left'), new XA(WS::link('/inbox', array('message'=>$mes->id)), $sub)),
                              new XTD(array('class'=>'left'), $con),
                              new XTD(array(), $mes->created->format('Y-m-d H:i')))));
    }

    $f->add($fi = new XP(
              array(),
              array(
                "With checked: ",
                new XSubmitInput('mark-read', "Mark as Read"),
                new XSubmitDelete('delete', "Delete")
              )));
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $messages = $this->extractMessageList($args);
      foreach ($messages as $mes) {
        DB::deleteMessage($mes);
      }
      $reply = sprintf("Deleted %d messages.", count($messages));
      if (count($messages) == 1)
        $reply = sprintf("Deleted message \"%s\".", $messages[0]->subject);
      Session::pa(new PA($reply));
      $this->redirect('inbox');
    }

    // ------------------------------------------------------------
    // Mark as read
    // ------------------------------------------------------------
    if (isset($args['mark-read'])) {
      $messages = $this->extractMessageList($args);
      $marked = 0;
      foreach ($messages as $message) {
        if ($message->read_time === null) {
          $message->read_time = DB::$NOW;
          DB::set($message);
          $marked++;
        }
      }
      if ($marked == 0)
        Session::pa(new PA("No new messages to mark read.", PA::I));
      elseif ($marked == 1)
        Session::pa(new PA(sprintf("Marked message \"%s\" as read.", $messages[0]->subject)));
      else
        Session::pa(new PA(sprintf("Marked %d messages as read.", $marked)));
    }

    // ------------------------------------------------------------
    // Reply
    // ------------------------------------------------------------
    if (isset($args['reply'])) {
      $mes = DB::getMessage($args['reply']);
      if ($mes === null || $mes->account != $this->USER)
        throw new SoterException("Invalid message to reply.");
      if ($mes->sender === null)
        throw new SoterException("No user to reply to for this message.");
      $text = DB::$V->reqString($args, 'text', 1, 16000, "Empty message body submitted.");
      DB::reply($mes, $text);
      Session::pa(new PA("Reply sent."));
      $this->redirect('inbox');
    }
    return array();
  }

  private function extractMessageList(Array $args) {
    $messages = array();
    foreach (DB::$V->incList($args, 'message') as $id) {
      $mes = DB::getMessage($id);
      if ($mes === null || $mes->account != $this->USER)
        throw new SoterException("Invalid message to delete.");
      $messages[] = $mes;
    }
    if (count($messages) == 0)
      throw new SoterException("No messages selected.");
    return $messages;
  }
}
?>