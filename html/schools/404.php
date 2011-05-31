<?php
/**
 * Custom 404 page to make navigating around schools a little
 * easier. This script uses the static (cached) database of schools if
 * available, and not a MySQLi database connection in an attempt to be
 * as speedy as possible. Upon failure to redirect, the script will
 * present a custom 404 error message, which can be generated using
 * lib/scripts/Update404.php
 *
 * @author Dayan Paez
 * @created 2011-05-30
 */

function redirect($school_id, $in_page) {
  // redirect straight to the page, if available
  if (file_exists(sprintf('%s/%s/%s.html', dirname(__FILE__), $school_id, $in_page))) {
    header('HTTP/1.1 301 Moved permanently');
    header(sprintf('Location: /schools/%s/%s', $school_id, str_replace('index', '', $in_page)));
    exit;
  }

  header('HTTP/1.1 301 Moved permanently');
  header(sprintf('Location: /schools/%s/', $school_id));
  exit;
}

// ------------------------------------------------------------
// 1. Check for simple misspellings
// ------------------------------------------------------------
$uri = str_replace('/schools/', '', $_SERVER['REQUEST_URI']);
$tok = explode('/', $uri);
$school_id = implode(" ", preg_split('/[^A-Z0-9.]/', strtoupper($tok[0])));
$in_page = 'index';
if (count($tok) > 1)
  $in_page = strtolower(str_replace('.html', '', $tok[1]));

// redirect to the school, at least
if (is_dir(sprintf('%s/%s', dirname(__FILE__), $school_id)))
  redirect($school_id, $in_page);


// ------------------------------------------------------------
// 2. Possible nick name: only if static cache exists
// ------------------------------------------------------------
if (($db = realpath(dirname(__FILE__).'/../../cache/schools.db')) !== false) {
  $db = file_get_contents($db);

  // is there a nick-name match?
  $matches = array();
  if (preg_match(sprintf("/^([A-Z]+)\t(%s)\t/m", $school_id), $db, $matches) > 0)
    redirect($matches[1], $in_page);

  $matches = array();
  if (preg_match(sprintf("/^([A-Z]+)\t[^\t]+\t%s$/m", $school_id), $db, $matches) > 0)
    redirect($matches[1], $in_page);
}

// ------------------------------------------------------------
// Last resort: 404 page from file, or from script, whichever is
// available
// ------------------------------------------------------------
header('HTTP/1.1 404 Page not found');
if (($path = realpath(dirname(__FILE__).'/../../cache/404-schools.html')) !== false) {
  echo file_get_contents($path);
  exit;
}

ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../../lib'));
require_once('conf.php');
require_once('scripts/Update404.php');

$M = new Update404('schools');
echo $M->getPage();
?>