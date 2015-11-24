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

// Load files under 'unit' via namespace
spl_autoload_register(
  function($name) {
    $dirs = array(
      __DIR__ . '/unit',
    );
    $translation = str_replace('\\', '/', $name);
    foreach ($dirs as $dir) {
      $name = sprintf('%s/%s.php', $dir, $translation);
      if (file_exists($name)) {
        require_once($name);
      }
    }
  }
);