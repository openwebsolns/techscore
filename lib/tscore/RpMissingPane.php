<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * Displays what is missing in the RP form for all teams
 *
 * @author Dayan Paez
 * @version 2013-03-20
 */
class RpMissingPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Missing RP information", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("For all teams"));
    $p->add(new XP(array(), "Below is a list of all the teams that are participating in the regatta and what RP information is missing for each one. Note that only RP for scored races are counted. Click on the team's name to edit its RP information."));

    foreach ($this->REGATTA->getTeams() as $team) {
      $p->add(new XH4(new XA($this->link('rp', array('chosen_team'=>$team->id)), $team)));

      $this->fillMissing($p, $team);
    }
  }

  protected function fillMissing(XPort $p, Team $chosen_team) {
    $divisions = $this->REGATTA->getDivisions();
    $rpManager = $this->REGATTA->getRpManager();

    $header = new XTR(array(), array(new XTH(array(), "#")));
    $rows = array();
    foreach ($divisions as $divNumber => $div) {
      $name = "Division " . $div;
      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        $name = $div->getLevel(count($divisions));
      $header->add(new XTH(array('colspan'=>2), $name));

      foreach ($this->REGATTA->getScoredRacesForTeam($div, $chosen_team) as $race) {
        // get missing info
        $skip = null;
        $crew = null;
        if (count($rpManager->getRpEntries($chosen_team, $race, RP::SKIPPER)) == 0)
          $skip = "Skipper";
        $diff = $race->boat->min_crews - count($rpManager->getRpEntries($chosen_team, $race, RP::CREW));
        if ($diff > 0) {
          if ($race->boat->min_crews == 1)
            $crew = "Crew";
          else
            $crew = sprintf("%d Crews", $diff);
        }

        if ($skip !== null || $crew !== null) {
          if (!isset($rows[$race->number]))
            $rows[$race->number] = array(new XTH(array(), $race->number));
          // pad the row with previous division
          for ($i = count($rows[$race->number]) - 1; $i < $divNumber * 2; $i += 2) {
            $rows[$race->number][] = new XTD();
            $rows[$race->number][] = new XTD();
          }
          $rows[$race->number][] = new XTD(array(), $skip);
          $rows[$race->number][] = new XTD(array(), $crew);
        }
      }
    }

    if (count($rows) > 0) {
      $p->add(new XTable(array('class'=>'missingrp-table'),
                         array(new XTHead(array(), array($header)),
                               $bod = new XTBody())));
      $rowIndex = 0;
      foreach ($rows as $row) {
        for ($i = count($row); $i < count($divisions) * 2 + 1; $i++)
          $row[] = new XTD();
        $bod->add(new XTR(array('class'=>'row' . ($rowIndex++ % 2)), $row));
      }
    }
    else
      $p->add(new XP(array('class'=>'valid'),
                     array(new XImg(WS::link('/inc/img/s.png'), "✓"), " Information is complete.")));
  }

  public function process(Array $args) {
    throw new SoterException("This class does not process any information.");
  }
}
?>