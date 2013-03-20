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
    $boat->min_crews = 1;
    $boat->max_crews = 1;
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
    if (isset($args['name']))      $boat->name = $args['name'];
    if (isset($args['min_crews'])) $boat->min_crews = $args['min_crews'];
    if (isset($args['max_crews'])) $boat->max_crews = $args['max_crews'];

    // ------------------------------------------------------------
    // 1. Add/edit new boat
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort($mess));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "The number of occupants will be used when entering RP information to determine how many crews to ask for in an RP form. It is assumed that all teams sail in the same boat class in a given race. Because of this, you may need to create a different boat class if the minimum and maximum number of crews allowed differ."));
    $form->add(new XP(array(), "For example, MIT's Techs can be sailed as singlehanded or with at most one crew. However, when they are sailed with a crew, every team sails with a crew. Thus, you should create 2 boat classes: one for Tech (single) and one for Tech (double). The latter would have a minimum and maximum requirement of 1 crew."));

    $form->add(new FItem("Name:", new XTextInput('name', $boat->name, array('maxlength'=>'15', 'required'=>'required'))));
    $form->add(new FItem("Minimum # Crews:", new XInput('number', 'min_crews', $boat->min_crews, array('required'=>'required', 'min'=>0, 'max'=>127, 'step'=>1))));
    $form->add(new FItem("Maximum # Crews:", new XInput('number', 'max_crews', $boat->max_crews, array('required'=>'required', 'min'=>0, 'max'=>127, 'step'=>1))));
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
    $p->add(new XP(array(), "Click on the boat name to edit that boat. To delete an unused boat class, check the box and click the \"Delete\" button."));
    $p->add($f = $this->createForm());
    $f->add($tab = new XQuickTable(array(), array("Name", "No. Crews", "Delete")));
    foreach (DB::getBoats() as $boat) {
      $crew = $boat->min_crews;
      if ($boat->max_crews != $boat->min_crews)
        $crew .= '-' . $boat->max_crews;

      $del = '';
      if (count($boat->getRaces()) == 0)
        $del = new XCheckboxInput('boat[]', $boat->id, array('title'=>"Delete this boat class."));

      $tab->addRow(array(new XTD(array('class'=>'left'), new XA(sprintf("boats?b=%d", $boat->id), $boat->name)),
                         $crew,
                         $del));
    }
    $f->add(new XSubmitP('delete-boats', "Delete"));
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
        if ($boat == null)
          throw new SoterException("Invalid boat to edit.");
        $mess = "Edited boat.";
      }
      $boat->name = DB::$V->incString($args, 'name', 1, 16, $boat->name);
      $boat->min_crews = DB::$V->incInt($args, 'min_crews', 0, 128, $boat->min_crews);
      $boat->max_crews = DB::$V->incInt($args, 'max_crews', $boat->min_crews, 128, $boat->min_crews);
      if ($boat->name === null || $boat->min_crews === null || $boat->max_crews === null)
        throw new SoterException("Missing parameters.");
      if ($boat->max_crews < $boat->min_crews)
        throw new SoterException("Maximum number of crews must be greater than or equal to minimum.");

      DB::set($boat);
      Session::pa(new PA($mess));
      WS::go('/boats');
    }

    // ------------------------------------------------------------
    // Delete boats
    // ------------------------------------------------------------
    if (isset($args['delete-boats'])) {
      $list = DB::$V->reqList($args, 'boat', null, "No boats to delete.");

      $boats = array();
      foreach ($list as $id) {
        if (($boat = DB::getBoat($id)) === null)
          throw new SoterException("Invalid boat to delete: $id.");
        $boats[] = $boat;
      }

      foreach ($boats as $boat)
        DB::remove($boat);
      Session::pa(new PA(sprintf("Deleted %d boat(s).", count($boats))));
    }
  }
}
?>