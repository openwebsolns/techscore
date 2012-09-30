<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */
require_once('conf.php');

/**
 * Pane to create the rotations
 *
 * 2011-02-18: Only one BYE team is allowed per rotation
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class SailsPane extends AbstractPane {

  // Options for rotation types
  private $ROTS = array("STD"=>"Standard: +1 each set",
                        "SWP"=>"Swap:  Odds up, evens down",
                        "OFF"=>"Offset by (+/-) amount from existing division",
                        "NOR"=>"No rotation");
  private $STYLES = array("navy"=>"Navy: rotate on division change",
                          "fran"=>"Franny: automatic offset",
                          "copy"=>"All divisions similar");
  private $SORT   = array("none"=>"Order as shown",
                          "num" =>"Numerically",
                          "alph"=>"Alpha-numerically");

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Setup rotations", $user, $reg);
  }

  /**
   * Presents options when there are combined divisions or only one
   * division. This function takes care of only the second step in
   * creating rotations below in fillHTML
   *
   * @param Const $chosen_rot one of the $ROTS type
   * @param Array $chosen_div the divisions to affect
   * @see fillHTML
   */
  private function fillCombined($chosen_rot, $chosen_div) {
    $chosen_rot_desc = explode(":", $this->ROTS[$chosen_rot]);
    $message = sprintf("2. %s", $chosen_rot_desc[0]);
    if (count($chosen_div) > 1)
      $message = sprintf("2. %s for all divisions", $chosen_rot_desc[0]);
    $this->PAGE->addContent($p = new XPort($message));
    $p->add($form = $this->createForm());
    $form->add(new XHiddenInput("rottype", $chosen_rot));

    $teams = $this->REGATTA->getTeams();
    $divisions = $this->REGATTA->getDivisions();

    // Races
    $range_races = sprintf('1-%d', count($this->REGATTA->getRaces(Division::A())));
    $form->add(new FItem(sprintf("Races (%s):", $range_races),
                         new XTextInput("races", $range_races, array("id"=>"frace"))));

    if ($chosen_rot == "OFF") {
      $form->add(new XHiddenInput('from_div', Division::A()));
      $form->add(new FItem("Amount to offset (±):", new XTextInput('offset', (int)(count($teams) / 2))));

      $form->add(new XP(array('class'=>'p-submit'),
                        array(new XA($this->link('rotations'), "← Start over"), " ",
                              new XSubmitInput("offsetrot", "Offset"))));
      return;
    }

    // ------------------------------------------------------------
    // Else
    // ------------------------------------------------------------
    // Set size
    if ($chosen_rot != "NOR")
      $form->add($fitem = new FItem("Races in set:", $f_text = new XTextInput("repeat", 2, array('size'=>2, 'id'=>'repeat'))));

    // Teams table
    $bye_team = null;
    if ($chosen_rot == "SWP" && count($divisions) * count($teams) % 2 > 0) {
      $bye_team = new ByeTeam();
      $form->add(new XP(array(), "Swap divisions require an even number of total teams at the time of creation. If you choose swap division, TechScore will add a \"BYE Team\" as needed to make the total number of teams even. This will produce an unused boat in every race."));
    }
    $form->add(new FItem("Enter sail numbers in first race:", $tab = new XTable(array('class'=>'narrow'))));

    $i = 1;
    if (count($divisions) == 1) {
      foreach ($teams as $team) {
        $name = sprintf("%s,%s", $divisions[0], $team->id);
        $tab->add(new XTR(array(),
                          array(new XTH(array(), $team),
                                new XTH(array(), new XTextInput($name, $i++,
                                                                array("size"=>"2",
                                                                      "maxlength"=>"8",
                                                                      "class"=>"small"))))));
      }
      if ($bye_team !== null)
        $tab->add(new XTR(array(),
                          array(new XTH(array(), $bye_team),
                                new XTD(array(), new XTextInput($bye_team->id, $i++,
                                                                array("size"=>"2",
                                                                      "maxlength"=>"8",
                                                                      "class"=>"small"))))));
    }
    else {
      $num_teams = count($teams);
      $tab->add(new XTHead(array(), array($row = new XTR(array(), array(new XTH(array(), "Team"))))));
      foreach ($divisions as $div)
        $row->add(new XTH(array(), "Div. $div"));
      $tab->add($bod = new XTBody());
      foreach ($teams as $team) {
        $bod->add($row = new XTR(array(), array(new XTD(array(), $team))));
        $off = 0;
        foreach ($divisions as $div) {
          $num = $i + $off * $num_teams;
          $name = sprintf("%s,%s", $div, $team->id);
          $row->add(new XTD(array(), new XTextInput($name, $num, array('size'=>'2', 'class'=>'small', 'maxlength'=>'8'))));
          $off++;
        }
        $i++;
      }
      // add bye team, if necessary
      if ($bye_team !== null) {
        $num = $i + ($off - 1) * $num_teams;
        $bod->add($row = new XTR(array(), array(new XTD(array(), $bye_team))));
        $row->add(new XTD(array(), new XTextInput($bye_team->id, $num, array('size'=>'2', 'class'=>'small', 'maxlength'=>'8'))));
        for ($i = 1; $i < count($divisions); $i++) {
          $row->add(new XTD());
        }
      }
    }

    // order
    $form->add(new FItem("Order sails in first race:", XSelect::fromArray('sort', $this->SORT, 'num')));

    // Submit form
    $form->add(new XP(array('class'=>'p-submit'),
                      array(new XA($this->link('rotations'), "← Start over"), " ",
                            new XSubmitInput("createrot", "Create rotation"))));
  }

  /**
   * Fills the HTML body, accounting for combined divisions, etc
   *
   */
  protected function fillHTML(Array $args) {

    $divisions = $this->REGATTA->getDivisions();
    $combined = ($this->REGATTA->scoring == Regatta::SCORING_COMBINED ||
                 count($divisions) == 1);

    // Listen to requests
    $chosen_rot = (isset($args['rottype'])) ?
      $args['rottype'] : null;

    $chosen_div = $divisions;
    if (isset($args['division']) && is_array($args['division'])) {
      try {
        $chosen_div = array();
        foreach ($args['division'] as $div)
          $chosen_div[$div] = Division::get($div);
      } catch (Exception $e) {
        Session::pa(new PA("Invalid division(s) specified. Using all.", PA::I));
        $chosen_div = $divisions;
      }
    }

    $repeats = 2;
    if (isset($args['repeat']) && $args['repeat'] >= 1)
      $repeats = (int)$args['repeat'];

    // Edittype
    $edittype = (isset($args['edittype']))
      ? $args['edittype'] : "ADD";

    // Existing divisions with rotations
    // Get divisions to choose from
    $rotation = $this->REGATTA->getRotation();

    $exist_div = $rotation->getDivisions();
    if (count($exist_div) == 0)
      $exist_div = array();
    else
      $exist_div = array_combine($exist_div, $exist_div);

    // Get signed in teams
    $p_teams = $this->REGATTA->getTeams();

    // ------------------------------------------------------------
    // 1. Choose a rotation type: SWAP rotations are allowed due to
    // the presence of a possible BYE team. Because of this, even for
    // combined scoring, the user must be given the choice of rotation
    // to use FIRST, which is this step here.
    // ------------------------------------------------------------
    if ($chosen_rot === null) {
      $this->PAGE->addContent($p = new XPort("1. Create a rotation"));
      $p->add($form = $this->createForm(XForm::GET));
      $form->set("id", "sail_setup");
      $form->add(new XP(array(), "Swap divisions require an even number of total teams at the time of creation. If you choose swap division, TechScore will add a \"BYE Team\" as needed to make the total number of teams even. This will produce an unused boat in every race."));

      $the_rots = $this->ROTS;
      if (count($exist_div) == 0)
        unset($the_rots["OFF"]);
      $form->add(new FItem("Type of rotation:", XSelect::fromArray('rottype', $the_rots, $chosen_rot)));

      // No need for this choice if combined
      if (!$combined) {
        $div_opts = array();
        foreach ($divisions as $div)
          $div_opts[(string)$div] = (string)$div;
        $form->add(new FItem("Divisions to affect:", XSelectM::fromArray('division[]', $div_opts, $chosen_div)));
      }
      $form->add(new XSubmitP("choose_rot", "Next >>"));

      // ------------------------------------------------------------
      // 1b. Delete a rotation
      // ------------------------------------------------------------
      if ($rotation->isAssigned()) {
        $this->PAGE->addContent($p = new XPort("Remove rotation"));
        $p->add($form = $this->createForm());
        $form->add(new XP(array(), "You can replace an existing rotation simply by creating a new one using the form above. Note that rotation changes will not affect finishes already entered."));
        $form->add(new XP(array(), "If you wish to not use rotations at all, click the button below. Note that you will still be able to enter finishes using team names instead of sail numbers."));
        $form->add(new XSubmitP('remove-rotation', "Remove rotation"));
      }
    }
    // ------------------------------------------------------------
    // 2. Starting sails
    // ------------------------------------------------------------
    else {
      // This part is inherently different for combined
      if ($combined) {
        $this->fillCombined($chosen_rot, $chosen_div);
        return;
      }

      // Divisions
      $chosen_rot_desc = explode(":", $this->ROTS[$chosen_rot]);
      $this->PAGE->addContent($p = new XPort(sprintf("2. %s for Div. %s",
                                                     $chosen_rot_desc[0],
                                                     implode(", ", $chosen_div))));
      $p->addHelp("node13.html");
      $p->add($form = $this->createForm());

      $form->add(new XHiddenInput("rottype", $chosen_rot));
      // Divisions
      if (count($chosen_div) > 1) {
        $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/tablesort.js'));

        $form->add(new FItem("Order:", $tab = new XQuickTable(array('class'=>'narrow', 'id'=>'divtable'), array("#", "Div."))));
        $i = 0;
        foreach ($chosen_div as $div) {
          $tab->addRow(array(new XTextInput("order[]", ++$i, array('class'=>'small', 'size'=>2, 'maxlength'=>1)),
                             new XTD(array('class'=>'drag'), array($div, new XHiddenInput('division[]', $div)))),
                       array('class'=>'sortable'));
        }
      }
      else {
        foreach ($chosen_div as $div)
          $form->add(new XHiddenInput("division[]", $div));
      }

      // Suggest Navy/Franny special
      if (count($chosen_div) > 1 &&
          $chosen_rot != "NOR" &&
          $chosen_rot != "OFF") {
        $form->add(new FItem("Style:", XSelect::fromArray('style', $this->STYLES, 'copy')));
      }
      else {
        $form->add(new XHiddenInput("style", "copy"));
      }

      // Races
      $range = sprintf("1-%d", count($this->REGATTA->getRaces(Division::A())));
      $form->add(new FItem(sprintf("Races (%s):", $range),
                           new XTextInput("races", $range,
                                          array("id"=>"frace"))));

      // For Offset rotations, print only the 
      // current divisions for which there are rotations entered
      // and the offset amount
      if ($chosen_rot == "OFF") {
        $form->add(new FItem("Amount to offset (+/-):",
                             new XTextInput("offset", (int)(count($p_teams) / count($exist_div)),
                                            array("size"=>"2",
                                                  "maxlength"=>"2"))));

        $form->add(new XP(array('class'=>'p-submit'),
                          array(new XA(WS::link(sprintf('/score/%d/sails', $this->REGATTA->id)), "← Start over"), " ",
                                new XSubmitInput("offsetrot", "Offset"))));
      }
      else {
        if ($chosen_rot != "NOR") {
          $form->add(new FItem("Races in set:",
                               $f_text = new XTextInput("repeat", $repeats,
                                                        array("size"=>"2",
                                                              "id"=>"repeat"))));
        }
        $divs = array_values($chosen_div);
        $form->add(new FItem("Enter sail numbers in first race of div. " . $divs[0],
                             $tab = new XQuickTable(array('class'=>'narrow'))));

        // require a BYE team if the total number of teams
        // (divisions * number of teams) is not even
        if ($chosen_rot == "SWP" && count($p_teams) % 2 > 0)
          $p_teams[] = new ByeTeam();
        $i = 1;
        foreach ($p_teams as $team) {
          $tab->addRow(array($team,
                             new XTextInput($team->id, $i++,
                                            array("size"=>"2",
                                                  "class"=>"small",
                                                  "maxlength"=>"8"))));
        }

        // order
        $form->add(new FItem("Order sails in first race:", XSelect::fromArray('sort', $this->SORT, 'num')));

        // Submit form
        $form->add(new XP(array('class'=>'p-submit'),
                          array(new XA($this->link('rotations'), "← Start over"), " ",
                                new XSubmitInput("createrot", "Create rotation"))));
      }

      // FAQ's
      $this->PAGE->addContent($p = new XPort("FAQ"));
      $fname = sprintf("%s/faq/sail.html", dirname(__FILE__));
      $p->add(new XRawText(file_get_contents($fname)));
    }
  }

  /**
   * Sets up rotation in the case of combined divisions or only one
   * division. Note that the rotation type and divisions must already
   * have been chosen
   *
   * @param Array $args the arguments
   * @param Const $rottype the rotation type
   * @return Array the processed arguments
   */
  private function processCombined(Array $args, $rottype) {

    $rotation = $this->REGATTA->getRotation();
    $races = DB::$V->reqString($args, 'races', 1, 101, "No races provided.");
    if (($races = DB::parseRange($races)) === null)
      throw new SoterException("Unable to parse range of races provided.");
    sort($races);
    if (count($races) == 0)
      throw new SoterException("No races for which to setup rotations.");

    // ------------------------------------------------------------
    // Offset rotation
    // ------------------------------------------------------------
    if (isset($args['offsetrot'])) {

      // 4a. validate FROM division
      $all_divs = $rotation->getDivisions();
      if (count($all_divs) == 0)
        throw new SoterException("No existing rotation to offset from.");

      // 4b. validate offset amount
      $offset = DB::$V->reqInt($args, 'offset', -100, 101, "Invalid or missing offset amount.");

      // Queue the sail offset
      $rotation->initQueue();
      $rotation->queueCombinedOffset($races, $offset);
      $rotation->commit();

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array("Offset rotation successfully created. ",
                               new XA(sprintf('/view/%s/rotation', $this->REGATTA->id), "View", array('onclick'=> 'javascript:this.target="rotation";')),
                               ".")));
      unset($args['rottype']);
      $this->redirect('finishes');
      return $args;
    }


    // validate races
    $divisions = $this->REGATTA->getDivisions();

    // ------------------------------------------------------------
    // Create the rotation
    // ------------------------------------------------------------
    // validate repeats
    $repeats = count($divisions) * count($races);
    if ($rottype !== "NOR")
      $repeats = DB::$V->incInt($args, 'repeat', 1, $repeats, 1);

    // validate teams
    $keys = array_keys($args);
    $sails = array();
    $divs  = array();                      // keep track of divisions
    $tlist = array();                      // keep track of teams for multisorting
    $teams = $this->REGATTA->getTeams();
    foreach ($divisions as $div) {
      foreach ($teams as $team) {
        $sails[] = DB::$V->reqString($args, sprintf("%s,%s", $div, $team->id), 1, 9, "Missing sail for team $team in division $div");
        $tlist[] = $team;
        $divs[] = $div;
      }
    }

    // require BYE team, when applicable
    if ($rottype == "SWP" && count($divisions) * count($teams) % 2 > 0) {
      $team = new ByeTeam();
      if (!isset($args[$team->id]))
        throw new SoterException("Missing BYE team.");
      $sails[] = $args[$team->id];
      $tlist[] = $team;
      $divs[]  = Division::A();
    }

    // 3c. sorting
    $sort = "none";
    if (isset($args['sort']) && in_array($args['sort'], array_keys($this->SORT)))
      $sort = $args['sort'];
    switch ($sort) {
    case "num":
      array_multisort($sails, $tlist, SORT_NUMERIC);
      break;

    case "alph":
      array_multisort($sails, $tlist, SORT_STRING);
      break;
    }

    switch ($rottype) {
    case "STD":
    case "NOR":
      $rotation->createStandard($sails, $tlist, $divs, $races, $repeats);
    break;

    case "SWP":
      $rotation->createSwap($sails, $tlist, $divs, $races, $repeats);
      break;

    default:
      throw new SoterException("Unsupported rotation type.");
    }

    // reset
    UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
    Session::pa(new PA(array("New rotation successfully created. ",
			     new XA(sprintf('/view/%s/rotation', $this->REGATTA->id), "View", array('onclick'=> 'javascript:this.target="rotation";')),
			     ".")));
    unset($args['rottype']);
    $this->redirect('finishes');
  }

  /**
   * Sets up rotations according to requests. The request for creating
   * a new rotation should include:
   * <dl>
   *   <dt>
   *
   * </dl>
   */
  public function process(Array $args) {

    // ------------------------------------------------------------
    // 1b. remove rotation
    // ------------------------------------------------------------
    if (isset($args['remove-rotation'])) {
      $rotation = $this->REGATTA->getRotation();
      if (!$rotation->isAssigned())
        throw new SoterException("Rotations are not assigned.");
      $rotation->reset();

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA("Rotations removed."));
      return array();
    }


    $rottype = null;
    // ------------------------------------------------------------
    // 0. Validate inputs
    // ------------------------------------------------------------
    //   a. validate rotation
    $rottype = DB::$V->reqKey($args, 'rottype', $this->ROTS, "Invalid or missing rotation type.");
    $regDivisions = $this->REGATTA->getDivisions();
    $combined = (count($regDivisions) == 1 ||
                 $this->REGATTA->scoring == Regatta::SCORING_COMBINED);

    //   b. validate division, only if not combined division, and
    //   order by order, if provided
    if (!$combined) {
      $divisions = DB::$V->reqDivisions($args, 'division', $regDivisions, 1, "Expected list of divisions, but none found.");
      if (isset($args['order'])) {
        $order = DB::$V->reqList($args, 'order', count($divisions), "Invalid order provided for divisions.");
        array_multisort($order, $divisions, SORT_NUMERIC);
      }
      $args['division'] = $divisions;
    }

    // ------------------------------------------------------------
    // 1. Choose rotation
    // ------------------------------------------------------------
    if (isset($args['choose_rot'])) return $args;


    // ------------------------------------------------------------
    // 2. Validate other variables
    // ------------------------------------------------------------
    // call for combined helper method
    if ($combined)
      return $this->processCombined($args, $rottype);

    //   c. validate rotation style
    $style = DB::$V->reqKey($args, 'style', $this->STYLES, "Invalid or missing rotation style.");

    //   d. validate races
    $races = DB::$V->reqString($args, 'races', 1, 101, "No races provided.");
    if (($races = DB::parseRange($races)) === null)
      throw new SoterException("Unable to parse range of races provided.");
    sort($races);

    if (count($races) == 0)
      throw new SoterException("No races for which to setup rotations.");

    $rotation = $this->REGATTA->getRotation();

    // ------------------------------------------------------------
    // 3. Create new rotation
    // ------------------------------------------------------------
    if (isset($args['createrot'])) {

      // 3a. validate repeats
      $repeats = null;
      if ($rottype === "NOR")
        $repeats = count($divisions) * count($races);
      else
        $repeats = DB::$V->reqInt($args, 'repeat', 1, 101, "Invalid or missing value for repeats.");

      // 3b. validate teams: every signed-in team must exist
      $keys  = array_keys($args);
      $sails = array();
      $teams = array();
      $missing = array();
      foreach ($this->REGATTA->getTeams() as $team) {
        $teams[] = $team;
        $id = $team->id;
        if (!DB::$V->hasString($sail, $args, $id, 1, 9))
          $missing[] = (string)$team;
        $sails[] = $sail;
      }
      // Add BYE team if requested
      if (isset($args['BYE'])) {
        $teams[] = new ByeTeam();
        $sails[] = $args['BYE'];
      }
      if (count($missing) > 0)
        throw new SoterException(sprintf("Missing team or sail for %s.", implode(", ", $missing)));

      // 3c. sorting
      switch (DB::$V->incKey($args, 'sort', $this->SORT, 'none')) {
      case "num":
        array_multisort($sails, $teams, SORT_NUMERIC);
        break;

      case "alph":
        array_multisort($sails, $teams, SORT_STRING);
        break;
      }

      // Arrange the races in order according to repeats and rotation
      // style. If the style is franny, then use only the first division
      // for rotation, and offset it to get the others.

      // ------------------------------------------------------------
      //   3-1 Franny-style rotations
      // ------------------------------------------------------------
      if ($style === "fran") {
        $offset = (int)(count($teams) / count($divisions));

        $template = array_shift($divisions);
        $ordered_races = $races;
        $ordered_divs  = array();
        foreach ($races as $num)
          $ordered_divs[] = $template;

        // Perform template rotation
        switch ($rottype) {
        case "STD":
        case "NOR":
          $rotation->createStandard($sails, $teams, $ordered_divs, $ordered_races, $repeats);
        break;

        case "SWP":
          // ascertain that there are an even number of teams
          if (count($teams) % 2 > 0)
            throw new SoterException("There must be an even number of teams for swap rotation.");
          $rotation->createSwap($sails, $teams, $ordered_divs, $ordered_races, $repeats);
          break;

        default:
          throw new SoterException("Unsupported rotation type \"$rottype\".");
        }

        // Offset subsequent divisions, but first queue this one
        $rotation->initQueue();
        foreach ($races as $num) {
          $race = $this->REGATTA->getRace($template, $num);
          if ($race !== null) {
            foreach ($teams as $team) {
              if (($sail = $rotation->getSail($race, $team)) !== null)
                $rotation->queue($sail);
            }
          }
        }

        $num_teams = count($teams);
        $index = 0;
        foreach ($divisions as $div) {
          $rotation->queueOffset($template,
                                 $div,
                                 $races,
                                 $offset * (++$index));
        }
        $rotation->commit();

        // Reset
        Session::pa(new PA("Franny-style rotation successfully created."));
        unset($args);
        $this->redirect('finishes');
      }

      // ------------------------------------------------------------
      //   3-2 Other styles
      // ------------------------------------------------------------
      $ordered_races = array();
      $ordered_divs  = array();
      $racei = 0;
      while ($racei < count($races)) {
        foreach ($divisions as $div) {
          $repi = 0;
          while ($repi < $repeats && ($racei + $repi) < count($races)) {
            $ordered_races[] = $races[$racei + $repi];
            $ordered_divs[]  = $div;
            $repi++;
          }
        }
        $racei += $repeats;
      }

      // With copy style, the "set" includes all divisions
      if ($style == "copy") $repeats *= count($divisions);

      // Perform rotation
      switch ($rottype) {
      case "STD":
      case "NOR":
        $rotation->createStandard($sails, $teams, $ordered_divs, $ordered_races, $repeats);
      break;

      case "SWP":
        // ascertain that there are an even number of teams
        if (count($teams) % 2 > 0)
          throw new SoterException("There must be an even number of teams for swap rotation.");
        $rotation->createSwap($sails, $teams, $ordered_divs, $ordered_races, $repeats);
        break;

      default:
        throw new SoterException("Unsupported rotation type.");
      }

      // Reset
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      Session::pa(new PA(array("New rotation successfully created. ",
                               new XA(sprintf('/view/%s/rotation', $this->REGATTA->id), "View", array('onclick'=> 'javascript:this.target="rotation";')),
                               ".")));
      unset($args['rottype']);
      $this->redirect('finishes');
    }

    // ------------------------------------------------------------
    // 4. Offset rotation
    // ------------------------------------------------------------
    if (isset($args['offsetrot'])) {

      // 4a. validate FROM division
      $all_divs = $rotation->getDivisions();
      $from_div = DB::$V->reqDivision($args, 'from_div', $all_divs, "Invalid division to offset from.");

      // 4b. validate offset amount
      $offset = DB::$V->reqInt($args, 'offset', -100, 101, "Invalid or missing offset amount.");

      // Queue ALL BUT the destination divs
      $teams = $this->REGATTA->getTeams();
      $rotation->initQueue();

      foreach ($regDivisions as $division) {
        if (!in_array($division, $divisions)) {
          foreach ($races as $num) {
            $race = $this->REGATTA->getRace($division, $num);
            if ($race !== null) {
              foreach ($teams as $team) {
                if (($sail = $rotation->getSail($race, $team)) !== null)
                  $rotation->queue($sail);
              }
            }
          }
        }
      }

      foreach ($divisions as $div) {
        $rotation->queueOffset($from_div, $div, $races,        $offset);
      }

      // Reset
      $rotation->commit();
      Session::pa(new PA(array("Offset rotation successfully created. ",
                               new XA(sprintf('/view/%s/rotation', $this->REGATTA->id), "View", array('onclick'=> 'javascript:this.target="rotation";')),
                               ".")));
      unset($args['rottype']);
      $this->redirect('finishes');
    }
    return $args;
  }
}
?>
