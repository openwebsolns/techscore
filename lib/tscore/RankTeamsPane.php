<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Provide visual organization of teams to rank, applicable to team
 * racing only.
 *
 * @author Dayan Paez
 * @version 2013-01-05
 */
class RankTeamsPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Rank teams", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new SoterException("Pane only available for team racing regattas.");
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/team-rank.js')));
    $this->PAGE->addContent(new XP(array(), "Use this pane to set the rank for the teams in the regatta. By default, teams are ranked by the system according to win percentage, but tie breaks must be broken manually."));
    $this->PAGE->addContent(new XP(array(), "To edit a particular team's record by setting which races count towards their record, click on the win-loss record for that team. Remember to click \"Set ranks\" to save the order before editing a team's record."));

    $this->PAGE->addContent($f = $this->createForm());
    $f->add($tab = new XQuickTable(array('id'=>'rank-table'), array("#", "Explanation", "Record", "Team")));
    foreach ($this->REGATTA->getRanker()->rank($this->REGATTA) as $i => $rank) {
      $tab->addRow(array(new XTextInput('rank[]', ($i + 1), array('size'=>2)),
			 new XTextInput('explanation[]', $rank->explanation),
			 new XA($this->link('rank', array('team' => $rank->team->id)), $rank->getRecord()),
			 $rank->team));
    }
    $f->add(new XSubmitP('set-ranks', "Set ranks"));
  }

  public function process(Array $args) {
    throw new SoterException("No POST processed.");
  }
}
?>