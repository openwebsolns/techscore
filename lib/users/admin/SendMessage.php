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
 *   - recipients: [all|conferences|schools|roles|users|regattaStatus]
 *
 * Based on the intended recipients type, there must then be a
 * corresponding variable as a list, except for 'all':
 *
 *   - conferences: list of conference IDs
 *   - roles:       list of roles (student, staff, coach)
 *   - schools:     list of users with access to schools
 *   - users:       list of account IDs
 *   - regattaStatus: one of Not-finalized, Missing RP, or Finalized
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

  private $AXES;

  public function __construct(Account $user) {
    parent::__construct("Send message", $user);
    $this->AXES = array(
      Outbox::R_ALL => "All users",
      Outbox::R_CONF => sprintf("Users in %s", DB::g(STN::CONFERENCE_TITLE)),
      Outbox::R_SCHOOL => "Users in school(s)",
      Outbox::R_ROLE => "Assigned to Role",
      Outbox::R_STATUS => "With regatta status",
      Outbox::R_USER => "Specific user(s)"
    );
  }

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
      } catch (SoterException $e) {
        if (isset($this->AXES[$args['axis']])) {
          $this->fillCategory($args['axis']);
          return;
        }
        Session::pa(new PA($e->getMessage(), PA::E));
        WS::go('/send-message');
      }
    }

    // ------------------------------------------------------------
    // Choose message category
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("1. Message type"));
    $p->add(new XP(array(), "To send a message, first choose the type of message you wish to send. You will then choose the list of recipients based on the category chosen."));
    $p->add($f = $this->createForm(XForm::GET));
    $f->add(new FReqItem("Recipients in category:", XSelect::fromArray('axis', $this->AXES), "We recommend selecting the most specific category possible for your message, to avoid spamming all users."));
    $f->add(new XSubmitP('choose-axis', "Next →"));
  }

  /**
   * Fill the second step: choosen recipients based on category
   *
   * @param String $axis one of the constants from Outbox
   */
  private function fillCategory($axis) {

    // ------------------------------------------------------------
    // Choose message recipients
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("2. Choose recipients"));
    $p->add(new XP(array(), "You may send a message to as many individuals as you'd like at a time. First, select the recipients using this port. Once you have added all recipients, use the form below to send the message."));

    $p->add($f = $this->createForm(XForm::GET));
    $f->add(new XHiddenInput('axis', $axis));

    switch ($axis) {
      // conference
    case Outbox::R_CONF:
      $f->add(new FItem(sprintf("All users in %s:", DB::g(STN::CONFERENCE_TITLE)), $sel = new XSelectM('list[]')));
      $sel->set('size', 7);
      foreach (DB::getConferences() as $conf)
        $sel->add(new FOption($conf->id, $conf));
      break;

    case Outbox::R_SCHOOL:
      // schools
      $p->add($f = $this->createForm(XForm::GET));
      $f->add(new FItem("All users in schools:", $sel = new XSelectM('list[]')));
      $sel->set('size', 10);
      foreach (DB::getConferences() as $conf) {
        $sel->add($grp = new XOptionGroup($conf));
        foreach ($conf->getSchools() as $school) {
          $grp->add(new FOption($school->id, $school));
        }
      }
      break;

    case Outbox::R_ROLE:
      // roles
      $p->add($f = $this->createForm(XForm::GET));
      $f->add(new FItem("All users with role:", $sel = XSelect::fromArray('list[]', Account::getRoles())));
      break;

    case Outbox::R_STATUS:
      // regatta status
      $p->add($f = $this->createForm(XForm::GET));
      $f->add(new FItem("Scorers for regattas:", $sel = XSelect::fromArray('list[]', Outbox::getStatusTypes())));
      break;

    case Outbox::R_USER:
      // user
      $p->add($f = $this->createForm(XForm::GET));
      $f->add(new FItem("Specific user:", new XTextInput('list[]', "", array('required'=>'required'))));
      break;
    }

    $f->add($xp = new XSubmitP('recipients', "Write message →"));
    $xp->add(new XA($this->link(), "Cancel"));
  }

  /**
   * Fills the pane with a form for the user to enter the subject and
   * content (or message) to be sent.
   *
   * @param Outbox $out the message object
   */
  private function fillMessage(Outbox $out) {
    $orgname = DB::g(STN::ORG_NAME);
    $this->PAGE->addContent($p = new XPort("Instructions"));
    $p->add(new XP(array(), "When filling out the message, you may use the keywords in the table below to customize each message."));
    $p->add($this->keywordReplaceTable());

    $title = "";
    $recip = "";
    switch ($out->recipients) {
    case Outbox::R_ALL:
      $title = "2. Send message to all users";
      $recip = "All users";
      break;

      // conference
    case Outbox::R_CONF:
      $title = sprintf("2. Send message to users from %s(s)", DB::g(STN::CONFERENCE_TITLE));
      $recip = implode(", ", $out->arguments);
      break;

      // schools
    case Outbox::R_SCHOOL:
      $title = "2. Send message to users from school(s)";
      $recip = "";
      $i = 0;
      foreach ($out->arguments as $id) {
        if ($i++ > 0)
          $recip .= ", ";
        $recip .= DB::getSchool($id)->nick_name;
      }
      break;

      // roles
    case Outbox::R_ROLE:
      $title = "2. Send message to users with role(s)";
      $recip = implode(", ", $out->arguments);
      break;

      // status
    case Outbox::R_STATUS:
      $title = "2. Send message to scorers from regattas with status in current season";
      $recip = array();
      $stats = Outbox::getStatusTypes();
      foreach ($out->arguments as $stat)
        $recip[] = $stats[$stat];
      $recip = implode(", ", $recip);
      break;

      // specific user
    case Outbox::R_USER:
      $title = "2. Send message to specific user";
      $recip = implode(", ", $out->arguments);
    }

    $this->PAGE->addContent($p = new XPort($title));
    $p->add($f = $this->createForm());

    $f->add(new FReqItem("Recipients:", new XSpan($recip, array('class'=>'strong'))));
    $f->add(new FReqItem("Subject:",
                         new XTextInput('subject', $out->subject, array('maxlength'=>100)),
                         "Fewer than 100 characters"));

    $f->add(new FReqItem("Message body:", new XTextArea('content', $out->content, array('rows'=>16, 'cols'=>75))));
    $f->add(new FItem("Copy me:", new FCheckbox('copy-me', 1, "Send me a copy of message, whether or not I would otherwise receive one.")));
    $f->add($para = new XP(array('class'=>'p-submit'), array(new XHiddenInput('axis', $out->recipients))));
    if ($out->arguments !== null) {
      foreach ($out->arguments as $item)
        $para->add(new XHiddenInput('list[]', $item));
    }
    $para->add(new XSubmitInput('send-message', "Send message"));
    $para->add(new XA($this->link(), "Cancel"));
  }

  /**
   * Assume that this is a complete request to send message.
   *
   * @param Array $args the arguments
   * @throws SoterException (as usual)
   */
  public function process(Array $args) {
    $out = $this->parseArgs($args, true);
    $out->sender = $this->USER;
    if (isset($args['copy-me']))
      $out->copy_sender =  1;

    DB::set($out);
    Session::pa(new PA("Successfully queued message to be sent."));
    $this->redirect('send-message');
  }

  /**
   * Parses the argument in the given array (which comes from $_GET or
   * $_POST) and returns the appropriate recipient 'axis' and
   * corresponding list.
   *
   * @param Array $args the variables to parse
   * @param boolean $req_message if true, require non-empty subject
   *   and message
   * @return Outbox the message
   * @throws SoterException if user is trying to pull a fast one
   */
  private function parseArgs($args, $req_message = false) {
    $res = new Outbox();
    $res->recipients = DB::$V->reqKey($args, 'axis', Outbox::getRecipientTypes(), "Invalid recipient type provided.");
    $res->subject = DB::$V->incString($args, 'subject', 1, 101);
    if ($req_message && $res->subject === null)
      throw new SoterException("Missing subject for message.");
    $res->content = DB::$V->incString($args, 'content', 1, 16000);
    if ($req_message && $res->content === null)
      throw new SoterException("Missing content for message, or possibly too long.");
    if ($res->recipients == Outbox::R_ALL)
      return $res;

    // require appropriate list
    $list = array();
    $roles = Account::getRoles();
    $stats = Outbox::getStatusTypes();
    foreach (DB::$V->reqList($args, 'list', null, "Missing list of recipients.") as $m) {
      $obj = null;
      $ind = (string)$m;
      switch ($res->recipients) {
      case Outbox::R_ROLE:
        if (isset($roles[$ind]))
          $obj = $ind;
        break;

      case Outbox::R_CONF:
        if (($ind = DB::getConference($ind)) !== null)
          $obj = $ind->id;
        break;

      case Outbox::R_USER:
        if (($ind = DB::getAccountByEmail($ind)) !== null)
          $obj = $ind->id;
        break;

      case Outbox::R_SCHOOL:
        if (($ind = DB::getSchool($ind)) !== null)
          $obj = $ind->id;
        break;

      case Outbox::R_STATUS:
        if (isset($stats[$ind]))
          $obj = $ind;
        break;

      default:
        throw new RuntimeException("Unknown recipient axis for message.");
      }
      if ($obj !== null)
        $list[$obj] = $obj;
    }
    if (count($list) == 0)
      throw new SoterException("No valid recipients provided.");
    $res->arguments = $list;
    return $res;
  }
}
?>