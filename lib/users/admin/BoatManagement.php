<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manages (edits, adds, removes) boats from the database
 *
 */
class BoatManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Boat management", $user);
    $this->page_url = 'boats';
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
    $hidd = "";
    $link = null;
    // ------------------------------------------------------------
    // 0a. Editing boat?
    // ------------------------------------------------------------
    if (isset($args['b'])) {
      $boat = DB::getBoat($args['b']);
      if ($boat === null) {
        Session::pa(new PA("Invalid boat to edit."));
        WS::go('/boats');
      }
      $mess = "Edit boat";
      $link = new XA("boats", "Cancel");
      $hidd = new XHiddenInput("boat", $boat->id);
    }

    // 0b. Process requests from $args
    // ------------------------------------------------------------
    if (isset($args['name']))          $boat->name = $args['name'];
    if (isset($args['occupants'])) $boat->occupants = $args['occupants'];

    // ------------------------------------------------------------
    // 1. Add/edit new boat
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort($mess));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "The number of occupants will be used when entering RP information to determine how many crews are allowed in an RP form. If the same boat class can have multiple number of crews, add separate entries and distinguish them by adding the number of occupants in the name."));

    $form->add(new XP(array(), "Keep in mind that the number of occupants includes 1 skipper. Therefore, the minimum value is 1 for a singlehanded boat class."));

    $form->add(new FItem("Name:", new XTextInput("name", $boat->name, array("maxlength"=>"15"))));
    $form->add(new FItem("Number of occupants:", new XTextInput("occupants", $boat->occupants)));
    $form->add($hidd);
    $form->add(new XSubmitInput("set-boat", $mess));
    if ($link !== null) {
      $form->add(" ");
      $form->add($link);
    }

    // ------------------------------------------------------------
    // 2. Current boat list
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("All boat classes"));
    $p->add(new XP(array(), "Click on the boat name to edit that boat."));
    $p->add($tab = new XQuickTable(array(), array("Name", "No. Occupants")));
    foreach (DB::getBoats() as $boat) {
      $tab->addRow(array(new XA(sprintf("boats?b=%d", $boat->id), $boat->name), $boat->occupants));
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
        $boat = DB::getBoat((int)$args['boat']);
        if ($boat == null) {
          Session::pa(new PA("Invalid boat to edit.", PA::E));
          unset($args['boat']);
          return $args;
        }
        $mess = "Edited boat.";
      }
      if (isset($args['name'])) $boat->name = $args['name'];
      if (isset($args['occupants']) && $args['occupants'] >= 1) {
        $boat->occupants = (int)$args['occupants'];
      }
      else {
        Session::pa(new PA("Invalid value for number of occupants.", PA::E));
        return $args;
      }
      DB::set($boat);
      Session::pa(new PA($mess));
      Session::s('POST', array());
      WS::go('/boats');
    }
    return $args;
  }
}
?>