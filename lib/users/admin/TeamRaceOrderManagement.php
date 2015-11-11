<?php
use \users\AbstractUserPane;

/**
 * Manages (edits, adds, removes) race order look-up tables for team racing
 *
 */
class TeamRaceOrderManagement extends AbstractUserPane {

  public function __construct(Account $user) {
    parent::__construct("Race orders for team racing", $user);
  }

  private function fillRaceList(XPort $p, Race_Order $template) {
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/addSwapButton.js')));

    $p->add(new XP(array(),
                   array("Use the list of races, grouped by flight, to enter the order in which teams should meet. ", new XStrong(sprintf("Use numbers 1 through %d to indicate which team %s will use in that race.", $template->num_teams, DB::g(STN::APP_NAME))))));
    $p->add(new XP(array(),
                   array("For example, to indicate that the second and third team should meet in a race, with the teams \"swapped\", enter ", new XVar("3"), ", ", new XVar("2"), " for that race number.")));
    $p->add(new XP(array(), "A template will not be considered if every team-pairing is not present."));

    $frequencies = Race_Order::getFrequencyTypes();

    $p->add($form = $this->createForm());
    $form->add(new FItem("Description:", new XTextArea('description', $template->description)));
    $form->add(new FReqItem("Num. of teams:", new XStrong($template->num_teams)));
    if (count($template->master_teams) > 0) {
      $form->add($fi = new FReqItem("Teams carried over:", new XStrong(implode(", ", $template->master_teams))));
      $fi->add(" (Use ");

      $last = 1;
      foreach ($template->master_teams as $i => $num) {
        if ($i > 0)
          $fi->add(", ");
        $fi->add(sprintf("%d-%d for Round %d", $last, $last + $num - 1, ($i + 1)));
        $last += $num;
      }
      $fi->add(")");
    }
    $form->add(new FReqItem("Num. of boats/team:", new XStrong($template->num_divisions)));
    $form->add(new FReqItem("Num. of boats:", new XStrong($template->num_boats)));
    $form->add(new FReqItem("Boat rotation:", new XStrong($frequencies[$template->frequency])));
    $form->add(new FReqItem("Race order:", $tableform = new XDiv(array('class'=>'form_b'))));

    $num_races = $template->num_teams * ($template->num_teams - 1) / 2;
    if ($template->master_teams !== null) {
      foreach ($template->master_teams as $cnt)
        $num_races -= ($cnt * ($cnt - 1)) / 2;
    }

    $races_per_flight = $template->num_boats / $template->num_divisions / 2;
    for ($num = 0; $num < $num_races; $num++) {
      $pair = $template->getPair($num);

      if ($num % $races_per_flight == 0) {
        $tableform->add($tab = new XQuickTable(array('class'=>'tr-order-race'), array("#", "Team A", "Team B")));
      }
      $tab->addRow(array($num + 1,
                         new XNumberInput('team1[]', $pair[0], 1, $template->num_teams, 1, array('size'=>2)),
                         new XNumberInput('team2[]', $pair[1], 1, $template->num_teams, 1, array('size'=>2))));
    }

    // Add template as dump
    $form->add(new XP(array(), "Alternatively, you can dump the order directly into the space below."));
    $form->add(new FItem("Dump template:", new XTextArea('race_order', ""), "One pairing per line, separated by whitespace"));

    if ($template->template === null) {
      $teams = $template->num_teams;
      if ($template->master_teams !== null) {
        $teams = implode(" ", $template->master_teams);
      }
      $form->add($xp = new XP(array('class'=>'p-submit'),
                              array(new XA(WS::link('/race-order'), "← Cancel"), " ",
                                    new XSubmitInput('create', "Create template"),
                                    new XHiddenInput('teams', $teams),
                                    new XHiddenInput('boats', $template->num_boats),
                                    new XHiddenInput('frequency', $template->frequency),
                                    new XHiddenInput('divs', $template->num_divisions))));
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
    foreach (DB::getAll(DB::T(DB::RACE_ORDER)) as $order)
      $current[$order->id] = $order;

    $frequencies = Race_Order::getFrequencyTypes();

    // ------------------------------------------------------------
    // Request to export?
    // ------------------------------------------------------------
    if (isset($args['export'])) {
      if (!isset($current[$args['export']]))
        Session::pa(new PA("Invalid template ID requested for exporting. Please try again.", PA::E));
      else {
        header('Content-Type: text/plain');
        $template = $current[$args['export']];
        for ($i = 0; $i < count($template->template); $i++) {
          $pair = $template->getPair($i);
          printf("%s\t%s\n", $pair[0], $pair[1]);
        }
        exit;
      }
    }

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
        $template = $this->createTemplateFromMetadata($args);

        $title = "Specify race order for new template";
        $current = DB::getRaceOrder(
          $template->num_divisions,
          $template->num_teams,
          $template->num_boats,
          $template->frequency,
          $template->master_teams
        );
        if ($current !== null) {
          $template = $current;
          $title = "Edit existing template";
        }

        $this->PAGE->addContent($p = new XPort($title));
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
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-race-order-inputs.js'), null, array('async'=>'async', 'defer'=>'defer')));
    $this->PAGE->addContent($p = new XPort("Create a new race order"));
    $p->add(new XP(array(), "Race orders are used in team racing to automatically order which teams face off in which order. Create a look-up table which will then be used if the scorer's choice of parameters match the ones specified below. Note that there can only be one table per set of parameters below."));

    $p->add($form = $this->createForm(XForm::GET));

    $form->add(new FReqItem("Number of boats/team:", new XNumberInput('divs', 3, 1, 4, 1, array('size'=>2)), "Use 3 for a \"3 on 3\" team race, for example"));
    $form->add(new FReqItem("Number of teams:", new XTextInput('teams', '', array('pattern'=>'^[0-9]+([, ]+[0-9]+)*$')), "To indicate teams carried over, use a comma-separated list, e.g. \"4, 4\", or \"2, 2, 2, 2\"."));
    $form->add(new FReqItem("Rotation frequency:", XSelect::fromArray('frequency', $frequencies, null, array('id'=>'input_rotation_frequency'))));
    $form->add(new FReqItem("Number of boats:", new XNumberInput('boats', "", 1, null, 1, array('size'=>2, 'id'=>'input_num_boats')), "Total number of boats, i.e. 18, 24. For \"No rotation\", this must equal ([number of teams] * [number of boats/team])."));
    $form->add(new XSubmitP('create', "Create template"));

    // ------------------------------------------------------------
    // Current ones
    // ------------------------------------------------------------
    if (count($current) == 0) {
      return;
    }

    // Group by # of divisions, then by # of teams, then by # of boats
    $orders = array();
    foreach ($current as $order) {
      if (!isset($orders[$order->num_divisions]))
        $orders[$order->num_divisions] = array();
      if (!isset($orders[$order->num_divisions][$order->num_teams]))
        $orders[$order->num_divisions][$order->num_teams] = array();

      $id = $order->num_boats;
      if ($order->master_teams !== null)
        $id .= implode("-", $order->master_teams);

      if (!isset($orders[$order->num_divisions][$order->num_teams][$id]))
        $orders[$order->num_divisions][$order->num_teams][$id] = array();
      $orders[$order->num_divisions][$order->num_teams][$id][] = $order;
    }

    foreach ($orders as $num_divs => $current) {
      $this->PAGE->addContent($p = new XPort(sprintf("%d vs. %d", $num_divs, $num_divs)));
      $p->add(new XP(array(), "Click on the \"Edit\" link next to the template name to edit that template. To delete a template, check the box in the last column and click \"Delete\" at the bottom of the form."));
      $p->add($form = $this->createForm());

      foreach ($current as $num_teams => $orders) {
        $form->add(new XH4(sprintf("%d Teams", $num_teams)));

        $form->add($tab = new XQuickTable(array('class'=>'tr-race-order full'), array("Total boats", "Teams carried?", "Rotation", "Description", "Author", "Edit", "Export", "Delete?")));

        $rowIndex = 0;
        foreach ($orders as $list) {
          foreach ($list as $i => $order) {
            $row = array();
            if ($i == 0)
              $row[] = new XTH(array('rowspan' => count($list)), $order->num_boats);

            $row[] = ($order->master_teams === null) ? "" : implode(", ", $order->master_teams);
            $row[] = $frequencies[$order->frequency];
            $row[] = $order->description;
            $row[] = $order->author;
            $row[] = new XA(WS::link('/race-order', array('template'=>$order->id)), "Edit");
            $row[] = new XA(WS::link('/race-order', array('export'=>$order->id)), "Export");
            $row[] = new FCheckbox('template[]', $order->id);

            $tab->addRow($row, array('class'=>'row' . ($rowIndex % 2)));
          }
          $rowIndex++;
        }
      }
      $form->add(new XSubmitP('delete', "Delete", array(), true));
    }
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Create new one
    // ------------------------------------------------------------
    if (isset($args['create'])) {
      $template = $this->createTemplateFromMetadata($args);

      $current = DB::getRaceOrder(
        $template->num_divisions,
        $template->num_teams,
        $template->num_boats,
        $template->frequency,
        $template->master_teams
      );
      if ($current !== null) {
        $template->id = $current->id;
      }

      $template->description = DB::$V->incString($args, 'description', 1, 16000);

      $this->processPairings($template, $args);
      Session::pa(new PA(sprintf("Saved team race order template for %d teams in %d boats.",
                                 $template->num_teams,
                                 $template->num_boats)));
      $this->redirect('race-order');
    }

    // ------------------------------------------------------------
    // Edit existing
    // ------------------------------------------------------------
    if (isset($args['edit'])) {
      $template = DB::$V->reqID($args, 'template', DB::T(DB::RACE_ORDER), "Invalid template to edit.");
      $template->description = DB::$V->incString($args, 'description', 1, 16000);
      $this->processPairings($template, $args);
      Session::pa(new PA(sprintf("Edited template for %d teams in %d boats.",
                                 $template->num_teams,
                                 $template->num_boats)));
      $this->redirect('race-order');
    }

    // ------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------
    if (isset($args['delete'])) {
      $deleted = 0;
      foreach (DB::$V->reqList($args, 'template', null, "No list of templates to delete provided.") as $id) {
        $templ = DB::get(DB::T(DB::RACE_ORDER), $id);
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

  /**
   * Helper method to parse template metadata arguments.
   *
   * @param Array $args the GET/POST request items.
   * @return Race_Order an empty (sans races) template.
   * @throws SoterException when bad input is encountered.
   */
  private function createTemplateFromMetadata(Array $args) {
    $template = new Race_Order();

    $num_teams = DB::$V->reqString($args, 'teams', 1, 100, "Number of teams required.");
    $num_teams = preg_replace('/[^0-9]/', " ", $num_teams);
    $num_teams = preg_replace('/ +/', " ", $num_teams);
    $num_teams = trim($num_teams);
    if ($num_teams == "") {
      throw new SoterException("Invalid number of teams provided.");
    }
    $num_teams = explode(" ", $num_teams);

    if (count($num_teams) == 1) {
      $template->num_teams = $num_teams[0];
    }
    else {
      $parts = array();
      foreach ($num_teams as $value) {
        if ($value > 0) {
          $parts[] = $value;
          $template->num_teams += $value;
        }
      }
      $template->master_teams = $parts;
    }

    $template->num_divisions = DB::$V->reqInt($args, 'divs', 1, 5, "Invalid number of boats per team specified.");
    $template->frequency = DB::$V->reqKey($args, 'frequency', Race_Order::getFrequencyTypes(), "Invalid boat rotation frequency provided.");
    if ($template->frequency == Race_Order::FREQUENCY_NONE) {
      $template->num_boats = $template->num_divisions * $template->num_teams;
    }
    else {
      $template->num_boats = DB::$V->reqInt($args, 'boats', 1, 100, "Invalid number of boats specified.");
      if ($template->num_boats % ($template->num_divisions * 2) != 0) {
        throw new SoterException("Invalid number of boats in flight given number of boats per team.");
      }
    }
    return $template;
  }

  private function processPairings(Race_Order $template, Array $args) {
    $num_races = $template->num_teams * ($template->num_teams - 1) / 2;
    if ($template->master_teams !== null) {
      foreach ($template->master_teams as $num) {
        $num_races -= ($num * ($num - 1)) / 2;
      }
    }

    $dump = DB::$V->incString($args, 'race_order', 3, 16000, null);
    if ($dump !== null) {
      $teams1 = array();
      $teams2 = array();
      foreach (explode("\r\n", $dump) as $i => $line) {
        $line = preg_replace('/[^0-9]+/', ' ', trim($line));
        if (strlen($line) == 0)
          continue;

        $tokens = explode(" ", $line);
        if (count($tokens) != 2)
          throw new SoterException(sprintf("Invalid team pairing in line %d.", $i));
        $teams1[] = $tokens[0];
        $teams2[] = $tokens[1];
      }
    }
    else {
      $teams1 = DB::$V->reqList($args, 'team1', $num_races, "Invalid or incomplete list for \"Team A\".");
      $teams2 = DB::$V->reqList($args, 'team2', $num_races, "Invalid or incomplete list for \"Team B\".");
    }

    // All handshakes must be accounted for
    $handshakes = array();
    for ($i = 1; $i <= $template->num_teams; $i++) {
      for ($j = $i + 1; $j <= $template->num_teams; $j++) {
        $shake = sprintf("%d-%d", $i, $j);
        $handshakes[$shake] = $shake;
      }
    }
    if ($template->master_teams !== null) {
      $first = 1;
      foreach ($template->master_teams as $num) {
        $last = $first + $num;
        for ($i = $first; $i < $last; $i++) {
          for ($j = $i + 1; $j < $last; $j++) {
            $shake = sprintf("%d-%d", $i, $j);
            unset($handshakes[$shake]);
          }
        }
        $first = $last;
      }
    }

    $pairings = array();
    foreach ($teams1 as $i => $team1) {
      $team1 = DB::$V->reqInt($teams1, $i, 1, $template->num_teams + 1, "Invalid Team A index provided.");
      $team2 = DB::$V->reqInt($teams2, $i, 1, $template->num_teams + 1, "Invalid partner for Team A provided.");
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