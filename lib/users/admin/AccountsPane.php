<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Pane to edit user accounts
 *
 */
class AccountsPane extends AbstractAdminUserPane {

  const NUM_PER_PAGE = 20;

  /**
   * Creates a new such pane
   *
   * @param Account $user the administrator with access
   * @throws InvalidArgumentException if the User is not an
   * administrator
   */
  public function __construct(Account $user) {
    parent::__construct("All users", $user);
    $this->page_url = 'users';
  }

  private function fillUser(Account $user) {
    $this->PAGE->addContent(new XP(array(), new XA(WS::link('/'.$this->page_url), "← Go back")));

    // ------------------------------------------------------------
    // General information
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("General information"));
    if ($user->status == Account::STAT_INACTIVE || $user->status == Account::STAT_REJECTED)
      $p->add(new XP(array('class'=>'warning'), "This account is not able to log in and use the system because their account has either been rejected or deleted."));

    $p->add($f = $this->createForm());
    $f->add($fi = new FReqItem("Name:", new XStrong($user), "Only the user can change the name using the \"My Account\" page."));

    $f->add(new FReqItem("Email:", new XA('mailto:'.$user->id, $user->id)));
    $f->add(new FReqItem("Role: ", XSelect::fromArray('role', Account::getRoles(), $user->role)));

    $f->add($fi = new FItem("Admin:", $chk = new XCheckboxInput('admin', 1, array('id'=>'chk-admin'))));
    $fi->add(new XLabel('chk-admin', "Does this account have admin privileges?"));
    if ($user->isAdmin())
      $chk->set('checked', 'checked');
    if ($user == $this->USER) {
      $chk->set('disabled', 'disabled');
      $chk->set('title', "You may not remove permissions for yourself.");
    }
    elseif ($user->isSuper()) {
      $chk->set('disabled', 'disabled');
      $chk->set('title', "This account may not be disabled.");
    }

    $f->add(new FItem("Regattas created:", new XStrong(count($user->getRegattasCreated()))));

    $f->add($xp = new XSubmitP('edit-user', "Edit user"));
    $xp->add(new XHiddenInput('user', $user->id));

    // ------------------------------------------------------------
    // Schools
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("School affiliations"));
    $p->add(new XP(array(), "Each account has one primary school affiliation, and any number of secondary school affiliations. The user has access to edit the information for all affiliated schools. In addition, all schools will be considered in determining whether the account has access to a regatta as a \"participant\"."));
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

    $f->add(new FReqItem("Primary school:", XSelect::fromArray('school', $opts, $user->school->id)));
    $f->add(new FItem("Other schools:", $sel = new XSelectM('schools[]', array('size'=>10))));

    $my_schools = array();
    foreach ($user->getSchools(null, false) as $school) {
      if ($school->id != $user->school->id)
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
      if ($user->id != $this->USER->id && !$user->isSuper()) {
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
   * Generates and returns the HTML page
   *
   */
  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific user
    // ------------------------------------------------------------
    if (isset($args['id'])) {
      if (($user = DB::getAccount($args['id'])) !== null) {
        $this->fillUser($user);
        return;
      }
      Session::pa(new PA("Invalid account requested.", PA::I));
    }

    $pageset  = (isset($args['r'])) ? (int)$args['r'] : 1;
    if ($pageset < 1)
      $pageset = 1;
    $startint = self::NUM_PER_PAGE * ($pageset - 1);

    // ------------------------------------------------------------
    // Current users
    // ------------------------------------------------------------
    require_once('regatta/Account.php');
    $this->PAGE->addContent($p = new XPort("Current users"));
    $p->add(new XP(array(), "Click on the user's name to edit."));

    // Filter?
    $roles = Account::getRoles();
    $role_chosen = DB::$V->incKey($_GET, 'role', $roles, null);

    $statuses = Account::getStatuses();
    $stat_chosen = DB::$V->incKey($_GET, 'status', $statuses, null);

    // Search?
    $qry = null;
    $empty_mes = array("There are no users.");
    $users = array();
    $num_users = 0;
    DB::$V->hasString($qry, $_GET, 'q', 1, 256);
    if ($qry !== null) {
      $empty_mes = "No users match your request.";
      if (strlen($qry) < 3)
        $empty_mes = "Search query is too short.";
      else {
        $users = DB::searchAccounts($qry, $role_chosen, $stat_chosen);
        $num_users = count($users);
        if ($startint > 0 && $startint >= $num_users)
          $startint = (int)(($num_users - 1) / self::NUM_PER_PAGE) * self::NUM_PER_PAGE;
      }
    }
    else {
      $users = DB::getAccounts($role_chosen, $stat_chosen);
      $num_users = count($users);
    }

    // Offer pagination
    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz($num_users, self::NUM_PER_PAGE, '/' . $this->page_url, $_GET);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search users: "));

    // Filter
    $role_opts = array("" => "[All]");
    foreach ($roles as $key => $val)
      $role_opts[$key] = $val;

    $stat_opts = array("" => "[All]");
    foreach ($statuses as $key => $val)
      $stat_opts[$key] = $val;

    $p->add($fs = new XFieldSet("Filter options", array('class'=>'filter')));
    $fs->add($f = $this->createForm(XForm::GET));

    foreach ($_GET as $key => $val) {
      if (!in_array($key, array('role', 'status')))
        $f->add(new XHiddenInput($key, $val));
    }
    $f->add(new XP(array(),
                   array(new XSpan("Role:", array('class'=>'span_h')),
                         XSelect::fromArray('role', $role_opts, $role_chosen),
                         " ",
                         new XSpan("Status:", array('class'=>'span_h')),
                         XSelect::fromArray('status', $stat_opts, $stat_chosen),
                         " ",
                         new XSubmitInput('go', "Apply", array('class'=>'inline')))));

    $p->add($ldiv = $whiz->getPages('r', $_GET));

    // Create table, if applicable
    if ($num_users > 0) {
      $p->add($tab = new XQuickTable(array('class'=>'users-table'),
                                     array("Name", "Email", "Schools", "Role", "Status")));
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $num_users; $i++) {
        $user = $users[$i];
        if ($user->isAdmin())
          $schools = new XEm("All (admin)");
        else {
          $schools = "";
          foreach ($user->getSchools() as $j => $school) {
            if ($j > 0)
              $schools .= ", ";
            $schools .= $school->nick_name;
          }
        }
        
        $tab->addRow(array(new XA(WS::link('/' . $this->page_url, array('id'=>$user->id)), $user),
                           $user->id,
                           $schools,
                           ucwords($user->role),
                           new XSpan(ucwords($user->status), array('class'=>'stat user-' . $user->status))),
                     array('class'=>'row'.($i % 2)));
      }
    }
    $p->add($ldiv);

    $this->PAGE->addContent($p = new XPort("Legend"));
    $p->add(new XP(array(), "The \"Status\" indicators have the following meaning:"));
    $p->add($tab = new XQuickTable(array('class'=>'users-legend'), array("Status", "Meaning")));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_REQUESTED), array('class'=>'stat user-' . Account::STAT_REQUESTED)),
                       "The account was requested, but user has not yet confirmed the e-mail address is valid by following the link sent."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_PENDING), array('class'=>'stat user-' . Account::STAT_PENDING)),
                       "The user has confirmed ownership of e-mail address and the account needs to be approved or rejected by an administrator."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_ACCEPTED), array('class'=>'stat user-' . Account::STAT_ACCEPTED)),
                       "The account has been approved by an administrator, but the user has not agreed to the EULA."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_ACTIVE), array('class'=>'stat user-' . Account::STAT_ACTIVE)),
                       "The user has accepted the EULA."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_REJECTED), array('class'=>'stat user-' . Account::STAT_REJECTED)),
                       "The account has been rejected by an administrator. The e-mail address will never be used for another account."));

    $tab->addRow(array(new XSpan(ucwords(Account::STAT_INACTIVE), array('class'=>'stat user-' . Account::STAT_INACTIVE)),
                       "The account has been removed. Functionally similar to a \"rejected\" status."));
  }

  public function process(Array $args) {
    require_once('regatta/Account.php');
    $user = DB::$V->reqID($args, 'user', DB::$ACCOUNT, "No user provided.");

    // ------------------------------------------------------------
    // Edit user
    // ------------------------------------------------------------
    if (isset($args['edit-user'])) {
      $user->role = DB::$V->reqKey($args, 'role', Account::getRoles(), "Invalid role provided.");
      if ($user != $this->USER && !$user->isSuper())
        $user->admin = DB::$V->incInt($args, 'admin', 1, 2, null);
      DB::set($user);
      Session::pa(new PA(sprintf("Updated account information for user %s.", $user)));
      if ($user->admin !== null)
        Session::pa(new PA("User has \"admin\" privileges and can change key program settings.", PA::I));
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
      $user->school = DB::$V->reqID($args, 'school', DB::$SCHOOL, "Invalid school ID provided.");
      DB::set($user);

      // Other school affiliations
      $schools = array();
      foreach (DB::$V->incList($args, 'schools') as $id) {
        if (($school = DB::get(DB::$SCHOOL, $id)) !== null && $school != $user->school)
          $schools[$school->id] = $school;
      }

      $user->setSchools($schools);
      Session::pa(new PA(sprintf("Updated school affiliations for user %s.", $user)));
    }
    return array();
  }
}
?>