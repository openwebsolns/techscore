<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

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
    foreach ($this->REGATTA->getPenalizedFinishes() as $finish) {
      $penalty = $finish->getModifier();
      if ($penalty instanceof Penalty)
        $penalties[] = $finish;
      elseif ($penalty instanceof Breakdown)
        $handicaps[] = $finish;
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
      foreach ($penalties as $finish) {
        $amount = $finish->amount;
        if ($amount < 1)
          $amount = "FLEET + 1";
        $displace = "";
        if ($finish->displace > 0)
          $displace = new XImg(WS::link('/inc/img/s.png'), "✓");
        $tab->addRow(array($finish->race,
                           $finish->team,
                           $finish->penalty,
                           $finish->comments,
                           $amount,
                           $displace,
                           $form = $this->createForm()));

        $form->add(new XHiddenInput("r_finish", $finish->id));
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
      foreach ($handicaps as $finish) {
        $amount = $finish->amount;
        if ($amount < 1)
          $amount = "Average in division";
        $displace = "";
        if ($finish->displace > 0)
          $displace = new XImg(WS::link('/inc/img/s.png'), "✓");
        $tab->addRow(array($finish->race,
                           $finish->team,
                           $finish->penalty,
                           $finish->comments,
                           $amount,
                           $displace,
                           $form = $this->createForm()));

        $form->add(new XHiddenInput("r_finish", $finish->id));
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

      $finish = DB::$V->reqID($args, 'r_finish', DB::$FINISH, "Invalid or missing finish provided.");
      if ($finish->race->regatta != $this->REGATTA ||
          $finish->getModifier() == null)
        throw new SoterException("Invalid finish provided.");
      $finish->setModifier();
      $this->REGATTA->commitFinishes(array($finish));
      $this->REGATTA->runScore($finish->race);
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);

      // Announce
      Session::pa(new PA(sprintf("Dropped penalty for %s in race %s.", $finish->team, $finish->race)));
    }
    return $args;
  }
}