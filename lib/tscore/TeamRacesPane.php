<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

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
class TeamRacesPane extends AbstractPane {

  const SIMPLE = 'simple';
  const COPY = 'copy';
  const COMPLETION = 'completion';

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
    foreach ($rounds as $round) {
      if (count($round->getMasters()) == 0 && $round->num_teams == count($round->getSeeds()))
        $master_rounds[] = $round;
    }

    $this->PAGE->head->add(new LinkCSS('/inc/css/round.css'));
    $ROUND = Session::g('round');
    $type = Session::g('round_type');

    // Calculate step
    $MAX_STEP = 0;
    if ($ROUND === null && count($rounds) == 0) {
      $ROUND = new Round();
      Session::s('round', $ROUND);
    }

    $team_ids = Session::g('round_teams');
    if ($ROUND !== null) {
      $MAX_STEP = 1;
      if ($ROUND->num_teams !== null) {
        $MAX_STEP = 2;
        if ($ROUND->race_order !== null) {
          $MAX_STEP = 3;
          if ($ROUND->rotation !== null) {
            $MAX_STEP = 4;
            if ($team_ids !== null)
              $MAX_STEP = 5;
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
      if (count($rounds) > 0)
        $opts[self::COPY] = "Using existing round as template";
      if (count($master_rounds) > 1)
        $opts[self::COMPLETION] = "Completion round";
      $f->add(new FItem("Add round:", XSelect::fromArray('create-round', $opts)));
      $f->add(new XSubmitP('go', "Next →"));

      $this->PAGE->addContent($p = new XPort("Explanation"));
      $p->add($ul = new XUl(array(),
                            array(new XLi(array(new XStrong("Standard round robin"), " refers to a regular round robin whose races do not depend on any other round. This is the default choice.")))));
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
        if ($ROUND->num_teams === null)
          $ROUND->num_teams = count($this->REGATTA->getTeams());
        if ($ROUND->num_boats === null)
          $ROUND->num_boats = $group_size * 3;
        $boat = null;
        if ($ROUND->boat !== null)
          $boat = $ROUND->boat->id;

        $this->PAGE->addContent($p = new XPort("New round settings"));
        $p->add($form = $this->createForm());
        $form->add(new FItem("Round name:", new XTextInput('title', $ROUND->title)));
        $form->add(new FItem("Number of teams:", new XTextInput('num_teams', $ROUND->num_teams)));

        $form->add(new FItem("Number of boats:", new XInput('number', 'num_boats', $ROUND->num_boats, array('min'=>$group_size, 'step'=>$group_size))));
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
                                 new XInput('number', 'num_teams_per_round[]', '', array('min'=>0, 'max'=>($ROUND->num_teams - 1), 'id'=>$id)),
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
      $rotation = $ROUND->rotation;
      if ($rotation === null) {
        // Find another rotation for this number of boats
        for ($i = count($rounds) - 1; $i >= 0; $i--) {
          $other = $rounds[$i];
          if ($other->num_boats == $ROUND->num_boats && $other->rotation !== null) {
            $rotation = $other->rotation;
            break;
          }
        }
      }

      $form->add(new XP(array(), "Assign the sail numbers using the table below. If applicable, choose the color that goes with the sail. This color will be displayed in the \"Rotations\" dialog."));
      if ($ROUND->rotation_frequency == Race_Order::FREQUENCY_FREQUENT ||
          $ROUND->rotation_frequency == Race_Order::FREQUENCY_INFREQUENT) {

        // Prefill
        if ($rotation === null) {
          $rotation = new TeamRotation();
          $s = array();
          $c = array();
          for ($i = 0; $i < $flight; $i++) {
            $s[] = ($i + 1);
            $c[] = "";
          }
          $rotation->sails = $s;
          $rotation->colors = $c;
        }

        $form->add(new XP(array(), array("The flight size for this rotation is ", new XStrong($flight / $group_size), " races.")));

        $form->add(new XTable(array('class'=>'tr-rotation-sails'),
                              array(new XTHead(array(),
                                               array(new XTR(array(),
                                                             array(new XTH(array(), "#"),
                                                                   new XTH(array(), "Team A"),
                                                                   new XTH(array(), "Team B"))))),
                                    $bod = new XTBody())));

        $sailIndex = 0;
        for ($race_num = 0; $race_num < $flight / $group_size; $race_num++) {
          $bod->add($row = new XTR(array()));
          $row->add(new XTH(array(), sprintf("Race %d", ($race_num + 1))));

          // Team A, then Team B
          for ($teamIndex = 0; $teamIndex < 2; $teamIndex++) {
            $row->add(new XTD(array(), new XTable(array('class'=>'sail-list'), array($tab = new XTBody()))));
            for ($i = 0; $i < $num_divs; $i++) {
              $sail = $rotation->sails[$sailIndex];
              $color = $rotation->colors[$sailIndex];

              $tab->add(new XTR(array(),
                                array(new XTD(array(), new XTextInput('sails[]', $sail, array('size'=>5, 'tabindex'=>($sailIndex + 1)))),
                                      new XTD(array('title'=>"Optional"), $sel = new XSelect('colors[]')))));
              $sel->set('class', 'color-chooser');
              $sel->set('tabindex', ($sailIndex + 1 + $flight));
              $sel->add(new XOption("", array(), "[None]"));
              foreach ($COLORS as $code => $title) {
                $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
                $sel->add($opt = new XOption($code, $attrs, $title));
                if ($code == $color)
                  $opt->set('selected', 'selected');
              }

              $sailIndex++;
            }
          }
        }
      }
      else {
        // Prefill
        if ($rotation === null) {
          $rotation = new TeamRotation();
          $s = array();
          $c = array();
          for ($i = 0; $i < $flight; $i++) {
            $s[] = ($i + 1);
            $c[] = "";
          }
          $rotation->sails = $s;
          $rotation->colors = $c;
        }

        // No rotation frequency: display an entry PER team
        $form->add($tab = new XQuickTable(array('class'=>'tr-rotation-sails'),
                                          array("Team #", "Sail #", "Color")));

        for ($i = 0; $i < $rotation->count(); $i++) {
          $row = array();
          if ($i % $num_divs == 0)
            $row[] = new XTH(array('rowspan'=>$num_divs), sprintf("Team %d", floor($i / $num_divs)));

          $sail = $rotation->sails[$i];
          $color = $rotation->colors[$i];

          $sel = new XSelect('colors[]', array('class'=>'color-chooser', 'tabindex'=>($i + 1 + $rotation->count())));
          $row[] = new XTextInput('sails[]', $sail, array('size'=>5, 'tabindex'=>($i + 1)));
          $row[] = $sel;

          $sel->add(new XOption("", array(), "[None]"));
          foreach ($COLORS as $code => $title) {
            $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
            $sel->add($opt = new XOption($code, $attrs, $title));

            if ($code == $color)
              $opt->set('selected', 'selected');
          }

          $tab->addRow($row);
        }

      }
      $form->add(new XSubmitP('create-sails', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 4: Teams?
    // ------------------------------------------------------------
    if ($STEP == 4) {
      $ids = Session::g('round_teams');
      if ($ids === null)
        $ids = array();

      $this->PAGE->addContent($p = new XPort("Teams (optional)"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(), "Specify and seed the teams that will participate in this round. You may specify the teams at a later time."));

      $master_ids = Session::g('round_masters');
      if ($master_ids === null) {
        // Simple round
        $form->add(new XP(array(), sprintf("Place numbers 1-%d next to the teams to be included in this round.", $ROUND->num_teams)));

        $form->add($ul = new XUl(array('id'=>'teams-list')));
        foreach ($this->REGATTA->getTeams() as $team) {
          $id = 'team-'.$team->id;
          $order = array_search($team->id, $ids);
          $order = ($order === false) ? "" : $order + 1;
          $ul->add(new XLi(array(new XHiddenInput('team[]', $team->id),
                                 new XTextInput('order[]', $order, array('id'=>$id)),
                                 new XLabel($id, $team,
                                            array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
        }
      }
      else {
        // Completion round: choose team from other round
        $first = 1;
        foreach ($master_ids as $id => $cnt) {
          $last = $first + $cnt - 1;
          $round = DB::get(DB::$ROUND, $id);
          if ($round !== null) {
            $form->add(new XH4($round));
            $form->add(new XP(array(), sprintf("Place numbers %d-%d next to the teams to be included from this round.", $first, $last)));

            $form->add($ul = new XUl(array('class'=>'teams-list')));
            foreach ($round->getSeeds() as $seed) {
              $id = 'team-' . $seed->team->id;
              $order = array_search($seed->team->id, $ids);
              $order = ($order === false) ? "" : $order + 1;
              $ul->add(new XLi(array(new XHiddenInput('team[]', $seed->team->id),
                                     new XTextInput('order[]', $order, array('id'=>$id)),
                                     new XLabel($id, $seed->team,
                                                array('onclick'=>sprintf('addTeamToRound("%s");', $id))))));
            }
          }
          $first = $last + 1;
        }
      }
      $form->add(new XSubmitP('create-teams', "Next →"));
      return;
    }

    // ------------------------------------------------------------
    // Step 5: Review
    // ------------------------------------------------------------
    if ($STEP == 5) {
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

      $sails = $ROUND->rotation->assignSails($ROUND, $teams, $divisions, $ROUND->rotation_frequency);
      $tab = new XTable(array('class'=>'tr-rotation-table'),
                        array(new XTHead(array(),
                                         array(new XTR(array(),
                                                       array(new XTH(array(), "#"),
                                                             new XTH(array('colspan'=>2), "Team 1"),
                                                             new XTH(array('colspan'=>count($divisions)), "Sails"),
                                                             new XTH(array(), ""),
                                                             new XTH(array('colspan'=>count($divisions)), "Sails"),
                                                             new XTH(array('colspan'=>2), "Team 2"))))),
                              $body = new XTBody()));

      $flight = $ROUND->num_boats / $group_size;
      for ($i = 0; $i < count($ROUND->race_order); $i++) {
        // spacer
        if ($flight > 0 && $i % $flight == 0) {
          $body->add(new XTR(array('class'=>'tr-flight'), array(new XTD(array('colspan' => 8 + 2 * count($divisions)), sprintf("Flight %d", ($i / $flight + 1))))));
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
      }

      $form->add($tab);

      // Include all information at once
      $form->add(new XHiddenInput('type', $type));
      $form->add(new XHiddenInput('title', $ROUND->title));
      $form->add(new XHiddenInput('num_teams', $ROUND->num_teams));
      $form->add(new XHiddenInput('num_boats', $ROUND->num_boats));
      $form->add(new XHiddenInput('rotation_frequency', $ROUND->rotation_frequency));
      $form->add(new XHiddenInput('boat', $ROUND->boat->id));
      for ($i = 0; $i < count($ROUND->race_order); $i++) {
        $pair = $ROUND->getRaceOrderPair($i);
        $form->add(new XHiddenInput('team1[]', $pair[0]));
        $form->add(new XHiddenInput('team2[]', $pair[1]));
      }
      $rotation = $ROUND->rotation;
      $num_divs = count($divisions);
      if ($rotation != null) {
        foreach ($rotation->sails as $i => $sail) {
          $form->add(new XHiddenInput('sails[]', $sail));
          $form->add(new XHiddenInput('colors[]', $rotation->colors[$i]));
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
    $boatOptions = array();
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

    $rounds = array();
    $master_rounds = array();
    foreach ($this->REGATTA->getRounds() as $round) {
      $rounds[$round->id] = $round;
      if (count($round->getMasters()) == 0)
        $master_rounds[] = $round;
    }

    // ------------------------------------------------------------
    // Step 0: Round type
    // ------------------------------------------------------------
    if (isset($args['create-round'])) {
      if ($args['create-round'] == self::SIMPLE || $args['create-round'] == self::COPY) {
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

      $this->processStep3($args, $ROUND, $divisions);
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

      $seeds = $this->processStep4($args, $ROUND, $masters);
      $list = array();
      foreach ($seeds as $seed)
        $list[$seed->seed] = $seed->team->id;
      Session::s('round_teams', $list);
      $this->redirect('races');
      return;
    }

    // ------------------------------------------------------------
    // Create round
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      $type = DB::$V->reqValue($args, 'type', array(self::SIMPLE, self::COPY, self::COMPLETION), "Invalid type provided.");

      $round = new Round();
      $round->relative_order = count($rounds) + 1;
      $masters = array();
      if ($type == self::COMPLETION) {
        $masters = $this->processStep1Completion($args, $round, $rounds, $divisions);
        $this->processStep2($args, $round, $masters);
        $this->processStep3($args, $round, $divisions);
        $seeds = $this->processStep4($args, $round, $masters);
        $message = "Created new empty completion round.";
      }
      else {
        $this->processStep1($args, $round, $rounds, $divisions);
        $this->processStep2($args, $round);
        $this->processStep3($args, $round, $divisions);
        $seeds = $this->processStep4($args, $round);
        $message = "Created new empty round.";
      }

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
        }
      }

      // Insert all at once
      foreach ($new_races as $race)
        DB::set($race, false);
      DB::insertAll($new_sails);
      $message = "Created new round.";

      $this->REGATTA->setData(); // new races
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array($message, " ", new XA($this->link('races'), "Add another round"), ".")));
      Session::d('round');
      Session::d('round_teams');
      Session::d('round_masters');
    }

    return array();
  }

  /**
   * Returns elements created in associative array
   *
   */
  private function processStep1(Array $args, Round $round, Array $rounds, Array $divisions) {
    $round->title = DB::$V->reqString($args, 'title', 1, 61, "Invalid or missing name.");
    foreach ($rounds as $r) {
      if ($r->title == $round->title)
        throw new SoterException("Duplicate round title provided.");
    }

    $clean_races = false;
    $clean_teams = false;
    $clean_rotation = false;
    $group_size = 2 * count($divisions);

    $num_teams = DB::$V->reqInt($args, 'num_teams', 2, count($this->REGATTA->getTeams()) + 1, "Invalid number of teams provided.");
    if ($num_teams != $round->num_teams) {
      $round->num_teams = $num_teams;
      $clean_races = true;
      $clean_teams = true;
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
        $clean_races = true;
        $clean_rotation = true;
      }
    }

    if ($clean_teams)
      Session::d('round_teams');
    if ($clean_races)
      $round->race_order = null;
    if ($clean_rotation)
      $round->rotation = null;

    $round->boat = DB::$V->reqID($args, 'boat', DB::$BOAT, "Invalid or missing boat.");
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

  private function processStep3(Array $args, Round $round, Array $divisions) {
    $group_size = 2 * count($divisions);

    $s = array();
    $c = array();

    if ($round->rotation_frequency == Race_Order::FREQUENCY_FREQUENT ||
        $round->rotation_frequency == Race_Order::FREQUENCY_INFREQUENT) {
      $sails = DB::$V->reqList($args, 'sails', $round->num_boats, "Missing list of sails.");
      $c = DB::$V->incList($args, 'colors', $round->num_boats);

      // make sure all sails are present and distinct
      foreach ($sails as $sail) {
        $sail = trim($sail);
        if ($sail == "")
          throw new SoterException("Empty sail provided.");
        if (in_array($sail, $s))
          throw new SoterException("Duplicate sail \"$sail\" provided.");
        $s[] = $sail;
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
    }
    $rot = new TeamRotation();
    $rot->sails = $s;
    $rot->colors = $c;

    $round->rotation = $rot;
    return array();
  }

  private function processStep4(Array $args, Round $round, Array $masters = null) {
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
      foreach ($this->REGATTA->getTeams() as $team)
        $all_teams[$team->id] = $team;
      
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

  private function calculateNextRaceNumber(Round $round) {
    $race_num = 0;
    foreach ($this->REGATTA->getRounds() as $r) {
      if ($r->id == $round->id)
        break;
      if ($r->race_order != null)
        $race_num += count($r->race_order);
      else
        $race_num += count($this->REGATTA->getRacesInRound($r, Division::A(), false));
    }
    return $race_num;
  }
}
?>