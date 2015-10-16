<?php
use \error\PanicHandler;

/*
 * This file is part of Techscore
 *
 * Unit test configuration, for PHPUnit.
 *
 * @author Dayan Paez
 * @created 2015-03-04
 */
require_once(dirname(__DIR__) . '/lib/conf.php');

// Always throw exceptions
(new PanicHandler())->registerAll();

$_SESSION = array();
