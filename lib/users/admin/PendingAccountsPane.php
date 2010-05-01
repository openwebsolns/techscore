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
class PendingAccountsPane extends AbstractUserPane {

  private $manager;

  /**
   * Creates a new such pane
   *
   * @param User $user the administrator with access
   * @throws InvalidArgumentException if the User is not an
   * administrator
   */
  public function __construct(User $user) {
    parent::__construct("Pending users", $user);
    $this->manager = new AccountManager();
  }

  /**
   * Generates and returns the HTML page
   *
   */
  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("Pending accounts"));
    
  }

  public function process(Array $args) {
    return $args;
  }
}
?>