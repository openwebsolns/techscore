<?php
/**
 * This file is part of TechScore
 *
 * @package users
 */
require_once('conf.php');

/**
 * Manages the user's own account information
 *
 * @author Dayan Paez
 * @date   2010-09-19
 */
class AccountPane extends AbstractUserPane {

  public function __construct(User $user) {
    parent::__construct("My Account", $user);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new Port("My information"));
    $p->addChild($form = new Form("account-edit"));
    $form->addChild(new FItem("First name:", new FText("first_name", $this->USER->get(User::FIRST_NAME))));
    $form->addChild(new FItem("Last name:",  new FText("last_name",  $this->USER->get(User::LAST_NAME))));
    $form->addChild(new Para("To leave password as is, leave the two fields below blank:"));
    $form->addChild(new FItem("New password:",     new FPassword("sake1", "")));
    $form->addChild(new FItem("Confirm password:", new FPassword("sake2", "")));
    // new FText("username",  $this->USER->get(User::LAST_NAME))));
  }

  public function process(Array $args) {
    // @TODO
  }
}
?>