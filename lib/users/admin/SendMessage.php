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

    $this->PAGE->addContent($p = new Port("1. Choose recipients"));
    $p->add(new Para("You may send a message to as many individuals as you'd like at a time. First, select the recipients using this port. Once you have added all recipients, use the form below to send the message."));

    $p->add($f = new XForm("/send-message-edit", XForm::POST));
    $f->add($fi = new FItem(sprintf("All %s users:", NAME), new FHidden('all-recipients', 1)));
    $fi->add(new FSubmit('choose-recipients', "Write message >"));
    $fi->add(new FSpan("Broadcast general message to all users. Use sparingly.", array('class'=>'message')));

    // conference
    $p->add($f = new XForm("/send-message-edit", XForm::POST));
    $f->add($fi = new FItem("All users in conference:", $sel = new FSelect('conferences[]')));
    $fi->add(new XText(" "));
    $fi->add(new FSubmit('choose-recipients', "Write message >"));
    $opts = array();
    foreach (Preferences::getConferences() as $conf)
      $opts[$conf->id] = $conf;
    $sel->addOptions($opts);
    $sel->set('multiple', 'multiple');
    $sel->set('size', 7);

    // roles
    $p->add($f = new XForm("/send-message-edit", XForm::POST));
    $f->add($fi = new FItem("All users with role:", $sel = new FSelect('roles[]')));
    $fi->add(new XText(" "));
    $fi->add(new FSubmit('choose-recipients', "Write message >"));
    $sel->addOptions(AccountManager::getRoles());
    $sel->set('multiple', 'multiple');
    $sel->set('size', 3);
  }

  private function fillMessage(Array $args) {
    $this->PAGE->addContent($p = new Port("Instructions"));
    $p->add($f = new XForm('/send-message-edit', XForm::POST));
    $f->add(new FSubmit('reset-recipients', "<< Restart"));
    $p->add(new Para("When filling out the form, you may use the keywords in the table below to customize each message."));
    $p->add($tab = new Table());
    $tab->addHeader(new Row(array(Cell::th("Keyword"), Cell::th("Description"), Cell::th("Example"))));
    $tab->addRow(new Row(array(new Cell("{FULL_NAME}"), new Cell("Full name of user"), new Cell($this->USER->getName()))));
    $tab->addRow(new Row(array(new Cell("{SCHOOL}"), new Cell("User's ICSA school"), new Cell($this->USER->get(User::SCHOOL)))));
    $tab->set('style', 'margin:0 auto 2em;');
    
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

    $this->PAGE->addContent($p = new Port($title));
    $p->add($f = new XForm('/send-message-edit', XForm::POST));
    
    $f->add(new FItem("Recipients:", new FSpan($recip, array('class'=>'strong'))));
    $f->add($fi = new FItem("Subject:", new FText('subject', "")));
    $fi->add(new FSpan("Less than 100 characters", array('class'=>'message')));

    $f->add(new FItem("Message body:", new FTextarea('content', "", array('rows'=>16, 'cols'=>75))));
    $f->add($fi = new FItem("Copy me:", new FCheckbox('copy-me', 1)));
    $fi->add(new FSpan("Send me a copy of message, whether or not I would otherwise receive one.", array('class'=>'message')));
    $f->add(new FSubmit('send-message', "Send message now"));
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
	  $this->announce(new Announcement("No conferences provided. Please try again.", Announcement::WARNING));
	  return array();
	}
	$confs = array();
	foreach ($args['conferences'] as $conf) {
	  $c = Preferences::getConference($conf);
	  if ($c !== null)
	    $confs[$c->id] = $c;
	}
	if (count($confs) == 0) {
	  $this->announce(new Announcement("No conferences provided. Please try again.", Announcement::WARNING));
	  return array();
	}
	return array('recipients'=>'conferences', 'conferences'=>$confs);
      }
      // By role
      if (isset($args['roles'])) {
	if (!is_array($args['roles']) || count($args['roles']) == 0) {
	  $this->announce(new Announcement("No roles provided. Please try again.", Announcement::WARNING));
	  return array();
	}
	$roles = array();
	$ROLES = AccountManager::getRoles();
	foreach ($args['roles'] as $role) {
	  if (isset($ROLES[$role]))
	    $roles[$role] = $ROLES[$role];
	}
	if (count($roles) == 0) {
	  $this->announce(new Announcement("No roles provided. Please try again.", Announcement::WARNING));
	  return array();
	}
	return array('recipients'=>'roles', 'roles'=>$roles);
      }
    }

    // ------------------------------------------------------------
    // Add message to outbox
    // ------------------------------------------------------------
    if (isset($args['send-message'])) {
      if (!isset($_SESSION['POST']['recipients'])) {
	$this->announce(new Announcement("No recipients found.", Announcement::ERROR));
	return array();
      }
      // require non-empty subject and content
      if (!isset($args['subject']) || ($sub = trim($args['subject'])) == "") {
	$this->announce(new Announcement("Subject must not be empty.", Announcement::ERROR));
	return $_SESSION;
      }
      if (!isset($args['content']) || ($cnt = trim($args['content'])) == "") {
	$this->announce(new Announcement("Message body must not be empty.", Announcement::ERROR));
	return $_SESSION;
      }

      // recipients and arguments
      $args = null;
      switch ($_SESSION['POST']['recipients']) {
      case 'conferences':
	$args = implode(',', $_SESSION['POST']['conferences']);
	break;
      case 'roles':
	$args = implode(',', array_keys($_SESSION['POST']['roles']));
	break;
      }

      // Add the message to the outbox
      $out = new Outbox();
      $out->sender = $this->USER->username();
      $out->recipients = $_SESSION['POST']['recipients'];
      $out->arguments = $args;
      $out->subject = $sub;
      $out->content = $cnt;
      if (isset($args['copy-me']))
	$out->copy_sender =  1;

      Preferences::queueOutgoing($out);
      $this->announce(new Announcement("Successfully queued message to be sent."));
      return array();
    }
  }
}
?>