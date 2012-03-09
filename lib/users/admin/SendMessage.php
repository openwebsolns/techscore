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
 * based on certain criteria.
 *
 * @version 2011-11-18: As of now, this class will simply queue the
 * message for delivery in the database's "outbox"; to be run at a
 * convenient, non-immediate time by a command line script.
 *
 * The page allows the user to choose the potential recipients a
 * number of ways, which must be specified in the submission with the
 * following POST/GET parameters:
 *
 *   - recipients: [all|conferences|roles|users]
 *
 * Based on the intended recipients type, there must then be a
 * corresponding variable as a list, except for 'all':
 *
 *   - conferences: list of conference IDs
 *   - roles:       list of roles (student, staff, coach)
 *   - users:       list of account IDs
 *
 * If this pane is accessed through GET with the above parameters set,
 * then the program will present the user with a form to fill in the
 * subject and body of the message.
 *
 * If these variables do not exist, then a form to choose the
 * recipient type will be shown instead.
 *
 * The script can be POSTed directly, with all the parameters set
 * (including the subject and body of the message, of course). If a
 * POST is attempted with complete recipients specification but
 * missing message subject/body, then the user will be directed to the
 * appropriate GET version for their request.
 */
class SendMessage extends AbstractAdminUserPane {
  public function __construct(Account $user) {
    parent::__construct("Send message", $user);
  }

  /**
   * @var String constant to use for sending to all users
   */
  const SEND_ALL = 'all';
  /**
   * @var String send based on account role
   */
  const SEND_ROLE = 'roles';
  /**
   * @var String send based on conference membership
   */
  const SEND_CONF = 'conferences';
  /**
   * @var String send based on IDs
   */
  const SEND_USER = 'users';

  /**
   * To send messages, administrators can either choose all accounts, 
   * all accounts from a conference, all accounts from individual
   * schools, or all accounts, period.
   *
   */
  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Request for recipient?
    // ------------------------------------------------------------
    if (isset($args['axis'])) {
      try {
	$this->fillMessage($this->parseArgs($args));
	return;
      }
      catch (SoterException $e) {
	Session::pa(new PA($e->getMessage(), PA::E));
	WS::go('/send-message');
      }
    }

    // ------------------------------------------------------------
    // Choose message
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("1. Choose recipients"));
    $p->add(new XP(array(), "You may send a message to as many individuals as you'd like at a time. First, select the recipients using this port. Once you have added all recipients, use the form below to send the message."));

    $p->add($f = new XForm("/send-message", XForm::GET));
    $f->add($fi = new FItem(sprintf("All %s users:", Conf::$NAME), new XHiddenInput('axis', self::SEND_ALL)));
    $fi->add(new XSubmitInput('recipients', "Write message >"));
    $fi->add(new XMessage("Broadcast general message to all users. Use sparingly."));

    // conference
    $p->add($f = new XForm("/send-message", XForm::GET));
    $f->add($fi = new FItem("All users in conference:", $sel = new XSelectM('list[]')));
    $fi->add(" ");
    $fi->add(new XSubmitInput('recipients', "Write message >"));
    $fi->add(new XHiddenInput('axis', self::SEND_CONF));
    $sel->set('size', 7);
    foreach (DB::getConferences() as $conf)
      $sel->add(new FOption($conf->id, $conf));

    // roles
    $p->add($f = new XForm("/send-message", XForm::GET));
    $f->add($fi = new FItem("All users with role:", $sel = XSelect::fromArray('list[]', Account::getRoles())));
    $fi->add(" ");
    $fi->add(new XSubmitInput('choose-recipients', "Write message >"));
    $fi->add(new XHiddenInput('axis', self::SEND_ROLE));
    $sel->set('size', 3);
  }

  private function fillMessage(Array $args) {
    $this->PAGE->addContent(new XP(array(), new XA(WS::link('/send-message'), "← Discard changes and restart")));
    $this->PAGE->addContent($p = new XPort("Instructions"));
    $p->add(new XP(array(), "When filling out the message, you may use the keywords in the table below to customize each message."));
    $p->add($tab = new XQuickTable(array('style'=>'margin:0 auto 2em;'), array("Keyword", "Description", "Example")));
    $tab->addRow(array("{FULL_NAME}", "Full name of user",  new XTD(array('class'=>'left'), $this->USER->getName())));
    $tab->addRow(array("{SCHOOL}",    "User's ICSA school", new XTD(array('class'=>'left'), $this->USER->school)));
    
    $title = "";
    $recip = "";
    switch ($args['axis']) {
    case self::SEND_ALL:
      $title = "2. Send message to all users";
      $recip = "All users";
      break;

      // conference
    case self::SEND_CONF:
      $title = "2. Send message to users from conference(s)";
      $recip = implode(", ", $args['list']);
      break;

      // roles
    case self::SEND_ROLE:
      $title = "2. Send message ro users with role(s)";
      $recip = implode(", ", $args['list']);
      break;
    }

    $this->PAGE->addContent($p = new XPort($title));
    $p->add($f = new XForm('/send-message-edit', XForm::POST));
    
    $f->add(new FItem("Recipients:", new XSpan($recip, array('class'=>'strong'))));
    $f->add($fi = new FItem("Subject:", new XTextInput('subject', $args['subject'])));
    $fi->add(new XMessage("Less than 100 characters"));

    $f->add(new FItem("Message body:", new XTextArea('content', $args['message'], array('rows'=>16, 'cols'=>75))));
    $f->add($fi = new FItem("Copy me:", new XCheckboxInput('copy-me', 1, array('id'=>'copy-me'))));
    $fi->add(new XLabel('copy-me', "Send me a copy of message, whether or not I would otherwise receive one."));
    $f->add($para = new XP(array('class'=>'p-submit'), array(new XHiddenInput('axis', $args['axis']))));
    foreach ($args['list'] as $id => $item)
      $para->add(new XHiddenInput('list[]', $id));
    $para->add(new XSubmitInput('send-message', "Send message now"));
  }

  /**
   * Assume that this is a complete request to send message.
   *
   * @param Array $args the arguments
   * @throws SoterException (as usual)
   */
  public function process(Array $args) {
    $post = $this->parseArgs($args, true);

    // Add the message to the outbox
    $out = new Outbox();
    $out->sender = $this->USER->id;
    $out->recipients = $post['axis'];
    $out->arguments = $post['list'];
    $out->subject = $post['subject'];
    $out->content = $post['message'];
    if (isset($args['copy-me']))
      $out->copy_sender =  1;

    DB::set($out);
    Session::pa(new PA("Successfully queued message to be sent."));
    WS::go('/send-message');
  }

  /**
   * Parses the argument in the given array (which comes from $_GET or
   * $_POST) and returns the appropriate recipient 'axis' and
   * corresponding list.
   *
   * @param Array $args the variables to parse
   * @param boolean $req_message if true, require non-empty subject
   *   and message
   * @return Array with indices: 'axis' and 'list', 'subject', 'message'
   * @throws SoterException if user is trying to pull a fast one
   */
  private function parseArgs($args, $req_message = false) {
    $res = array('list'=>array());
    $res['axis'] = DB::$V->reqValue($args, 'axis', array(self::SEND_ALL,
							 self::SEND_ROLE,
							 self::SEND_CONF,
							 self::SEND_USER), "Invalid recipient type provided.");
    $res['subject'] = DB::$V->incString($args, 'subject', 1, 101);
    if ($req_message && $res['subject'] === null)
      throw new SoterException("Missing subject for message.");
    $res['message'] = DB::$V->incString($args, 'content', 1, 16000);
    if ($req_message && $res['message'] === null)
      throw new SoterException("Missing content for message, or possibly too long.");
    if ($res['axis'] == self::SEND_ALL)
      return $res;
    // require appropriate list
    $roles = Account::getRoles();
    foreach (DB::$V->reqList($args, 'list', null, "Missing list of recipients.") as $m) {
      $obj = null;
      $ind = (string)$m;
      switch ($res['axis']) {
      case self::SEND_ROLE:
	if (isset($roles[$ind]))
	  $obj = $roles[$ind];
	break;

      case self::SEND_CONF:
	$obj = DB::getConference($m);
	break;

      case self::SEND_USER:
	$obj = DB::getAccount($m);
	break;

      default:
	throw new RuntimeException("Unknown recipient axis for message.");
      }
      if ($obj !== null)
	$res['list'][$ind] = $obj;
    }
    return $res;
  }
}
?>