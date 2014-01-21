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
  const COMPLETION = 'completion';

  /**
   * Fills the form where teams are seeded for a particular round
   *
   * @param XForm $form the form to fill
   * @param Round $ROUND the round with which to fill
   * @param Array $masters the optional list of masters to use
   * @param Array $ids the optional list of existing seeded team IDs
   */
  protected function fillTeamsForm(XForm $form, Round $ROUND, Array $masters = null, Array $ids = null) {
    if ($masters === null)
      $masters = $ROUND->getMasters();
    if ($ids === null) {
      $ids = array();
      foreach ($ROUND->getSeeds() as $seed)
        $ids[] = $seed->team->id;
    }

    if (count($masters) == 0) {
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
      foreach ($masters as $master) {
        $last = $first + $master->num_teams - 1;
        $round = $master->master;

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
        $first = $last + 1;
      }
    }
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
    return $form;
  }

  /**
   * Creates and assigns a TeamRotation object based on arguments
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
}
?>