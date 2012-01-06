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

  public function __construct(User $user) {
    parent::__construct("My Account", $user);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("My information"));
    $p->add($form = new XForm("/account-edit", XForm::POST));
    $form->add(new FItem("First name:", new XTextInput("first_name", $this->USER->get(User::FIRST_NAME))));
    $form->add(new FItem("Last name:",  new XTextInput("last_name",  $this->USER->get(User::LAST_NAME))));
    $form->add(new XP(array(), "To leave password as is, leave the two fields below blank:"));
    $form->add(new FItem("New password:",     new XPasswordInput("sake1", "")));
    $form->add(new FItem("Confirm password:", new XPasswordInput("sake2", "")));
    $form->add(new XSubmitInput('edit-info', "Edit"));

    // new XTextInput("username",  $this->USER->get(User::LAST_NAME))));
  }

  public function process(Array $args) {
    if (isset($args['edit-info'])) {
      if (isset($args['first_name'])) {
	$name = trim($args['first_name']);
	if (empty($name)) {
	  $this->announce(new PA("First name cannot be empty.", PA::E));
	  return $args;
	}
	$this->USER->set(User::FIRST_NAME, $name);
      }
      if (isset($args['last_name'])) {
	$name = trim($args['last_name']);
	if (empty($name)) {
	  $this->announce(new PA("Last name cannot be empty.", PA::E));
	  return $args;
	}
	$this->USER->set(User::LAST_NAME, $name);
      }
      // password change?
      if (isset($args['sake1']) && isset($args['sake2']) &&
	  !(empty($args['sake1']) && empty($args['sake2']))) {
	if ($args['sake1'] != $args['sake2']) {
	  $this->announce(new PA("The passwords do not match.", PA::E));
	  return $args;
	}
	if (strlen($args['sake1']) < 8) {
	  $this->announce(new PA("The password must be at least 8 characters long.", PA::E));
	  return $args;
	}
	AccountManager::resetPassword($this->USER, $args['sake1']);
	$this->announce(new PA("Password reset."));
      }
      $this->announce(new PA("Information updated."));
    }
    return array();
  }
}
?>