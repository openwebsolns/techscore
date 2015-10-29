<?php
namespace users\super;

use \AbstractUserPane;
use \Account;

require_once('users/AbstractUserPane.php');

/**
 * Only for super users
 *
 */
abstract class AbstractSuperUserPane extends AbstractUserPane {
  public function __construct($title, Account $user) {
    parent::__construct($title, $user);
  }
}
