<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Pane to edit (add/edit/remove) venues. This is yet the most
 * interesting of the editing panes in that it takes an extra argument
 * during construction, one of the TYPE_* constants, which makes this
 * pane act like more than one pane at a time.
 *
 */
class VenueManagement extends AbstractAdminUserPane {

  const NUM_PER_PAGE = 5;
  const TYPE_EDIT = "edit";
  const TYPE_LIST = "list";

  private $type;

  public function __construct(User $user, $type = self::TYPE_LIST) {
    parent::__construct("Venue management", $user);
    $this->type = $type;
  }

  public function fillHTML(Array $args) {
    switch ($this->type) {
    case self::TYPE_EDIT:
      $this->fillAdd($args);
      return;
    default:
      $this->fillList($args);
      return;
    }
  }

  private function fillAdd(Array $args) {
    $name = "";
    $addr = "";
    $city = "";
    $stat = "";
    $code = "";
    $mess = "Add";
    $hidd = new XText("");

    if (isset($args['v'])) {
      $v = Preferences::getVenue((int)$_GET['v']);
      if ($v === null) {
	$_SESSION['ANNOUNCE'][] = new PA("Invalid venue ID provided.", PA::E);
	WebServer::go("venue");
      }
      $name = $v->name;
      $addr = $v->address;
      $city = $v->city;
      $stat = $v->state;
      $code = $v->zipcode;
      $mess = "Edit";
      $hidd = new XHiddenInput("venue", $v->id);
    }

    if (isset($args['name']))    $name = $args['name'];
    if (isset($args['address'])) $addr = $args['address'];
    if (isset($args['city']))    $city = $args['city'];
    if (isset($args['state']))   $stat = $args['state'];
    if (isset($args['zipcode'])) $code = $args['zipcode'];
    // ------------------------------------------------------------
    // 1. Add new venue
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Add venue"));
    $p->add($f = new XForm("/venue-edit", XForm::POST));
    $f->add(new FItem("Name:", new XTextInput("name", $name, array("maxlength"=>40))));
    $f->add(new FItem("Address:", new XTextInput("address", $addr, array("maxlength"=>40))));
    $f->add(new FItem("City:", new XTextInput("city", $city, array("maxlength"=>20))));
    $f->add(new FItem("State:", $this->getStateSelect($stat)));
    $f->add(new FItem("Zipcode:", new XTextInput("zipcode", $code, array("maxlength"=>5))));
    $f->add($hidd);
    $f->add(new XSubmitInput("set-venue", $mess));
  }
  private function fillList(Array $args) {
    $pageset  = (isset($args['page'])) ? (int)$args['page'] : 1;
    if ($pageset < 1)
      WebServer::go("venue");
    $startint = self::NUM_PER_PAGE * ($pageset - 1);
    $count = Preferences::getNumVenues();
    $num_pages = ceil($count / self::NUM_PER_PAGE);
    if ($startint > $count)
      WebServer::go(sprintf("venue|%d", $num_pages));
    // ------------------------------------------------------------
    // 2. Current venues
    // ------------------------------------------------------------
    $list = Preferences::getVenues($startint, $startint + self::NUM_PER_PAGE);
    $this->PAGE->addContent($p = new XPort("Current venue list"));
    if (count($list) == 0) {
      $p->add(new XP(array(), "There are no venues in the database."));
      return;
    }
    $p->add(new XP(array(), "Click on the venue name in the table below to edit."));
    $p->add($t = new XQuickTable(array('style'=>'width:100%'), array("Name", "Address")));
    foreach ($list as $venue) {
      $t->addRow(array(new XA(sprintf("edit-venue?v=%d", $venue->id), $venue),
		       sprintf("%s %s, %s %s",
			       $venue->address,
			       $venue->city,
			       $venue->state,
			       $venue->zipcode)));
    }
  }

  public function process(Array $args) {
    if (isset($args['set-venue'])) {
      $venue = new Venue();
      // Check for existing venue
      if (isset($args['venue']) &&
	  ($venue = Preferences::getVenue((int)$args['venue'])) === null) {
	$_SESSION['ANNOUNCE'][] = new PA("Invalid venue to edit.", PA::E);
	return $args;
      }

      if (!isset($args['name']) || empty($args['name'])) {
	$_SESSION['ANNOUNCE'][] = new PA("Venue name must not be empty.",
						   PA::E);
	unset($args['name']);
	return $args;
      }
      $name = addslashes($args['name']);

      if (!isset($args['address']) || empty($args['address'])) {
	$_SESSION['ANNOUNCE'][] = new PA("Address field must not be empty.",
						   PA::E);
	unset($args['address']);
	return $args;
      }

      if (!isset($args['city']) || empty($args['city'])) {
	$_SESSION['ANNOUNCE'][] = new PA("City field must not be empty.",
						   PA::E);
	unset($args['city']);
	return $args;
      }

      if (!isset($args['state']) || !isset(self::$states[$args['state']])) {
	$_SESSION['ANNOUNCE'][] = new PA("Invalid state field.",
						   PA::E);
	unset($args['state']);
	return $args;
      }

      if (!isset($args['zipcode']) ||
	  !preg_match('/^[0-9]{5}$/', $args['zipcode'])) {
	$_SESSION['ANNOUNCE'][] = new PA("Invalid zipcode entered.",
						   PA::E);
	unset($args['zipcode']);
	return $args;
      }

      // Let's create one!
      $old_id = $venue->id;
      $venue->name = addslashes($args['name']);
      $venue->address = addslashes($args['address']);
      $venue->city = addslashes($args['city']);
      $venue->state = addslashes($args['state']);
      $venue->zipcode = addslashes($args['zipcode']);
      
      Preferences::setVenue($venue);
      if ($old_id == $venue->id) {
	$_SESSION['ANNOUNCE'][] = new PA(sprintf('Edited venue "%s".', $venue->name));
	WebServer::go("venue");
      }
      $_SESSION['ANNOUNCE'][] = new PA('Added new venue.');
      return array();
    }
  }

  /**
   * Helper method
   *
   */
  private function getStateSelect($chosen) {
    return XSelect::fromArray('state', self::$states, $chosen);
  }

  private static $states = array("AL" => "AL - ALABAMA",
				 "AK" => "AK - ALASKA",
				 "AS" => "AS - AMERICAN SAMOA",
				 "AZ" => "AZ - ARIZONA",
				 "AR" => "AR - ARKANSAS",
				 "CA" => "CA - CALIFORNIA",
				 "CO" => "CO - COLORADO",
				 "CT" => "CT - CONNECTICUT",
				 "DE" => "DE - DELAWARE",
				 "DC" => "DC - DISTRICT OF COLUMBIA",
				 "FL" => "FL - FLORIDA",
				 "GA" => "GA - GEORGIA",
				 "GU" => "GU - GUAM",
				 "HI" => "HI - HAWAII",
				 "ID" => "ID - IDAHO",
				 "IL" => "IL - ILLINOIS",
				 "IN" => "IN - INDIANA",
				 "IA" => "IA - IOWA",
				 "KS" => "KS - KANSAS",
				 "KY" => "KY - KENTUCKY",
				 "LA" => "LA - LOUISIANA",
				 "ME" => "ME - MAINE",
				 "MH" => "MH - MARSHALL ISLANDS",
				 "MD" => "MD - MARYLAND",
				 "MA" => "MA - MASSACHUSETTS",
				 "MI" => "MI - MICHIGAN",
				 "MN" => "MN - MINNESOTA",
				 "MS" => "MS - MISSISSIPPI",
				 "MO" => "MO - MISSOURI",
				 "MT" => "MT - MONTANA",
				 "NE" => "NE - NEBRASKA",
				 "NV" => "NV - NEVADA",
				 "NH" => "NH - NEW HAMPSHIRE",
				 "NJ" => "NJ - NEW JERSEY",
				 "NM" => "NM - NEW MEXICO",
				 "NY" => "NY - NEW YORK",
				 "NC" => "NC - NORTH CAROLINA",
				 "ND" => "ND - NORTH DAKOTA",
				 "MP" => "MP - NORTHERN MARIANA ISLANDS",
				 "OH" => "OH - OHIO",
				 "OK" => "OK - OKLAHOMA",
				 "OR" => "OR - OREGON",
				 "PW" => "PW - PALAU",
				 "PA" => "PA - PENNSYLVANIA",
				 "PR" => "PR - PUERTO RICO",
				 "RI" => "RI - RHODE ISLAND",
				 "SC" => "SC - SOUTH CAROLINA",
				 "SD" => "SD - SOUTH DAKOTA",
				 "TN" => "TN - TENNESSEE",
				 "TX" => "TX - TEXAS",
				 "UT" => "UT - UTAH",
				 "VT" => "VT - VERMONT",
				 "VI" => "VI - VIRGIN ISLANDS",
				 "VA" => "VA - VIRGINIA",
				 "WA" => "WA - WASHINGTON",
				 "WV" => "WV - WEST VIRGINIA",
				 "WI" => "WI - WISCONSIN",
				 "WY" => "WY - WYOMING");
}
?>