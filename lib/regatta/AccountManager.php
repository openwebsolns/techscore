<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

/**
 * Takes care of dealing with user accounts in a static fashion
 *
 * @author Dayan Paez
 * @version 2010-04-20
 */
class AccountManager {

  /**
   * Checks that the account holder is active. Otherwise, redirect to
   * license. Otherwise, redirect out
   *
   * @param User $user the user to check
   * @throws InvalidArgumentException if invalid parameter
   */
  public static function requireActive(User $user) {
    switch ($user->get(User::STATUS)) {
    case "active":
      return;

    case "accepted":
      WebServer::go("license");

    default:
      WebServer::go('/');
    }
  }

  // ROLE based
}
?>