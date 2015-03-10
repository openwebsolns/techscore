<?php
/*
 * This file is part of Techscore
 *
 * Unit test configuration, for PHPUnit.
 *
 * @author Dayan Paez
 * @created 2015-03-04
 */
require_once(dirname(__DIR__) . '/lib/conf.php');
require_once('error/PanicHandler.php');

// Always throw exceptions
PanicHandler::registerAll();
?>