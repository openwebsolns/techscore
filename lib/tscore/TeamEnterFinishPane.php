<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('tscore/EnterFinishPane.php');

/**
 * Enter finishes for team racing regattas
 *
 * 2013-07-15: Divide it into a two-step process: the first is where
 * the race is chosen (do not collapse the port). Unlike in fleet
 * racing, recommend the next race automatically.
 *
 * @author Dayan Paez
 * @created 2012-12-11 
 */
class TeamEnterFinishPane extends EnterFinishPane {

  /**
   * @var Map penalty options available when entering finishes
   */
  protected $pen_opts = array("" => "", Penalty::DNF => "DNF (6)", Penalty::DNS => "DNS (6)");

  protected function fillHTML(Array $args) {
    // Chosen race, by number
    $race = null;
    $num = DB::$V->incString($args, 'race', 1, 1001, null);
    if ($num !== null) {
      $race = $this->REGATTA->getRace(Division::A(), $num);
      if ($race === null)
        Session::pa(new PA("Invalid race chosen.", PA::I));
    }
    // ------------------------------------------------------------
    // Choose race: provide either numerical input, or direct selection
    // ------------------------------------------------------------
    if ($race === null) {
      $this->PAGE->addContent($p = new XPort("Choose race"));
      $p->add($form = $this->createForm(XForm::GET));
      $form->set("id", "race_form");

      $form->add(new FItem("Race number:", 
                           $race_input = new XTextInput('race', "",
                                                        array("size"=>"4",
                                                              "maxlength"=>"3",
                                                              "id"=>"chosen_race",
                                                              "class"=>"narrow"))));
      // Add next unscored, or last scored race
      $races = $this->REGATTA->getUnscoredRaces();
      if (count($races) > 0)
        $race_input->set('value', $races[0]);
      else
        $race_input->set('value', $this->REGATTA->getLastScoredRace());
      
      // No rotation yet
      $form->add(new XSubmitP('go', "Enter finishes →"));

      // ------------------------------------------------------------
      // Choose race: provide grid
      // ------------------------------------------------------------
      require_once('tscore/ScoresGridDialog.php');
      $D = new ScoresGridDialog($this->REGATTA);
      foreach ($this->REGATTA->getRounds() as $round)
        $p->add($D->getRoundTable($round, true));
      return;
    }

    // ------------------------------------------------------------
    // Enter finishes
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/finish.js'));
    $this->fillFinishesPort($race);
  }
}
?>