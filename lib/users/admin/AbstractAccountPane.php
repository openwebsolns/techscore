<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Common ancestor of panes that handle account information
 *
 * Provides convenience methods for subclasses
 *
 * @author Dayan Paez
 * @version 2014-05-10
 */
abstract class AbstractAccountPane extends AbstractAdminUserPane {

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
      $p->add(new XP(array('class'=>'warning'), "This account is not able to log in and use the system because their account has either been rejected or deleted."));

    $p->add($f = $this->createForm());
    $f->add($fi = new FReqItem("Name:", new XStrong($user), "Only the user can change the name using the \"My Account\" page."));

    $f->add(new FReqItem("Email:", new XA('mailto:'.$user->id, $user->id)));
    $f->add(new FReqItem(DB::g(STN::ORG_NAME) . " Role: ", XSelect::fromArray('role', Account::getRoles(), $user->role)));
    if ($user != $this->USER && !$user->isSuper())
      $f->add(new FReqItem("Role:", XSelect::fromDBM('ts_role', DB::getAll(DB::$ROLE), $user->ts_role, array(), "")));

    if ($user->status != Account::STAT_PENDING) {
      $f->add(new FItem("Regattas created:", new XStrong(count($user->getRegattasCreated()))));
    }

    $f->add($xp = new XSubmitP('edit-user', "Edit user"));
    $xp->add(new XHiddenInput('user', $user->id));

    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("School affiliations"));
    $p->add(new XP(array(), "Each account may be associated with one or more schools. These affiliations will be used along with the role to determine the full set of permissions for a given account. For instance, users with access to edit school information can only do so on the schools associated with their account via this form."));
    if ($user->isAdmin())
      $p->add(new XP(array('class'=>'warning'), "As this account has administrator privileges, the user has access to every regatta and every school in the system."));

    $opts = array();
    foreach (DB::getConferences() as $conf) {
      $subs = array();
      foreach ($conf->getSchools() as $school)
        $subs[$school->id] = $school;
      $opts[(string)$conf] = $subs;
    }
    $p->add($f = $this->createForm());
    $f->add(new XHiddenInput('user', $user->id));

    $f->add(new FItem("Schools:", $sel = new XSelectM('schools[]', array('size'=>10))));

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
    $f->add(new XSubmitP('user-schools', "Set affiliations"));
    

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
    require_once('regatta/Account.php');
    $user = DB::$V->reqID($args, 'user', DB::$ACCOUNT, "No user provided.");

    // ------------------------------------------------------------
    // Edit user
    // ------------------------------------------------------------
    if (isset($args['edit-user'])) {
      $user->role = DB::$V->reqKey($args, 'role', Account::getRoles(), "Invalid school role provided.");
      if ($user != $this->USER && !$user->isSuper())
        $user->ts_role = DB::$V->reqID($args, 'ts_role', DB::$ROLE, "Invalid role provided.");
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
      if (!$this->sendRegistrationEmail($user))
        throw new SoterException("There was a problem sending e-mails. Please notify the system administrator.");
      Session::pa(new PA(sprintf("Resent registration email for user %s.", $user)));
    }

    // ------------------------------------------------------------
    // Delete user
    // ------------------------------------------------------------
    if (isset($args['delete-user'])) {
      if ($user->id == $this->USER->id && !$user->isSuper())
        throw new SoterException("You cannot delete your own account.");
      $user->status = Account::STAT_INACTIVE;
      DB::set($user);
      Session::pa(new PA(sprintf("Removed account %s for %s.", $user->id, $user)));
    }

    // ------------------------------------------------------------
    // Reactivate user
    // ------------------------------------------------------------
    if (isset($args['accept-user'])) {
      if ($user->status != Account::STAT_INACTIVE)
        throw new SoterException("Only inactivated accounts may be reactivated.");
      $user->status = Account::STAT_ACCEPTED;
      DB::set($user);
      Session::pa(new PA(sprintf("Reactivated account %s for %s.", $user->id, $user)));
    }

    // ------------------------------------------------------------
    // Set affiliations
    // ------------------------------------------------------------
    if (isset($args['user-schools'])) {
      // Other school affiliations
      $schools = array();
      foreach (DB::$V->incList($args, 'schools') as $id) {
        if (($school = DB::get(DB::$SCHOOL, $id)) !== null)
          $schools[$school->id] = $school;
      }

      $user->setSchools($schools);
      Session::pa(new PA(sprintf("Updated school affiliations for user %s.", $user)));
    }
    return array();
  }
}
?>