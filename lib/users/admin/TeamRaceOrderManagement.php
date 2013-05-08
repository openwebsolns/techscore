<?php
/*
 * This file is part of TechScore
 *
 * @package users-admin
 */

require_once('users/admin/AbstractAdminUserPane.php');

/**
 * Manages (edits, adds, removes) race order look-up tables for team racing
 *
 */
class TeamRaceOrderManagement extends AbstractAdminUserPane {

  public function __construct(Account $user) {
    parent::__construct("Race orders for team racing", $user);
    $this->page_url = 'race-order';
  }

  private function fillRaceList(XPort $p, Race_Order $template) {
    $num_boats = $template->getNumBoats();
    $num_teams = $template->getNumTeams();
    $num_divs = $template->getNumDivisions();

    $p->add(new XP(array(),
                   array("Use the list of races, grouped by flight, to enter the order in which teams should meet. ", new XStrong(sprintf("Use numbers 1 through %d to indicate which team %s will use in that race.", $num_teams, Conf::$NAME)))));
    $p->add(new XP(array(),
                   array("For example, to indicate that the second and third team should meet in a race, with the teams \"swapped\", enter ", new XVar("3"), ", ", new XVar("2"), " for that race number.")));
    $p->add(new XP(array(), "A template will not be considered if every team-pairing is not present."));

    $p->add($form = $this->createForm());
    $form->add(new FItem("Num. of teams:", new XStrong($num_teams)));
    $form->add(new FItem("Num. of boats/team:", new XStrong($num_divs)));
    $form->add(new FItem("Num. of boats:", new XStrong($num_boats)));
    $form->add(new FItem("Rotate frequently:", ($template->isFrequent()) ? new XStrong("Yes") : new XStrong("No")));
    $form->add(new FItem("Race order:", new XSpan("(Table below)", array('class'=>'hidden'))));

    $num_races = $num_teams * ($num_teams - 1) / 2;
    $races_per_flight = $num_boats / $num_divs / 2;
    for ($num = 0; $num < $num_races; $num++) {
      $pair = $template->getPair($num);

      if ($num % $races_per_flight == 0)
        $form->add($tab = new XQuickTable(array(), array("#", "Team A", "Team B")));
      $tab->addRow(array($num + 1,
                         new XTextInput('team1[]', $pair[0], array('size'=>2, 'min'=>1, 'max'=>$num_teams)),
                         new XTextInput('team2[]', $pair[1], array('size'=>2, 'min'=>1, 'max'=>$num_teams))));
    }

    if ($template->template === null) {
      $form->add(new XP(array('class'=>'p-submit'),
                        array(new XA(WS::link('/race-order'), "← Cancel"), " ",
                              new XSubmitInput('create', "Create template"),
                              new XHiddenInput('teams', $num_teams),
                              new XHiddenInput('boats', $num_boats),
                              new XHiddenInput('divs', $num_divs),
                              new XHiddenInput('freq', ($template->isFrequent()) ? 1 : 0))));
    }
    else {
      $form->add(new XP(array('class'=>'p-submit'),
                        array(new XA(WS::link('/race-order'), "← Cancel"), " ",
                              new XSubmitInput('edit', "Edit template"),
                              new XHiddenInput('template', $template->id))));
    }
  }

  public function fillHTML(Array $args) {
    $current = array();
    foreach (DB::getAll(DB::$RACE_ORDER) as $order)
      $current[$order->id] = $order;

    // ------------------------------------------------------------
    // Request to edit?
    // ------------------------------------------------------------
    if (isset($args['template'])) {
      if (!isset($current[$args['template']]))
        Session::pa(new PA("Invalid template ID requested for editing. Please try again.", PA::E));
      else {
        $this->PAGE->addContent($p = new XPort("Edit existing template"));
        $this->fillRaceList($p, $current[$args['template']]);
        return;
      }
    }

    // ------------------------------------------------------------
    // New one requested?
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      try {
        $num_teams = DB::$V->reqInt($args, 'teams', 1, 100, "Invalid number of teams specified.");
        $num_boats = DB::$V->reqInt($args, 'boats', 1, 100, "Invalid number of boats specified.");
        $num_divs = DB::$V->reqInt($args, 'divs', 1, 5, "Invalid number of boats per team specified.");
        if ($num_boats % ($num_divs * 2) != 0)
          throw new SoterException("Invalid number of boats in flight given number of boats per team.");

        $freq = DB::$V->incInt($args, 'freq', 1, 2, 0);

        $id = Race_Order::createID($num_divs, $num_teams, $num_boats, $freq > 0);
        if (isset($current[$id]))
          throw new SoterException("A template already exists for these parameters. Please edit that template instead.");

        $template = new Race_Order();
        $template->id = $id;

        $this->PAGE->addContent($p = new XPort("Specify race order for new template"));
        $this->fillRaceList($p, $template);
        return;
      }
      catch (SoterException $e) {
        Session::pa(new PA($e->getMessage(), PA::I));
      }
    }

    // ------------------------------------------------------------
    // Create a new one
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Create a new race order"));
    $p->add(new XP(array(), "Race orders are used in team racing to automatically order which teams face off in which order. Create a look-up table which will then be used if the scorer's choice of parameters match the ones specified below. Note that there can only be one table per set of parameters below."));

    $p->add($form = $this->createForm(XForm::GET));

    $form->add(new FItem("# of teams:", new XTextInput('teams', "", array('min'=>2, 'size'=>2))));

    $form->add($fi = new FItem("# of boats/team:", new XTextInput('divs', 3, array('size'=>2, 'min'=>1, 'max'=>4))));
    $fi->add(new XMessage("Use 3 for a \"3 on 3\" team race, for example"));

    $form->add($fi = new FItem("# of boats:", new XTextInput('boats', "", array('size'=>2))));
    $fi->add(new XMessage("Total number of boats, i.e. 18, 24"));

    $form->add($fi = new FItem("Frequent rotation:", new XCheckboxInput('freq', 1, array('id'=>'chk-freq'))));
    $fi->add(new XLabel('chk-freq', "The race order specified switches teams frequently."));
    $form->add(new XSubmitP('create', "Create template"));

    // ------------------------------------------------------------
    // Current ones
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Current race orders"));
    if (count($current) == 0) {
      $p->add(new XP(array('class'=>'warning'), "No race order templates exist."));
      return;
    }
    $p->add(new XP(array(), "Click on the \"Edit\" link next to the template name to edit that template. To delete a template, check the box in the last column and click \"Delete\" at the bottom of the form."));
    $p->add($form = $this->createForm());
    $form->add($tab = new XQuickTable(array(), array("Teams", "Total boats", "T/B", "Freq?", "Author", "Edit", "Delete?")));
    foreach ($current as $i => $order) {
      $tab->addRow(array($order->getNumTeams(),
                         $order->getNumBoats(),
                         $order->getNumDivisions(),
                         ($order->isFrequent()) ? new XImg(WS::link('/inc/img/s.png'), "✓") : "",
                         $order->author,
                         new XA(WS::link('/race-order', array('template'=>$order->id)), "Edit"),
                         new XCheckboxInput('template[]', $order->id)),
                   array('class'=>'row' . ($i % 2)));
    }
    $form->add(new XSubmitP('delete', "Delete"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Create new one
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      $num_teams = DB::$V->reqInt($args, 'teams', 1, 100, "Invalid number of teams specified.");
      $num_boats = DB::$V->reqInt($args, 'boats', 1, 100, "Invalid number of boats specified.");
      $num_divs = DB::$V->reqInt($args, 'divs', 1, 5, "Invalid number of boats per team specified.");
      if ($num_boats % ($num_divs * 2) != 0)
        throw new SoterException("Invalid number of boats in flight given number of boats per team.");

      $freq = DB::$V->incInt($args, 'freq', 1, 2, 0);

      $id = Race_Order::createID($num_divs, $num_teams, $num_boats, $freq > 0);
      if (DB::get(DB::$RACE_ORDER, $id) !== null)
        throw new SoterException("A template already exists for these parameters. Please edit that template instead.");

      $template = new Race_Order();
      $template->id = $id;
      $this->processPairings($template, $args);
      Session::pa(new PA(sprintf("Created new team race order template for %d teams in %d boats.", $num_teams, $num_boats)));
      WS::go('/race-order');
    }

    // ------------------------------------------------------------
    // Edit existing
    // ------------------------------------------------------------
    if (isset($args['edit'])) {
      $template = DB::$V->reqID($args, 'template', DB::$RACE_ORDER, "Invalid template to edit.");
      $this->processPairings($template, $args);
      Session::pa(new PA(sprintf("Edited template for %d teams in %d boats.",
                                 $template->getNumTeams(),
                                 $template->getNumBoats())));
      WS::go('/race-order');
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $deleted = 0;
      foreach (DB::$V->reqList($args, 'template', null, "No list of templates to delete provided.") as $id) {
        $templ = DB::get(DB::$RACE_ORDER, $id);
        if ($templ === null)
          throw new SoterException("Invalid template to delete: $id");
        DB::remove($templ);
        $deleted++;
      }

      if (count($deleted) == 0)
        Session::pa(new PA("No templates were deleted.", PA::I));
      else
        Session::pa(new PA(sprintf("Deleted %d template(s).", $deleted)));
    }
  }

  private function processPairings(Race_Order $template, Array $args) {
    $num_teams = $template->getNumTeams();
    $num_boats = $template->getNumBoats();
    $num_divs = $template->getNumDivisions();

    $num_races = $num_teams * ($num_teams - 1) / 2;
    $teams1 = DB::$V->reqList($args, 'team1', $num_races, "Invalid or incomplete list for \"Team A\".");
    $teams2 = DB::$V->reqList($args, 'team2', $num_races, "Invalid or incomplete list for \"Team B\".");

    // All handshakes must be accounted for
    $handshakes = array();
    for ($i = 1; $i <= $num_teams; $i++) {
      for ($j = $i + 1; $j <= $num_teams; $j++) {
        $shake = sprintf("%d-%d", $i, $j);
        $handshakes[$shake] = $shake;
      }
    }

    $pairings = array();
    foreach ($teams1 as $i => $team1) {
      $team1 = DB::$V->reqInt($teams1, $i, 1, $num_teams + 1, "Invalid Team A index provided.");
      $team2 = DB::$V->reqInt($teams2, $i, 1, $num_teams + 1, "Invalid partner for Team A provided.");
      if ($team1 == $team2)
        throw new SoterException("Teams cannot sail against themselves.");
      $shake = sprintf("%d-%d", $team1, $team2);
      if ($team2 < $team1)
        $shake = sprintf("%d-%d", $team2, $team1);
      if (!isset($handshakes[$shake]))
        throw new SoterException("Invalid team pairing provided.");
      unset($handshakes[$shake]);
      $pairings[] = sprintf("%d-%d", $team1, $team2);
    }

    if (count($pairings) < $num_races)
      throw new SoterException("Not all pairings have been accounted for.");

    $template->template = $pairings;
    $template->author = Conf::$USER;
    DB::set($template);
  }
}
?>