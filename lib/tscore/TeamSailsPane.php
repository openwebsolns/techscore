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
                                "#ddd" => "White",
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

    $divisions = $this->REGATTA->getDivisions();
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

        $flight = DB::$V->reqInt($args, 'flight', $step, $step * 20 + 1, "Invalid flight size provided.");
        if ($flight % $step != 0)
          throw new SoterException(sprintf("Number of boats must be divisible by %d.", $step));

        $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-rot.js')));
        $this->PAGE->addContent($p = new XPort("2. Provide sail numbers"));
        $p->add($form = $this->createForm());
        $form->add(new XP(array(), "Assign the sail numbers to use using the list below. If applicable, choose the optional color that goes with the sail. This color will be displayed in the \"Rotations\" dialog."));
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
        for ($race_num = 0; $race_num < $flight / $step; $race_num++) {
          foreach ($divisions as $i => $div) {
            $name1 = sprintf('%s-1[]', $div);
            $name2 = sprintf('%s-2[]', $div);

            $bod->add($row = new XTR(array()));
            if ($i == 0)
              $row->add(new XTH(array('rowspan' => count($divisions)), sprintf("Race %d", ($race_num + 1))));
            $row->add(new XTD(array(), new XTextInput($name1, "", array('size'=>5))));
            $row->add(new XTD(array('title'=>"Optional"), $sel1 = new XSelect('sail-' . $name1)));
            $row->add(new XTD(array(), new XTextInput($name2, "", array('size'=>5))));
            $row->add(new XTD(array('title'=>"Optional"), $sel2 = new XSelect('sail-' . $name2)));

            $sel1->set('class', 'color-chooser');
            $sel1->set('onchange', 'this.style.background=this.value;');
            $sel2->set('class', 'color-chooser');
            $sel2->set('onchange', 'this.style.background=this.value;');
            $sel1->add(new XOption("", array(), "[None]"));
            $sel2->add(new XOption("", array(), "[None]"));
            foreach (self::$COLORS as $code => $title) {
              $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
              $sel1->add(new XOption($code, $attrs, $title));
              $sel2->add(new XOption($code, $attrs, $title));
            }
          }
        }

        $form->add(new XP(array('class'=>'p-submit'),
                       array(new XA($this->link(), "← Cancel"), " ",
                             new XSubmitInput('create', "Create rotation"),
                             new XHiddenInput('round', $round->id))));
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::E));
      }
    }

    // ------------------------------------------------------------
    // 1. Choose parameters
    // ------------------------------------------------------------
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
        $ul->add($li = new XLi(array(new XRadioInput('round', $round->id, array('id'=>$id)),
                                     new XLabel($id, $label))));
      }
    }

    $form->add(new FItem("# of boats:", new XInput('number', 'flight', $step * 3, array('min'=>$step, 'step'=>$step, 'max'=>($step * 20)))));
    $form->add(new XSubmitP('go', "Next →"));
  }

  /**
   * Sets up rotations according to requests.
   */
  public function process(Array $args) {
    if (isset($args['create'])) {
      $divisions = $this->REGATTA->getDivisions();

      $rounds = array();
      foreach ($this->REGATTA->getRounds() as $round)
        $rounds[$round->id] = $round;
      
      $round = $rounds[DB::$V->reqKey($args, 'round', $rounds, "Invalid round chosen.")];

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

        // make sure all sails are present and accounted for, and distinct
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

      $races = ($round->round_group !== null) ?
        $this->REGATTA->getRacesInRoundGroup($round->round_group, Division::A(), false) :
        $this->REGATTA->getRacesInRound($round, Division::A(), false);

      // rotations set up manually
      $rotation = $this->REGATTA->getRotation();
      foreach ($races as $i => $race) {
        $i = $i % $num_races;
        foreach ($divisions as $div) {
          $r = $this->REGATTA->getRace($div, $race->number);

          $sail = new Sail();
          $sail->race = $r;
          $sail->team = $r->tr_team1;
          $sail->sail = trim($sails1[(string)$div][$i]);
          $sail->color = DB::$V->incRE($color1[(string)$div], $i, '/^#[0-9A-Fa-f]{3,6}$/');
          $rotation->setSail($sail);

          $sail = new Sail();
          $sail->race = $r;
          $sail->team = $r->tr_team2;
          $sail->sail = $sails2[(string)$div][$i];
          $sail->color = DB::$V->incRE($color2[(string)$div], $i, '/^#[0-9A-Fa-f]{3,6}$/');
          $rotation->setSail($sail);
        }
      }

      $label = $round;
      if ($round->round_group !== null) {
        $label = array();
        foreach ($round->round_group->getRounds() as $round)
          $label[] = $round;
        $label = implode(", ", $label);
      }
      Session::pa(new PA(sprintf("Rotation assigned for %s.", $label)));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      $this->redirect('rotations');
    }
  }
}
?>
