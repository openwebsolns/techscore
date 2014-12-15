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
    // Where in the process are we?
    // ------------------------------------------------------------
    $outbox = new Outbox();
    try {
      $question = $this->parseArgs($outbox, $args);
    } catch (SoterException $e) {}

    // ------------------------------------------------------------
    // Progress report
    // ------------------------------------------------------------
    $this->PAGE->addContent($f = $this->createForm(XForm::GET));
    $f->add($prog = new XP(array('id'=>'progressdiv')));
    $step = $this->calculateProgress($prog, $outbox);

    switch ($step) {
    case 1:
      $this->fillCategory($args['axis']);
      return;

    case 2:
      $this->fillMessage($outbox, $question);
      return;
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
      $f->add(new FReqItem(sprintf("All users in %s:", DB::g(STN::CONFERENCE_TITLE)), $sel = new XSelectM('list[]')));
      $sel->set('size', 7);
      foreach (DB::getConferences() as $conf)
        $sel->add(new FOption($conf->id, $conf));
      break;

    case Outbox::R_SCHOOL:
      // schools
      $f->add(new FReqItem("All users in schools:", $sel = new XSelectM('list[]')));
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
      $f->add(new FReqItem("All users with role:", XSelect::fromArray('list[]', Account::getRoles())));
      break;

    case Outbox::R_STATUS:
      // regatta status
      $f->add(new FReqItem("Scorers for regattas:", XSelect::fromArray('list[]', Outbox::getStatusTypes())));
      break;

    case Outbox::R_USER:
      // user
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/userSelect.js')));
      $f->add(new FReqItem("Specific users:", new XTextArea('inline-list', "", array('id'=>'user-select')), "Add one e-mail address per line."));
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
   * @param Question $question is this answering a question?
   */
  private function fillMessage(Outbox $out, Question $question = null) {
    $this->setupTextEditors(array('content'));

    $title = "";
    $recip = "";
    $omit_instructions = false;
    switch ($out->recipients) {
    case Outbox::R_ALL:
      $title = "3. Send message to all users";
      $recip = "All users";
      break;

      // conference
    case Outbox::R_CONF:
      $title = sprintf("3. Send message to users from %s(s)", DB::g(STN::CONFERENCE_TITLE));
      $recip = implode(", ", $out->arguments);
      break;

      // schools
    case Outbox::R_SCHOOL:
      $title = "3. Send message to users from school(s)";
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
      $title = "3. Send message to users with role(s)";
      $recip = implode(", ", $out->arguments);
      break;

      // status
    case Outbox::R_STATUS:
      $title = "3. Send message to scorers from regattas with status in current season";
      $recip = array();
      $stats = Outbox::getStatusTypes();
      foreach ($out->arguments as $stat)
        $recip[] = $stats[$stat];
      $recip = implode(", ", $recip);
      break;

      // specific user
    case Outbox::R_USER:
      $title = "3. Send message to specific user";
      $recip = "";
      foreach ($out->arguments as $i => $email) {
        if ($i > 0)
          $recip .= ", ";
        $acc = DB::getAccountByEmail($email);
        $recip .= $acc;
        if (count($out->arguments) == 1) {
          $omit_instructions = true;
          $recip .= sprintf(', %s at %s', $acc->role, $acc->getAffiliation());
        }
      }
      break;
    }

    // Instructions on replacements available
    if (!$omit_instructions) {
      $this->PAGE->addContent($p = new XPort("Instructions"));
      $p->add(new XP(array(), "When filling out the message, you may use the keywords in the table below to customize each message."));
      $p->add($this->keywordReplaceTable());
    }

    // Form to send messages
    $this->PAGE->addContent($p = new XPort($title));
    $p->add($f = $this->createForm());

    $f->add(new FReqItem("Recipients:", new XSpan($recip, array('class'=>'strong'))));
    $f->add(new FReqItem("Subject:",
                         new XTextInput(
                           'subject',
                           $out->subject,
                           array(
                             'maxlength'=>100,
                             'size'=>75,
                             'placeholder' => "Fewer than 100 characters"
                           )
                         )));

    $f->add(
      $te = new XTextEditor(
        'content', 'content', $out->content,
        array(
          'required'=>'required',
          'placeholder'=>"Message body (required).")));
    require_once('xml5/TEmailMessage.php');
    $te->add(new XStyle('text/css', TEmailMessage::getCSSStylesheet(), array('scoped'=>'scoped')));

    if (count(DB::getAdmins()) > 1) {
      $copy_admin = false;
      if ($question !== null) {
        $copy_admin = true;
        $f->add(new FItem(
                  "Answer is publishable?",
                  new FCheckbox(
                    'publishable', 1,
                    "Check if answer may be shown to other users asking a similar question."),
                  "Make sure no personal information is present anywhere in the message (including the original message quoted)."
                ));
      }
      $f->add(new FItem(
                "Copy other Admins:",
                new FCheckbox(
                  'copy_admin', 1,
                  "Send a blind carbon copy to all other admins.",
                  $copy_admin),
                "This is recommended when answering questions to users so others are aware that the question has been answered."));
    }
    $f->add(new FItem("Copy me:", new FCheckbox('copy-me', 1, "Send me a copy of message, whether or not I would otherwise receive one.")));
    $f->add($para = new XP(array('class'=>'p-submit'), array(new XHiddenInput('axis', $out->recipients))));
    if ($out->arguments !== null) {
      foreach ($out->arguments as $item)
        $para->add(new XHiddenInput('list[]', $item));
    }
    if ($question !== null) {
      $para->add(new XHiddenInput('q', $question->id));
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
    $out = new Outbox();
    $question = $this->parseArgs($out, $args, true);
    $out->sender = $this->USER;

    // If the number of recipients is small enough, send now
    if ($out->recipients == Outbox::R_USER && count($out->arguments) <= 5) {
      require_once('scripts/ProcessOutbox.php');
      $P = new ProcessOutbox();
      $P->process($out);
      Session::pa(new PA("Message successfully sent."));
    }
    else {
      DB::set($out);
      Session::pa(new PA("Successfully queued message to be sent."));
    }

    // Is this a question being answered?
    if ($question !== null) {
      $answer = new Answer();
      $answer->answered_by = $this->USER;
      $answer->answer = $out->content;
      $answer->publishable = DB::$V->incInt($args, 'publishable', 1, 2);
      $question->addAnswer($answer);
      Session::pa(new PA(sprintf("Marked message as answer to question \"%s\".", $question->subject)));

      // TODO: Redirect to page with question/answers?
    }

    $this->redirect('send-message');
  }

  /**
   * Parses the argument in the given array (which comes from $_GET or
   * $_POST) and returns the appropriate recipient 'axis' and
   * corresponding list.
   *
   * @param Outbox $res the outbox object to fill
   * @param Array $args the variables to parse
   * @param boolean $req_message if true, require non-empty subject
   *   and message
   * @return Question|null the question if this is a reply
   * @throws SoterException if user is trying to pull a fast one
   */
  private function parseArgs(Outbox &$res, $args, $req_message = false) {
    $res->copy_admin = DB::$V->incInt($args, 'copy_admin', 1, 2, null);
    $res->copy_sender = DB::$V->incInt($args, 'copy-me', 1, 2, null);

    // If answering a question, autofill most items
    if (isset($args['q'])) {
      $question = DB::$V->reqID($args, 'q', new Question(), "Invalid question provided in argument.");
      $res->recipients = Outbox::R_USER;
      $res->arguments = array($question->asker->email);
      $res->subject = DB::$V->incString($args, 'subject', 1, 101, "RE: " . $question->subject);
      $res->content = DB::$V->incString($args, 'content', 1, 16000);
      if ($res->content === null) {
        $res->content = "\n\n> " . str_replace("\n", "\n> ", $question->question);
      }
      return $question;
    }

    $res->recipients = DB::$V->reqKey($args, 'axis', Outbox::getRecipientTypes(), "Invalid recipient type provided.");
    $res->subject = DB::$V->incString($args, 'subject', 1, 101);
    if ($req_message && $res->subject === null)
      throw new SoterException("Missing subject for message.");
    $res->content = DB::$V->incString($args, 'content', 1, 16000);
    if ($req_message && $res->content === null)
      throw new SoterException("Missing content for message, or possibly too long.");
    if ($res->recipients == Outbox::R_ALL) {
      $res->arguments = array();
      return null;
    }

    $inputList = DB::$V->incList($args, 'list');
    if (count($inputList) == 0) {
      $inlineList = DB::$V->reqString($args, 'inline-list', 1, 10000, "Missing list of recipients.");
      $inlineList = preg_replace('/\s+/', ' ', $inlineList);
      $inputList = explode(' ', $inlineList);
    }

    // require appropriate list
    $list = array();
    $roles = Account::getRoles();
    $stats = Outbox::getStatusTypes();
    foreach ($inputList as $m) {
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
          $obj = $ind->email;
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
    $res->arguments = array_keys($list);
    return null;
  }

  /**
   * Helper function to fill in the progress bar at the top of page
   *
   * @return $int the step, zero-indexed
   */
  private function calculateProgress(XP $prog, Outbox $outbox) {
    $steps = array(
      "Message Type",
      "Recipients",
      "Message",
    );

    if ($outbox->recipients) {
      $prog->add(new XSpan(new XA($this->link(), $steps[0]), array('class'=>'completed')));

      if ($outbox->arguments !== null) {
        $prog->add(new XSpan(new XA($this->link(array('axis' => $outbox->recipients)), $steps[1]), array('class'=>'completed')));
        $prog->add(new XSpan($steps[2], array('class'=>'current')));
        return 2;
      }

      $prog->add(new XSpan(new XA($this->link(array('axis' => $outbox->recipients)), $steps[1]), array('class'=>'current')));
      $prog->add(new XSpan($steps[2]));
      return 1;
    }

    // Step 0
    $prog->add(new XSpan(new XA($this->link(), $steps[0]), array('class'=>'current')));
    $prog->add(new XSpan($steps[1]));
    $prog->add(new XSpan($steps[2]));
    return 0;
  }
}
?>