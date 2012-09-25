<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Pane to edit (approve/reject) pending accounts
 *
 */
class PendingAccountsPane extends AbstractAdminUserPane {

  const NUM_PER_PAGE = 10;

  /**
   * Creates a new such pane
   *
   * @param Account $user the administrator with access
   * @throws InvalidArgumentException if the User is not an
   * administrator
   */
  public function __construct(Account $user) {
    parent::__construct("Pending users", $user);
  }

  /**
   * Generates and returns the HTML page
   *
   * @see UserHomePane::fillHTML
   */
  protected function fillHTML(Array $args) {
    $pageset  = (isset($args['page'])) ? (int)$args['page'] : 1;
    if ($pageset < 1)
      WS::go('/pending');
    $startint = self::NUM_PER_PAGE * ($pageset - 1);
    $list = DB::getPendingUsers();
    $count = count($list);
    $num_pages = ceil($count / self::NUM_PER_PAGE);
    if ($startint > $count)
      WS::go(sprintf('/pending|%d', $num_pages));
    
    $this->PAGE->addContent($p = new XPort("Pending accounts"));
    if ($count == 0) {
      $p->add(new XP(array(), "There are no pending accounts."));
    }
    else {
      $p->add(new XP(array(), "Use the checkboxes below to select the accounts, and then click on the appropriate button to approve/reject."));
      $p->add($f = new XForm("/pending-edit", XForm::POST));
      $f->add(new XP(array(),
                     array("With selected: ",
                           new XSubmitInput("approve", "Approve"),
                           " ", new XSubmitInput("reject",  "Reject"))));

      $f->add($tab = new XQuickTable(array('style'=>'width:100%;'),
                                     array("", "Name", "E-mail", "School", "Role")));
      $row = 0;
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $count; $i++) {
        $acc = $list[$i];
        $tab->addRow(array(new XCheckboxInput('accounts[]', $acc->id, array('id'=>$acc->id)),
                           new XLabel($acc->id, $acc->getName()),
                           new XLabel($acc->id, new XA(sprintf("mailto:%s", $acc->id), $acc->id)),
                           new XLabel($acc->id, $acc->school->nick_name),
                           new XLabel($acc->id, $acc->role)));
      }
      if ($num_pages > 1) {
        require_once('xml5/LinksDiv.php');
        $p->add(new LinksDiv($num_pages, $pageset, "/pending", array(), 'page'));
      }
    }
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