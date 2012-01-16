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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Race notes", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $divisions = $this->REGATTA->getDivisions();
    
    // OUTPUT
    $this->PAGE->addContent($p = new XPort("Enter observation"));

    // Form
    $p->add($form = $this->createForm());
    $form->add($fitem = new FItem("Race:", 
				  new XTextInput("chosen_race", "",
						 array("size"=>"4",
						       "maxlength"=>"4",
						       "id"=>"chosen_race",
						       "class"=>"narrow"))));

    // Table of possible races
    $fitem->add($tab = new XQuickTable(array('class'=>'narrow'), $divisions));
    $cells = array();
    foreach ($divisions as $div)
      $cells[] = count($this->REGATTA->getRaces($div));
    $tab->addRow($cells);

    // Observation
    $form->add(new FItem("Observation:",
			 new XTextArea("observation","",
				       array("rows"=>3,
					     "cols"=>30))));
    // Observer
    $form->add(new FItem("Observer:",
			 new XTextInput("observer",
					$this->USER->getName(),
					array("maxlength"=>"50"))));

    $form->add(new XSubmitInput("observe",
				"Add note"));
    // CURRENT NOTES
    $notes = $this->REGATTA->getNotes();
    if (count($notes) > 0) {
      $this->PAGE->addContent($p = new XPort("Current notes"));

      // Table
      $p->add($tab = new XQuickTable(array(), array("Race", "Note", "Observer", "")));
      foreach ($notes as $note) {
	$tab->addRow(array($note->race, $note->observation, $note->observer, $form = $this->createForm()));
	$form->add(new XP(array(), array(new XHiddenInput("observation", $note->id),
					 new XSubmitInput("remove", "Remove", array("class"=>"thin")))));
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
	Session::pa(new PA($mes, PA::E));
	return false;
      }

      // require an observation
      $mes = null;
      if (isset($args['observation']) &&
	  !empty($args['observation'])) {
	$mes = addslashes($args['observation']);
      }
      else {
	Session::pa(new PA("No observation found.", PA::E));
	return false;
      }

      // require an observer
      $observer = null;
      if (isset($args['observer']) &&
	  !empty($args['observer'])) {
	$observer = addslashes($args['observer']);
      }
      else {
	Session::pa(new PA("No observer included.", PA::E));
	return false;
      }

      $note = new Note();
      $note->observation = $mes;
      $note->observer    = $observer;
      $note->race        = $race->id;
      $note->noted_at = new DateTime();
      $this->REGATTA->addNote($note);

      Session::pa(new PA(sprintf("Observation from %s recorded.", $note->observer)));
    }

    
    // ------------------------------------------------------------
    // Remove existing
    // ------------------------------------------------------------
    if (isset($args['remove'])) {
      if (isset($args['observation']) &&
	  ($note = DB::get(DB::$NOTE, $args['observation'])) !== null &&
	  $note->regatta == $this->REGATTA->id()) {
	$this->REGATTA->deleteNote($note);
	Session::pa(new PA(sprintf("Deleted observation from %s.", $note->observer)));
      }
      else {
	$mes = sprintf("Invalid or missing observation (%s) to delete.", $args['observation']);
	Session::pa(new PA($mes, PA::E));
	$this->redirect();
      }
    }

    return $args;
  }
}
?>