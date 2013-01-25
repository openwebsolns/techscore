<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Drop individual penalties
 *
 * @author Dayan Paez
 * @version 2010-01-25
 */
class DropPenaltyPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Drop penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $penalties = array();
    $handicaps = array();

    $penlist = Penalty::getList();
    $bkdlist = Breakdown::getList();
    foreach ($this->REGATTA->getPenalizedFinishes() as $finish) {
      foreach ($finish->getModifiers() as $penalty) {
        $modifiers[$penalty->id] = $penalty;
        if (isset($penlist[$penalty->type]))
          $penalties[] = $penalty;
        elseif (isset($bkdlist[$penalty->type]))
          $handicaps[] = $penalty;
      }
    }

    // ------------------------------------------------------------
    // Existing penalties
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Penalties"));

    if (count($penalties) == 0) {
      $p->add(new XP(array(), "There are currently no penalties."));
    }
    else {
      $p->add($tab = new XQuickTable(array(), array("Race", "Team", "Type", "Comments", "Amount", "Displace?", "Action")));
      foreach ($penalties as $modifier) {
        $amount = $modifier->amount;
        if ($amount < 1)
          $amount = "FLEET + 1";
        $displace = "";
        if ($modifier->displace > 0)
          $displace = new XImg(WS::link('/inc/img/s.png'), "✓");
        $team = $modifier->finish->team;
        if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
          $team = $modifier->finish->race->division->getLevel() . ': ' . $team;
        $tab->addRow(array($modifier->finish->race,
                           $team,
                           $modifier->type,
                           $modifier->comments,
                           $amount,
                           $displace,
                           $form = $this->createForm()));

        $form->add(new XHiddenInput('modifier', $modifier->id));
        $form->add($sub = new XSubmitInput("p_remove", "Drop/Reinstate", array("class"=>"thin")));
      }
    }

    // ------------------------------------------------------------
    // Existing breakdowns
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Breakdowns"));

    if (count($handicaps) == 0) {
      $p->add(new XP(array(), "There are currently no breakdowns."));
    }
    else {
      $p->add($tab = new XQuickTable(array(), array("Race", "Team", "Type", "Comments", "Amount", "Displace", "Action")));
      foreach ($handicaps as $modifier) {
        $amount = $modifier->amount;
        if ($amount < 1)
          $amount = "Average in division";
        $displace = "";
        if ($modifier->displace > 0)
          $displace = new XImg(WS::link('/inc/img/s.png'), "✓");
        $team = $modifier->finish->team;
        if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
          $team = $modifier->finish->race->division->getLevel() . ': ' . $team;
        $tab->addRow(array($modifier->finish->race,
                           $team,
                           $modifier->type,
                           $modifier->comments,
                           $amount,
                           $displace,
                           $form = $this->createForm()));

        $form->add(new XHiddenInput('modifier', $modifier->id));
        $form->add($sub = new XSubmitInput("p_remove", "Drop/Reinstate",
                                           array("class"=>"thin")));
      }
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Drop penalty/breakdown
    // ------------------------------------------------------------
    if (isset($args['p_remove'])) {

      $penalty = DB::$V->reqID($args, 'modifier', DB::$FINISH_MODIFIER, "Missing penalty/breakdown provided.");
      if ($penalty->finish->race->regatta != $this->REGATTA)
        throw new SoterException("Invalid penalty/breakdown provided.");
      if ($penalty->finish->removeModifier($penalty)) {
        $this->REGATTA->commitFinishes(array($penalty->finish));
        $this->REGATTA->runScore($penalty->finish->race);
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

        // Announce
        Session::pa(new PA(sprintf("Dropped penalty for %s in race %s.", $penalty->finish->team, $penalty->finish->race)));
      }
    }
    return $args;
  }
}