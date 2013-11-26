<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/AbstractUserPane.php');

/**
 * Only for priviledged users
 *
 */
abstract class AbstractAdminUserPane extends AbstractUserPane {
  public function __construct($title, Account $user) {
    parent::__construct($title, $user);

    if (!$user->isAdmin())
      throw new PaneException("Insufficient permission.");
  }

  protected function keywordReplaceTable() {
    $tab = new XQuickTable(array('id'=>'keyword-replace'), array("Keyword", "Description", "Example"));
    $kws = array(
                 '{FULL_NAME}' => "Full name of user",
                 '{FIRST_NAME}' => "First name of user",
                 '{LAST_NAME}' => "Last name of user",
                 '{SCHOOL}' => "User's school",
                 '{ROLE}' => "Account role (coach, etc)",
                 );

    foreach ($kws as $kw => $desc)
      $tab->addRow(array($kw, $desc, DB::keywordReplace($this->USER, $kw)));

    return $tab;
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

    return DB::mail($acc->id,
                    sprintf("[%s] Account approved", DB::g(STN::APP_NAME)),
                    DB::keywordReplace($acc, DB::g(STN::MAIL_APPROVED_USER)));
  }
}
?>