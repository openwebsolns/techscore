<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

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
?>