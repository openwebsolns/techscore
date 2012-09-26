<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Pane to manually specify rotations
 *
 * @author Dayan Paez
 * @version 2010-01-20
 */
class ManualTweakPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Manual setup", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $rotation  = $this->REGATTA->getRotation();
    $exist_div = $this->REGATTA->getDivisions();

    // ------------------------------------------------------------    
    // Chosen division: only applies if not "singlehanded"
    // ------------------------------------------------------------
    if (count($exist_div) > 1 && $this->REGATTA->scoring != Regatta::SCORING_COMBINED) {
      $chosen_div = DB::$V->incDivision($args, 'division', $exist_div, $exist_div[0]);
      $races = $rotation->getRaces($chosen_div);
      $port_title = "Manual rotation for division " . $chosen_div;

      // Provide links to change division
      $d = new FItem("Choose division:", "");
      foreach ($exist_div as $div) {
        $mes = new XStrong($div);
        if ($div != $chosen_div)
          $mes = new XA($this->link('manual-rotation', array('division'=>(string)$div)), $mes);
        $d->add(" ");
        $d->add($mes);
      }

      $divraces = $this->REGATTA->getRaces($chosen_div);
      $teams = array();
      $races = array();
      foreach ($this->REGATTA->getTeams() as $team) {
        $teams[(string)$team] = $team;
        $races[(string)$team] = $divraces;
      }
    }
    else {
      $port_title = "Manual rotation";
      $d = "";
      $races = $rotation->getRaces($exist_div[0]);

      // Include all teams across all divisions
      $teams = array();
      $races = array();
      $existing = $this->REGATTA->getTeams();
      foreach ($exist_div as $division) {
        $divraces = $this->REGATTA->getRaces($division);
        foreach ($existing as $team) {
          $label = sprintf('%s: %s', $division, $team);
          $teams[$label] = $team;
          $races[$label] = $divraces;
        }
      }
    }

    // OUTPUT
    $this->PAGE->addContent($p = new XPort($port_title));
    $p->add($d);
    $p->add($form = $this->createForm());

    $row = array("");
    foreach ($races as $list) {
      foreach ($list as $race) {
        $row[] = $race->number;
      }
      break;
    }
    $form->add($tab = new XQuickTable(array(), $row));

    // Get teams
    $attrs = array('size'=>'3', 'maxlength'=>'3', 'class'=>'small');
    foreach ($teams as $label => $team) {
      $row = array($label);
      foreach ($races[$label] as $race) {
        $sail = $rotation->getSail($race, $team);
        $row[] = new XTextInput(sprintf("%s,%s", $race->id, $team->id), ($sail !== null) ? $sail : "", $attrs);
      }
      $tab->addRow($row);
    }
    $form->add(new XP(array('class'=>'p-submit'),
                      array(new XReset("reset", "Reset"),
                            new XSubmitInput("editboat", "Edit sails"))));
  }

  public function process(Array $args) {

    $rotation = $this->REGATTA->getRotation();

    // ------------------------------------------------------------
    // Boat by boat
    // ------------------------------------------------------------
    if (isset($args['editboat'])) {
      unset($args['editboat']);

      $races = array(); // assoc map of affected race ID => Race
      $sails = array(); // assoc map of race ID => map team ID => sail

      DB::$SAIL->db_set_cache(true);
      foreach ($args as $rAndt => $value) {
        $value = DB::$V->reqString($args, $rAndt, 1, 9, "Invalid value for sail.");
        $rAndt = explode(",", $rAndt);
        $r = $this->REGATTA->getRaceById($rAndt[0]);
        $t = $this->REGATTA->getTeam($rAndt[1]);
        if ($r != null && $t != null) {
          $oldsail = $rotation->getSail($r, $t);
          if ($oldsail != $value) {
            $sail = new Sail();
            $sail->race = $r;
            $sail->team = $t;
            $sail->sail = $value;

            $id = (string)$r;
            $races[$id] = $r;
            if (!isset($sails[$id]))
              $sails[$id] = array();
            $sails[$id][$t->id] = $sail;
          }
        }
      }

      if (count($sails) == 0)
        throw new SoterException("No changes made.");

      // Ascertain that the rotation makes sense
      $combined = ($this->REGATTA->scoring == Regatta::SCORING_COMBINED);
      foreach ($sails as $rid => $teamlist) {
        $oldsails = ($combined) ? $rotation->getCombinedSails($races[$rid]) : $rotation->getSails($races[$rid]);
        $newsails = array();
        foreach ($oldsails as $sail)
          $newsails[$sail->team->id] = (string)$sail;
        foreach ($teamlist as $id => $sail)
          $newsails[$id] = (string)$sail;
        $unique = array_unique($newsails);
        if (count($unique) != count($oldsails))
          throw new SoterException("Duplicate sails in the same race: $rid.");
      }

      // All is well: replace sails
      $count = 0;
      foreach ($sails as $id => $list) {
        foreach ($list as $sail) {
          $rotation->setSail($sail);
          $count++;
        }
      }

      DB::$SAIL->db_set_cache();
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(sprintf("Updated %d sail(s).", $count)));
    }
    return $args;
  }
}
?>