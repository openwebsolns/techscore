<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Rank a regatta based on a number of races
 *
 * @author Dayan Paez
 * @created 2014-03-18
 */
class TeamPartialRankPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Partial ranking", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new SoterException("Pane only available for team racing regattas.");
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // By round
    // ------------------------------------------------------------
    if (isset($args['rank'])) {
      try {
        $races = array();

        if (isset($args['round'])) {
          $rounds = array();
          foreach (DB::$V->reqList($args, 'round', null, "No list of rounds provided.") as $id) {
            $round = DB::get(DB::$ROUND, $id);
            if ($round === null || $round->regatta != $this->REGATTA)
              throw new SoterException("Invalid round requested: " . $id);

            $rounds[] = $round;
            foreach ($this->REGATTA->getRacesInRound($round, Division::A()) as $race) {
              if (count($this->REGATTA->getFinishes($race)) > 0)
                $races[] = $race;
            }
          }
          $title = "Ranks based on round(s) " . implode(", ", $rounds);
        }
        else {
          $max = count($this->REGATTA->getRaces(Division::A()));
          $start = DB::$V->reqInt($args, 'start_race', 1, $max + 1, "No start race provided.");
          $end = DB::$V->reqInt($args, 'end_race', $start, $max + 1, "Invalid end race provided.");

          for ($i = $start; $i <= $end; $i++) {
            $race = $this->REGATTA->getRace(Division::A(), $i);
            if ($race !== null && count($this->REGATTA->getFinishes($race)) > 0)
              $races[] = $race;
          }

          $title = sprintf("Rank based on races %d-%d", $start, $end);
        }

        if (count($races) == 0)
          throw new SoterException("No scored races to rank in rounds provided! Please try again.");

        $this->PAGE->addContent(new XP(array(), new XA($this->link('partial'), "← Start Over")));
        $this->PAGE->addContent($p = new XPort($title));
        $p->add(new XP(array('class'=>'warning'), "Note: only teams that participated in chosen races are shown."));
        $p->add($tab = new XQuickTable(array('id'=>'ranktable', 'class'=>'teamtable'),
                                       array("#", "Record", "Team", "Explanation")));

        $ranker = $this->REGATTA->getRanker();
        $ranks = $ranker->rank($this->REGATTA, $races, true);
        foreach ($ranks as $rank) {
          $tab->addRow(array($rank->rank,
                             $rank->getRecord(),
                             $rank->team,
                             $rank->explanation));
        }
        return;

      } catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // Choose method for ranking: rounds
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Rank teams from round(s)"));
    $p->add(new XP(array(), "Use this form to show how the teams rank in the regatta based only on their performance in the round(s) chosen."));
    $p->add($form = $this->createForm(XForm::GET));

    require_once('xml5/XMultipleSelect.php');
    $form->add(new FItem("Round(s):", $sel = new XMultipleSelect('round[]')));
    foreach ($this->REGATTA->getScoredRounds() as $round)
      $sel->addOption($round->id, $round);

    $form->add(new XSubmitP('rank', "Show ranks"));

    // ------------------------------------------------------------
    // Or by races
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Rank teams based on races"));
    $p->add(new XP(array(), "Use this form to show how the teams rank based only on their performance in the range of races provided."));
    $p->add($form = $this->createForm(XForm::GET));

    $scored_races = $this->REGATTA->getScoredRaces(Division::A());
    $form->add(new FItem("Start race:", new XTextInput('start_race', 1)));
    $form->add(new FItem("End race:", new XTextInput('end_race', $scored_races[count($scored_races) - 1])));
    $form->add(new XSubmitP('rank', "Show ranks"));
  }

  public function process(Array $args) {
    throw new SoterException("This pane does not process any requests.");
  }
}
?>