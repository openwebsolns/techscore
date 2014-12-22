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
    $form->add($fitem = new FReqItem("Race:", $this->newRaceInput('race')));

    // Table of possible races
    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) {
      $fitem->add($tab = new XQuickTable(array('class'=>'narrow'), $divisions));
      $cells = array();
      foreach ($divisions as $div)
        $cells[] = count($this->REGATTA->getRaces($div));
      $tab->addRow($cells);
    }

    // Observation
    $form->add(new FReqItem("Observation:",
                            new XTextArea('observation', "", array('rows'=>3, 'cols'=>30))));
    // Observer
    $form->add(new FReqItem("Observer:",
                            new XTextInput('observer', $this->USER->getName(), array('maxlength'=>'50'))));

    $form->add(new XSubmitInput('observe', "Add note"));

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
      $race = DB::$V->reqRace($args, 'race', $this->REGATTA, "Invalid or missing race chosen.");
      $mes = DB::$V->reqString($args, 'observation', 1, 1001, "No observation found.");
      $obs = DB::$V->reqString($args, 'observer', 1, 51, "No observer or name too long (must be less than 50 characters).");

      $note = new Note();
      $note->observation = $mes;
      $note->observer = $obs;
      $note->race = $race;
      $note->noted_at = new DateTime();
      $this->REGATTA->addNote($note);

      Session::pa(new PA(sprintf("Observation from %s recorded.", $note->observer)));
    }


    // ------------------------------------------------------------
    // Remove existing
    // ------------------------------------------------------------
    if (isset($args['remove'])) {
      $note = DB::$V->reqID($args, 'observation', DB::T(DB::NOTE), "Invalid or missing observation to delete.");
      if ($note->race->regatta != $this->REGATTA)
        throw new SoterException("Chosen note does not belong to the given regatta.");
      $this->REGATTA->deleteNote($note);
      Session::pa(new PA(sprintf("Deleted observation from %s for race %s.", $note->observer, $note->race)));
    }

    return array();
  }
}
?>