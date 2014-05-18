<?php
/*
 * This file is part of TechScore
 *
 * @package users-reports
 */

require_once('users/AbstractUserPane.php');

/**
 * Common parent for all report panes
 *
 * @author Dayan Paez
 * @created 2014-05-18
 */
abstract class AbstractReportPane extends AbstractUserPane {
  public function __construct($title, Account $user) {
    parent::__construct($title, $user);
  }
}
?>