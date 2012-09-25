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
    $this->PAGE->addContent($p = new XPort("All divisions"));
    $divisions = $this->REGATTA->getDivisions();
    $rotation = $this->REGATTA->getRotation();

    $header = array("Race");
    for ($i = 1; $i <= count($this->REGATTA->getTeams()) * count($divisions); $i++)
      $header[] = $i;
    $header[] = ""; // Drop finish
    $p->add($tab = new XQuickTable(array('class'=>'finishes'), $header));

    $races = $this->REGATTA->getScoredRaces(Division::A());
    foreach ($races as $i => $race) {
      $row = array(new XTH(array(), $race . ":"));
      // get finishes in order
      $finishes = $this->REGATTA->getCombinedFinishes($race);
      foreach ($finishes as $finish) {
        $sail = $rotation->getSail($race, $finish->team);
        $row[] = $sail;
      }
      $form = $this->createForm();
      $form->add(new XP(array('class'=>'thin'),
                        array(new XHiddenInput('race', $race->id),
                              new XSubmitInput('removerace', "Remove", array('class'=>'small')))));
      $row[] = $form;
      $tab->addRow($row, array('class'=>'row'.($i % 2)));
    }
  }

  protected function fillHTML(Array $args) {
    // Delegate in case of combined scoring
    if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
      $this->fillCombined($args);
      return;
    }

    $rotation = $this->REGATTA->getRotation();
    $url = sprintf("edit/%s/drop-finish", $this->REGATTA->id);

    // ------------------------------------------------------------
    // Print finishes for each division
    // ------------------------------------------------------------
    foreach ($this->REGATTA->getDivisions() as $division) {
      $this->PAGE->addContent($p = new XPort("Division " . $division));

      $races = $this->REGATTA->getScoredRaces($division);
      if (count($races) == 0) {
        $p->add(new XP(array(), "No race finishes for $division division."));
        continue;
      }

      $header = array("Race");
      for ($i = 1; $i <= count($this->REGATTA->getTeams()); $i++)
        $header[] = $i;
      $header[] = ""; // Drop finish
      $p->add($tab = new XQuickTable(array('class'=>'finishes'), $header));

      // row for each race
      foreach ($races as $i => $race) {
        $row = array($race);

        // get finishes in order
        $finishes = $this->REGATTA->getFinishes($race);
        foreach ($finishes as $finish) {
          $sail = $rotation->getSail($race, $finish->team);
          $row[] = $sail;
        }
        $form = $this->createForm();
        $form->add(new XP(array('class'=>'thin'),
                          array(new XHiddenInput('race', $race->id),
                                new XSubmitInput('removerace', "Remove", array('class'=>'small')))));
        $row[] = $form;
        $tab->addRow($row, array('class'=>'row'.($i % 2)));
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
      $race = DB::$V->reqID($args, 'race', DB::$RACE, "Invalid or missing race to drop.");
      if ($race->regatta != $this->REGATTA)
        throw new SoterException("Provided race does not belong to this regatta.");

      $this->REGATTA->dropFinishes($race);
      $mes = sprintf("Removed finishes for race %s.", $race);
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
        $mes = sprintf("Removed finishes for race %s.", $race->number);
      Session::pa(new PA($mes));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
    }
    return $args;
  }
}
