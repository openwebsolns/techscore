<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Displays and drops existing finishes
 *
 * @author Dayan Paez
 * @created 2010-01-25
 */
class DropFinishPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Current finishes", $user, $reg);
    $this->title = "All finishes";
  }

  /**
   * Fills for combined divisions
   *
   * @param Array $args the arguments
   */
  private function fillCombined(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $rotation = $this->REGATTA->getRotation();
    
    $this->PAGE->addContent($p = new Port("All divisions"));
    $races = $this->REGATTA->getCombinedScoredRaces();

    foreach ($races as $num) {
      $p->addChild($tab = new Table());
      $tab->addAttr("class", "narrow");
      $tab->addHeader(new Row(array(new Cell("Race " . $num,
					     array("colspan" => "2"), 1))));

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
	$tab->addRow(new Row(array(Cell::th($place++),
				   Cell::td($sail))));
      }
      // add form
      $tab->addRow(new Row(array(new Cell($form = $this->createForm(),
					  array("colspan"=>"2")))));
      $form->addChild(new FHidden("race", $race->id));
      $form->addChild($submit = new FSubmit("removerace", "Remove"));
      $submit->addAttr("class", "thin");
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
      $this->PAGE->addContent($p = new Port("Division " . $division));

      $races = $this->REGATTA->getScoredRaces($division);
      $div = new Div();
      if (count($races) == 0)
	$p->addChild(new Para("No race finishes for $division division."));
      else
	$p->addChild($div);

      // create table for each race
      foreach ($races as $race) {
	$div->addChild($tab = new Table());
	$tab->addAttr("class", "narrow");
	$tab->addHeader(new Row(array(new Cell("Race " . $race,
					       array("colspan" => "2"), 1))));

	// get finishes in order
	$place = 1;
	$finishes = $this->REGATTA->getFinishes($race);
	foreach ($finishes as $finish) {
	  $sail = $rotation->getSail($race, $finish->team);
	  $tab->addRow(new Row(array(Cell::th($place++),
				     Cell::td($sail))));
	}
	// add form
	$tab->addRow(new Row(array(new Cell($form = $this->createForm(),
					    array("colspan"=>"2")))));
	$form->addChild(new FHidden("race", $race->id));
	$form->addChild($submit = new FSubmit("removerace", "Remove"));
	$submit->addAttr("class", "thin");
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
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }
      $this->REGATTA->dropFinishes($race);
      $mes = sprintf("Removed finishes for race %s.", $race);
      if ($this->REGATTA->get(Regatta::SCORING) == Regatta::SCORING_COMBINED)
	$mes = sprintf("Removed finishes for race %s.", $race->number);
      $this->announce(new Announcement($mes));
    }
    return $args;
  }

  public function isActive() {
    return $this->REGATTA->hasFinishes();
  }
}
