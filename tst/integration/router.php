<?php
use \mail\TSMailer;
use \mail\senders\SessionMailSender;

/**
 * Router script expected by php -S.
 *
 * Acts as Apache's rewrite engine.
 *
 * @author Dayan Paez
 * @created 2015-03-04
 */

$envroot = dirname(dirname(__DIR__));
$webroot = $envroot . '/www';

$filepath = $webroot . $_SERVER['REQUEST_URI'];
if (is_file($filepath)) {
  return false;
}

ini_set('include_path', sprintf('.:%s/lib', $envroot));
require_once('conf.php');

// Don't send e-mails
TSMailer::setEmailSender(new SessionMailSender());

require_once($webroot . '/index.php');
