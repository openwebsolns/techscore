<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2011-11-05
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Pane for administrators to send messages to one or more users,
 * based on certain criteria
 *
 * @version 2011-11-18: As of now, this clas will simply queue the
 * message for delivery in the database's "outbox"; to be run at a
 * convenient, non-immediate time by a command line script.
 */
class SendMessage extends AbstractAdminUserPane {
  public function __construct(User $user) {
    parent::__construct("Send message", $user);
  }

  /**
   * To send messages, administrators can either choose all accounts, 
   * all accounts from a conference, all accounts from individual
   * schools, or all accounts, period.
   *
   */
  public function fillHTML(Array $args) {
    if (isset($args['recipients'])) {
      $this->fillMessage($args);
      return;
    }

    $this->PAGE->addContent($p = new XPort("1. Choose recipients"));
    $p->add(new XP(array(), "You may send a message to as many individuals as you'd like at a time. First, select the recipients using this port. Once you have added all recipients, use the form below to send the message."));

    $p->add($f = new XForm("/send-message-edit", XForm::POST));
    $f->add($fi = new FItem(sprintf("All %s users:", Conf::$NAME), new XHiddenInput('all-recipients', 1)));
    $fi->add(new XSubmitInput('choose-recipients', "Write message >"));
    $fi->add(new XMessage("Broadcast general message to all users. Use sparingly."));

    // conference
    $p->add($f = new XForm("/send-message-edit", XForm::POST));
    $f->add($fi = new FItem("All users in conference:", $sel = new XSelectM('conferences[]')));
    $fi->add(" ");
    $fi->add(new XSubmitInput('choose-recipients', "Write message >"));
    $sel->set('size', 7);
    foreach (Preferences::getConferences() as $conf)
      $sel->add(new FOption($conf->id, $conf));

    // roles
    $p->add($f = new XForm("/send-message-edit", XForm::POST));
    $f->add($fi = new FItem("All users with role:", $sel = XSelect::fromArray('roles[]', AccountManager::getRoles())));
    $fi->add(" ");
    $fi->add(new XSubmitInput('choose-recipients', "Write message >"));
    $sel->set('size', 3);
  }

  private function fillMessage(Array $args) {
    $this->PAGE->addContent($p = new XPort("Instructions"));
    $p->add($f = new XForm('/send-message-edit', XForm::POST));
    $f->add(new XSubmitInput('reset-recipients', "<< Restart"));
    $p->add(new XP(array(), "When filling out the form, you may use the keywords in the table below to customize each message."));
    $p->add($tab = new XQuickTable(array('style'=>'margin:0 auto 2em;'), array("Keyword", "Description", "Example")));
    $tab->addRow(array("{FULL_NAME}", "Full name of user",  $this->USER->getName()));
    $tab->addRow(array("{SCHOOL}",    "User's ICSA school", $this->USER->get(User::SCHOOL)));
    
    $title = "";
    $recip = "";
    switch ($args['recipients']) {
    case 'all':
      $title = "2. Send message to all users";
      $recip = "All users";
      break;

      // conference
    case 'conferences':
      $title = "2. Send message to users from conference(s)";
      $recip = implode(", ", $args['conferences']);
      break;

      // roles
    case 'roles':
      $title = "2. Send message ro users with role(s)";
      $recip = implode(", ", $args['roles']);
      break;
    }

    $this->PAGE->addContent($p = new XPort($title));
    $p->add($f = new XForm('/send-message-edit', XForm::POST));
    
    $f->add(new FItem("Recipients:", new XSpan($recip, array('class'=>'strong'))));
    $f->add($fi = new FItem("Subject:", new XTextInput('subject', "")));
    $fi->add(new XMessage("Less than 100 characters"));

    $f->add(new FItem("Message body:", new XTextArea('content', "", array('rows'=>16, 'cols'=>75))));
    $f->add($fi = new FItem("Copy me:", new XCheckboxInput('copy-me', 1)));
    $fi->add(new XMessage("Send me a copy of message, whether or not I would otherwise receive one."));
    $f->add(new XSubmitInput('send-message', "Send message now"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Restart
    // ------------------------------------------------------------
    if (isset($args['reset-recipients'])) {
      return array();
    }

    // ------------------------------------------------------------
    // Choose recipients, will ya?
    // ------------------------------------------------------------
    if (isset($args['choose-recipients'])) {
      if (isset($args['all-recipients']) && $args['all-recipients'] > 0) {
	return array('recipients'=>'all');
      }
      if (isset($args['conferences'])) {
	if (!is_array($args['conferences']) || count($args['conferences']) == 0) {
	  Session::pa(new PA("No conferences provided. Please try again.", PA::I));
	  return array();
	}
	$confs = array();
	foreach ($args['conferences'] as $conf) {
	  $c = Preferences::getConference($conf);
	  if ($c !== null)
	    $confs[$c->id] = $c;
	}
	if (count($confs) == 0) {
	  Session::pa(new PA("No conferences provided. Please try again.", PA::I));
	  return array();
	}
	return array('recipients'=>'conferences', 'conferences'=>$confs);
      }
      // By role
      if (isset($args['roles'])) {
	if (!is_array($args['roles']) || count($args['roles']) == 0) {
	  Session::pa(new PA("No roles provided. Please try again.", PA::I));
	  return array();
	}
	$roles = array();
	$ROLES = AccountManager::getRoles();
	foreach ($args['roles'] as $role) {
	  if (isset($ROLES[$role]))
	    $roles[$role] = $ROLES[$role];
	}
	if (count($roles) == 0) {
	  Session::pa(new PA("No roles provided. Please try again.", PA::I));
	  return array();
	}
	return array('recipients'=>'roles', 'roles'=>$roles);
      }
    }

    // ------------------------------------------------------------
    // Add message to outbox
    // ------------------------------------------------------------
    if (isset($args['send-message'])) {
      $post = Session::g('POST');
      if (!isset($post['recipients'])) {
	Session::pa(new PA("No recipients found.", PA::E));
	return array();
      }
      // require non-empty subject and content
      if (!isset($args['subject']) || ($sub = trim($args['subject'])) == "") {
	Session::pa(new PA("Subject must not be empty.", PA::E));
	return $post;
      }
      if (!isset($args['content']) || ($cnt = trim($args['content'])) == "") {
	Session::pa(new PA("Message body must not be empty.", PA::E));
	return $post;
      }

      // recipients and arguments
      $args = null;
      switch ($post['recipients']) {
      case 'conferences':
	$args = implode(',', $post['conferences']);
	break;
      case 'roles':
	$args = implode(',', array_keys($post['roles']));
	break;
      }

      // Add the message to the outbox
      $out = new Outbox();
      $out->sender = $this->USER->username();
      $out->recipients = $post['recipients'];
      $out->arguments = $args;
      $out->subject = $sub;
      $out->content = $cnt;
      if (isset($args['copy-me']))
	$out->copy_sender =  1;

      Preferences::queueOutgoing($out);
      Session::pa(new PA("Successfully queued message to be sent."));
      return array();
    }
  }
}
?>