<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('tscore/AbstractRoundPane.php');

/**
 * Page for editing races when using team scoring. These team races
 * require not just a number, but also the two teams from the set of
 * teams which will be participating. Note that this pane will
 * automatically allocate 3 divisions for the regatta.
 *
 * Each race must also belong to a particular "round". In a particular
 * round, each team races against every other team in a round robin.
 * It would be useful to have the user choose the teams that will
 * participate in a given round and have the program create the
 * pairings automatically. Then, the user has the option to add/remove
 * or reorder the pairings as needed.
 *
 * @author Dayan Paez
 * @version 2012-03-05
 */
class TeamRacesPane extends AbstractRoundPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Create Round", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new InvalidArgumentException("TeamRacesPane only available for team race regattas.");
  }

  /**
   * Fills out the pane, allowing the user to add up to 10 races at a
   * time, or edit any one of the previous races
   *
   * @param Array $args (ignored)
   */
  protected function fillHTML(Array $args) {
    $rounds = $this->REGATTA->getRounds();
    $master_rounds = array();
    $sailoff_rounds = array();
    foreach ($rounds as $round) {
      if (count($round->getMasters()) == 0 && $round->num_teams == count($round->getSeeds()))
        $master_rounds[] = $round;
      if ($round->sailoff_for_round === null)
        $sailoff_rounds[] = $round;
    }

    $this->PAGE->head->add(new LinkCSS('/inc/css/round.css'));
    $ROUND = Session::g('round');
    $type = Session::g('round_type');
    if ($type === null)
      $type = self::SIMPLE;

    // Calculate step
    $MAX_STEP = 0;
    if ($ROUND === null && count($rounds) == 0) {
      $ROUND = new Round();
      Session::s('round', $ROUND);
    }

    $team_ids = Session::g('round_teams');
    $finish_round_ids = Session::g('round_finishes');
    if ($ROUND !== null) {
      $MAX_STEP = 1;
      if ($ROUND->num_teams !== null) {
        $MAX_STEP = 2;
        if ($ROUND->race_order !== null) {
          $MAX_STEP = 3;
          if ($ROUND->rotation !== null) {
            $MAX_STEP = 4;
            if ($team_ids !== null) {
              $MAX_STEP = 5;
              if (count($rounds) == 0 || count($team_ids) < 2 || $finish_round_ids !== null) {
                $MAX_STEP = 6;
              }
            }
          }
        }
      }
    }
    $STEP = DB::$V->incInt($args, 'step', 0, $MAX_STEP + 1, $MAX_STEP);

    // ------------------------------------------------------------
    // Progress report
    // ------------------------------------------------------------
    $this->PAGE->addContent($f = $this->createForm());
    $f->add($prog = new XP(array('id'=>'progressdiv')));
    $this->fillProgress($prog, $MAX_STEP, $STEP);

    // ------------------------------------------------------------
    // Step 0: Offer choice
    // ------------------------------------------------------------
    if ($STEP == 0) {
      $this->PAGE->addContent($p = new XPort("Add round"));
      $p->add(new XP(array(), "To get started, choose the  kind of round you would like to add."));
      $p->add($f = $this->createForm());

      $opts = array(self::SIMPLE => "Standard round robin");
      if (count($sailoff_rounds) > 0) {
        $opts[self::SAILOFF] = "Sailoff round";
        $opts[self::COPY] = "Using existing round as template";
      }
      if (count($master_rounds) > 1)
        $opts[self::COMPLETION] = "Completion round";
      $f->add(new FItem("Add round:", XSelect::fromArray('create-round', $opts)));
      $f->add(new XSubmitP('go', "Next →"));

      $this->PAGE->addContent($p = new XPort("Explanation"));
      $p->add($ul = new XUl(array(),
                            array(new XLi(array(new XStrong("Standard round robin"), " refers to a regular round robin whose races do not depend on any other round. This is the default choice.")))));
      if (isset($opts[self::SAILOFF]))
        $ul->add(new XLi(array(new XStrong("Sailoff round"), " to be used to break ties in a previous round. These races do not count towards the overall record for the teams.")));
      if (isset($opts[self::COPY]))
        $ul->add(new XLi(array(new XStrong("Using existing round as template"), " will create a round by copying races and teams from a previously-existing round.")));
      if (isset($opts[self::COMPLETION]))
        $ul->add(new XLi(array(new XStrong("Completion round"), " refers to a round where some of the races come from previously existing round(s).")));
      return;

    }

    $boats = $this->getBoatOptions();
    
    // ------------------------------------------------------------
    // Step 1: Settings
    // ------------------------------------------------------------
    $divisions = $this->REGATTA->getDivisions();
    $num_divs = count($divisions);
    $group_size = 2 * $num_divs;

    if ($STEP == 1) {
      if ($ROUND->title === null)
        $ROUND->title = sprintf("Round %d", count($rounds) + 1);

      if ($type == self::SIMPLE) {
        $num_teams = count($this->REGATTA->getTeams());
        if ($ROUND->num_teams !== null)
          $num_teams = $ROUND->num_teams;

        $num_boats = $group_size * 3;
        if ($ROUND->num_boats !== null)
          $num_boats = $ROUND->num_boats;

        $boat = null;
        if ($ROUND->boat !== null)
          $boat = $ROUND->boat->id;

        $this->PAGE->addContent($p = new XPort("New round settings"));
        $p->add($form = $this->createForm());
        $form->add(new FItem("Round name:", new XTextInput('title', $ROUND->title)));
        $form->add(new FItem("Number of teams:", new XTextInput('num_teams', $num_teams)));

        $form->add(new FItem("Number of boats:", new XInput('number', 'num_boats', $num_boats, array('min'=>$group_size, 'step'=>$group_size))));
        $form->add(new FItem("Rotation frequency:", XSelect::fromArray('rotation_frequency', Race_Order::getFrequencyTypes())));
        $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boats, $boat)));
        $form->add($p = new XSubmitP('create-settings', "Next →"));
      }

      elseif ($type == self::COPY) {
        $this->PAGE->addContent($p = new XPort("New round settings"));
        $p->add($form = $this->createForm());
        $form->add(new FItem("Round name:", new XTextInput('title', $ROUND->title)));
        $form->add(new FItem("Template round:", XSelect::fromDBM('template', $rounds, Session::g('round_template'))));
        $form->add($fi = new FItem("Swap teams:", new XCheckboxInput('swap', 1, array('id'=>'chk-swap'))));
        $fi->add(new XLabel('chk-swap', "Reverse the teams in each race."));
        $form->add($p = new XSubmitP('create-settings', "Next →"));
      }

      elseif ($type == self::SAILOFF) {
        $num_teams = "";
        if ($ROUND->num_teams !== null)
          $num_teams = $ROUND->num_teams;

        $freq = array("" => "");
        foreach (Race_Order::getFrequencyTypes() as $key => $val)
          $freq[$key] = $val;

        $boats[""] = "";

        $this->PAGE->addContent($p = new XPort("Sailoff round settings"));
        $p->add($form = $this->createForm());
        $form->add(new FItem("Round name:", new XTextInput('title', $ROUND->title)));
        $form->add(new FItem("Round to sailoff:", XSelect::fromDBM('sailoff_for_round', $sailoff_rounds)));
        $form->add(new FItem("Number of teams:", new XTextInput('num_teams', $num_teams)));

        $form->add(new XP(array(), "To use the same settings as the round chosen above, leave the following settings blank."));
        $form->add(new FItem("Number of boats:", new XInput('number', 'num_boats', '', array('min'=>0, 'step'=>$group_size))));
        $form->add(new FItem("Rotation frequency:", XSelect::fromArray('rotation_frequency', $freq)));
        $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boats, "")));
        $form->add($p = new XSubmitP('create-settings', "Next →"));
      }

      elseif ($type == self::COMPLETION) {
        $boat = null;
        if ($ROUND->boat !== null)
          $boat = $ROUND->boat->id;

        $this->PAGE->addContent($p = new XPort("Completion round settings"));
        $p->add($form = $this->createForm());
        $form->add(new FItem("Round name:", new XTextInput('title', $ROUND->title)));
        $form->add(new FItem("Number of boats:", new XInput('number', 'num_boats', $ROUND->num_boats, array('min'=>$group_size, 'step'=>$group_size))));
        $form->add(new FItem("Rotation frequency:", XSelect::fromArray('rotation_frequency', Race_Order::getFrequencyTypes())));
        $form->add(new FItem("Boat:", XSelect::fromArray('boat', $boats, $boat)));

        $form->add(new XP(array(), "Enter the number of teams to carry over from each of the possible rounds below."));
        $form->add($ul = new XUl(array('id' => 'teams-list')));
        foreach ($master_rounds as $round) {
          $id = 'r-' . $round->id;
          $ul->add(new XLi(array(new XHiddenInput('master_round[]', $round->id),
                                 new XInput('number', 'num_teams_per_round[]', '', array('min'=>0, 'max'=>($round->num_teams - 1), 'id'=>$id)),
                                 new XLabel($id, $round))));
                                 
        }

        $form->add($p = new XSubmitP('create-settings', "Next →"));
      }
      return;
    }

    // ------------------------------------------------------------
    // Step 2: Race order
    // ------------------------------------------------------------
    if ($STEP == 2) {

      $master_count = Session::g('round_masters');

      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/toggle-tablesort.js'));
      $this->PAGE->addContent($p = new XPort("Race orders"));
      $p->add($form = $this->createForm());
      $form->set('id', 'edit-races-form');

      $order = null;
      $message = null;
      if ($ROUND->race_order !== null) {
        $order = new Race_Order();
        $order->template = $ROUND->race_order;
        $message = "The saved race order is shown below.";
      }
      else {
        $order = DB::getRaceOrder($num_divs, $ROUND->num_teams, $ROUND->num_boats, $ROUND->rotation_frequency, $master_count);
        $message = "A race order template has been automatically chosen below.";
      }
      if ($order === null) {
        // Create basic order
        $template = array();
        for ($i = 0; $i < $ROUND->num_teams - 1; $i++) {
          for ($j = $i + 1; $j < $ROUND->num_teams; $j++) {
            $shake = sprintf("%d-%d", ($i + 1), ($j + 1));
            $template[$shake] = $shake;
          }
        }
        if ($master_count !== null) {
          $first = 1;
          foreach ($master_count as $num) {
            $last = $first + $num;
            for ($i = $first; $i < $last; $i++) {
              for ($j = $i + 1; $j < $last; $j++) {
                $shake = sprintf("%d-%d", $i, $j);
                unset($template[$shake]);
              }
            }
            $first = $last;
          }
        }
        $order = new Race_Order();
        $order->template = array_keys($template);
        $message = new XStrong("Please set the race order below.");
      }

      $form->add(new XNoScript("To reorder the races, indicate the relative desired order in the first cell."));
      $form->add(new XScript('text/javascript', null, 'var f = document.getElementById("edit-races-form"); var p = document.createElement("p"); p.appendChild(document.createTextNode("To reorder the races, move the rows below by clicking and dragging on the first cell (\"#\") of that row.")); f.appendChild(p);'));
      $form->add(new XP(array(), $message));
      $form->add(new XNoScript(array(new XP(array(),
                                            array(new XStrong("Important:"), " check the edit column if you wish to edit that race. The race will not be updated regardless of changes made otherwise.")))));
      $header = array("Order", "#");
      $header[] = "First team";
      $header[] = "← Swap →";
      $header[] = "Second team";
      $form->add($tab = new XQuickTable(array('id'=>'divtable', 'class'=>'teamtable'), $header));
      for ($i = 0; $i < count($order->template); $i++) {
        $pair = $order->getPair($i);
        $tab->addRow(array(new XTextInput('order[]', ($i + 1), array('size'=>2)),
                           new XTD(array('class'=>'drag'), ($i + 1)),
                           new XTD(array(),
                                   array(new XEm(sprintf("Team %d", $pair[0])),
                                         new XHiddenInput('team1[]', $pair[0]))),
                           new XCheckboxInput('swap[]', $i),
                           new XTD(array(),
                                   array(new XEm(sprintf("Team %d", $pair[1])),
                                         new XHiddenInput('team2[]', $pair[1])))),
                     array('class'=>'sortable'));
      }
      $form->add(new XSubmitP('create-order', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 3: Sails
    // ------------------------------------------------------------
    if ($STEP == 3) {
      $form = $this->createRotationForm($ROUND, $rounds, $num_divs);
      $form->add(new XSubmitP('create-sails', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 4: Teams?
    // ------------------------------------------------------------
    if ($STEP == 4) {
      $ids = Session::g('round_teams');
      $seeds = array();
      if ($ids !== null) {
        foreach ($ids as $num => $id)
          $seeds[$id] = $num;
      }

      $form = $this->createForm();
      if ($type == self::SAILOFF) {
        $this->PAGE->addContent($p = new XPort("Teams"));
        $p->add($form);
        $form->add(new XP(array(),
                          array("Specify and seed the teams that will participate in this sailoff round from the list below. ",
                                new XStrong("You must specify the teams now."))));
      }
      else {
        $this->PAGE->addContent($p = new XPort("Teams (optional)"));
        $p->add($form);
        $form->add(new XP(array(),
                          array("Specify and seed the teams that will participate in this round. ",
                                new XStrong("You may specify the teams at a later time."))));
      }

      $masters = array();
      $master_ids = Session::g('round_masters');
      if ($master_ids !== null) {
        foreach ($master_ids as $id => $cnt) {
          $round = DB::get(DB::$ROUND, $id);
          if ($round !== null) {
            $master = new Round_Slave();
            $master->master = $round;
            $master->num_teams = $cnt;
            $masters[] = $master;
          }
        }
      }

      $this->fillTeamsForm($form, $ROUND, $masters, $seeds);
      $form->add(new XSubmitP('create-teams', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 5: Copy finishes
    // ------------------------------------------------------------
    if ($STEP == 5) {
      $this->PAGE->addContent($p = new XPort("Copy finishes (optional)"));
      $p->add($form = $this->createForm());
      if (count($rounds) == 0) {
        $form->add(new XP(array('class'=>'warning'), "There are no rounds from which to copy races."));
      }
      elseif (count($team_ids) < 2) {
        $form->add(new XP(array('class'=>'warning'), "At least two teams must be seeded in order to copy finishes."));
      }
      else {
        $ids = Session::g('round_finishes');
        if ($ids === null)
          $ids = array();

        $form->add(new XP(array(), "This optional step allows you to import finishes from previous races for matchups that appear in this round. This step will save you the work of re-entering scores for races already sailed. Note that this is not the same as carrying races from previous rounds."));
        if (count($rounds) > 1) {
          $form->add(new XP(array(), "Order the rounds below to determine which race's finishes to copy in case multiple races exist for the same matchup."));
          $form->add($tab = new XQuickTable(array('class'=>'copy-finishes-table'), array("Order", "Round")));
          foreach ($rounds as $round) {
            $id = 'round-' . $round->id;
            $order = "";
            $num = array_search($round->id, $ids);
            if ($num !== false)
              $order = $num + 1;
            $tab->addRow(array(new XTD(array(),
                                       array(new XInput('number', 'copy_order[]', $order, array('min'=>0, 'max'=>count($rounds), 'step'=>1, 'size'=>3, 'id'=>$id)),
                                             new XHiddenInput('copy_round[]', $round->id))),
                               new XLabel($id, $round)));
          }
        }
        else {
          $round = $rounds[0];
          $id = 'round-' . $round->id;
          $form->add($fi = new FItem($round . ":", new XCheckboxInput('copy_round[]', $round->id, array('id'=>$id))));
          $fi->add(new XLabel($id, "Copy scores from this round."));
          $fi->add(new XHiddenInput('copy_order[]', 1));
        }
      }
      $form->add(new XSubmitP('create-finishes', "Next →"));
    }

    // ------------------------------------------------------------
    // Step 6: Review
    // ------------------------------------------------------------
    if ($STEP == 6) {
      $this->PAGE->addContent($p = new XPort("Review"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "Verify that all the information is correct. Click \"Create\" to create the round, or use the progress bar above to go back to a different step."));

      $seeds = Session::g('round_teams');
      $teams = array();
      for ($i = 1; $i <= $ROUND->num_teams; $i++) {
        $team = null;
        if (isset($seeds[$i])) {
          $team = $this->REGATTA->getTeam($seeds[$i]);
        }
        if ($team === null)
          $team = new XEm(sprintf("Team %d", $i), array('class'=>'no-team'));
        $teams[] = $team;
      }

      $copy_rounds = array();
      $copy_ids = Session::g('round_finishes');
      if ($copy_ids !== null) {
        foreach ($rounds as $round) {
          if (in_array($round->id, $copy_ids))
            $copy_rounds[] = $round;
        }
      }

      $sails = $ROUND->rotation->assignSails($ROUND, $teams, $divisions, $ROUND->rotation_frequency);
      $tab = new XTable(array('class'=>'tr-rotation-table'),
                        array(new XTHead(array(),
                                         array(new XTR(array(),
                                                       array(new XTH(array(), "#"),
                                                             new XTH(array('colspan'=>2), "Team 1"),
                                                             new XTH(array('colspan'=>count($divisions)), "Sails"),
                                                             new XTH(array(), ""),
                                                             new XTH(array('colspan'=>count($divisions)), "Sails"),
                                                             new XTH(array('colspan'=>2), "Team 2"),
                                                             new XTH(array(), "Copy finishes?"))))),
                              $body = new XTBody()));

      $flight = $ROUND->num_boats / $group_size;
      for ($i = 0; $i < count($ROUND->race_order); $i++) {
        // spacer
        if ($flight > 0 && $i % $flight == 0) {
          $body->add(new XTR(array('class'=>'tr-flight'), array(new XTD(array('colspan' => 9 + 2 * count($divisions)), sprintf("Flight %d", ($i / $flight + 1))))));
        }

        $pair = $ROUND->getRaceOrderPair($i);
        $team1 = $teams[$pair[0] - 1];
        $team2 = $teams[$pair[1] - 1];

        // Burgees
        $burg1 = "";
        $burg2 = "";
        if ($team1 instanceof Team)
          $burg1 = $team1->school->drawSmallBurgee("");
        if ($team2 instanceof Team)
          $burg2 = $team2->school->drawSmallBurgee("");

        $body->add($row = new XTR(array(), array(new XTD(array(), ($i + 1)),
                                                 new XTD(array('class'=>'team1'), $burg1),
                                                 new XTD(array('class'=>'team1'), $team1))));
        // first team
        foreach ($divisions as $div) {
          $sail = null;
          if (isset($sails[$i]))
            $sail = $sails[$i][$pair[0]][(string)$div];
          $row->add($s = new XTD(array('class'=>'team1 tr-sail'), $sail));
          if ($sail !== null && $sail->color !== null)
            $s->set('style', sprintf('background:%s;', $sail->color));
        }

        $row->add(new XTD(array('class'=>'vscell'), "vs"));

        // second team
        foreach ($divisions as $div) {
          $sail = null;
          if (isset($sails[$i]))
            $sail = $sails[$i][$pair[1]][(string)$div];
          $row->add($s = new XTD(array('class'=>'team2 tr-sail'), $sail));
          if ($sail !== null && $sail->color !== null)
            $s->set('style', sprintf('background:%s;', $sail->color));
        }

        $row->add(new XTD(array('class'=>'team2'), $team2));
        $row->add(new XTD(array('class'=>'team2'), $burg2));

        // copy finishes?
        $copy = "";
        if ($team1 instanceof Team && $team2 instanceof Team) {
          foreach ($copy_rounds as $round) {
            $races = $this->REGATTA->getRacesForMatchup($team1, $team2, $round, Division::A());
            if (count($races) > 0) {
              $copy = $round;
              break;
            }
          }
        }
        $row->add(new XTD(array(), $copy));
      }

      $form->add($tab);

      // Include all information at once
      $form->add(new XHiddenInput('type', $type));
      $form->add(new XHiddenInput('title', $ROUND->title));
      $form->add(new XHiddenInput('num_teams', $ROUND->num_teams));
      $form->add(new XHiddenInput('num_boats', $ROUND->num_boats));
      $form->add(new XHiddenInput('rotation_frequency', $ROUND->rotation_frequency));
      $form->add(new XHiddenInput('boat', $ROUND->boat->id));
      if ($ROUND->sailoff_for_round !== null)
        $form->add(new XHiddenInput('sailoff_for_round', $ROUND->sailoff_for_round->id));
      foreach ($copy_rounds as $i => $round) {
        $form->add(new XHiddenInput('copy_order[]', $i + 1));
        $form->add(new XHiddenInput('copy_round[]', $round->id));
      }
      for ($i = 0; $i < count($ROUND->race_order); $i++) {
        $pair = $ROUND->getRaceOrderPair($i);
        $form->add(new XHiddenInput('team1[]', $pair[0]));
        $form->add(new XHiddenInput('team2[]', $pair[1]));
      }
      $rotation = $ROUND->rotation;
      $num_divs = count($divisions);
      if ($rotation != null) {
        for ($i = 0; $i < $rotation->count(); $i++) {
          $form->add(new XHiddenInput('sails[]', $rotation->sailAt($i)));
          $form->add(new XHiddenInput('colors[]', $rotation->colorAt($i)));
        }
      }
      foreach ($teams as $i => $team) {
        if ($team instanceof Team) {
          $form->add(new XHiddenInput('team[]', $team->id));
          $form->add(new XHiddenInput('order[]', ($i + 1)));
        }
      }
      $ids = Session::g('round_masters');
      if ($ids !== null) {
        foreach ($ids as $id => $num) {
          $form->add(new XHiddenInput('master_round[]', $id));
          $form->add(new XHiddenInput('num_teams_per_round[]', $num));
        }
      }
      $form->add(new XSubmitP('create', "Create round"));
    }
  }

  private function fillProgress(XP $prog, $max, $step) {
    $steps = array("Round Type",
                   "Settings",
                   "Race Order",
                   "Sail # and Colors",
                   "Teams",
                   "Finishes",
                   "Review");
    for ($i = 0; $i < $max + 1; $i++) {
      $prog->add($span = new XSpan(new XA($this->link('races', array('step' => $i)), $steps[$i])));
      if ($i == $step)
        $span->set('class', 'current');
      else
        $span->set('class', 'completed');
    }
    for ($i = $max + 1; $i < count($steps); $i++)
      $prog->add(new XSpan($steps[$i]));
  }

  private function getBoatOptions() {
    $boats = DB::getBoats();
    $boatOptions = array("" => "");
    foreach ($boats as $boat)
      $boatOptions[$boat->id] = $boat->name;
    return $boatOptions;
  }

  /**
   * Processes new races and edits to existing races
   */
  public function process(Array $args) {
    $ROUND = Session::g('round');
    $type = Session::g('round_type');
    if ($type === null) {
      $type = self::SIMPLE;
      Session::s('round_type', $type);
    }

    $rounds = array();
    $master_rounds = array();
    $sailoff_rounds = array();
    foreach ($this->REGATTA->getRounds() as $round) {
      $rounds[$round->id] = $round;
      if (count($round->getMasters()) == 0)
        $master_rounds[] = $round;
      if ($round->sailoff_for_round === null)
        $sailoff_rounds[] = $round;
    }

    // ------------------------------------------------------------
    // Step 0: Round type
    // ------------------------------------------------------------
    if (isset($args['create-round'])) {
      if ($args['create-round'] == self::SIMPLE) {
        $ROUND = new Round();
        Session::s('round_type', $args['create-round']);
        Session::s('round', $ROUND);
        $this->redirect('races');
        return;
      }
      if ($args['create-round'] == self::SAILOFF) {
        if (count($sailoff_rounds) == 0)
          throw new SoterException("There must be at least one non-sailoff round in order to create a sailoff round.");
        $ROUND = new Round();
        Session::s('round_type', $args['create-round']);
        Session::s('round', $ROUND);
        $this->redirect('races');
        return;
      }
      if ($args['create-round'] == self::COPY) {
        if (count($rounds) == 0)
          throw new SoterException("There must be at least one existing round to copy.");
        $ROUND = new Round();
        Session::s('round_type', $args['create-round']);
        Session::s('round', $ROUND);
        $this->redirect('races');
        return;
      }
      if ($args['create-round'] == self::COMPLETION) {
        if (count($master_rounds) < 2)
          throw new SoterException("Cannot create completion rounds without at least 2 master rounds.");
        $ROUND = new Round();
        Session::s('round_type', $args['create-round']);
        Session::s('round', $ROUND);
        $this->redirect('races');
        return;
      }
      throw new SoterException("Unknown round type provided.");
    }

    $divisions = $this->REGATTA->getDivisions();
    $group_size = 2 * count($divisions);

    // ------------------------------------------------------------
    // Step 1: settings
    // ------------------------------------------------------------
    if (isset($args['create-settings'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");

      if ($type == self::SIMPLE) {
        $this->processStep1($args, $ROUND, $rounds, $divisions);
        Session::d('round_masters');
      }
      elseif ($type == self::COPY) {
        $this->processStep1Copy($args, $ROUND, $rounds);
        Session::d('round_masters');
      }
      elseif ($type == self::SAILOFF) {
        $this->processStep1Sailoff($args, $ROUND, $rounds, $divisions);
        Session::d('round_masters');
      }
      elseif ($type == self::COMPLETION) {
        $elems = $this->processStep1Completion($args, $ROUND, $rounds, $divisions);
        $count = array();
        foreach ($elems as $id => $slave)
          $count[$id] = $slave->num_teams;
        Session::s('round_masters', $count);
      }
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 2: race order
    // ------------------------------------------------------------
    if (isset($args['create-order'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");
      if ($ROUND->num_teams === null)
        throw new SoterException("Order error: number of teams unknown.");

      $masters = array();
      $master_ids = Session::g('round_masters');
      if ($master_ids !== null) {
        foreach ($master_ids as $id => $cnt) {
          $s = new Round_Slave();
          $s->num_teams = $cnt;
          $masters[] = $s;
        }
      }
      
      $this->processStep2($args, $ROUND, $masters);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 3: Sails
    // ------------------------------------------------------------
    if (isset($args['create-sails'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");
      if ($ROUND->num_boats === null)
        throw new SoterException("Order error: number of teams unknown.");

      $this->processSails($args, $ROUND, $divisions);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 4: Teams
    // ------------------------------------------------------------
    if (isset($args['create-teams'])) {
      if ($ROUND === null)
        throw new SoterException("Order error: no round to work with.");
      if ($ROUND->num_teams === null)
        throw new SoterException("Order error: number of teams unknown.");
      if ($ROUND->race_order === null)
        throw new SoterException("Order error: race order not known.");
      if ($ROUND->rotation === null)
        throw new SoterException("Order error: no rotation found.");

      $masters = array();
      $master_ids = Session::g('round_masters');
      if ($master_ids !== null) {
        foreach ($master_ids as $id => $cnt) {
          $s = new Round_Slave();
          $s->master = DB::get(DB::$ROUND, $id);
          $s->num_teams = $cnt;
          $masters[] = $s;
        }
      }

      $seeds = $this->processSeeds($args, $ROUND, $masters);
      if ($type == self::SAILOFF && count($seeds) != $ROUND->num_teams)
        throw new SoterException("All seeds must be created for sailoff rounds.");

      $list = array();
      foreach ($seeds as $seed)
        $list[$seed->seed] = $seed->team->id;
      Session::s('round_teams', $list);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Step 5: Finishes
    // ------------------------------------------------------------
    if (isset($args['create-finishes'])) {
      $list = $this->processCopyFinishes($args, $rounds);
      Session::s('round_finishes', array_keys($list));
      $this->redirect('races');
    }

    // ------------------------------------------------------------
    // Create round
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      $type = DB::$V->reqValue($args, 'type', array(self::SIMPLE, self::COPY, self::COMPLETION, self::SAILOFF), "Invalid type provided.");

      $round = new Round();
      $round->relative_order = count($rounds) + 1;
      $masters = array();
      if ($type == self::COMPLETION) {
        $masters = $this->processStep1Completion($args, $round, $rounds, $divisions);
        $this->processStep2($args, $round, $masters);
        $this->processSails($args, $round, $divisions);
        $seeds = $this->processSeeds($args, $round, $masters);
        $message = "Created new empty completion round.";
      }
      elseif ($type == self::SAILOFF) {
        $this->processStep1Sailoff($args, $round, $rounds, $divisions);
        $this->processStep2($args, $round);
        $this->processSails($args, $round, $divisions);
        $round->relative_order = $round->sailoff_for_round->relative_order + 1;
        if ($round->sailoff_for_round->round_group !== null) {
          $group_rounds = $round->sailoff_for_round->round_group->getRounds();
          $round->relative_order = $group_rounds[0]->relative_order + count($group_rounds);
        }
        $seeds = $this->processSeeds($args, $round);
        if (count($seeds) != $ROUND->num_teams)
          throw new SoterException("All seeds must be created for sailoff rounds.");
        $message = "Created new sailoff round.";
      }
      else {
        $this->processStep1($args, $round, $rounds, $divisions);
        $this->processStep2($args, $round);
        $this->processSails($args, $round, $divisions);
        $seeds = $this->processSeeds($args, $round);
        $message = "Created new empty round.";
      }
      $copy_finishes = $this->processCopyFinishes($args, $rounds);

      $teams = array();
      for ($i = 1; $i <= $round->num_teams; $i++) {
        if (isset($seeds[$i]))
          $teams[] = $seeds[$i]->team;
        else
          $teams[] = null;
      }

      $round->regatta = $this->REGATTA;
      DB::set($round);
      $round->setSeeds($seeds);
      foreach ($masters as $master)
        $round->addMaster($master->master, $master->num_teams);

      // Actually create the races
      $racenum = $this->calculateNextRaceNumber($round);

      $sails = array();
      if ($round->rotation !== null)
        $sails = $round->rotation->assignSails($round, $teams, $divisions, $round->rotation_frequency);
      $new_races = array();
      $new_sails = array();
      $new_finishes = array();
      for ($i = 0; $i < count($round->race_order); $i++) {
        $racenum++;
        $pair = $round->getRaceOrderPair($i);
        $t1 = $teams[$pair[0] - 1];
        $t2 = $teams[$pair[1] - 1];

        foreach ($divisions as $div) {
          $race = new Race();
          $race->regatta = $this->REGATTA;
          $race->division = $div;
          $race->number = $racenum;
          $race->boat = $round->boat;
          $race->round = $round;
          $race->tr_team1 = $t1;
          $race->tr_team2 = $t2;
          $new_races[] = $race;

          if ($t1 !== null && isset($sails[$i])) {
            $templ = $sails[$i][$pair[0]][(string)$div];
            $sail = new Sail();
            $sail->sail = $templ->sail;
            $sail->color = $templ->color;
            $sail->race = $race;
            $sail->team = $t1;
            $new_sails[] = $sail;
          }

          if ($t2 !== null && isset($sails[$i])) {
            $templ = $sails[$i][$pair[1]][(string)$div];
            $sail = new Sail();
            $sail->sail = $templ->sail;
            $sail->color = $templ->color;
            $sail->race = $race;
            $sail->team = $t2;
            $new_sails[] = $sail;
          }

          // Any finishes
          if ($t1 !== null && $t2 !== null) {
            foreach ($copy_finishes as $copy_round) {
              $races = $this->REGATTA->getRacesForMatchup($t1, $t2, $copy_round, $div);
              if (count($races) > 0) {
                $r = $races[0];
                foreach ($this->REGATTA->getFinishes($r) as $finish) {
                  $copy = clone($finish);
                  $copy->id = null;
                  $copy->race = $race;
                  foreach ($finish->getModifiers() as $mod)
                    $copy->addModifier($mod);
                  $new_finishes[] = $copy;
                }
                break;
              }
            }
          }
        }
      }

      // Insert all at once
      foreach ($new_races as $race)
        DB::set($race, false);
      DB::insertAll($new_sails);
      if (count($new_finishes) > 0) {
        $this->REGATTA->commitFinishes($new_finishes);
        $this->REGATTA->doScore();
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }

      // Displaced rounds?
      if ($round->sailoff_for_round !== null) {
        $other_rounds = array_values($rounds);
        $round_to_check = $round->sailoff_for_round;
        if ($round->sailoff_for_round->round_group !== null) {
          $group_rounds = $round->sailoff_for_round->round_group->getRounds();
          $round_to_check = $group_rounds[count($group_rounds) - 1];
        }
        foreach ($other_rounds as $i => $other_round) {
          if ($other_round->id == $round_to_check->id)
            break;
        }
        $i++;
        for (; $i < count($other_rounds); $i++) {
          $other_rounds[$i]->relative_order = ($i + 2);
          foreach ($this->REGATTA->getRacesInRound($other_rounds[$i], Division::A()) as $race) {
            $racenum++;

            for ($j = 1; $j < count($divisions); $j++) {
              $r = $this->REGATTA->getRace($divisions[$j], $race->number);
              $r->number = $racenum;
              DB::set($r, true);
            }
            $race->number = $racenum;
            DB::set($race, true);
          }
          DB::set($other_rounds[$i], true);
        }
      }

      $this->REGATTA->setData(); // new races
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array($message, " ", new XA($this->link('races'), "Add another round"), ".")));
      Session::d('round');
      Session::d('round_type');
      Session::d('round_teams');
      Session::d('round_masters');
      Session::d('round_finishes');
    }

    return array();
  }

  /**
   * Returns elements created in associative array
   *
   */
  private function processStep1(Array $args, Round $round, Array $rounds, Array $divisions) {
    $title = DB::$V->reqString($args, 'title', 1, 61, "Invalid or missing name.");
    foreach ($rounds as $r) {
      if ($r->title == $title)
        throw new SoterException("Duplicate round title provided.");
    }

    $clean_races = false;
    $clean_teams = false;
    $clean_rotation = false;
    $group_size = 2 * count($divisions);

    $num_teams = DB::$V->reqInt($args, 'num_teams', 2, count($this->REGATTA->getTeams()) + 1, "Invalid number of teams provided.");
    if ($num_teams != $round->num_teams) {
      $clean_races = true;
      $clean_teams = true;
    }

    $freq = DB::$V->reqKey($args, 'rotation_frequency', Race_Order::getFrequencyTypes(), "Invalid rotation frequency requested.");
    if ($freq != $round->rotation_frequency) {
      $clean_races = true;
      $clean_rotation = true;
    }

    $num_boats = null;
    if ($round->rotation_frequency == Race_Order::FREQUENCY_NONE) {
      $num_boats = count($divisions) * $round->num_teams;
    }
    else {
      $num_boats = DB::$V->reqInt($args, 'num_boats', $group_size, 101, "Invalid number of boats provided.");
      if ($num_boats % $group_size != 0)
        throw new SoterException(sprintf("Number of boats must be divisible by %d.", $group_size));
      if ($num_boats != $round->num_boats) {
        $clean_races = true;
        $clean_rotation = true;
      }
    }

    $round->boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "No boat provided.");

    $round->title = $title;
    $round->num_teams = $num_teams;
    $round->num_boats = $num_boats;
    $round->rotation_frequency = $freq;

    if ($clean_teams)
      Session::d('round_teams');
    if ($clean_races)
      $round->race_order = null;
    if ($clean_rotation)
      $round->rotation = null;

    return array();
  }

  private function processStep1Sailoff(Array $args, Round $round, Array $rounds, Array $divisions) {
    $round->title = DB::$V->reqString($args, 'title', 1, 61, "Invalid or missing name.");
    foreach ($rounds as $r) {
      if ($r->title == $round->title)
        throw new SoterException("Duplicate round title provided.");
    }

    $group_size = 2 * count($divisions);

    $templ = DB::$V->reqID($args, 'sailoff_for_round', DB::$ROUND, "Invalid template round provided.");
    if ($templ->regatta->id != $this->REGATTA->id)
      throw new SoterException("Invalid template round.");
    if ($templ->sailoff_for_round !== null)
      throw new SoterException("Sailoff rounds are not eligible for further sailoffs.");
    if (count($templ->getSeeds()) < 2)
      throw new SoterException("There aren't enough known teams for the chosen round to perform sailoff.");

    // Copy values over
    $freq = DB::$V->incKey($args, 'rotation_frequency', Race_Order::getFrequencyTypes(), $templ->rotation_frequency);
    $num_teams = DB::$V->reqInt($args, 'num_teams', 2, $templ->num_teams + 1, "Invalid number of teams provided.");

    if ($freq == Race_Order::FREQUENCY_NONE) {
      $num_boats = count($divisions) * $num_teams;
    }
    else {
      $num_boats = DB::$V->incInt($args, 'num_boats', $group_size, 101, $templ->num_boats);
      if ($num_boats % $group_size != 0)
        throw new SoterException(sprintf("Number of boats must be divisible by %d.", $group_size));
    }

    // Assign the values
    if ($round->num_teams != $num_teams) {
      $round->num_teams = $num_teams;
      $round->race_order = null;
      Session::d('round_teams');
    }
    if ($round->num_boats != $num_boats) {
      $round->num_boats = $num_boats;
      $round->race_order = null;
      $round->rotation = null;
    }
    if ($round->rotation_frequency != $freq) {
      $round->rotation_frequency = $templ->rotation_frequency;
      $round->race_order = null;
      $round->rotation = null;
    }
    $round->boat = DB::$V->incID($args, 'boat', DB::$BOAT, $templ->boat);
    $round->sailoff_for_round = $templ;
    return array();
  }

  private function processStep1Copy(Array $args, Round $round, Array $rounds) {
    $round->title = DB::$V->reqString($args, 'title', 1, 61, "Invalid or missing name.");
    foreach ($rounds as $r) {
      if ($r->title == $round->title)
        throw new SoterException("Duplicate round title provided.");
    }

    $templ = DB::$V->reqID($args, 'template', DB::$ROUND, "Invalid template round provided.");
    if ($templ->regatta->id != $this->REGATTA->id)
      throw new SoterException("Invalid template round.");

    // Copy values over
    $round->num_teams = $templ->num_teams;
    $round->num_boats = $templ->num_boats;
    $round->rotation_frequency = $templ->rotation_frequency;
    $round->race_order = $templ->race_order;
    $round->boat = $templ->boat;

    if (DB::$V->incInt($args, 'swap', 1, 2, 0) > 0) {
      $pairings = array();
      for ($i = 0; $i < count($round->race_order); $i++) {
        $pair = $round->getRaceOrderPair($i);
        $pairings[] = sprintf('%s-%s', $pair[1], $pair[0]);
      }
      $round->race_order = $pairings;
    }

    // Rotation
    $round->rotation = $templ->rotation;
    Session::d('round_teams');
    Session::s('round_template', $templ->id);
  }

  private function processStep1Completion(Array $args, Round $round, Array $rounds, Array $divisions) {
    $round->title = DB::$V->reqString($args, 'title', 1, 61, "Invalid or missing name.");
    foreach ($rounds as $r) {
      if ($r->title == $round->title)
        throw new SoterException("Duplicate round title provided.");
    }

    $group_size = 2 * count($divisions);

    // Teams
    $round_ids = DB::$V->reqList($args, 'master_round', null, "No list of master rounds provided.");
    if (count($round_ids) < 2)
      throw new SoterException("There must be at least 2 existing rounds per completion round.");
    $num_teams = DB::$V->reqList($args, 'num_teams_per_round', count($round_ids), "Missing number of teams per round.");

    $master_rounds = array();
    $team_count = 0;
    foreach ($round_ids as $i => $id) {
      if ($num_teams[$i] == 0)
        continue;

      $r = DB::get(DB::$ROUND, $id);
      if ($r === null || isset($master_rounds[$r->id]) || $r->regatta != $this->REGATTA || count($r->getMasters()) > 0)
        throw new SoterException("Invalid master round provided.");

      $slave = new Round_Slave();
      $slave->master = $r;
      $slave->num_teams = $num_teams[$i];
      $master_rounds[$r->id] = $slave;

      $team_count += $num_teams[$i];
    }

    if (count($master_rounds) < 2)
      throw new SoterException("There must be at least 2 template rounds per completion round.");

    if ($team_count != $round->num_teams) {
      $round->num_teams = $team_count;
    }

    $freq = DB::$V->reqKey($args, 'rotation_frequency', Race_Order::getFrequencyTypes(), "Invalid rotation frequency requested.");
    if ($freq != $round->rotation_frequency) {
      $round->rotation_frequency = $freq;
      $clean_races = true;
      $clean_rotation = true;
    }

    if ($round->rotation_frequency == Race_Order::FREQUENCY_NONE) {
      $round->num_boats = count($divisions) * $round->num_teams;
    }
    else {
      $num_boats = DB::$V->reqInt($args, 'num_boats', $group_size, 101, "Invalid number of boats provided.");
      if ($num_boats % $group_size != 0)
        throw new SoterException(sprintf("Number of boats must be divisible by %d.", $group_size));
      if ($num_boats != $round->num_boats) {
        $round->num_boats = $num_boats;
      }
    }

    Session::d('round_teams');
    $round->race_order = null;
    $round->rotation = null;

    $round->boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid or missing boat.");
    return $master_rounds;
  }

  private function processStep2(Array $args, Round $round, Array $masters = null) {
    // All handshakes must be accounted for
    $handshakes = array();
    for ($i = 1; $i <= $round->num_teams; $i++) {
      for ($j = $i + 1; $j <= $round->num_teams; $j++) {
        $shake = sprintf("%d-%d", $i, $j);
        $handshakes[$shake] = $shake;
      }
    }
    if ($masters !== null) {
      $first = 1;
      foreach ($masters as $slave) {
        $last = $first + $slave->num_teams;
        for ($i = $first; $i < $last; $i++) {
          for ($j = $i + 1; $j < $last; $j++) {
            unset($handshakes[sprintf("%d-%d", $i, $j)]);
          }
        }
        $first = $last;
      }
    }

    $num_races = count($handshakes);
    $map = DB::$V->reqMap($args, array('team1', 'team2'), $num_races, "Invalid team order.");
    $swp = DB::$V->incList($args, 'swap');

    $pairings = array();
    foreach ($map['team1'] as $i => $team1) {
      $team1 = DB::$V->reqInt($map['team1'], $i, 1, $round->num_teams + 1, "Invalid first team index provided.");
      $team2 = DB::$V->reqInt($map['team2'], $i, 1, $round->num_teams + 1, "Invalid partner provided.");
      if (in_array($i, $swp)) {
        $team3 = $team1;
        $team1 = $team2;
        $team2 = $team3;
      }
      if ($team1 == $team2)
        throw new SoterException("Teams cannot sail against themselves.");
      $shake = sprintf("%d-%d", $team1, $team2);
      $pair = $shake;
      if ($team2 < $team1)
        $shake = sprintf("%d-%d", $team2, $team1);
      if (!isset($handshakes[$shake]))
        throw new SoterException("Invalid team pairing provided.");
      unset($handshakes[$shake]);
      $pairings[] = $pair;
    }

    if (count($pairings) < $num_races)
      throw new SoterException("Not all pairings have been accounted for.");

    $round->race_order = $pairings;
    return array();
  }

  private function processCopyFinishes(Array $args, Array $rounds) {
    $other_rounds = array();
    $map = array('copy_round'=>array(), 'copy_order'=>array());
    $map = DB::$V->incMap($args, array('copy_round', 'copy_order'), null, $map);
    array_multisort($map['copy_order'], SORT_NUMERIC, $map['copy_round']);
    foreach ($map['copy_round'] as $i => $id) {
      if ($map['copy_order'][$i] == 0)
        continue;

      if (!isset($rounds[$id]))
        throw new SoterException(sprintf("Invalid round from which to copy finishes: %s.", $id));
      $other_rounds[$id] = $rounds[$id];
    }
    return $other_rounds;
  }

  private function calculateNextRaceNumber(Round $round) {
    if ($round->sailoff_for_round !== null) {
      if ($round->sailoff_for_round->round_group !== null)
        $races = $this->REGATTA->getRacesInRoundGroup($round->sailoff_for_round->round_group, Division::A());
      else
        $races = $this->REGATTA->getRacesInRound($round->sailoff_for_round, Division::A());
      return $races[count($races) - 1]->number;
    }
    $race_num = 0;
    foreach ($this->REGATTA->getRounds() as $r) {
      if ($r->id == $round->id)
        break;
      $race_num += count($this->REGATTA->getRacesInRound($r, Division::A()));
    }
    return $race_num;
  }
}
?>