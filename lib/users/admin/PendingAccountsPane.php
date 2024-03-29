<?php
use \users\admin\AbstractAccountPane;

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
        $f->add(new XP(array(), "This account's status is still pending approval. Please take a moment to review the account details below, before clicking one of the following buttons."));
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
                                     array("Name", "E-mail", "Affiliation", "Role", "Notes")));
      $row = 0;
      for ($i = $startint; $i < $startint + self::NUM_PER_PAGE && $i < $count; $i++) {
        $acc = $list[$i];
        $tab->addRow(array(new XA(WS::link('/pending', array('account'=>$acc->id)), $acc->getName()),
                           new XA(sprintf("mailto:%s", $acc->email), $acc->email),
                           $acc->getAffiliation(),
                           ucfirst($acc->role),
                           $acc->message));
      }
      if ($num_pages > 1) {
        require_once('xml5/LinksDiv.php');
        $p->add(new LinksDiv($num_pages, $pageset, "/pending", array(), 'page'));
      }
    }

  }

  public function process(Array $args) {
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

  /**
   * Sends message to approved user
   *
   * @param Account $acc the account to notify
   * @return boolean the result of DB::mail
   */
  protected function notifyApprovedUser(Account $acc) {
    if (DB::g(STN::MAIL_APPROVED_USER) === null)
      return false;

    return DB::mailAccount(
      $acc,
      sprintf("[%s] Account approved", DB::g(STN::APP_NAME)),
      DB::keywordReplace(DB::g(STN::MAIL_APPROVED_USER), $acc, $acc->getFirstSchool())
    );
  }
}
?>
