<?php
namespace users;

use \Session;

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
    Session::s('user', null);
    session_destroy();
    $this->redirect('');
  }

}