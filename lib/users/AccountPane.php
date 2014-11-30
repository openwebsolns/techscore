<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Manages the user's own account information
 *
 * @author Dayan Paez
 * @version   2010-09-19
 */
class AccountPane extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("My Account", $user);
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Reset session
    // ------------------------------------------------------------
    if (array_key_exists('reset', $args)) {
      Session::d('usurped_user');
      $this->redirect();
    }

    $this->PAGE->addContent($p = new XPort("My information"));
    $p->add($form = $this->createForm());
    $form->add(new FReqItem("First name:", new XTextInput("first_name", $this->USER->first_name, array('maxlength'=>30))));
    $form->add(new FReqItem("Last name:",  new XTextInput("last_name",  $this->USER->last_name, array('maxlength'=>30))));
    $form->add(new FReqItem("Role:",  XSelect::fromArray('role', Account::getRoles(), $this->USER->role)));
    $form->add(new XSubmitP('edit-info', "Edit"));

    $this->PAGE->addContent($p = new XPort("Change password"));
    $p->add($form = $this->createForm());
    $form->add(new FReqItem("Current password:", new XPasswordInput('current', "")));
    $form->add(new FReqItem("New password:",     new XPasswordInput("sake1", "")));
    $form->add(new FReqItem("Confirm password:", new XPasswordInput("sake2", "")));
    $form->add(new XSubmitP('edit-password', "Change"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // edit info
    // ------------------------------------------------------------
    if (isset($args['edit-info'])) {
      $this->USER->first_name = DB::$V->reqString($args, 'first_name', 1, 31, "First name cannot be empty (and must be less than 30 characters.");
      $this->USER->last_name = DB::$V->reqString($args, 'last_name', 1, 31, "Last name cannot be empty (and must be less than 30 characters.");
      $this->USER->role = DB::$V->reqKey($args, 'role', Account::getRoles(), "Invalid role provided.");
      DB::set($this->USER);
      Session::pa(new PA("Information updated."));
    }

    // ------------------------------------------------------------
    // password change?
    // ------------------------------------------------------------
    if (isset($args['edit-password'])) {
      $cur = DB::$V->reqRaw($args, 'current', 1, 101, "No current password provided.");
      if ($this->USER->password != DB::createPasswordHash($this->USER, $cur))
        throw new SoterException("Invalid current password provided.");

      $pw1 = DB::$V->reqRaw($args, 'sake1', 8, 101, "The password must be at least 8 characters long.");
      $pw2 = DB::$V->reqRaw($args, 'sake2', strlen($pw1), strlen($pw1) + 1, "The two passwords do not match.");
      if ($pw1 != $pw2)
        throw new SoterException("The password confirmation does not match.");
      $this->USER->password = DB::createPasswordHash($this->USER, $pw1);
      DB::set($this->USER);
      Session::pa(new PA("Password reset."));
    }
    return array();
  }
}
?>