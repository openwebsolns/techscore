<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('tscore/EnterPenaltyPane.php');

/**
 * Add, edit, and display penalties for team-racing regattas
 *
 * @author Dayan Paez
 * @version 2013-01-04
 */
class EnterTeamPenaltyPane extends EnterPenaltyPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct($user, $reg);
    unset($this->breakdowns[Breakdown::BYE]);
  }

  /**
   * Overrides parent method to show concise table listing instead
   *
   */
  protected function getRaceTable() {
    $num = array();
    foreach ($this->REGATTA->getScoredRaces(Division::A()) as $race)
      $num[] = $race->number;
    return new XTable(array('class'=>'narrow'),
		      array(new XTHead(array(), array(new XTR(array(), array(new XTH(array(), "Race #"))))),
			    new XTBody(array(), array(new XTR(array(), array(new XTD(array(), DB::makeRange($num))))))));
  }

  protected function fillPenaltyScheme(XForm $form, $type) {
    $b = Penalty::getList();
    if (isset($b[$type])) {
      $new_score = new FItem("New score:", $cb = new XCheckboxInput('average', 'yes', array('id'=>'def_box')));
      $cb->set('onclick', 'document.getElementById("p_amount").disabled = this.checked;');
      $default = "+6";
      if ($type == Penalty::OCS)
        $default = "+10";
      elseif ($type == Penalty::DNS || $type == Penalty::DNF)
        $default = "6";
      $new_score->add(new XLabel('def_box', sprintf("Use standard scoring (%s)", $default)));
      $form->add($new_score);

      $new_score = new FItem("OR set amount:", new XTextInput('p_amount', "", array('size'=>2, 'id'=>'p_amount')));
      $form->add($new_score);

      $form->add(new XScript('text/javascript', null,
			     'document.getElementById("p_amount").disabled = true;' .
			     'document.getElementById("def_box").checked = true;'));
    }
    else {
      // Assign score only
      $form->add(new FItem("New place:", new XTextInput('p_amount', "", array('size'=>2, 'id'=>'p_amount'))));
    }
  }

  protected function canHaveModifier(Finish $fin, $type) {
    $mods = $fin->getModifiers();
    if (count($mods) == 0)
      return true;
    if ($type != Penalty::DSQ)
      return false;
    return true;
  }

  protected function fillAlternateRaceSelection(XForm $form) {
    $form->add(new FItem("OR choose:", new XSpan("(Use grids below)")));
    foreach ($this->REGATTA->getRounds() as $round) {
      $form->add(new XH4($round));
      $teams = array();
      $matches = array();
      foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
        if (!isset($teams[$race->tr_team1->id])) {
          $teams[$race->tr_team1->id] = $race->tr_team1;
          $matches[$race->tr_team1->id] = array();
        }
        if (!isset($teams[$race->tr_team2->id])) {
          $teams[$race->tr_team2->id] = $race->tr_team2;
          $matches[$race->tr_team2->id] = array();
        }
        if (!isset($matches[$race->tr_team1->id][$race->tr_team2->id]))
          $matches[$race->tr_team1->id][$race->tr_team2->id] = array();
        if (!isset($matches[$race->tr_team2->id][$race->tr_team1->id]))
          $matches[$race->tr_team2->id][$race->tr_team1->id] = array();

        $matches[$race->tr_team1->id][$race->tr_team2->id][] = $race;
        $matches[$race->tr_team2->id][$race->tr_team1->id][] = $race;
      }
      $form->add(new XTable(array('class'=>'teamscores'), array($tab = new XTBody(array(), array($header = new XTR(array(), array(new XTH(array(), "↓ vs. →"))))))));
      foreach ($teams as $myId => $team) {
        $header->add(new XTH(array(), $team));
        $tab->add($row = new XTR(array(), array(new XTH(array(), $team))));
        foreach ($teams as $theirId => $opponent) {
          if (!isset($matches[$myId][$theirId])) {
            $row->add(new XTD(array('class'=>'tr-ns')));
            continue;
          }
          $races = $matches[$myId][$theirId];
          if (count($races) == 1)
            $row->add(new XTD(array(), new XRadioInput('race_id', $races[0]->id)));
          else {
            $row->add(new XTable(array(), array($sub = new XTBody())));
            foreach ($races as $race) {
              $sub->add(new XTR(array(), array(new XTD(array(), new XRadioInput('race_id', $race->id)))));
            }
          }
        }
      }
    }
  }
}
?>