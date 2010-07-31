<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

/**
 * Manages (edits, adds, removes) boats from the database
 *
 */
class BoatManagement extends AbstractAdminUserPane {

  public function __construct(User $user) {
    parent::__construct("Boat management", $user);
  }

  /**
   * Returns empty boat
   */
  private function defaultBoat() {
    $boat = new Boat();
    $boat->name = "";
    $boat->occupants = 2;
    return $boat;
  }

  public function fillHTML(Array $args) {
    $boat = $this->defaultBoat();
    $mess = "Add boat";
    $hidd = new Text("");
    $link = new Text("");
    // ------------------------------------------------------------
    // 0a. Editing boat?
    // ------------------------------------------------------------
    if (isset($args['b'])) {
      $boat = Preferences::getBoat($args['b']);
      if ($boat === null) {
	$this->announce(new Announcement("Invalid boat to edit."));
	WebServer::go("boats");
      }
      $mess = "Edit boat";
      $link = new Link("boats", "Cancel");
      $hidd = new FHidden("boat", $boat->id);
    }

    // 0b. Process requests from $args
    // ------------------------------------------------------------
    if (isset($args['name']))          $boat->name = $args['name'];
    if (isset($args['occupants'])) $boat->occupants = $args['occupants'];

    // ------------------------------------------------------------
    // 1. Add/edit new boat
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port($mess));
    $p->addChild($form = new Form("boat-edit"));
    $form->addChild(new Para("The number of occupants will be used when entering RP information to " .
			     "determine how many crews are allowed in an RP form. If the same boat " .
			     "class can have multiple number of crews, add separate entries and " .
			     "distinguish them by adding the number of occupants in the name."));
    
    $form->addChild(new Para("Keep in mind that the number of occupants includes 1 skipper. Therefore, " .
			     "the minimum value is 1 for a singlehanded boat class."));
    
    $form->addChild(new FItem("Name:", new FText("name", $boat->name, array("maxlength"=>"15"))));
    $form->addChild(new FItem("Number of occupants:", new FText("occupants", $boat->occupants)));
    $form->addChild($hidd);
    $form->addChild(new FSubmit("set-boat", $mess));
    $form->addChild($link);

    // ------------------------------------------------------------
    // 2. Current boat list
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port("All boat classes"));
    $p->addChild(new Para("Click on the boat name to edit that boat."));
    $p->addChild($tab = new Table());
    $tab->addHeader(new Row(array(Cell::th("Name"),
				  Cell::th("No. Occupants"))));
    foreach (Preferences::getBoats() as $boat) {
      $tab->addRow(new Row(array(new Cell(new Link(sprintf("boats?b=%d", $boat->id), $boat->name)),
				 new Cell($boat->occupants))));
    }
  }
  
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Add/edit boat
    // ------------------------------------------------------------
    if (isset($args['set-boat'])) {
      $boat = new Boat();
      $mess = "Added new boat.";
      if (isset($args['boat'])) {
	$boat = Preferences::getBoat((int)$args['boat']);
	if ($boat == null) {
	  $this->announce(new Announcement("Invalid boat to edit.", Announcement::ERROR));
	  unset($args['boat']);
	  return $args;
	}
	$mess = "Edited boat.";
      }
      if (isset($args['name'])) $boat->name = addslashes($args['name']);
      if (isset($args['occupants']) && $args['occupants'] >= 1) {
	$boat->occupants = (int)$args['occupants'];
      }
      else {
	$this->announce(new Announcement("Invalid value for number of occupants.", Announcement::ERROR));
	return $args;
      }
      Preferences::setBoat($boat);
      $this->announce(new Announcement($mess));
      $_SESSION['POST'] = array();
      WebServer::go("boats");
    }
    return $args;
  }
}
?>