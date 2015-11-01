<?php
namespace users\super;

use \users\AbstractUserPane;
use \Account;

/**
 * Only for super users
 *
 */
abstract class AbstractSuperUserPane extends AbstractUserPane {
  public function __construct($title, Account $user) {
    parent::__construct($title, $user);
  }
}
