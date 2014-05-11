<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAccountPane.php');

/**
 * Pane to edit (approve/reject) pending accounts
 *
 */
class PendingAccountsPane extends AbstractAccountPane {

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
    $this->page_url = 'pending';
  }

  /**
   * Generates and returns the HTML page
   *
   */
  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific account?
    // ------------------------------------------------------------
    if (isset($args['account'])) {
      try {
	$account = DB::getAccount($args['account']);
	if ($account === null || $account->status != Account::STAT_PENDING)
	  throw new SoterException("Invalid account requested.");

	$this->PAGE->addContent($p = new XPort("Approve or reject"));
	$p->set('id', 'approve-port');
	$p->add($f = $this->createForm());
	$f->add(new XHiddenInput('account', $account->id));
	$f->add(new XP(array(), "This account's status is still pending approval. Please take a moment to review the account details below, before clicking one of the buttons below."));
	$f->add($xp = new XSubmitP('approve', "Approve"));
	$xp->add(" ");
	$xp->add(new XSubmitDelete('reject', "Reject"));

	$this->fillUser($account);

	return;
      }
      catch (SoterException $e) {
	Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // List pending
    // ------------------------------------------------------------
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
      $p->add(new XP(array(), "Below is a list of pending accounts. Click on the account name to approve or reject that account."));

      $p->add($tab = new XQuickTable(array('class'=>'full pending-accounts'),
                                     array("Name", "E-mail", "School", "Role", "Notes")));
      $row = 0;
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $count; $i++) {
        $acc = $list[$i];
        $tab->addRow(array(new XA(WS::link('/pending', array('account'=>$acc->id)), $acc->getName()),
                           new XA(sprintf("mailto:%s", $acc->id), $acc->id),
                           $acc->school->nick_name,
                           $acc->role,
			   $acc->message));
      }
      if ($num_pages > 1) {
        require_once('xml5/LinksDiv.php');
        $p->add(new LinksDiv($num_pages, $pageset, "/pending", array(), 'page'));
      }
    }

    $this->PAGE->addContent($p = new XPort("Allow registrations"));
    $p->add($f = $this->createForm());
    $f->add(new XP(array(), "Check the box below to allow users to register for accounts. If unchecked, users will not be allowed to apply for new accounts. Note that this action has no effect on any pending users listed above."));
    $f->add(new FItem("Allow:", new FCheckbox(STN::ALLOW_REGISTER, 1, "Users may register for new accounts through the site.", DB::g(STN::ALLOW_REGISTER) !== null)));
    $f->add(new XSubmitP('set-register', "Save changes"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Update register flag
    // ------------------------------------------------------------
    if (isset($args['set-register'])) {
      $val = DB::$V->incInt($args, STN::ALLOW_REGISTER, 1, 2, null);
      if ($val != DB::g(STN::ALLOW_REGISTER)) {
        DB::s(STN::ALLOW_REGISTER, $val);
        if ($val === null)
          Session::pa(new PA("Users will no longer be able to register for new accounts.", PA::I));
        else
          Session::pa(new PA("Users can register for new accounts, subject to approval."));
      }
      return;
    }

    // ------------------------------------------------------------
    // Approve / Reject
    // ------------------------------------------------------------
    if (isset($args['account'])) {
      $account = DB::getAccount($args['account']);
      if ($account === null || $account->status != Account::STAT_PENDING)
	throw new SoterException("Invalid account provided.");

      // Approve
      if (isset($args['approve'])) {
	$account->status = Account::STAT_ACCEPTED;
	DB::set($account);

	// Notify user
	if (!$this->notifyApprovedUser($account))
	  Session::pa(new PA("Unable to notify user of account approval. Consider sending manual e-mail.", PA::I));
	Session::pa(new PA(sprintf("Approved account for %s.", $account)));
      }
      elseif (isset($args['reject'])) {
	$account->status = Account::STAT_REJECTED;
	DB::set($account);
	Session::pa(new PA(sprintf("Rejected account for %s.", $account)));
      }
      else
	throw new SoterException("Invalid action provided.");

      $this->redirect('pending');
    }

    return parent::process($args);
  }
}
?>