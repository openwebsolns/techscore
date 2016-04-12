<?php
namespace users;

use \Session;
use \xml5\SessionParams;

/**
 * A boring, but necessary page.
 *
 * @author Dayan Paez
 * @version 2016-03-25
 */
class LogoutPage extends AbstractUserPane {

  public function __construct() {
    parent::__construct("Logout");
  }

  protected function fillHTML(Array $args) {
    $this->logout();
  }

  public function process(Array $args) {
    $this->logout();
  }

  private function logout() {
    Session::s(SessionParams::USER, null);
    session_destroy();
    $this->redirectTo('HomePane');
  }

}