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
    $this->PAGE->addContent($p = new XPort("General information"));
    $p->add(new XP(array('class'=>'warning'), "The user's name may only be changed by the account holder, using the \"My Account\" link in the main menu."));

    $p->add(new XP(array(), "Coming soon..."));
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
        $users = DB::search(DB::$ACCOUNT, $qry);
        $num_users = count($users);
        if ($startint > 0 && $startint >= $num_users)
          $startint = (int)(($num_users - 1) / self::NUM_PER_PAGE) * self::NUM_PER_PAGE;
      }
    }
    else {
      $users = DB::getAccounts();
      $num_users = count($users);
    }

    // Offer pagination
    require_once('xml5/PageWhiz.php');
    $whiz = new PageWhiz($num_users, self::NUM_PER_PAGE, '/' . $this->page_url, $_GET);
    $p->add($whiz->getSearchForm($qry, 'q', $empty_mes, "Search users: "));
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
                           new XA('mailto:'.$user->id, $user->id),
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
    $legend = array("approve"=>array("success"=>"Approved accounts(s):",
                                     "error"  =>"Unable to approve",
                                     "status" =>"accepted"),
                    "reject" =>array("success"=>"Rejected account(s):",
                                     "error"  =>"Unable to reject",
                                     "status" =>"rejected"));
    // ------------------------------------------------------------
    // Approve / Reject
    // ------------------------------------------------------------
    foreach (array("approve", "reject") as $action) {
      if (isset($args[$action])) {
        $accounts = DB::$V->reqList($args, 'accounts', null, "No account list provided.");
        if (count($accounts) == 0)
          throw new SoterException("No accounts chosen.");

        $unnotified = array();
        $success = array();
        $errors  = 0;
        foreach ($accounts as $id) {
          $acc = DB::getAccount($id);
          if ($acc === null || $acc->status != Account::STAT_PENDING)
            $errors++;
          else {
            $acc->status = $legend[$action]["status"];
            DB::set($acc);
            // Notify user
            if ($action == 'approve') {
              if (!$this->notifyUser($acc))
                $unnotified[] = sprintf('%s <%s>', $acc->getName(), $acc->id);
            }
            $success[] = $acc->id;
          }
        }

        // Announce the good news
        if ($errors > 0) {
          Session::pa(new PA(sprintf("%s %d accounts.", $legend[$action]["error"], $errors), PA::I));
        }
        if (count($unnotified) > 0)
          Session::pa(new PA(sprintf("Unable to notify the following accounts: %s.", implode(", ", $unnotified)), PA::I));
        if (count($success) > 0) {
          Session::pa(new PA(sprintf("%s %s.", $legend[$action]["success"], implode(", ", $success))));
        }
      }
    }
    return array();
  }

  /**
   * Sends message to approved user
   *
   * @param Account $acc the account to notify
   * @return boolean the result of DB::mail
   */
  private function notifyUser(Account $acc) {
    return DB::mail($acc->id,
                    sprintf("[%s] Account approved", Conf::$NAME),
                    sprintf("Dear %1\$s,\n\nYou are receiving this message as notification that your account at %2\$s has been approved. To start using %2\$s, please login now at:\n\n%3\$s\n\nWhen you login the first time, you will be asked to sign an End-User License Agreement (EULA), where you assert that you will use %2\$s solely for scoring ICSA regattas.\n\nIt is *strongly* recommended that all new users become acquainted with the proper use of the program and member responsibilities by reading the user manual available on every page of the site (look for the \"Help\" link in to the top right corner).\n\nIn addition, you may request help from ICSA's %2\$s committee, led by Matt Lindblad, (mitsail@mit.edu).\n\nThank you for using %2\$s,\n-- \n%2\$s Administration",
                            $acc->first_name, Conf::$NAME, WS::alink('/')));
  }
}
?>