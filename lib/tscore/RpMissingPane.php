<?php
use \tscore\AbstractRpPane;

/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Displays what is missing in the RP form for all teams
 *
 * @author Dayan Paez
 * @version 2013-03-20
 */
class RpMissingPane extends AbstractRpPane {

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

  public function process(Array $args) {
    throw new SoterException("This class does not process any information.");
  }
}
?>