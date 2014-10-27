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
 * Manage the different e-mail templates for auto-generated messages.
 *
 * @author Dayan Paez
 * @created 2013-11-26
 */
class EmailTemplateManagement extends AbstractAdminUserPane {

  private static $TEMPLATES = array(STN::MAIL_REGISTER_USER => "Account requested",
                                    STN::MAIL_REGISTER_ADMIN => "New user admin message",
                                    STN::MAIL_APPROVED_USER => "Account approved",
                                    STN::MAIL_RP_REMINDER => "Daily summary RP reminder",
                                    STN::MAIL_UNFINALIZED_REMINDER => "Unfinalized regattas reminder",
                                    STN::MAIL_MISSING_RP_REMINDER => "Missing RP reminder (participants)",
                                    STN::MAIL_UPCOMING_REMINDER => "Upcoming regatta reminder",
                                    );

  public function __construct(Account $user) {
    parent::__construct("E-mail templates", $user);
  }

  /**
   * Provides list of e-mail templates to edit
   *
   */
  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific template?
    // ------------------------------------------------------------
    if (isset($args['r'])) {
      try {
        $template = DB::$V->reqKey($args, 'r', self::$TEMPLATES, "Invalid template requested.");
        $this->fillTemplate($template);
        return;
      } catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // Table of templates
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Choose template to edit"));
    $p->add($tab = new XQuickTable(array('id'=>'mail-template-table', 'class'=>'full left'),
                                   array("Name", "Current value", "Example")));

    $i = 0;
    foreach (self::$TEMPLATES as $name => $title) {
      $val = new XEm("No template set.");
      $exm = new XEm("No template set.");
      if (DB::g($name) !== null) {
        $val = new XPre(wordwrap(DB::g($name), 50));
        $exm = new XPre(wordwrap(DB::keywordReplace(DB::g($name), $this->USER, $this->USER->getFirstSchool()), 50));
      }
      $tab->addRow(array(new XA($this->link(array('r'=>$name)), $title),
                         $val, $exm),
                   array('class'=>'row' . ($i++ % 2)));
    }
  }

  private function fillTemplate($const) {
    $this->PAGE->addContent($p = new XPort(self::$TEMPLATES[$const]));
    switch ($const) {
    case STN::MAIL_REGISTER_USER:
      $p->add(new XP(array(),
                     array("This is the message sent to users who register for an account. It is important to include a ",
                           new XVar("{BODY}"),
                           " element where the special link will be included for users to verify their e-mail addresses.")));
      break;

    case STN::MAIL_REGISTER_ADMIN:
      $p->add(new XP(array(),
                     array("This is the message sent to the administrators when a new user has registered and verified the e-mail address. Remember to include a ",
                           new XVar("{BODY}"),
                           " element as part of the template which will contain a summary of the user's information.")));
      break;

    case STN::MAIL_APPROVED_USER:
      $p->add(new XP(array(),
                     array("This is the message sent to users when the account is approved by an administrator. It ",
                           new XStrong("does not"),
                           " use a {BODY} section. Use this message to welcome the new user.")));
      break;

    case STN::MAIL_UNFINALIZED_REMINDER:
      $p->add(new XP(array(),
                     array("This is the weekly reminder e-mail message sent to scorers regarding any unfinalized regattas or regattas with missing RP information. An empty template means that no message will be sent. It ",
                           new XStrong("requires"),
                           " a ",
                           new XVar("{BODY}"),
                           " section, in which the list of regattas will appear.")));
      break;

    case STN::MAIL_MISSING_RP_REMINDER:
      $p->add(new XP(array(),
                     array("This is the weekly reminder e-mail message sent to participants regarding any teams for which there is missing RP information. An empty template disables sending this message. It ",
                           new XStrong("requires"),
                           " a ",
                           new XVar("{BODY}"),
                           " section, in which the list of regattas will appear.")));

    case STN::MAIL_UPCOMING_REMINDER:
      $p->add(new XP(array(),
                     array("This is the weekly reminder e-mail message sent to participants regarding upcoming regattas in which they will be participating. An empty template disables sending this message. It ",
                           new XStrong("requires"),
                           " a ",
                           new XVar("{BODY}"),
                           " section, in which the list of regattas will appear.")));
      break;

    case STN::MAIL_RP_REMINDER:
      $p->add(new XP(array(),
                     array("This is the message sent by the scorer at the end of each day of competition reminding teams to look after their RP form. It ",
                           new XStrong("requires"),
                           " a ",
                           new XVar("{BODY}"),
                           " section, in which the regatta name and team(s) for that user will be inserted.")));
      break;
    }

    $p->add($this->keywordReplaceTable());
    $p->add($f = $this->createForm());
    $f->add(new XHiddenInput('template', $const));
    $f->add(new FItem("Message body:", new XTextArea('content', DB::g($const), array('rows'=>16, 'cols'=>75))));
    $f->add($fi = new XSubmitP('edit-template', "Save changes"));
    $fi->add(" ");
    $fi->add(new XA($this->link(), "Go back"));
  }

  public function process(Array $args) {
    if (isset($args['edit-template'])) {
      $templ = DB::$V->reqKey($args, 'template', self::$TEMPLATES, "Invalid mail template requested.");
      $body = DB::$V->incString($args, 'content', 1, 16000);
      
      $req_content = array(STN::MAIL_REGISTER_USER,
                           STN::MAIL_APPROVED_USER,
                           );
      if (in_array($templ, $req_content) && $body === null)
        throw new SoterException("Email template cannot be empty.");

      $req_body = array(STN::MAIL_REGISTER_USER,
                        STN::MAIL_REGISTER_ADMIN,
                        STN::MAIL_UNFINALIZED_REMINDER,
                        STN::MAIL_MISSING_RP_REMINDER,
                        STN::MAIL_UPCOMING_REMINDER,
                        STN::MAIL_RP_REMINDER,
                        );
      if ($body !== null && in_array($templ, $req_body) && strpos($body, '{BODY}') === false)
        throw new SoterException("Missing {BODY} element for template.");

      if ($body == DB::g($templ))
        throw new SoterException("Nothing changed.");

      DB::s($templ, $body);
      Session::pa(new PA("E-mail template saved."));
    }
  }
}
?>