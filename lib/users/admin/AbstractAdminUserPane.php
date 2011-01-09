<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

/**
 * Only for priviledged users
 *
 */
abstract class AbstractAdminUserPane extends AbstractUserPane {
  public function __construct($title, User $user) {
    parent::__construct($title, $user);

    if ($user->get(User::ADMIN) === false)
      WebServer::go('/');
  }
}
?>