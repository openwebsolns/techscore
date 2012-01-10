<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Displays and drops existing finishes
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class DropFinishPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("All finishes", $user, $reg);
  }

  /**
   * Fills for combined divisions
   *
   * @param Array $args the arguments
   */
  private function fillCombined(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $rotation = $this->REGATTA->getRotation();
    
    $this->PAGE->addContent($p = new XPort("All divisions"));
    $races = $this->REGATTA->getCombinedScoredRaces();

    foreach ($races as $num) {
      $p->add(new XTable(array('class'=>'narrow'),
			 array(new XTHead(array(),
					  array(new XTR(array(), array(new XTH(array('colspan' => 2), "Race $num"))))),
			       $tab = new XTBody())));
      
      // get finishes in timestamp order
      $place = 1;
      $finishes = array();
      foreach ($divisions as $div) {
	$race = $this->REGATTA->getRace($div, $num);
	$finishes = array_merge($finishes, $this->REGATTA->getFinishes($race));
      }
      usort($finishes, "Finish::compareEntered");
      foreach ($finishes as $finish) {
	$sail = $rotation->getSail($race, $finish->team);
	$tab->add(new XTR(array(), array(new XTH(array(), $place++), new XTH(array(), $sail))));
      }
      // add form
      $tab->add(new XTR(array(), array(new XTD(array('colspan'=>2), $form = $this->createForm()))));
      $form->add(new XP(array(),
			array(new XHiddenInput("race", $race->id),
			      new XSubmitInput("removerace", "Remove", array('class'=>'thin')))));
    }
  }

  protected function fillHTML(Array $args) {
    // Delegate in case of combined scoring
    if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED) {
      $this->fillCombined($args);
      return;
    }

    $rotation = $this->REGATTA->getRotation();
    $url = sprintf("edit/%s/drop-finish", $this->REGATTA->id());

    // ------------------------------------------------------------
    // Print finishes for each division
    // ------------------------------------------------------------
    foreach ($this->REGATTA->getDivisions() as $division) {
      $this->PAGE->addContent($p = new XPort("Division " . $division));

      $races = $this->REGATTA->getScoredRaces($division);
      $div = new XDiv();
      if (count($races) == 0)
	$p->add(new XP(array(), "No race finishes for $division division."));
      else
	$p->add($div);

      // create table for each race
      foreach ($races as $race) {
	$div->add(new XTable(array('class'=>'narrow'),
			     array(new XTHead(array(),
					      array(new XTR(array(), array(new XTH(array('colspan'=>2), "Race $race"))))),
				   $tab = new XTBody())));

	// get finishes in order
	$place = 1;
	$finishes = $this->REGATTA->getFinishes($race);
	foreach ($finishes as $finish) {
	  $sail = $rotation->getSail($race, $finish->team);
	  $tab->add(new XTR(array(), array(new XTH(array(), $place++), new XTD(array(), $sail))));
	}
	// add form
	$tab->add(new XTR(array(), array(new XTD(array('colspan'=>2), $form = $this->createForm()))));
	$form->add(new XP(array(),
			  array(new XHiddenInput("race", $race->id),
				new XSubmitInput("removerace", "Remove"), array('class'=>'thin'))));
      }
    }
  }

  /**
   * Processes deletion requests. Note that due to the awesomeness of
   * the Regatta class, it is not necessary to create a different
   * process request for combined divisions because removing finishes
   * from one division in a combined race results in all finishes for
   * that race number in all divisions to be deleted also. Does that
   * make sense?
   *
   */
  public function process(Array $args) {
    // ------------------------------------------------------------
    // Remove a set of race finishes
    // ------------------------------------------------------------
    if (isset($args['removerace'])) {
      $races = $this->REGATTA->getScoredRaces();
      if (!isset($args['race']) ||
	  ($race = Preferences::getObjectWithProperty($races,
						      "id",
						      $args['race'])) == null) {
	$mes = sprintf("Invalid or missing race (%s).", $args['race']);
	Session::pa(new PA($mes, PA::E));
	return $args;
      }
      $this->REGATTA->dropFinishes($race);
      $mes = sprintf("Removed finishes for race %s.", $race);
      if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED)
	$mes = sprintf("Removed finishes for race %s.", $race->number);
      Session::pa(new PA($mes));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }
    return $args;
  }
}
