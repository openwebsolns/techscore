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
      $new_score->add(new XLabel('def_box', "Use standard scoring (+6)"));
      $form->add($new_score);

      $new_score = new FItem("OR set additional amount:", new XTextInput('p_amount', "", array('size'=>2, 'id'=>'p_amount')));
      $form->add($new_score);

      $form->add(new XScript('text/javascript', null,
			     'document.getElementById("p_amount").disabled = true;' .
			     'document.getElementById("def_box").checked = true;'));
    }
    else // @TODO
      return parent::fillPenaltyScheme($form, $type);
  }
}
?>