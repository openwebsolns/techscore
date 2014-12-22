<?php
/*
 * This file is part of Techscore
 */



/**
 * Exception class for permission-related issues
 *
 * @author Dayan Paez
 * @version 2014-05-11
 */
class PermissionException extends Exception {
  public $regatta;
  public function __construct($message = null, Regatta $regatta = null) {
    parent::__construct($message, 1);
    $this->regatta = $regatta;
  }
}
