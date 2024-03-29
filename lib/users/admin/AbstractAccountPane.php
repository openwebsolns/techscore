<?php
namespace users\admin;

use \users\utils\RegistrationEmailSender;
use \users\AbstractUserPane;
use \users\RegisterPane;
use \users\membership\RegisterStudentPane;
use \users\membership\StudentProfilePane;
use \xml5\SessionParams;

use \Account;
use \DB;
use \Email_Token;
use \PA;
use \Permission;
use \STN;
use \Session;
use \SoterException;

use \FItem;
use \FOption;
use \FOptionGroup;
use \FReqItem;
use \XA;
use \XCollapsiblePort;
use \XHiddenInput;
use \XP;
use \XPort;
use \XQuickTable;
use \XSelect;
use \XSelectM;
use \XStrong;
use \XSubmitDelete;
use \XSubmitInput;
use \XSubmitP;
use \XWarning;

/**
 * Common ancestor of panes that handle account information
 *
 * Provides convenience methods for subclasses
 *
 * @author Dayan Paez
 * @version 2014-05-10
 */
abstract class AbstractAccountPane extends AbstractUserPane {

  private $registrationEmailSender;

  /**
   * Creates form for a specific user object
   *
   */
  protected function fillUser(Account $user) {
    $this->PAGE->addContent(new XP(array(), new XA($this->link(), "← Go back")));

    // ------------------------------------------------------------
    // General information
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("General information"));
    if ($user->status == Account::STAT_INACTIVE || $user->status == Account::STAT_REJECTED)
      $p->add(new XWarning("This account is not able to log in and use the system because their account has either been rejected or deleted."));

    $p->add($f = $this->createForm());
    $f->add($fi = new FReqItem("Name:", new XStrong($user), "Only the user can change the name using the \"My Account\" page."));

    $f->add(new FReqItem("Email:", new XA('mailto:'.$user->email, $user->email)));
    $f->add(new FReqItem(DB::g(STN::ORG_NAME) . " Role: ", XSelect::fromArray('role', Account::getRoles(), $user->role)));
    if ($user != $this->USER && !$user->isSuper())
      $f->add(new FReqItem("Role:", XSelect::fromDBM('ts_role', DB::getAll(DB::T(DB::ROLE)), $user->ts_role, array(), "")));

    $f->add($xp = new XSubmitP('edit-user', "Edit user"));
    $xp->add(new XHiddenInput('user', $user->id));
    if ($user->status == Account::STAT_ACTIVE && $this->USER->can(Permission::USURP_USER))
      $xp->add(new XSubmitInput('usurp-user', "Usurp"));

    // ------------------------------------------------------------
    // Student profiles
    // ------------------------------------------------------------
    $profiles = $user->getStudentProfiles();
    if (count($profiles) > 0) {
      $this->PAGE->addContent($p = new XPort("Student profiles"));
      $p->add($table = new XQuickTable(array(), array("ID", "Name", "School", "Year")));
      foreach ($profiles as $profile) {
        $table->addRow(
          array(
            new XA(
              $this->linkTo('users\membership\StudentProfilePane', array(StudentProfilePane::INPUT_PROFILE => $profile->id)),
              $profile->id
            ),
            $profile->getName(),
            $profile->school,
            $profile->graduation_year,
          )
        );
      }
    }

    // ------------------------------------------------------------
    // Conferences
    // ------------------------------------------------------------
    $confs = DB::getConferences();
    $conf_title = DB::g(STN::CONFERENCE_TITLE);
    $my_confs = array();
    foreach ($user->getConferences() as $conf)
      $my_confs[$conf->id] = $conf;


    $p = new XPort("Affiliations");
    if ($user->ts_role->is_student !== null) {
      $p = new XCollapsiblePort("Affiliations");
      $p->add(new XWarning("This is a Student account. Student accounts should not need any affiliations set; their affiliation is dependent on their student profiles."));
    }

    $this->PAGE->addContent($p);
    $p->add(new XP(array(), "Affiliations will be used along with the role to determine the full set of permissions for a given account. For instance, users with access to edit school information can only do so on the schools affiliated with their account."));
    $p->add(new XP(array(),
                   array("An account may be affiliated at the individual school level or at the ", $conf_title, " level, which will grant the user implicit access to all the schools in that ", $conf_title, ", including those that may be added at a later time.")));
    if ($user->isAdmin())
      $p->add(new XWarning("As this account has administrator privileges, the user already has access to every school in the system."));

    $p->add($f = $this->createForm());
    $f->add(new XHiddenInput('user', $user->id));

    $f->add(new FItem($conf_title . "s:", $sel = new XSelectM('conferences[]'), "It is recommended to affiliate accounts either at the " . $conf_title . " level, or the School level, but not both."));
    $sel->set('id', 'conference-associations');
    foreach ($confs as $conf) {
      $attrs = array();
      if (isset($my_confs[$conf->id]))
        $attrs['selected'] = 'selected';
      $sel->add(new FOption($conf->id, $conf, $attrs));
    }

    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    $opts = array();
    foreach ($confs as $conf) {
      $subs = array();
      foreach ($conf->getSchools() as $school)
        $subs[$school->id] = $school;
      $opts[(string)$conf] = $subs;
    }

    $f->add(new FItem("Schools:", $sel = new XSelectM('schools[]', array('size'=>10)), "You need only include schools that are not part of a " . $conf_title . " chosen above."));

    $my_schools = array();
    foreach ($user->getSchools(null, false) as $school) {
      $my_schools[$school->id] = $school;
    }
    foreach ($opts as $conf => $schools) {
      $sel->add($grp = new FOptionGroup($conf));
      foreach ($schools as $key => $val) {
        $attrs = array();
        if (isset($my_schools[$key]))
          $attrs['selected'] = 'selected';
        $grp->add(new FOption($key, $val, $attrs));
      }
    }

    $f->add(new XSubmitP('user-affiliations', "Set affiliations"));
    

    if ($user->status != Account::STAT_INACTIVE) {
      if ($user->status == Account::STAT_REQUESTED && DB::g(STN::MAIL_REGISTER_USER)) {
        // ------------------------------------------------------------
        // Resend registration
        // ------------------------------------------------------------
        $this->PAGE->addContent($p = new XPort("Resend registration e-mail"));
        $p->add($f = $this->createForm());
        $f->add(new XP(array(), "Resend user registration e-mail in order to verify user account."));
        $f->add($xp = new XSubmitP('resend-registration', "Resend e-mail"));
        $xp->add(new XHiddenInput('user', $user->id));
      }

      if ($user->email_inbox_status === Account::EMAIL_INBOX_STATUS_RECEIVING) {
        // ------------------------------------------------------------
        // Stop e-mail messages
        // ------------------------------------------------------------
        $this->PAGE->addContent($p = new XPort("Stop e-mail notifications"));
        $p->add($f = $this->createForm());
        $f->add(new XP(array(), "Permanently stop e-mail delivery to this account by clicking the button below. The user will have to change e-mail addresses to continue."));
        $f->add(new XP(array('class'=>'p-submit'),
                       array(new XSubmitInput('stop-email-sending', "Stop e-mail delivery"),
                             new XHiddenInput('user', $user->id))));
      }

      if ($user->id !== $this->USER->id && $user->status === Account::STAT_REJECTED) {
        // ------------------------------------------------------------
        // Reset rejected account back to pending?
        // ------------------------------------------------------------
        $this->PAGE->addContent($p = new XPort("Reset rejected account"));
        $p->add($f = $this->createForm());
        $f->add(new XP(array(), "The current account was previously rejected by an administrator. Use the button below to reset the account back to Pending status, so that it may be accepted (or re-rejected)."));
        $f->add(new XP(array('class'=>'p-submit'),
                       array(new XSubmitInput('reset-rejected-user', "Reset user"),
                             new XHiddenInput('user', $user->id))));
      }

      if ($user->id != $this->USER->id && !$user->isSuper() && $user->status != Account::STAT_PENDING) {
        // ------------------------------------------------------------
        // Delete account?
        // ------------------------------------------------------------
        $this->PAGE->addContent($p = new XPort("Inactivate account"));
        $p->add($f = $this->createForm());
        $f->add(new XP(array(), "Delete this account by clicking the button below. The user will no longer be allowed to use the application, or create a new account."));
        $f->add(new XP(array('class'=>'p-submit'),
                       array(new XSubmitDelete('delete-user', "Delete user", array('onclick'=>'return confirm("Are you sure you wish to delete this user?");')),
                             new XHiddenInput('user', $user->id))));
      }
    }
    else {
      // ------------------------------------------------------------
      // Undelete account
      // ------------------------------------------------------------
      $this->PAGE->addContent($p = new XPort("Reactivate account"));
      $p->add($f = $this->createForm());
      $f->add(new XP(array(), sprintf("Reactivate this account by clicking the button below. As a result, the account's status will be changed to \"%s\", which means the user has to agree to the EULA upon log-in. Note that the system will not notify the user that their account has been reactivated.", Account::STAT_ACCEPTED)));
      $f->add(new XP(array('class'=>'p-submit'),
                     array(new XSubmitInput('accept-user', "Reactivate"),
                           new XHiddenInput('user', $user->id))));
    }
  }

  /**
   * Processes requests from fillUser
   *
   * @param Array $args the arguments
   * @see fillUser
   */
  public function process(Array $args) {
    $user = DB::$V->reqID($args, 'user', DB::T(DB::ACCOUNT), "No user provided.");

    // ------------------------------------------------------------
    // Edit user
    // ------------------------------------------------------------
    if (isset($args['edit-user'])) {
      $user->role = DB::$V->reqKey($args, 'role', Account::getRoles(), "Invalid school role provided.");
      if ($user != $this->USER && !$user->isSuper())
        $user->ts_role = DB::$V->reqID($args, 'ts_role', DB::T(DB::ROLE), "Invalid role provided.");
      DB::set($user);
      Session::pa(new PA(sprintf("Updated account information for user %s.", $user)));
    }

    // ------------------------------------------------------------
    // Resend registration e-mail
    // ------------------------------------------------------------
    if (isset($args['resend-registration'])) {
      if ($user->status != Account::STAT_REQUESTED)
        throw new SoterException("Registration e-mails can only be sent to requested accounts.");
      if (DB::g(STN::MAIL_REGISTER_USER) === null)
        throw new SoterException("No e-mail template exists. No message sent.");
      $token = $user->createToken();
      $sender = $this->getRegistrationEmailSender();
      if (!$sender->sendRegistrationEmail($user, $this->getRegistrationLink($user, $token))) {
        throw new SoterException("There was a problem sending e-mails. Please notify the system administrator.");
      }
      DB::set($user);
      Session::pa(new PA(sprintf("Resent registration email for user %s.", $user)));
    }

    // ------------------------------------------------------------
    // Stop e-mail messages
    // ------------------------------------------------------------
    if (isset($args['stop-email-sending'])) {
      if ($user->isSuper())
        throw new SoterException("You cannot change e-mail settings for this account.");
      $user->email_inbox_status = Account::EMAIL_INBOX_STATUS_BOUNCING;
      DB::set($user);
      Session::pa(new PA(sprintf("Stopped e-mail delivery to account %s.", $user->email)));
    }

    // ------------------------------------------------------------
    // Delete user
    // ------------------------------------------------------------
    if (isset($args['delete-user'])) {
      if ($user->id == $this->USER->id)
        throw new SoterException("You cannot delete your own account.");
      if ($user->isSuper())
        throw new SoterException("You cannot delete this account.");
      $user->status = Account::STAT_INACTIVE;
      DB::set($user);
      Session::pa(new PA(sprintf("Removed account %s for %s.", $user->email, $user)));
    }

    // ------------------------------------------------------------
    // Reactivate user
    // ------------------------------------------------------------
    if (isset($args['accept-user'])) {
      if ($user->status != Account::STAT_INACTIVE)
        throw new SoterException("Only inactivated accounts may be reactivated.");
      $user->status = Account::STAT_ACCEPTED;
      DB::set($user);
      Session::pa(new PA(sprintf("Reactivated account %s for %s.", $user->email, $user)));
    }

    // ------------------------------------------------------------
    // Reset rejected account
    // ------------------------------------------------------------
    if (isset($args['reset-rejected-user'])) {
      if ($user->status != Account::STAT_REJECTED) {
        throw new SoterException("Only rejected accounts may be reset.");
      }
      $user->status = Account::STAT_PENDING;
      DB::set($user);
      Session::pa(new PA(sprintf("Reset account %s for %s to Pending status.", $user->email, $user)));
    }

    // ------------------------------------------------------------
    // Set affiliations
    // ------------------------------------------------------------
    if (isset($args['user-affiliations'])) {
      // Other school affiliations
      $schools = array();
      foreach (DB::$V->incList($args, 'schools') as $id) {
        if (($school = DB::getSchool($id)) !== null)
          $schools[$school->id] = $school;
      }
      $user->setSchools($schools);

      // Other conference affiliations
      $conferences = array();
      foreach (DB::$V->incList($args, 'conferences') as $id) {
        if (($conference = DB::getConference($id)) !== null)
          $conferences[$conference->id] = $conference;
      }
      $user->setConferences($conferences);
      Session::pa(new PA(sprintf("Updated affiliations for user %s.", $user)));
    }

    // ------------------------------------------------------------
    // Usurp user
    // ------------------------------------------------------------
    if (isset($args['usurp-user'])) {
      if (!$this->USER->can(Permission::USURP_USER))
        throw new SoterException("No access to this feature.");
      $user = DB::$V->reqID($args, 'user', DB::T(DB::ACCOUNT), "No user provided.");
      if ($user == $this->USER)
        throw new SoterException("What's the point of usurping yourself?");
      if ($user->status != Account::STAT_ACTIVE)
        throw new SoterException("Only active users can be usurped.");
      Session::s(SessionParams::USURPED_USER, $user->id);
      Session::pa(new PA("You're now logged in as " . $user));
      $this->redirect('');
    }

    return array();
  }

  public function setRegistrationEmailSender(RegistrationEmailSender $sender) {
    $this->registrationEmailSender = $sender;
  }

  protected function getRegistrationEmailSender() {
    if ($this->registrationEmailSender === null) {
      $this->registrationEmailSender = new RegistrationEmailSender();
    }
    return $this->registrationEmailSender;
  }

  private function getRegistrationLink(Account $user, Email_Token $token) {
    if ($user->ts_role && $user->ts_role->is_student) {
      return $this->linkTo('users\membership\RegisterStudentPane', array(RegisterStudentPane::INPUT_TOKEN => (string) $token));
    }
    return $this->linkTo('users\RegisterPane', array(RegisterPane::INPUT_TOKEN => (string) $token));
  }
}
