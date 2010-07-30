<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */
require_once("conf.php");

/**
 * Pane to edit (approve/reject) pending accounts
 *
 */
class PendingAccountsPane extends AbstractAdminUserPane {

  const NUM_PER_PAGE = 10;

  /**
   * Creates a new such pane
   *
   * @param User $user the administrator with access
   * @throws InvalidArgumentException if the User is not an
   * administrator
   */
  public function __construct(User $user) {
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
      WebServer::go("pending");
    $startint = self::NUM_PER_PAGE * ($pageset - 1);
    $count = AccountManager::getNumPendingUsers();
    $num_pages = ceil($count / self::NUM_PER_PAGE);
    if ($startint > $count)
      WebServer::go(sprintf("pending|%d", $num_pages));
    
    $list = AccountManager::getPendingUsers($startint, $startint + self::NUM_PER_PAGE);
    $this->PAGE->addContent($p = new Port("Pending accounts"));
    if ($count == 0) {
      $p->addChild(new Para("There are no pending accounts."));
    }
    else {
      $p->addChild(new Para("Use the checkboxes below to select the accounts, and then click " .
			    "on the appropriate button to approve/reject."));
      $p->addChild($f = new Form("pending-edit"));
      $f->addChild($para = new Para("With selected: "));
      $para->addChild(new FSubmit("approve", "Approve"));
      $para->addChild(new Text(" "));
      $para->addChild(new FSubmit("reject",  "Reject"));

      $f->addChild($tab = new Table());
      $tab->addAttr("style", "width: 100%");
      $tab->addHeader(new Row(array(Cell::th(""), // select all/none checkbox?
				    Cell::th("Name"),
				    Cell::th("E-mail"),
				    Cell::th("School"),
				    Cell::th("Role"))));
      $row = 0;
      foreach ($list as $acc) {
	$tab->addRow($r = new Row(array(new Cell(new FCheckBox("accounts[]",
							       $acc->username,
							       array("id"=>$acc->username))),
					new Cell(new Label($acc->username, $acc->getName())),
					new Cell(new Link(sprintf("mailto:%s", $acc->username),
							  $acc->username)),
					new Cell(new Label($acc->username, $acc->school->nick_name)),
					new Cell(new Label($acc->username, $acc->role)))));
      }
      if ($num_pages > 1)
	$p->addChild(new PageDiv($num_pages, $pageset, "pending"));
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
	if (!isset($args['accounts']) ||
	    !is_array($args['accounts']) ||
	    empty($args['accounts'])) {
	  $_SESSION['ANNOUNCE'][] = new Announcement("No accounts chosen.", Announcement::ERROR);
	  return $args;
	}

	$success = array();
	$errors  = 0;
	foreach ($args['accounts'] as $id) {
	  $acc = AccountManager::getAccount($id);
	  if ($acc === null || $acc->status != "pending")
	    $errors++;
	  else {
	    $acc->status = $legend[$action]["status"];
	    AccountManager::setAccount($acc);
	    $success[] = $acc->username;
	  }
	}

	// Announce the good news
	if ($errors > 0) {
	  $_SESSION['ANNOUNCE'][] = new Announcement(sprintf("%s %d accounts.",
							     $legend[$action]["error"], $errors),
						     Announcement::WARNING);
	}
	if (count($success) > 0) {
	  $_SESSION['ANNOUNCE'][] = new Announcement(sprintf("%s %s.",
							     $legend[$action]["success"],
							     implode(", ", $success)));
	}
      }
    }
    return array();
  }
}
?>