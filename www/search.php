<?php
/**
 * Here's something! A non-processing page: meant only for searching
 * the database of sailors, because that could be useful, no?
 *
 * @author Dayan Paez
 * @created 2011-04-20
 */

require_once('../lib/conf.php');

//
// Is logged-in
//
if (!Session::has('user')) {
  Session::s('last_page', $_SERVER['REQUEST_URI']);

  // provide the login page
  Session::pa(new PA("Please login to proceed.", PA::I));
  require_once('users/LoginPage.php');
  $PAGE = new LoginPage();
  $PAGE->getHTML($_GET);
  exit;
}

require_once('xml5/XmlLib.php');
$P = new XDoc('SailorSearch', array('version'=>'1.0'));

// Validate input
if (!isset($_GET['q']) || strlen($_GET['q']) < 3) {
  header('HTTP/1.1 400 Bad request');
  $P->set('count', -1);
  $P->add(new XElem('Error', array(), array(new XText("Please provide a long enough query to search."))));
  $P->printXML();
  exit;
}

$results = DB::searchSailors($_GET['q'], true);
$P->set('count', count($results));
foreach ($results as $result) {
  $P->add(new XElem('Sailor', array('id'=>$result->id, 'icsa_id'=>$result->icsa_id),
                    array(new XElem('FirstName', array(), array(new XText($result->first_name))),
                          new XElem('LastName',  array(), array(new XText($result->last_name))),
                          new XElem('Year',      array(), array(new XText($result->year))),
                          new XElem('Gender',    array(), array(new XText($result->gender))),
                          new XElem('School',    array('id' => $result->school->id),
                                    array(new XText($result->school->name))))));
}
$P->printXML();
?>