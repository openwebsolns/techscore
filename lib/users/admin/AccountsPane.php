<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAccountPane.php');

/**
 * Pane to edit user accounts
 *
 */
class AccountsPane extends AbstractAccountPane {

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
    $ts_roles = DB::getAll(DB::$ROLE);
    $ts_role_chosen = DB::$V->incID($_GET, 'ts_role', DB::$ROLE, null);

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
        $users = DB::searchAccounts($qry, $role_chosen, $stat_chosen, $ts_role_chosen);
        $num_users = count($users);
        if ($startint > 0 && $startint >= $num_users)
          $startint = (int)(($num_users - 1) / self::NUM_PER_PAGE) * self::NUM_PER_PAGE;
      }
    }
    else {
      $users = DB::getAccounts($role_chosen, $stat_chosen, $ts_role_chosen);
      $num_users = count($users);
    }

    // Offer pagination
    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz($num_users, self::NUM_PER_PAGE, $this->link(), $_GET);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search users: "));

    // Filter
    $ts_role_opts = array("" => "[All]");
    foreach ($ts_roles as $val)
      $ts_role_opts[$val->id] = $val;

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
                   array(new XDiv(array('class'=>'form-group'),
                                  array(new XSpan("Role:", array('class'=>'span_h')),
                                        XSelect::fromArray('ts_role', $ts_role_opts, ($ts_role_chosen) ? $ts_role_chosen->id : null))),
                         new XDiv(array('class'=>'form-group'),
                                  array(new XSpan("School Role:", array('class'=>'span_h')),
                                        XSelect::fromArray('role', $role_opts, $role_chosen))),
                         new XDiv(array('class'=>'form-group'),
                                  array(new XSpan("Status:", array('class'=>'span_h')),
                                        XSelect::fromArray('status', $stat_opts, $stat_chosen))),
                         new XSubmitInput('go', "Apply", array('class'=>'inline')))));

    $p->add($ldiv = $whiz->getPages('r', $_GET));

    // Create table, if applicable
    if ($num_users > 0) {
      $headers = array("Name", "Email", "Schools", "Role", "School Role", "Status");
      if ($this->USER->isSuper())
        $headers[] = "Usurp";
      $p->add($tab = new XQuickTable(array('class'=>'users-table'), $headers));

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
        
        $row = array(new XA($this->link(array('id'=>$user->id)), $user),
                     $user->id,
                     $schools,
                     $user->ts_role,
                     ucwords($user->role),
                     new XSpan(ucwords($user->status), array('class'=>'stat user-' . $user->status)));
        if ($this->USER->can(Permission::USURP_USER)) {
          $form = "";
          if ($user->status == Account::STAT_ACTIVE) {
            $form = $this->createForm();
            $form->add(new XHiddenInput('user', $user->id));
            $form->add(new XSubmitInput('usurp-user', "Usurp"));
          }
          $row[] = $form;
        }
        $tab->addRow($row, array('class'=>'row'.($i % 2)));
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
    if (isset($args['usurp-user'])) {
      if (!$this->USER->isSuper())
        throw new SoterException("No access to this feature.");
      $user = DB::$V->reqID($args, 'user', DB::$ACCOUNT, "No user provided.");
      if ($user == $this->USER)
        throw new SoterException("What's the point of usurping yourself?");
      if ($user->status != Account::STAT_ACTIVE)
        throw new SoterException("Only active users can be usurped.");
      Session::s('usurped_user', $user->id);
      Session::pa(new PA("You're now logged in as " . $user));
      $this->redirect('');
    }

    parent::process($args);
  }
}
?>