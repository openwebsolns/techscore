<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2014-01-21
 * @package tscore
 */

require_once('AbstractPane.php');

/**
 * Centralized support for common round-related operations
 *
 * @author Dayan Paez
 * @created 2014-01-21
 */
abstract class AbstractRoundPane extends AbstractPane {

  const SIMPLE = 'simple';
  const COPY = 'copy';
  const SAILOFF = 'sailoff';
  const COMPLETION = 'completion';

  /**
   * Fills the form where teams are seeded for a particular round
   *
   * @param XForm $form the form to fill
   * @param Round $ROUND the round with which to fill
   * @param Array $masters the optional list of masters to use
   * @param Array $seeds the optional map of team ID => seed #
   */
  protected function fillTeamsForm(XForm $form, Round $ROUND, Array $masters = null, Array $seeds = null) {
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tr-sort-teams.js'));

    if ($masters === null)
      $masters = $ROUND->getMasters();
    if ($seeds === null) {
      $seeds = array();
      foreach ($ROUND->getSeeds() as $seed)
        $seeds[$seed->team->id] = $seed->seed;
    }

    if (count($masters) == 0) {
      $slaves = $ROUND->getSlaves();

      // Simple round
      $form->add(new XP(array(), sprintf("Place numbers 1-%d next to the teams to be included in this round.", $ROUND->num_teams)));

      $form->add($ul = new XUl(array('id'=>'teams-list')));
      $has_finishes = false;
      $has_carries = false;

      $teams = array();
      if ($ROUND->sailoff_for_round !== null) {
        foreach ($ROUND->sailoff_for_round->getSeeds() as $seed) {
          $teams[] = $seed->team;
        }
      }
      else {
        $teams = $this->REGATTA->getRankedTeams();
      }

      foreach ($teams as $team) {
        $id = 'team-'.$team->id;
        $order = "";
        if (isset($seeds[$team->id]))
          $order = $seeds[$team->id];
        $ul->add($li = new XLi(array(new XHiddenInput('team[]', $team->id),
                                     $ti = new XNumberInput('order[]', $order, 0, count($teams), 1, array('id'=>$id)),
                                     new XLabel($id, $team,
                                                array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
        if ($team->dt_rank !== null) {
          $li->add(new XMessage(sprintf(" Rank: %2d, (%s-%s) %s", $team->dt_rank, $team->dt_wins, $team->dt_losses, $team->dt_explanation)));
        }
        if ($this->teamHasScoresInRound($ROUND, $team)) {
          $li->add(new XMessage("*"));
          $ti->set('title', "There are finishes for this team, so it must be part of this round.");
          $has_finishes = true;
        }
        elseif ($this->teamInSlaveRounds($ROUND, $slaves, $team)) {
          $li->add(new XMessage("†"));
          $ti->set('title', "This team is being carried over to another round.");
          $has_carries = true;
        }
      }

      if ($has_finishes)
        $form->add(new XP(array(), new XMessage("* = Team must be present in this round because of scored races.")));
      if ($has_carries)
        $form->add(new XP(array(), new XMessage("† = Team must be present in this round because it is being carried over to another round.")));
    }
    else {
      // Completion round: choose team from other round
      $first = 1;
      $has_finishes = false;
      $ranker = $this->REGATTA->getRanker();
      foreach ($masters as $master) {
        $last = $first + $master->num_teams - 1;
        $round = $master->master;

        $form->add(new XH4($round));
        $form->add(new XP(array(), sprintf("Place numbers %d-%d next to the teams to be included from this round.", $first, $last)));

        $form->add($ul = new XUl(array('class'=>'teams-list')));
        $races = $this->REGATTA->getRacesInRound($round, Division::A());
        foreach ($ranker->rank($this->REGATTA, $races) as $seed) {
          $team = $seed->team;

          $id = 'team-' . $team->id;
          $order = "";
          if (isset($seeds[$team->id]))
            $order = $seeds[$team->id];
          $ul->add($li = new XLi(array(new XHiddenInput('team[]', $team->id),
                                       $ti = new XNumberInput('order[]', $order, 0, null, 1, array('id'=>$id)),
                                       new XLabel($id, $team,
                                                  array('onclick'=>sprintf('addTeamToRound("%s");', $id))),
                                       new XMessage(sprintf(" Rank: %2d, (%s) %s", $seed->rank, $seed->getRecord(), $seed->explanation)))));

          if ($this->teamHasScoresInRound($ROUND, $team)) {
            $li->add(new XMessage("*"));
            $ti->set('title', "There are finishes for this team, so it must be part of this round.");
            $has_finishes = true;
          }
        }
        $first = $last + 1;
      }

      if ($has_finishes)
        $form->add(new XP(array(), new XMessage("* = Team must be present in this round because of scored races.")));
    }
  }

  protected function teamHasScoresInRound(Round $round, Team $team) {
    foreach ($this->REGATTA->getScoredRacesForTeam(Division::A(), $team) as $race) {
      if ($race->round->id == $round->id)
        return true;
    }
    return false;
  }

  protected function teamInSlaveRounds(Round $round, Array $slaves, Team $team) {
    if (count($slaves) == 0)
      return false;
    foreach ($slaves as $slave) {
      foreach ($slave->slave->getSeeds() as $seed) {
        if ($seed->team->id == $team->id && $seed->original_round->id == $round->id)
          return true;
      }
    }
    return false;
  }

  protected function createRotationForm(Round $ROUND, $rounds = null, $num_divs = null) {
    if ($rounds === null)
      $rounds = $this->REGATTA->getRounds();
    if ($num_divs === null)
      $num_divs = count($this->REGATTA->getDivisions());
    $group_size = 2 * $num_divs;

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-rot.js')));
    $this->PAGE->addContent($p = new XPort("Sail numbers and colors"));
    $p->add($form = $this->createForm());
    $form->set('id', 'tr-rotation-form');

    $COLORS = array(
      "#eee" => "White",
      "#ccc" => "Light Grey",
      "#666" => "Grey",
      "#000" => "Black",
      "#884B2A" => "Brown",
      "#f80" => "Orange",
      "#f00" => "Red",
      "#fcc" => "Pink",
      "#add8e6" => "Light Blue",
      "#00f" => "Blue",
      "#808" => "Purple",
      "#0f0" => "Lime Green",
      "#080" => "Green",
      "#ff0" => "Yellow"
    );

    $flight = $ROUND->num_boats;
    if (!$ROUND->hasRotation()) {
      // Find another rotation for this number of boats
      for ($i = count($rounds) - 1; $i >= 0; $i--) {
        $other = $rounds[$i];
        if ($other->num_boats == $ROUND->num_boats && $other->hasRotation()) {
          $ROUND->setRotation($other->getSails(), $other->getColors());
          break;
        }
      }
    }

    $boats = $this->getBoatOptions();
    $form->add(new XP(array(), "Assign the sail numbers using the table below. If applicable, choose the color that goes with the sail. This color will be displayed in the \"Rotations\" dialog."));
    if ($ROUND->rotation_frequency == Race_Order::FREQUENCY_FREQUENT ||
        $ROUND->rotation_frequency == Race_Order::FREQUENCY_INFREQUENT) {

      // Prefill
      if (!$ROUND->hasRotation()) {
        $s = array();
        $c = array();
        for ($i = 0; $i < $flight; $i++) {
          $s[] = ($i + 1);
          $c[] = "";
        }
        $ROUND->setRotation($s, $c);
      }

      $form->add($xp = new XP(array(), array("The flight size for this rotation is ", new XStrong($flight / $group_size), " races.")));
      if ($ROUND->rotation_frequency == Race_Order::FREQUENCY_FREQUENT)
        $xp->add(" Remember to set the boat in the last column.");
      else {
        $boat = $ROUND->getBoat();
        if ($boat !== null)
          $boat = $boat->id;
        $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boats, $boat, array('required'=>'required'))));
      }

      $form->add(new XTable(array('class'=>'tr-rotation-sails'),
                            array(new XTHead(array(),
                                             array($h = new XTR(array(),
                                                                array(new XTH(array(), "#"),
                                                                      new XTH(array(), "Team A"),
                                                                      new XTH(array(), "Team B"))))),
                                  $bod = new XTBody())));

      if ($ROUND->rotation_frequency == Race_Order::FREQUENCY_FREQUENT)
        $h->add(new XTH(array(), "Boat"));

      $sailIndex = 0;
      for ($race_num = 0; $race_num < $flight / $group_size; $race_num++) {
        $bod->add($row = new XTR(array('class'=>'row' . ($race_num % 2))));
        $row->add(new XTH(array(), $race_num + 1));

        // Team A, then Team B
        for ($teamIndex = 0; $teamIndex < 2; $teamIndex++) {
          $row->add(new XTD(array(), new XTable(array('class'=>'sail-list'), array(new XTBody(array(), array($tab = new XTR()))))));
          for ($i = 0; $i < $num_divs; $i++) {
            $sail = $ROUND->getSailAt($sailIndex);
            $color = $ROUND->getColorAt($sailIndex);

            $tab->add(new XTD(array(), new XSailCombo('sails[]', 'colors[]', $sail, $color)));
            $sailIndex++;
          }
        }

        // Race number?
        if ($ROUND->rotation_frequency == Race_Order::FREQUENCY_FREQUENT) {
          $boat = $ROUND->getRaceOrderBoat($race_num);
          if ($boat !== null)
            $boat = $boat->id;
          $row->add(new XTD(array(), XSelect::fromArray('boats[]', $boats, $boat, array('required'=>'required', 'class'=>'boat'))));
        }
      }
    }
    else {
      // Prefill
      if (!$ROUND->hasRotation()) {
        $s = array();
        $c = array();
        for ($i = 0; $i < $flight; $i++) {
          $s[] = ($i + 1);
          $c[] = "";
        }
        $ROUND->setRotation($s, $c);
      }

      $boat = $ROUND->getBoat();
      if ($boat !== null)
        $boat = $boat->id;
      $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boats, $boat, array('required'=>'required'))));

      // No rotation frequency: display an entry PER team
      $form->add($tab = new XQuickTable(array('class'=>'tr-rotation-sails'),
                                        array("Team #", "Sail")));

      $sailIndex = 0;
      for ($i = 0; $i < $ROUND->getRotationCount() / $num_divs; $i++) {
        $tab->addRow(array(new XTH(array(), sprintf("Team %d", $i + 1)),
                           new XTable(array('class'=>'sail-list'), array(new XTBody(array(), array($bod = new XTR()))))),
                     array('class'=>'row'.($i % 2)));

        for ($j = 0; $j < $num_divs; $j++) {
          $sail = $ROUND->getSailAt($sailIndex);
          $color = $ROUND->getColorAt($sailIndex);
          $bod->add(new XTD(array(), new XSailCombo('sails[]', 'colors[]', $sail, $color)));
          $sailIndex++;
        }
      }
    }
    return $form;
  }

  /**
   * Creates and assigns a TeamRotation object based on arguments
   *
   * Assigns boats to races
   *
   * @param Array $args the result of createRotationForm
   * @param Round $round the round to which assign the team rotation
   * @param Array $divisions the divisions in the regatta
   */
  protected function processSails(Array $args, Round $round, Array $divisions = null) {
    if ($divisions === null)
      $divisions = $this->REGATTA->getDivisions();
    $group_size = 2 * count($divisions);

    $s = array();
    $c = array();
    $boats = array();

    if ($round->rotation_frequency == Race_Order::FREQUENCY_FREQUENT) {
      $flight = $round->num_boats / $group_size;
      $boats = array();
      foreach (DB::$V->reqList($args, 'boats', $flight, "Missing list of boats.") as $i => $id) {
        if (($boat = DB::getBoat($id)) === null)
          throw new SoterException(sprintf("Invalid boat provided for race %d.", ($i + 1)));
        $boats[] = $boat;
      }

      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
        $round->setRaceOrderBoat($i, $boats[$i % count($boats)]);
      }

      $sails = DB::$V->reqList($args, 'sails', $round->num_boats, "Missing list of sails.");
      $c = DB::$V->incList($args, 'colors', $round->num_boats);

      // make sure all sails are present and distinct
      $sail_boats = array();
      foreach ($sails as $i => $sail) {
        $sail = trim($sail);
        if ($sail == "")
          throw new SoterException("Empty sail provided.");

        $boat = $boats[floor($i / $group_size)];
        $id = sprintf('%s-%s', $boat->id, $sail);
        if (isset($sail_boats[$id]))
          throw new SoterException("Duplicate sail \"$sail\" provided.");
        $s[] = $sail;
        $sail_boats[$id] = $sail;
      }
    }
    elseif ($round->rotation_frequency == Race_Order::FREQUENCY_INFREQUENT) {
      $sails = DB::$V->reqList($args, 'sails', $round->num_boats, "Missing list of sails.");
      $c = DB::$V->incList($args, 'colors', $round->num_boats);

      // make sure all sails are present and distinct
      foreach ($sails as $i => $sail) {
        $sail = trim($sail);
        if ($sail == "")
          throw new SoterException("Empty sail provided.");
        if (in_array($sail, $s))
          throw new SoterException("Duplicate sail \"$sail\" provided.");
        $s[] = $sail;
      }

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Missing boat. Please try again.");
      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
        $round->setRaceOrderBoat($i, $boat);
      }
    }
    else {
      $num_entries = $round->num_teams * count($divisions);

      // Assign all sails and colors to sails1 and colors1
      $sails = DB::$V->reqList($args, 'sails', $num_entries, "Invalid list of sails provided.");
      $c = DB::$V->incList($args, 'colors', $num_entries);

      foreach ($sails as $i => $sail) {
        $sail = trim($sail);
        if ($sail == "")
          throw new SoterException("Empty sail provided.");
        if (in_array($sail, $s))
          throw new SoterException("Duplicate sail \"$sail\" provided.");
        $s[] = $sail;
      }

      $boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Missing boat. Please try again.");
      for ($i = 0; $i < $round->getRaceOrderCount(); $i++) {
        $round->setRaceOrderBoat($i, $boat);
      }
    }

    $round->setRotation($s, $c);
    return array();
  }

  /**
   * Processes arguments and return list of seeds for given round
   *
   * @param Array $args the arguments, from fillTeamsForm
   * @param Round $round the round in question
   * @param Array $masters the optional list of master rounds
   */
  protected function processSeeds(Array $args, Round $round, Array $masters = null) {
    $seeds = array();

    $ids = DB::$V->incList($args, 'team');
    if (count($ids) == 0)
      return $seeds;

    $order = DB::$V->reqList($args, 'order', count($ids), "Missing list of seed values.");
    array_multisort($order, SORT_NUMERIC, $ids);

    if ($masters !== null && count($masters) > 0) {
      // Group the teams by masters
      $all_teams = array();
      $orig_round = array();
      $index_delims = array();

      $last = 0;
      foreach ($masters as $slave) {
        $last += $slave->num_teams;
        $index_delims[$last] = $slave->master;

        $all_teams[$slave->master->id] = array();
        $orig_round[$slave->master->id] = $slave->master;
        foreach ($slave->master->getSeeds() as $seed)
          $all_teams[$slave->master->id][$seed->team->id] = $seed->team;
      }

      $teams = array();
      foreach ($ids as $index => $id) {
        $rank = $order[$index];
        if ($rank > 0) {
          if ($rank < 1 || $rank > $round->num_teams)
            throw new SoterException(sprintf("Invalid seed value: %d.", $rank));
          if (isset($seeds[$rank]))
            throw new SoterException(sprintf("Duplicate seed specified: %d.", $rank));

          foreach ($index_delims as $last => $master_round) {
            if ($rank <= $last)
              break;
          }

          if (!isset($all_teams[$master_round->id][$id]))
            throw new SoterException(sprintf("Invalid round for seed %d provided.", $rank));
          if (isset($teams[$id]))
            throw new SoterException(sprintf("Team provided twice: %s.", $id));

          $team = $all_teams[$master_round->id][$id];
          $teams[$id] = $team;
          $seed = new Round_Seed();
          $seed->team = $team;
          $seed->seed = $rank;
          $seed->original_round = $master_round;
          $seeds[$rank] = $seed;
        }
      }
    }
    else {
      $all_teams = array();
      if ($round->sailoff_for_round !== null) {
        foreach ($round->sailoff_for_round->getSeeds() as $seed) {
          $all_teams[$seed->team->id] = $seed->team;
        }
      }
      else {
        foreach ($this->REGATTA->getTeams() as $team)
          $all_teams[$team->id] = $team;
      }

      $teams = array();
      foreach ($ids as $index => $id) {
        if ($order[$index] > 0) {
          if (!isset($all_teams[$id]))
            throw new SoterException("Invalid team ID provided: $id.");
          if (isset($teams[$id]))
            throw new SoterException("Team provided twice: $id.");
          if ($order[$index] < 1 || $order[$index] > $round->num_teams)
            throw new SoterException(sprintf("Invalid seed value: %d.", $order[$index]));
          if (isset($seeds[$order[$index]]))
            throw new SoterException(sprintf("Seed value duplicate found: %d.", $order[$index]));

          $teams[$id] = $all_teams[$id];
          $seed = new Round_Seed();
          $seed->team = $all_teams[$id];
          $seed->seed = $order[$index];
          $seeds[$order[$index]] = $seed;
        }
      }
    }

    return $seeds;
  }

  protected function getBoatOptions() {
    $boats = DB::getBoats();
    $boatOptions = array("" => "");
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;
    return $boatOptions;
  }
}
?>