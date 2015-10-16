<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('conf.php');

/**
 * Page for editing races in a regatta and their boats.
 *
 * @version 2010-07-31: Starting with this version, only the number of
 * races and division is chosen, with appropriate warning messages if
 * there are already rotations or finishes in place.
 *
 * @TODO For the boats in the race, provide a mechanism similar to rotation
 * creation, where the user can choose a range of races in each
 * division to assign a boat at a time. Provide an extra box for every
 * new division.
 */
class RacesPane extends AbstractPane {

  private static $NEW_SCORES = array('DNS' => 'DNS', 'BYE' => 'BYE');

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Edit Races", $user, $reg);
  }

  /**
   * Fills out the pane for empty regattas. For these, provide a drop
   * down of possible boats at the time of creating the races.
   *
   * @param Array $args (ignored)
   */
  private function fillNewRegatta(Array $args) {
    $boats     = DB::getBoats();
    $boatOptions = array("" => "");
    foreach ($boats as $boat) {
      $boatOptions[$boat->id] = $boat->name;
    }

    $this->PAGE->addContent($p = new XPort("Races and divisions"));
    $p->add($form = $this->createForm());
    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      $divs = array(1=>1, 2=>2, 3=>3, 4=>4);
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
        $divs = array(2=>2, 3=>3, 4=>4);
      $form->add(new FReqItem("Number of divisions:", XSelect::fromArray('num_divisions',
                                                                         $divs,
                                                                         count($this->REGATTA->getDivisions()))));
    }
    $form->add(new FReqItem("Number of races:", new XNumberInput('num_races', count($this->REGATTA->getTeams()), 1, null, 1)));
    $form->add(new FReqItem("Boat:", XSelect::fromArray('boat', $boatOptions), "Boats can be re-assigned per division or race afterwards."));
    $form->add(new XSubmitP("set-races", "Add races"));
  }

  protected function fillHTML(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    if (count($divisions) == 0) {
      $this->fillNewRegatta($args);
      return;
    }

    $boats = DB::getBoats();
    $boatOptions = array();
    $boatFullOptions = array('' => "[Use table]");
    foreach ($boats as $boat) {
      $boatOptions[$boat->id] = $boat->name;
      $boatFullOptions[$boat->id] = $boat->name;
    }

    //------------------------------------------------------------
    // Number of races and divisions
    // ------------------------------------------------------------
    $final = $this->REGATTA->finalized;
    $this->PAGE->addContent($p = new XPort("Races and divisions"));
    $p->add($form = $this->createForm());
    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      $divs = array(1=>1, 2=>2, 3=>3, 4=>4);
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED)
	$divs = array(2=>2, 3=>3, 4=>4);
      $form->add(new FReqItem("Number of divisions:",
                              $f_div = XSelect::fromArray(
                                'num_divisions',
                                $divs,
                                count($this->REGATTA->getDivisions()))));
    }
    $form->add(new FReqItem("Number of races:",
                            $f_rac = new XNumberInput('num_races',
                                                      count($this->REGATTA->getRaces(Division::A())),
                                                      1, null, 1)));

    $attrs = ($final) ? array('disabled'=>'disabled') : array();
    $form->add($xp = new XSubmitP("set-races", "Save changes", $attrs));
    $xp->add(new XMessage("Unsailed races are automatically removed when the regatta is finalized."));

    if ($final) {
      $f_div->set("disabled", "disabled");
      $f_rac->set("disabled", "disabled");
    }
    else {
      if ($this->REGATTA->getRotationManager()->isAssigned()) {
        $form->add(new XHeading("Existing rotations"));
        $form->add(new XP(array(), "Adding races or divisions to this regatta will require that you also edit rotations. Removing races or divisions will also remove the rotation for the removed races!"));
      }
      if ($this->REGATTA->hasFinishes()) {
        $form->add(new XHeading("Existing finishes"));
        $form->add($xp = new XP(array(),
                                array("Removing races will also remove finishes entered in those races. ",
                                      new XStrong("This is not recoverable."))));
        if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
          $xp->add(" If adding divisions, you will need to specify how to score the teams in the previously scored races by selecting the appropriate choice below.");
          $form->add(new FItem("New score:", XSelect::fromArray('new-score', self::$NEW_SCORES), "Only needed when adding divisions"));
        }
      }
    }

    //------------------------------------------------------------
    // Edit existing boats

    $this->PAGE->addContent($p = new XPort("Boat assignments"));
    $p->add(new XP(array(), "Edit the boat associated with each race. This is necessary at the time of entering RP information. Races that are not sailed are automatically removed when finalizing the regatta."));
    $p->add($form = $this->createForm());

    // Add input elements
    $form->add(new XSubmitP("editboats", "Edit boats"));

    // Table of races: columns are divisions; rows are race numbers
    $head = array("#");
    $races = array();
    $max   = 0; // the maximum number of races in any given division
    foreach ($divisions as $div) {
      $head[] = "Division $div";
      $races[(string)$div] = $this->REGATTA->getRaces($div);
      $max = max($max, count($races[(string)$div]));
    }
    $form->add($tab = new XQuickTable(array('class'=>'narrow'), $head));

    //  - Global boat
    $row = array(new XTH(array(), "All"));
    foreach ($divisions as $div) {
      $c = new XTD();
      $c->add(XSelect::fromArray($div, $boatFullOptions));
      $row[] = $c;
    }
    $tab->addRow($row);

    //  - Table content
    for ($i = 0; $i < $max; $i++) {
      // Add row
      $row = array(new XTH(array(), $i + 1));

      // For each division
      foreach ($divisions as $div) {
        $c = "";
        $race = $races[(string)$div][$i];
        if ($race !== false) {
          $c = XSelect::fromArray($race, $boatOptions, $race->boat->id);
        }
        $row[] = $c;
      }
      $tab->addRow($row);
    }
  }

  /**
   * Process edits to races for the regatta
   */
  public function process(Array $args) {

    $was_singlehanded = $this->REGATTA->isSingleHanded();

    // ------------------------------------------------------------
    // Set races
    // ------------------------------------------------------------
    if (isset($args['set-races'])) {
      if ($this->REGATTA->finalized !== null)
        throw new SoterException("You may not edit races after regatta has been finalized.");
      // ------------------------------------------------------------
      // Add new divisions
      //   1. Get host's preferred boat
      $added_races = false;
      $hosts = $this->REGATTA->getHosts();
      $host = $hosts[0];
      $boat = DB::$V->incID($args, 'boat', DB::T(DB::BOAT), DB::getPreferredBoat($host));

      $cur_divisions = $this->REGATTA->getDivisions();
      $min_divisions = ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) ? 2 : 1;
      $new_regatta = (count($cur_divisions) == 0);
      $pos_divisions = Division::getAssoc();
      $num_races = DB::$V->reqInt($args, 'num_races', 1, 100, "Invalid number of races.");
      $num_divisions = ($this->REGATTA->scoring == Regatta::SCORING_TEAM) ? 1 :
        DB::$V->reqInt($args, 'num_divisions', $min_divisions, count($pos_divisions) + 1, "Invalid number of divisions.");
      $pos_divisions_list = array_values($pos_divisions);

      // If combined division, and finishes have been entered, then
      // REQUIRE a new score (defaults to DNS)
      $teams = array();
      $new_score = false;
      $scored_numbers = array();
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED && $this->REGATTA->hasFinishes()) {
        $new_score = DB::$V->incKey($args, 'new-score', self::$NEW_SCORES, 'DNS');
        $teams = $this->REGATTA->getTeams();
        foreach ($this->REGATTA->getScoredRaces(Division::A()) as $race)
          $scored_numbers[$race->number] = $race->number;
      }
      $new_races = array();

      // Track new divisions should rotations need to be reset
      $new_divs = array();
      for ($i = count($cur_divisions); $i < $num_divisions; $i++) {
        $div = $pos_divisions_list[$i];
        $new_divs[] = $div;
        for ($j = 0; $j < $num_races; $j++) {
          $race = new Race();
          $race->division = $div;
          $race->boat = $boat;
          $race->number = ($j + 1);
          $this->REGATTA->setRace($race);
          if (isset($scored_numbers[$race->number]))
            $new_races[] = $race;
        }
        $added_races = true;
      }
      if ($new_score !== false) {
        $finishes = array();
        foreach ($new_races as $race) {
          foreach ($teams as $team) {
            $finish = $this->REGATTA->createFinish($race, $team);
            $finish->entered = DB::T(DB::NOW);
            $finish->setModifier(new Penalty($new_score));
            $finishes[] = $finish;
          }
        }
        $this->REGATTA->commitFinishes($finishes);
        $this->REGATTA->doScore();
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
        Session::pa(new PA("Assigned $new_score finish to teams in new division(s)."));
      }

      // Remove rotation?
      if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED && count($new_divs) > 0) {
        $rot = $this->REGATTA->getRotationManager();
        if ($rot->isAssigned()) {
          $rot->reset();
          Session::pa(new PA("Rotations reset due to new division(s).", PA::I));
        }
      }

      // ------------------------------------------------------------
      // Subtract extra divisions
      $removed_races = false;
      for ($i = count($cur_divisions); $i > $num_divisions; $i--) {
        $this->REGATTA->removeDivision($pos_divisions_list[$i - 1]);
        $removed_races = true;
      }
      $cur_divisions = $this->REGATTA->getDivisions();

      // Add
      $cur_races = count($this->REGATTA->getRaces(Division::A()));
      for ($i = $cur_races; $i < $num_races; $i++) {
        foreach ($cur_divisions as $div) {
          $race = new Race();
          $race->division = $div;
          $race->boat = $boat;
          $race->number = ($i + 1);
          $this->REGATTA->setRace($race);
          $added_races = true;
        }
      }

      // Remove
      $toRemove = array();
      for ($i = $cur_races; $i > $num_races; $i--) {
        foreach ($cur_divisions as $div) {
          $race = new Race();
          $race->division = $div;
          $race->number = $i;
          $toRemove[] = $race;
          $removed_races = true;
        }
      }
      $this->REGATTA->removeRaces($toRemove);
      if ($removed_races && $this->REGATTA->hasFinishes()) {
        $this->REGATTA->doScore();
        Session::pa(new PA("Re-scored regatta."));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }

      if (!$removed_races && !$added_races)
        throw new SoterException("Nothing to change.");

      $rot = $this->REGATTA->getRotationManager();
      if ($rot->isAssigned()) {
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
        Session::pa(new PA("Rotations altered due to new races.", PA::I));
      }

      $this->REGATTA->setData(); // num_races changed
      if ($new_regatta) {
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
        Session::pa(new PA(array("Regatta races successfull set. You may create a rotation, or ",
                                 new XA($this->link('finishes'), "skip this step"),
                                 " to enter finishes directly.")));
        $this->redirect('rotations');
      }
      if ($was_singlehanded != $this->REGATTA->isSingleHanded()) {
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
        Session::pa(new PA("The regatta's singlehanded status has changed as a result of this update.", PA::I));
      }
      Session::pa(new PA("Set number of races."));
    }

    // ------------------------------------------------------------
    // Update boats

    $remaining_divisions = $this->REGATTA->getDivisions();
    $copy = $remaining_divisions;
    if (isset($args['editboats'])) {
      unset($args['editboats']);

      $changed = false;

      // Is there an assignment for all the races in the division?
      foreach ($copy as $i => $div) {
        if (($val = DB::$V->incID($args, (string)$div, DB::T(DB::BOAT), null)) !== null) {
          unset($args[(string)$div]);
          unset($remaining_divisions[$i]);
          foreach ($this->REGATTA->getRaces($div) as $race) {
            if ($race->boat != $val) {
              $race->boat = $val;
              $this->REGATTA->setRace($race);
              $changed = true;
            }
          }
        }
      }

      // Let the database decide whether the values are valid to begin
      // with. Just keep track of whether there were errors.
      foreach ($remaining_divisions as $div) {
        foreach ($this->REGATTA->getRaces($div) as $race) {
          if (($val = DB::$V->incID($args, (string)$race, DB::T(DB::BOAT), null)) !== null &&
              $val != $race->boat) {
            $race->boat = $val;
            DB::set($race);
            $changed = true;
          }
        }
      }

      if (!$changed)
        throw new SoterException("Nothing to change.");

      $this->REGATTA->setData(); // boats changed, possibly singlehanded
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
      Session::pa(new PA("Updated boat assignments."));
    }
    return array();
  }
}
?>