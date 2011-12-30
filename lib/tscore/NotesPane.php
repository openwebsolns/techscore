<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Pane to enter regatta observations
 *
 * @author Dayan Paez
 * @version 2010-02-22
 */
class NotesPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Race notes", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $divisions = $this->REGATTA->getDivisions();
    
    // OUTPUT
    $this->PAGE->addContent($p = new Portlet("Enter observation"));

    // Form
    $p->add($form = $this->createForm());
    $form->add($fitem = new FItem("Race:", 
				       new FText("chosen_race", "",
						 array("size"=>"4",
						       "maxlength"=>"4",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));

    // Table of possible races
    $fitem->add($tab = new Table());
    $tab->set("class", "narrow");
    $tab->addHeader($hrow = new Row(array(), array("id"=>"pos_divs")));
    $tab->addRow($brow = new Row(array(), array("id"=>"pos_races")));
    foreach ($divisions as $div) {
      $hrow->addCell(Cell::th($div));
      $brow->addCell(new Cell(count($this->REGATTA->getRaces($div))));
    }

    // Observation
    $form->add(new FItem("Observation:",
			      new FTextArea("observation","",
					    array("rows"=>3,
						  "cols"=>30))));
    // Observer
    $form->add(new FItem("Observer:",
			      new FText("observer",
					$this->USER->getName(),
					array("maxlength"=>"50"))));

    $form->add(new FSubmit("observe",
				"Add note"));

    // CURRENT NOTES
    $notes = $this->REGATTA->getNotes();
    if (count($notes) > 0) {
      $this->PAGE->addContent($p = new Portlet("Current notes"));

      // Table
      $p->add($tab = new Table());
      $tab->set("class", "left");
      $tab->addHeader(new Row(array(Cell::th("Race"),
				    Cell::th("Note"),
				    Cell::th("Observer"),
				    Cell::th())));

      foreach ($notes as $note) {
	$tab->addRow(new Row(array(new Cell($note->race),
				   new Cell($note->observation,
					    array("style"=>"max-width: 25em")),
				   new Cell($note->observer),
				   new Cell($form = $this->createForm()))));

	$form->add(new FHidden("observation", $note->id));
	$form->add(new FSubmit("remove", "Remove",
				    array("class"=>"thin")));
      }
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add observation
    // ------------------------------------------------------------
    if (isset($args['observe'])) {

      // get race
      $race = null;
      try {
	$race = Race::parse($args['chosen_race']);
	$race = $this->REGATTA->getRace($race->division, $race->number);
      } catch (Exception $e) {
	$mes = sprintf("Invalid or missing race chosen (%s).", $args['chosen_race']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return false;
      }

      // require an observation
      $mes = null;
      if (isset($args['observation']) &&
	  !empty($args['observation'])) {
	$mes = addslashes($args['observation']);
      }
      else {
	$this->announce(new Announcement("No observation found.", Announcement::ERROR));
	return false;
      }

      // require an observer
      $observer = null;
      if (isset($args['observer']) &&
	  !empty($args['observer'])) {
	$observer = addslashes($args['observer']);
      }
      else {
	$this->announce(new Announcement("No observer included.", Announcement::ERROR));
	return false;
      }

      $note = new Note();
      $note->observation = $mes;
      $note->observer    = $observer;
      $note->race        = $race;
      $this->REGATTA->addNote($note);

      $this->announce(new Announcement(sprintf("Observation from %s recorded.", $note->observer)));
    }

    
    // ------------------------------------------------------------
    // Remove existing
    // ------------------------------------------------------------
    if (isset($args['remove'])) {
      if (isset($args['observation']) &&
	  ($note = Preferences::getObjectWithProperty($this->REGATTA->getNotes(),
						      "id",
						      $args['observation'])) != null) {
	$this->REGATTA->deleteNote($note);
	$this->announce(new Announcement(sprintf("Deleted observation from %s.", $note->observer)));
      }
      else {
	$mes = sprintf("Invalid or missing observation (%s).", $args['observation']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	$this->redirect();
      }
    }

    return $args;
  }
}
?>