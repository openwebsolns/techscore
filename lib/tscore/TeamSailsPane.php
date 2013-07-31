<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Pane to create the rotations
 *
 * 2011-02-18: Only one BYE team is allowed per rotation
 *
 * @author Dayan Paez
 * @version 2009-10-04
 */
class TeamSailsPane extends AbstractPane {

  public static $COLORS = array(
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

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Setup rotations", $user, $reg);
  }

  /**
   * Fills the HTML body, accounting for combined divisions, etc
   *
   */
  protected function fillHTML(Array $args) {
    // New regatta?
    $divisions = $this->REGATTA->getDivisions();
    if (count($divisions) == 0) {
      $this->fillNewRegatta($args);
      return;
    }
    $step = count($divisions) * 2;

    $rounds = array();
    foreach ($this->REGATTA->getRounds() as $round)
      $rounds[$round->id] = $round;

    // ------------------------------------------------------------
    // 2. Enter values
    // ------------------------------------------------------------
    if (isset($args['round'])) {
      try {
        $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round chosen.")];
        if ($round->round_group != null) {
          $rnds = $round->round_group->getRounds();
          $round = $rnds[0];
        }

        $rot = DB::$V->incID($args, 'regatta_rotation', DB::$REGATTA_ROTATION);
        if ($rot !== null) {
          if ($rot->regatta != $this->REGATTA)
            throw new SoterException("Invalid saved rotation to use.");
          $rotation = $rot->rotation;
          $flight = 2 * $rotation->count();
        }
        else {
          $rotation = new TeamRotation();
          $flight = DB::$V->reqInt($args, 'flight', $step, $step * 20 + 1, "Invalid flight size provided.");
        }

        if ($flight % $step != 0)
          throw new SoterException(sprintf("Number of boats must be divisible by %d.", $step));

        $this->PAGE->addContent($p = new XPort("2. Provide sail numbers"));
        $p->add($form = $this->getSailsForm($rotation, $flight, $divisions, $rot));
        $form->add(new XHiddenInput('round', $round->id));
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // 1. Choose parameters
    // ------------------------------------------------------------
    $rotation = $this->REGATTA->getRotation();
    $have_rotations = array();
    foreach ($rotation->getRounds() as $round)
      $have_rotations[$round->id] = $round;

    $this->PAGE->addContent($p = new XPort("1. Choose round and flight size"));
    $p->add(new XP(array(), "Rotations are set up on a per-round basis. To begin, choose the round or group of rounds from the list below, and the number of total boats available. On the next page, you will be able to enter sail numbers to use."));
    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FItem("Round:", $ul = new XUl(array('class'=>'inline-list'))));

    // separate rounds into round/groups
    $copy = $rounds;
    foreach ($rounds as $round) {
      if (isset($copy[$round->id])) {
        unset($copy[$round->id]);
        $val = $round->id;
        $id = 'chk-round-' . $round->id;
        $label = $round;
        if ($round->round_group !== null) {
          $label = array();
          foreach ($round->round_group->getRounds() as $other) {
            unset($copy[$other->id]);
            $label[] = $other;
          }
          $label = implode(", ", $label);
        }
        $mes = "";
        if (isset($have_rotations[$round->id]))
          $mes = new Ximg(WS::link('/inc/img/s.png'), "✓", array('title'=>"Rotation exists"));
        $ul->add($li = new XLi(array(new XRadioInput('round', $round->id, array('id'=>$id)),
                                     new XLabel($id, $label),
                                     $mes)));
      }
    }

    $form->add(new FItem("# of boats:", new XInput('number', 'flight', $step * 3, array('min'=>$step, 'step'=>$step, 'max'=>($step * 20)))));

    $list = $this->REGATTA->getTeamRotations();
    if (count($list) > 0) {
      $form->add(new XP(array(), "Or, you may choose from the saved rotation(s) below."));
      $form->add(new FItem("Saved rotation:", $ul = new XUl(array('class'=>'inline-list'))));
      foreach ($list as $rot) {
        $id = 'chk-rot-' . $rot->id;
        $ul->add(new XLi(array(new XRadioInput('regatta_rotation', $rot->id, array('id'=>$id)),
                               new XLabel($id, $rot->name))));
      }
    }
    $form->add(new XSubmitP('go', "Next →"));
  }

  /**
   * Sets up rotations according to requests.
   */
  public function process(Array $args) {
    // New regatta?
    $divisions = $this->REGATTA->getDivisions();
    if (count($divisions) == 0) {
      return $this->processNewRegatta($args);
    }

    if (isset($args['create'])) {
      $divisions = $this->REGATTA->getDivisions();

      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $round)
        $rounds[$round->id] = $round;
      
      $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round chosen.")];
      $team_rot = $this->processSailsForm($divisions, $args);

      $races = ($round->round_group !== null) ?
        $this->REGATTA->getRacesInRoundGroup($round->round_group, Division::A(), false) :
        $this->REGATTA->getRacesInRound($round, Division::A(), false);

      $num_divs = count($divisions);

      // rotations set up manually
      $rotation = $this->REGATTA->getRotation();
      $i = 0;
      foreach ($races as $race) {
        foreach ($divisions as $div) {
          $r = $this->REGATTA->getRace($div, $race->number);

          $sail = new Sail();
          $sail->race = $r;
          $sail->team = $r->tr_team1;
          $sail->sail = $team_rot->sails1[$i];
          $sail->color = $team_rot->colors1[$i];
          $rotation->setSail($sail);

          $sail = new Sail();
          $sail->race = $r;
          $sail->team = $r->tr_team2;
          $sail->sail = $team_rot->sails2[$i];
          $sail->color = $team_rot->colors2[$i];
          $rotation->setSail($sail);

          $i = ($i + 1) % $team_rot->count();
        }
      }

      $label = $round;
      if ($round->round_group !== null) {
        $label = array();
        foreach ($round->round_group->getRounds() as $round)
          $label[] = $round;
        $label = implode(", ", $label);
      }

      // Save?
      $name = DB::$V->incString($args, 'name', 1, 51);
      if ($name !== null) {
        $rot = null;
        foreach ($this->REGATTA->getTeamRotations() as $prev) {
          if ($prev->name == $name) {
            $rot = $prev;
            break;
          }
        }
        if ($rot === null) {
          $rot = new Regatta_Rotation();
          $rot->name = $name;
          $rot->regatta = $this->REGATTA;
        }

        $rot->rotation = $team_rot;
        DB::set($rot);
        Session::pa(new PA(sprintf("Saved the rotation as \"%s\".", $name)));
      }

      Session::pa(new PA(sprintf("Rotation assigned for %s.", $label)));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $this->redirect('rotations');
    }
  }

  /**
   * Provides a way to store one or more rotations for use when
   * creating rounds. Assumes 3 divisions!
   *
   */
  protected function fillNewRegatta(Array $args) {
    // ------------------------------------------------------------
    // Creating a new rotation?
    // ------------------------------------------------------------
    if (isset($args['new-rotation'])) {
      try {
        $num_boats = DB::$V->reqInt($args, 'num_boats', 6, 100, "Invalid number of boats.");
        if (($num_boats % 6) != 0)
          throw new SoterException("Number of boats must be divisible by 6.");

        $this->PAGE->addContent($p = new XPort("2. Specify name and sail numbers"));
        $p->add($form = $this->getSailsForm(new TeamRotation(), $num_boats, array(Division::A(), Division::B(), Division::C())));
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }
    $current = $this->REGATTA->getTeamRotations();

    $this->PAGE->addContent(new XP(array(), "It is a good idea to create the different rotation configurations to be used in the regatta now, so that they are available when you create the rounds later. Creating a configuration means specifying which sail numbers (and colors) sail against each other in a flight. The program will then assign the same rotation configuration to each successive flight of races in a chosen round."));
    $this->PAGE->addContent(new XP(array(), "You may create multiple configurations: one for each \"fleet\" of boats available at the regatta. When creating a round, you will have the option to choose one of these configurations based on the name you have given them."));
    $mes = array();
    if (count($current) == 0)
      $mes[] = "Though recommended, this step is optional. ";
    $mes[] = "To continue setting up the regatta, start ";
    $mes[] = new XA($this->link('races'), "adding rounds");
    $mes[] = ".";
    $this->PAGE->addContent(new XP(array(), $mes));
    $this->PAGE->addContent($p = new XPort("1. Create new rotation template"));

    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FItem("Number of boats:", new XInput('number', 'num_boats', 18, array('step'=>6, 'min'=>6))));
    $form->add(new XSubmitP('new-rotation', "Next →"));

    if (count($current) > 0) {
      $this->PAGE->addContent($p = new XPort("Current rotation configuration"));
      $p->add(new XP(array(), "The following rotation configuration(s) have been created. You may edit them or delete them, as needed. Click on the name to edit."));
      $p->add($form = $this->createForm());
      $form->add($tab = new XQuickTable(array('class'=>'tr-rotation-table'), array("", "Name", "# Boats", "Summary")));
      foreach ($current as $i => $rotation) {
        $id = 'inp-' . $rotation->id;
        $tab->addRow(array(new XCheckboxInput('regatta_rotation[]', $rotation->id, array('id'=>$id)),
                           new XA($this->link('rotations', array('r'=>$rotation->id)), $rotation->name),
                           new XLabel($id, $rotation->rotation->count()),
                           $this->getSummaryTable($rotation->rotation)),
                     array('class'=>'row'.($i % 2)));
      }
      $form->add(new XSubmitP('delete', "Delete selected"));
    }
  }

  protected function getSummaryTable(TeamRotation $rot) {
    $num_divs = 3;
    $tab = new XTable(array('class'=>'tr-rotation-summary'),
                      array(new XTHead(array(),
                                       array(new XTR(array(),
                                                     array(new XTH(array('colspan'=>$num_divs), "Team 1"),
                                                           new XTH(array('colspan'=>$num_divs), "Team 2"))))),
                            $bod = new XTBody()));
    for ($i = 0; $i < $rot->count(); $i += $num_divs) {
      $t1 = array();
      $t2 = array();
      for ($j = 0; $j < $num_divs; $j++) {
        $index = $i + $j;
        $td = new XTD(array(), $rot->sails1[$index]);
        if ($rot->colors1[$index] != null)
          $td->set('style', sprintf('background:%s;', $rot->colors1[$index]));
        $t1[] = $td;

        $td = new XTD(array(), $rot->sails2[$index]);
        if ($rot->colors2[$index] != null)
          $td->set('style', sprintf('background:%s;', $rot->colors2[$index]));
        $t2[] = $td;
      }
      $bod->add(new XTR(array(), array_merge($t1, $t2)));
    }
    return $tab;
  }

  protected function processNewRegatta(Array $args) {
    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $ids = array();
      foreach ($this->REGATTA->getTeamRotations() as $rot)
        $ids[$rot->id] = $rot;

      $lst = DB::$V->reqList($args, 'regatta_rotation', null, "No rotations to delete.");
      $delete = array();
      foreach ($lst as $id) {
        if (!isset($ids[$id]))
          throw new SoterException("Invalid rotation to delete.");
        $delete[] = $ids[$id];
      }
      if (count($delete) == 0)
        throw new SoterException("Nothing to delete.");
      foreach ($delete as $rot)
        DB::remove($rot);
      Session::pa(new PA("Rotation configuration(s) deleted."));
    }

    // ------------------------------------------------------------
    // Create new one
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      $name = DB::$V->reqString($args, 'name', 1, 51, "No name provided for the rotation.");
      $rot = null;
      foreach ($this->REGATTA->getTeamRotations() as $prev) {
        if ($prev->name == $name) {
          $rot = $prev;
          break;
        }
      }
      if ($rot === null) {
        $rot = new Regatta_Rotation();
        $rot->name = $name;
        $rot->regatta = $this->REGATTA;
      }

      $rot->rotation = $this->processSailsForm(array(Division::A(), Division::B(), Division::C()), $args);

      DB::set($rot);
      Session::pa(new PA(sprintf("Saved the rotation as \"%s\".", $name)));
      $this->redirect('rotations');
    }
  }

  /**
   * Helper method creates the form with the sails table
   *
   * @param TeamRotation $rotation the rotation being edited
   * @param int $flight the number of boats
   * @param Array $divisions the divisions
   * @param Regatta_Rotation $rot the optional pre-existing saved rotation
   */
  private function getSailsForm(TeamRotation $rotation, $flight, Array $divisions, Regatta_Rotation $rot = null) {
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-rot.js')));

    $step = count($divisions) * 2;
    $form = $this->createForm();
    $form->add(new XP(array(), "Assign the sail numbers using the table below. If applicable, choose the color that goes with the sail. This color will be displayed in the \"Rotations\" dialog."));
    $form->add(new XP(array(),
                      array("The flight size for this rotation is ", new XStrong($flight / $step), " races.")));
    $form->add(new XTable(array('class'=>'tr-rotation-sails'),
                          array(new XTHead(array(),
                                           array(new XTR(array(),
                                                         array(new XTH(array('rowspan'=>2), "#"),
                                                               new XTH(array('colspan'=>2), "Team A"),
                                                               new XTH(array('colspan'=>2), "Team B"))),
                                                 new XTR(array(),
                                                         array(new XTH(array(), "Sail #"),
                                                               new XTH(array(), "Color"),
                                                               new XTH(array(), "Sail #"),
                                                               new XTH(array(), "Color"))))),
                                $bod = new XTBody())));
    $globalIndex = 0;
    $rotCount = $rotation->count();
    for ($race_num = 0; $race_num < $flight / $step; $race_num++) {
      foreach ($divisions as $i => $div) {
        $name1 = sprintf('%s-1[]', $div);
        $name2 = sprintf('%s-2[]', $div);

        $bod->add($row = new XTR(array()));
        if ($i == 0)
          $row->add(new XTH(array('rowspan' => count($divisions)), sprintf("Race %d", ($race_num + 1))));
        $s1 = ($globalIndex < $rotCount) ? $rotation->sails1[$globalIndex] : "";
        $s2 = ($globalIndex < $rotCount) ? $rotation->sails2[$globalIndex] : "";

        $row->add(new XTD(array(), new XTextInput($name1, $s1, array('size'=>5))));
        $row->add(new XTD(array('title'=>"Optional"), $sel1 = new XSelect('sail-' . $name1)));
        $row->add(new XTD(array(), new XTextInput($name2, $s2, array('size'=>5))));
        $row->add(new XTD(array('title'=>"Optional"), $sel2 = new XSelect('sail-' . $name2)));

        $c1 = ($globalIndex < $rotCount) ? $rotation->colors1[$globalIndex] : null;
        $c2 = ($globalIndex < $rotCount) ? $rotation->colors2[$globalIndex] : null;

        $sel1->set('class', 'color-chooser');
        $sel2->set('class', 'color-chooser');
        $sel1->add(new XOption("", array(), "[None]"));
        $sel2->add(new XOption("", array(), "[None]"));
        foreach (self::$COLORS as $code => $title) {
          $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
          $sel1->add($opt1 = new XOption($code, $attrs, $title));
          $sel2->add($opt2 = new XOption($code, $attrs, $title));

          if ($code == $c1)
            $opt1->set('selected', 'selected');
          if ($code == $c2)
            $opt2->set('selected', 'selected');
        }

        $globalIndex++;
      }
    }

    $name = ($rot === null) ? "" : $rot->name;
    $form->add($fi = new FItem("Save as:", new XTextInput('name', $name)));
    if ($rot === null)
      $fi->add(new XMessage("If blank, rotation will not be saved. Name must be unique."));
    else
      $fi->add(new XMessage("Change name to create new rotation."));

    $form->add(new XP(array('class'=>'p-submit'),
                      array(new XA($this->link('rotations'), "← Cancel"), " ",
                            new XSubmitInput('create', "Create rotation"))));
    return $form;
  }

  /**
   * Fetches a TeamRotation object based on arguments.
   *
   * Complement to getSailsForm()
   *
   * TODO: DB::$V->incRE($color1[(string)$div], $i, '/^#[0-9A-Fa-f]{3,6}$/');
   *
   * @param Array $divisions the list of divisions to account for
   * @param Array $args the POST
   * @return TeamRotation
   */
  private function processSailsForm(Array $divisions, Array $args) {
    // determine flight automatically by ascertaining that there are
    // the same number of sails for each division/team combo
    $sails1 = array();
    $sails2 = array();
    $color1 = array();
    $color2 = array();
    $num_races = null;
    $all_sails = array();
    foreach ($divisions as $div) {
      $name1 = sprintf('%s-1', $div);
      $name2 = sprintf('%s-2', $div);

      $sails1[(string)$div] = DB::$V->reqList($args, $name1, $num_races, "Missing list of races for first team.");
      if ($num_races === null) {
        $num_races = count($sails1[(string)$div]);
        if (count($num_races) == 0)
          throw new SoterException("Empty list of sails provided.");
      }
      $color1[(string)$div] = DB::$V->incList($args, 'sail-' . $name1, $num_races, array());
      $sails2[(string)$div] = DB::$V->reqList($args, $name2, $num_races, "Missing list of races for second team.");
      $color2[(string)$div] = DB::$V->incList($args, 'sail-' . $name2, $num_races, array());

      // make sure all sails are present and distinct
      foreach (array($sails1, $sails2) as $sails) {
        foreach ($sails[(string)$div] as $sail) {
          $sail = trim($sail);
          if ($sail == "")
            throw new SoterException("Empty sail provided.");
          if (isset($all_sails[$sail]))
            throw new SoterException("Duplicate sail \"$sail\" provided.");
          $all_sails[$sail] = $sail;
        }
      }
    }

    // Translate to non-nested lists
    $s1 = array();
    $s2 = array();
    $c1 = array();
    $c2 = array();
    for ($i = 0; $i < $num_races; $i++) {
      foreach ($divisions as $div) {
        $s1[] = $sails1[(string)$div][$i];
        $s2[] = $sails2[(string)$div][$i];
        $c1[] = $color1[(string)$div][$i];
        $c2[] = $color2[(string)$div][$i];
      }
    }

    $rot = new TeamRotation();
    $rot->sails1 = $s1;
    $rot->colors1 = $c1;
    $rot->sails2 = $s2;
    $rot->colors2 = $c2;

    return $rot;
  }
}
?>
