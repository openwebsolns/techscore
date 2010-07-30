<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

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
    $mess = "Add";
    $hidd = "";
    if (isset($args['v'])) {
      $v = Preferences::getVenue((int)$_GET['v']);
      if ($v === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid venue ID provided.", Announcement::ERROR);
	WebServer::go("venue");
      }
      $name = $v->name;
      $addr = $v->address;
      $city = $v->city;
      $stat = $v->state;
      $code = $v->zipcode;
      $mess = "Edit";
      $hidd = new FHidden("venue", $v->id);
    }

    if (isset($args['name']))    $name = $args['name'];
    if (isset($args['address'])) $addr = $args['address'];
    if (isset($args['city']))    $city = $args['city'];
    if (isset($args['state']))   $stat = $args['state'];
    if (isset($args['zipcode'])) $code = $args['zipcode'];
    // ------------------------------------------------------------
    // 1. Add new venue
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port("Add venue"));
    $p->addChild($f = new Form("venue-edit"));
    $f->addChild(new FItem("Name:", new FText("name", $name, array("maxlength"=>40))));
    $f->addChild(new FItem("Address:", new FText("address", $addr, array("maxlength"=>40))));
    $f->addChild(new FItem("City:", new FText("city", $city, array("maxlength"=>20))));
    $f->addChild(new FItem("State:", $this->getStateSelect($stat)));
    $f->addChild(new FItem("Zipcode:", new FText("zipcode", $code, array("maxlength"=>5))));
    $f->addChild($hidd);
    $f->addChild(new FSubmit("set-venue", $mess));
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
    $this->PAGE->addContent($p = new Port("Current venue list"));
    if (count($list) == 0) {
      $p->addChild(new Para("There are no venues in the database."));
      return;
    }
    $p->addChild(new Para("Click on the venue name in the table below to edit."));
    $p->addChild($t = new Table());
    $t->addAttr("style", "width:100%;");
    $t->addHeader(new Row(array(Cell::th("Name"),
				Cell::th("Address"))));
    foreach ($list as $venue) {
      $t->addRow(new Row(array(new Cell(new Link(sprintf("edit-venue?v=%d", $venue->id), $venue)),
			       new Cell(sprintf("%s %s, %s %s",
						$venue->address,
						$venue->city,
						$venue->state,
						$venue->zipcode)))));
    }
  }

  public function process(Array $args) {
    if (isset($args['set-venue'])) {
      $venue = new Venue();
      // Check for existing venue
      if (isset($args['venue']) &&
	  ($venue = Preferences::getVenue((int)$args['venue'])) === null) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid venue to edit.", Announcement::ERROR);
	return $args;
      }

      if (!isset($args['name']) || empty($args['name'])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Venue name must not be empty.",
						   Announcement::ERROR);
	unset($args['name']);
	return $args;
      }
      $name = addslashes($args['name']);

      if (!isset($args['address']) || empty($args['address'])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Address field must not be empty.",
						   Announcement::ERROR);
	unset($args['address']);
	return $args;
      }

      if (!isset($args['city']) || empty($args['city'])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("City field must not be empty.",
						   Announcement::ERROR);
	unset($args['city']);
	return $args;
      }

      if (!isset($args['state']) || !isset($this->states[$args['state']])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid state field.",
						   Announcement::ERROR);
	unset($args['state']);
	return $args;
      }

      if (!isset($args['zipcode']) ||
	  !preg_match('/^[0-9]{5}$/', $args['zipcode'])) {
	$_SESSION['ANNOUNCE'][] = new Announcement("Invalid zipcode entered.",
						   Announcement::ERROR);
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
	$_SESSION['ANNOUNCE'][] = new Announcement(sprintf('Edited venue "%s".', $venue->name));
	WebServer::go("venue");
      }
      $_SESSION['ANNOUNCE'][] = new Announcement('Added new venue.');
      return array();
    }
  }

  /**
   * Helper method
   *
   */
  private function getStateSelect($chosen) {
    $state_sel = new FSelect("state", array());
    foreach ($this->states as $code => $state) {
      $state_sel->addChild($opt = new Option($code, $state));
      if ($chosen == $code)
	$opt->addAttr("selected", "selected");
    }
    return $state_sel;
  }

  private $states = array("MA"=>"MA",
			  "FL"=>"FL");
}
?>