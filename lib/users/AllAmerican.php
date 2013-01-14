<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');
require_once('regatta/ScoresAnalyzer.php');
require_once('regatta/TeamDivision.php');

/**
 * Generates All-American reports.
 *
 * The process starts with the selection of the report parameters,
 * including the regatta participation, the boat role, the conferences
 * and the seasons in question.
 *
 * The next step is to select the regattas that will be included in
 * the report. Sufficiently important regattas are included by
 * default, with the user able to add/remove from the list.
 *
 * After that, the sailors are chosen. The default set includes those
 * who finished in the top 5 in A division, or top 4 in the other
 * divisions in any of the chosen regattas, and are from the
 * conference(s) chosen. The user can alter the list by adding
 * removing sailors.
 *
 * Based on chosen regattas and sailors, the report is created. For
 * each combination of the two, a participation record is generated,
 * consisting of the rank for just the races the user participated in,
 * as well as the division (if applicable).
 *
 * Because of the volume of regattas and sailors involved, the
 * information is maintained in the session rather than being
 * forwarded along using GET variables. This also makes it possible
 * for the user to "come back" to this pane without necessarily losing
 * their work.
 *
 * @author Dayan Paez
 * @version 2011-03-29
 */
class AllAmerican extends AbstractUserPane {
  private $AA;
  /**
   * Creates a new pane
   */
  public function __construct(Account $user) {
    parent::__construct("All-American", $user);
    if (!Session::has('aa'))
      Session::s('aa', array('table' => array(),
                             'regattas' => array(),
                             'regatta_races' => array(),
                             'sailors' => array(),

                             'report-participation' => null,
                             'report-role' => null,
                             'report-seasons' => null,
                             'report-confs' => null,
                             ));
    $this->AA = Session::g('aa');
    $this->page_url = 'aa';
  }

  private function seasonList($prefix, Array $preselect = array()) {
    $ul = new XUl(array('class'=>'inline-list'));
    foreach (Season::getActive() as $season) {
      $ul->add(new XLi(array($chk = new XCheckboxInput('seasons[]', $season, array('id' => $prefix . $season)),
                             new XLabel($prefix . $season, $season->fullString()))));
      if (in_array($season, $preselect))
        $chk->set('checked', 'checked');
    }
    return $ul;
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // 0. Choose participation and role
    // ------------------------------------------------------------
    if ($this->AA['report-participation'] === null) {
      $this->PAGE->addContent($p = new XPort("Choose report"));
      $now = Season::forDate(DB::$NOW);
      $then = null;
      if ($now->season == Season::SPRING)
        $then = DB::getSeason(sprintf('f%0d', ($now->start_date->format('Y') - 1)));

      $p->add($form = $this->createForm());
      $form->add(new FItem("Participation:", XSelect::fromArray('participation',
                                                                array(Regatta::PARTICIPANT_COED => "Coed",
                                                                      Regatta::PARTICIPANT_WOMEN => "Women"))));

      $form->add(new FItem("Boat role:", XSelect::fromArray('role', array(RP::SKIPPER => "Skipper", RP::CREW => "Crew"))));
      $form->add(new FItem("Seasons:", $this->seasonList('', array($now, $then))));

      $form->add($fi = new FItem("Conferences:", $ul2 = new XUl(array('class'=>'inline-list'))));
      $fi->set('title', "Only choose sailors from selected conference(s) automatically. You can manually choose sailors from other divisions.");

      // Conferences
      foreach (DB::getConferences() as $conf) {
        $ul2->add(new XLi(array($chk = new XCheckboxInput('confs[]', $conf, array('id' => $conf->id)),
                                new XLabel($conf->id, $conf))));
        $chk->set('checked', 'checked');
      }

      $form->add(new XSubmitP('set-report', "Choose regattas →"));

      $this->PAGE->addContent($p = new XPort("Special crew report"));
      $p->add($form = $this->createForm());
      $form->add(new XP(array(),
                        array("To choose crews from ",
                              new XStrong("all"),
                              " regattas regardless of participation, choose the seasons and click the button below.")));

      $form->add(new FItem("Season(s):", $this->seasonList('ss', array($now, $then))));
      $form->add(new XSubmitP('set-special-report', "All crews →"));
      return;
    }

    $this->PAGE->head->add(new LinkCSS('/inc/css/widescreen.css'));
    $this->PAGE->head->add(new LinkCSS('/inc/css/aa.css'));

    // ------------------------------------------------------------
    // 1. Step one: choose regattas. For women's reports, ICSA
    // requests that non-women's regattas may also be chosen for
    // inclusion. Note that male sailors should NOT be included in the
    // list of automatic sailors.
    // ------------------------------------------------------------
    if (count($this->AA['regattas']) == 0) {
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa-table.js')));
      // Add button to go back
      $this->PAGE->addContent($p = new XPort("Progress"));
      $p->add($form = $this->createForm());
      $form->add(new XSubmitP('unset-regattas', "← Start over"));

      // Reset lists
      $this->AA['table'] = array();
      $this->AA['regattas'] = array();
      $this->AA['regatta_races'] = array();
      $this->AA['sailors'] = array();
      Session::s('aa', $this->AA);

      $seasons = array();
      foreach ($this->AA['report-seasons'] as $season)
        $seasons[] = DB::getSeason($season);
      $regattas = Season::getRegattasInSeasons($seasons);
      $qual_regattas = array();

      $this->PAGE->addContent($p = new XPort("Regattas"));
      if (count($regattas) == 0) {
        $p->add("There are no regattas in the chosen season(s) for inclusion.");
        return;
      }

      $p->add($form = $this->createForm());
      $form->add($tab = new XQuickTable(array('id'=>'regtable', 'class'=>'regatta-list'),
                                        array("", "Name", "Type", "Part.", "Date", "Status")));

      $types = array('championship', 'conference-championship', 'intersectional');
      foreach ($regattas as $reg) {
        $chosen = false;

        $id = 'r'.$reg->id;
        $rattr = array();
        $cattr = array('id'=>$id);

        if ($reg->finalized !== null &&
            $reg->scoring != Regatta::SCORING_TEAM &&
            ($reg->participant == $this->AA['report-participation'] || 'special' == $this->AA['report-participation']) &&
            in_array($reg->type->id, $types)) {
          $cattr['checked'] = 'checked';
        }
        elseif ($reg->finalized === null ||
                ($reg->participant != $this->AA['report-participation'] &&
                 Regatta::PARTICIPANT_COED == $this->AA['report-participation'])) {
            $rattr['class'] = 'disabled';
            $cattr['disabled'] = 'disabled';
        }
        $tab->addRow(array(new XCheckboxInput("regatta[]", $reg->id, $cattr),
                           new XLabel($id, $reg->name),
                           new XLabel($id, $reg->type),
                           new XLabel($id, ($reg->participant == Regatta::PARTICIPANT_WOMEN) ? "Women" : "Coed"),
                           new XLabel($id, $reg->start_time->format('Y/m/d H:i')),
                           new XLabel($id, ($reg->finalized) ? "Final" : "Pending")),
                     $rattr);
      }
      $form->add(new XP(array(), "Next, choose the sailors to incorporate into the report."));
      $form->add(new XSubmitP('set-regattas', sprintf("Choose %ss →", $this->AA['report-role'])));

      Session::s('aa', $this->AA);
      return;
    }

    // ------------------------------------------------------------
    // 2. Step two: Choose sailors
    // ------------------------------------------------------------
    if (count($this->AA['sailors']) == 0) {
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa-table.js')));

      // Add button to go back
      $this->PAGE->addContent($p = new XPort("Progress"));
      $p->add($form = $this->createForm());
      $form->add(new XSubmitP('unset-regattas', "← Start over"));

      // Determine automatically-qualifying sailors
      $sailors = array();
      foreach ($this->AA['regattas'] as $regatta) {
        foreach ($this->getQualifyingSailors($regatta) as $sailor) {
          $sailors[$sailor->id] = $sailor;
        }
      }
      usort($sailors, 'Member::compare');

      // provide a list of sailors that are already included in the
      // list, and a search box to add new ones
      $this->PAGE->addContent($p = new XPort("Sailors in list"));
      $p->add(new XP(array(), sprintf("%d sailors meet the criteria for All-American inclusion based on the regattas chosen. Note that non-official sailors have been excluded. Use the bottom form to add more sailors to this list.",
                                      count($sailors))));

      $p->add(new XP(array(), array("Uncheck the sailor below to ", new XStrong("remove"), " from the list.")));

      $p->add($item = new XQuickTable(array('id'=>'sailortable'), array("", "Name", "Year", "School")));
      foreach ($sailors as $sailor) {
        $id = 's' . $sailor->id;
        $item->addRow(array(new XCheckboxInput('remove-sailor[]', $sailor->id, array('id'=>$id, 'checked'=>'checked')),
                            new XLabel($id, $sailor->getName()),
                            new XLabel($id, $sailor->year),
                            new XLabel($id, $sailor->school)));
      }

      // Form to fetch and add sailors
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa.js')));
      $this->PAGE->addContent($p = new XPort("New sailors"));
      $p->add($form = $this->createForm());
      $form->add(new XNoScript(new XP(array(), "Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding.")));
      $form->add(new FItem('Name:', $search = new XTextInput('name-search', "")));
      $search->set('id', 'name-search');
      $form->add($ul = new XUl(array('id'=>'aa-input'),
                               array(new XLi("No sailors.", array('class'=>'message')))));
      $form->add(new XSubmitP('set-sailors', "Generate report →"));

      return;
    }

    ini_set('memory_limit', '128M');
    ini_set('max_execution_time', 60);
    // ------------------------------------------------------------
    // 3. Step three: Generate and review
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Report"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "Please click only once:"));
    $form->add(new XSubmitP('gen-report', "Download as CSV"));

    $p->add($form = $this->createForm());
    $form->add(new XSubmitP('unset-sailors', "← Go back"));

    $this->PAGE->addContent(new XTable(array('id'=>'aa-table'),
                                       array(new XTHead(array(),
                                                        array($hrow1 = new XTR(array(),
                                                                               array(new XTH(array(), "ID"),
                                                                                     new XTH(array(), "Sailor"),
                                                                                     new XTH(array(), "YR"),
                                                                                     new XTH(array(), "School"),
                                                                                     new XTH(array(), "Conf."))),
                                                              $hrow2 = new XTR(array(),
                                                                               array(new XTH(array(), ""),
                                                                                     new XTH(array(), ""),
                                                                                     new XTH(array(), ""),
                                                                                     new XTH(array(), ""),
                                                                                     new XTH(array(), "Races/Div"))))),
                                             $table = new XTBody())));
    foreach ($this->AA['regatta_races'] as $reg_id => $num) {
      $hrow1->add(new XTH(array('class'=>'rotate'), $reg_id));
      $hrow2->add(new XTH(array(), $num));
    }
    $TABLE = $this->AA['table'];
    $row_num = 0;
    foreach ($this->AA['sailors'] as $id => $sailor) {
      $table->add($row = new XTR(array('class'=>'row'.($row_num++ % 2)),
                                 array(new XTD(array(), $sailor->id),
                                       new XTD(array(), sprintf("%s %s", $sailor->first_name, $sailor->last_name)),
                                       new XTD(array(), $sailor->year),
                                       new XTD(array(), $sailor->school->nick_name),
                                       new XTD(array(), $sailor->school->conference))));

      foreach ($TABLE as $reg_id => $sailor_list) {
        if (!isset($sailor_list[$id])) {
          $this->AA['table'][$reg_id][$id] = array();

          // "Reverse" populate table
          $regatta = DB::getRegatta($this->AA['regattas'][$reg_id]);
          $rpm = $regatta->getRpManager();
          $rps = $rpm->getParticipation($sailor, $this->AA['report-role']);

          foreach ($rps as $rp) {
            $team = ScoresAnalyzer::getTeamDivision($rp->team, $rp->division);
            if ($team === null) {
              echo "<pre>"; print_r($regatta); "</pre>";
              exit;
            }
            $content = sprintf('%d%s', $team->rank, $team->division);
            if (count($rp->races_nums) != $this->AA['regatta_races'][$reg_id])
              $content .= sprintf(' (%s)', DB::makeRange($rp->races_nums));

            $this->AA['table'][$reg_id][$id][] = $content;
          }
        }
        $row->add(new XTD(array(), implode("/", $this->AA['table'][$reg_id][$id])));
      }
    }
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Unset regatta choice (start over)
    // ------------------------------------------------------------
    if (isset($args['unset-regattas'])) {
      Session::s('aa', null);
      return false;
    }

    // ------------------------------------------------------------
    // Unset sailor choices
    // ------------------------------------------------------------
    if (isset($args['unset-sailors'])) {
      $this->AA['sailors'] = array();
      Session::s('aa', $this->AA);
      return false;
    }

    // ------------------------------------------------------------
    // Choose report
    // ------------------------------------------------------------
    if (isset($args['set-report'])) {
      if (!isset($args['participation']) ||
          !in_array($args['participation'], array(Regatta::PARTICIPANT_COED, Regatta::PARTICIPANT_WOMEN))) {
        Session::pa(new PA("Invalid participation provided.", PA::E));
        return false;
      }
      if (!isset($args['role']) ||
          !in_array($args['role'], array(RP::SKIPPER, RP::CREW))) {
        Session::pa(new PA("Invalid role provided.", PA::E));
        return false;
      }

      // seasons. If none provided, choose the default
      $this->AA['report-seasons'] = array();
      if (isset($args['seasons']) && is_array($args['seasons'])) {
        foreach ($args['seasons'] as $s) {
          if (($season = DB::getSeason($s)) !== null)
            $this->AA['report-seasons'][] = (string)$season;
        }
      }
      if (count($this->AA['report-seasons']) == 0) {
        $now = new DateTime();
        $season = Season::forDate(DB::$NOW);
        $this->AA['report-seasons'][] = (string)$season;
        if ($season->season == Season::SPRING) {
          $now->setDate($now->format('Y') - 1, 10, 1);
          $this->AA['report-seasons'][] = (string)$season;
        }
      }

      // conferences. If none provided, choose ALL
      $this->AA['report-confs'] = array();
      if (isset($args['confs']) && is_array($args['confs'])) {
        foreach ($args['confs'] as $s) {
          if (($conf = DB::getConference($s)) !== null)
            $this->AA['report-confs'][$conf->id] = $conf->id;
        }
      }
      if (count($this->AA['report-confs']) == 0) {
        foreach (DB::getConferences() as $conf)
          $this->AA['report-confs'][$conf->id] = $conf->id;
      }

      $this->AA['report-participation'] = $args['participation'];
      $this->AA['report-role'] = $args['role'];
      Session::s('aa', $this->AA);
      return false;
    }
    // Special report
    if (isset($args['set-special-report'])) {
      // seasons. If none provided, choose the default
      $this->AA['report-seasons'] = array();
      if (isset($args['seasons']) && is_array($args['seasons'])) {
        foreach ($args['seasons'] as $s) {
          if (($season = DB::getSeason($s)) !== null)
            $this->AA['report-seasons'][] = (string)$season;
        }
      }
      if (count($this->AA['report-seasons']) == 0) {
        $now = new DateTime();
        $season = Season::forDate(DB::$NOW);
        $this->AA['report-seasons'][] = $season;
        if ($season->season == Season::SPRING) {
          $now->setDate($now->format('Y') - 1, 10, 1);
          $this->AA['report-seasons'][] = (string)$season;
        }
      }

      $this->AA['report-participation'] = 'special';
      $this->AA['report-role'] = 'crew';
      Session::s('aa', $this->AA);
      return false;
    }

    // ------------------------------------------------------------
    // Choose regattas
    // ------------------------------------------------------------
    if (isset($args['set-regattas'])) {
      $ids = DB::$V->reqList($args, 'regatta', null, "No regattas provided.");
      if (count($ids) == 0)
        throw new SoterException("At least one regatta must be chosen.");

      $regs = array();
      $errors = 0;
      foreach ($ids as $id) {
        try {
          $reg = DB::getRegatta($id);
          $allow_other_ptcp = ($this->AA['report-participation'] != Regatta::PARTICIPANT_COED ||
                               $reg->participant == Regatta::PARTICIPANT_COED);
          if ($reg->private === null && $allow_other_ptcp && $reg->finalized !== null)
            $this->AA['regattas'][$reg->id] = $reg;
          else
            $errors++;
        }
        catch (Exception $e) {
          $errors++;
        }
      }
      if ($errors > 0)
        Session::pa(new PA("Some regattas specified are not valid.", PA::I));
      if (count($this->AA['regattas']) == 0)
        throw new SoterException("At least one valid regatta must be chosen.");
      Session::pa(new PA("Set regattas for All-American report."));
      Session::s('aa', $this->AA);
      return;
    }

    // ------------------------------------------------------------
    // Set sailors
    // ------------------------------------------------------------
    if (isset($args['set-sailors'])) {

      if (!isset($args['sailor']) || !is_array($args['sailor']) || count($args['sailor']) == 0) {
        Session::pa(new PA("Proceeding with no additional sailors."));
        Session::s('aa', $this->AA);
        return false;
      }

      // Add sailors, if not already in the 'sailors' list
      $errors = 0;
      foreach ($args['sailor'] as $id) {
        try {
          $sailor = DB::getSailor($id);
          $this->AA['sailors'][$sailor->id] = $sailor;
        } catch (Exception $e) {
          $errors++;
          Session::pa(new PA($e->getMessage(), PA::E));
        }
      }
      if ($errors > 0)
        Session::pa(new PA("Some invalid sailors were provided and ignored.", PA::I));
      Session::pa(new PA("Set sailors for report."));
      Session::s('aa', $this->AA);
      return false;
    }

    // ------------------------------------------------------------
    // Alas! Make the report
    // ------------------------------------------------------------
    if (isset($args['gen-report'])) {
      // is the regatta and sailor list set?
      if (count($this->AA['table']) == 0 ||
          count($this->AA['sailors']) == 0) {
        Session::pa(new PA("No regattas or sailors for report.", PA::E));
        return false;
      }

      $filename = sprintf('%s-aa-%s-%s.csv',
                          date('Y'),
                          $this->AA['report-participation'],
                          $this->AA['report-role']);
      header("Content-type: application/octet-stream");
      header("Content-Disposition: attachment; filename=$filename");

      $header1 = array("ID", "Sailor", "YR", "School", "Conf");
      $header2 = array("", "", "", "", "Races/Div");
      $spacer  = array("", "", "", "", "");
      $rows = array();

      foreach ($this->AA['sailors'] as $id => $sailor) {
        $row = array($sailor->id,
                     sprintf("%s %s", $sailor->first_name, $sailor->last_name),
                     $sailor->year,
                     $sailor->school->nick_name,
                     $sailor->school->conference);
        foreach ($this->AA['table'] as $reg_id => $sailor_list) {
          if (isset($sailor_list[$id]))
            $row[] = implode("/", $sailor_list[$id]);
          else
            $row[] = "";
          $header1[$reg_id] = $reg_id;
          $header2[$reg_id] = $this->AA['regatta_races'][$reg_id];
        }
        $rows[] = $row;
      }

      $this->csv = "";
      $this->rowCSV($header1);
      $this->rowCSV($header2);
      $this->rowCSV($spacer);
      foreach ($rows as $row)
        $this->rowCSV($row);

      header("Content-Length: " . strlen($this->csv));
      echo $this->csv;
      exit;
    }
    return false;
  }

  private $csv = "";
  private function rowCSV(Array $cells) {
    $quoted = array();
    foreach ($cells as $cell) {
      if (is_numeric($cell))
        $quoted[] = $cell;
      else
        $quoted[] = sprintf('"%s"', str_replace('"', '""', $cell));
    }
    $this->csv .= implode(',', $quoted) . "\n";
  }

  /**
   * Get list of sailors that qualify for inclusion in report.
   *
   * The rules for such a feat include a top 5 finish in A division,
   * and top 4 in any other. This method will also fill the
   * appropriate Session variables with the pertinent information
   * regarding this regatta, such as number of races.
   *
   * 2011-12-10: Respect conference membership.
   *
   * @param Regatta $reg the regatta
   */
  private function getQualifyingSailors(Regatta $reg) {
    $list = array();
    foreach ($reg->getDivisions() as $div) {
      $max_places = ($div == Division::A()) ? 5 : 4;
      foreach ($reg->getRanks($div) as $i => $rank) {
        if ($i >= $max_places)
          break;

        foreach ($rank->getRP($this->AA['report-role']) as $rp) {
          if ($rp->sailor->icsa_id !== null &&
              ($this->AA['report-participation'] != Regatta::PARTICIPANT_WOMEN || $rp->sailor->gender == Sailor::FEMALE) &&
              isset($this->AA['report-confs'][$rp->sailor->school->conference->id])) {
            $list[$rp->sailor->id] = $rp->sailor;
          }
        }
      }
    }
    return $list;
  }

  /**
   * Determines the sailors who, based on their performance in the
   * given regatta, merit inclusion in the report.
   *
   * The rules for such a feat include a top 5 finish in A division,
   * and top 4 in any other. This method will also fill the
   * appropriate Session variables with the pertinent information
   * regarding this regatta, such as number of races.
   *
   * 2011-12-10: Respect conference membership.
   *
   * @param Regatta $reg the regatta whose information to incorporate
   * into the table
   */
  private function populateSailors(Regatta $reg) {
    // use season/nick-name to sort
    $id = sprintf('%s/%s', $reg->getSeason(), $reg->nick);
    $this->AA['regatta_races'][$id] = count($reg->getRaces(Division::A()));
    $this->AA['regattas'][$id] = $reg->id;
    if (!isset($this->AA['table'][$id]))
      $this->AA['table'][$id] = array();

    // grab a list of lucky teams
    $teams = array(); 
    foreach ($reg->getDivisions() as $div) {
      $place = ($div == Division::A()) ? 5 : 4;
      foreach (ScoresAnalyzer::getHighFinishingTeams($reg, $div, $place) as $team)
        $teams[] = $team;
    }

    // get sailors participating in those lucky teams
    $rpm = $reg->getRpManager();
    $sng = $reg->isSingleHanded();
    foreach ($teams as $team) {
      foreach ($rpm->getRP($team->team,
                           $team->division,
                           $this->AA['report-role']) as $rp) {

        if ($rp->sailor->icsa_id !== null &&
            ($this->AA['report-participation'] != Regatta::PARTICIPANT_WOMEN ||
             $rp->sailor->gender == Sailor::FEMALE) &&
            isset($this->AA['report-confs'][$rp->sailor->school->conference->id])) {
          $content = ($sng) ? $team->rank : sprintf('%d%s', $team->rank, $team->division);
          if (count($rp->races_nums) != $this->AA['regatta_races'][$id])
            $content .= sprintf(' (%s)', DB::makeRange($rp->races_nums));

          if (!isset($this->AA['table'][$id][$rp->sailor->id]))
            $this->AA['table'][$id][$rp->sailor->id] = array();
          $this->AA['table'][$id][$rp->sailor->id][] = $content;

          if (!isset($this->AA['sailors'][$rp->sailor->id]))
            $this->AA['sailors'][$rp->sailor->id] = $rp->sailor;
        }
      }
    }
  }
}
?>