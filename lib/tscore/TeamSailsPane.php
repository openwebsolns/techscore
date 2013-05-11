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
        if ($round->round_group != null)
          $round = $round->round_group->getRounds()[0];

        $flight = DB::$V->reqInt($args, 'flight', $step, $step * 20 + 1, "Invalid flight size provided.");
        if ($flight % $step != 0)
          throw new SoterException(sprintf("Number of boats must be divisible by %d.", $step));

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
            $row->add(new XTD(array('title'=>"Optional"), new XTextInput('sail-' . $name1, "")));
            $row->add(new XTD(array(), new XTextInput($name2, "", array('size'=>5))));
            $row->add(new XTD(array('title'=>"Optional"), new XTextInput('sail-' . $name2, "")));
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
    throw new SoterException("Not yet ready to process requests.");
  }
}
?>
