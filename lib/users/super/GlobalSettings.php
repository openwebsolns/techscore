<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/super/AbstractSuperUserPane.php');

/**
 * Manage the global settings for this installation of Techscore
 *
 * @author Dayan Paez
 * @created 2013-11-24
 */
class GlobalSettings extends AbstractSuperUserPane {

  public function __construct(Account $user) {
    parent::__construct("Global settings", $user);
    $this->page_url = 'conf';
  }

  public function fillHTML(Array $args) {

  }

  public function process(Array $args) {
    throw new SoterException("A work in progress.");
  }
}
?>