<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

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
 * divisions in at least two of the chosen regattas, and are from the
 * conference(s) chosen. The user can alter the list by adding or
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

  const TYPE_COED = 'coed';
  const TYPE_WOMEN = 'women';
  const TYPE_ALL = 'all';
  private $types = array(self::TYPE_COED => "All sailors in Coed regattas only",
                         self::TYPE_WOMEN => "Only woman sailors in all regattas",
                         self::TYPE_ALL => "All sailors in all regattas");
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

                             'report-type' => null,
                             'report-role' => null,
                             'report-seasons' => null,
                             'report-confs' => null,
                             'report-min-regattas' => null,
                             ));
    $this->AA = Session::g('aa');
    $this->page_url = 'aa';
  }

  public function fillHTML(Array $args) {
    $this->PAGE->head->add(new LinkCSS('/inc/css/aa.css'));
    $this->PAGE->addContent($f = $this->createForm());
    $f->add($prog = new XP(array('id'=>'progressdiv')));

    // ------------------------------------------------------------
    // 0. Choose type and role
    // ------------------------------------------------------------
    if ($this->AA['report-type'] === null) {
      $prog->add(new XSubmitInput('unset-parameters', "Start", array('id'=>'progress-active', 'disabled'=>'disabled')));
      $prog->add(new XSpan("Regattas", array('class'=>'progress-disabled')));
      $prog->add(new XSpan("Sailors", array('class'=>'progress-disabled')));
      $prog->add(new XSpan("Download", array('class'=>'progress-disabled')));

      $this->PAGE->addContent($p = new XPort("Choose report"));
      $now = Season::forDate(DB::$NOW);
      $then = null;
      if ($now->season == Season::SPRING)
        $then = DB::getSeason(sprintf('f%0d', ($now->start_date->format('Y') - 1)));

      $p->add($form = $this->createForm());
      $form->add(new FItem("Report type:", XSelect::fromArray('type', $this->types)));

      $form->add(new FItem("Boat role:", XSelect::fromArray('role', array(RP::SKIPPER => "Skipper", RP::CREW => "Crew"))));
      $form->add(new FItem("Seasons:", $this->seasonList('', array($now, $then))));

      $form->add($fi = new FItem("Conferences:", $this->conferenceList('conf-')));
      $fi->set('title', "Only choose sailors from selected conference(s) automatically. You can manually choose sailors from other divisions.");

      $form->add($fi = new FItem("Min. # Regattas", new XTextInput('min-regattas', 2, array('size'=>3, 'maxlength'=>3, 'style'=>'min-width:3em'))));
      $fi->add(new XMessage("Sailors must qualify for at least this many regattas to be automatically considered."));

      $form->add(new XSubmitP('set-report', "Choose regattas →"));
      return;
    }

    $this->PAGE->head->add(new LinkCSS('/inc/css/widescreen.css'));

    $prog->add(new XSubmitInput('unset-parameters', "Start"));

    // ------------------------------------------------------------
    // 1. Step one: choose regattas. For women's reports, ICSA
    // requests that non-women's regattas may also be chosen for
    // inclusion. Note that male sailors should NOT be included in the
    // list of automatic sailors.
    //
    // For crew reports, do not include regattas with no crews in
    // them, whether singlehanded or otherwise
    // ------------------------------------------------------------
    if (count($this->AA['regattas']) == 0) {
      $prog->add(new XSubmitInput('unset-regattas', "Regattas", array('id'=>'progress-active', 'disabled'=>'disabled')));
      $prog->add(new XSpan("Sailors", array('class'=>'progress-disabled')));
      $prog->add(new XSpan("Download", array('class'=>'progress-disabled')));

      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa-table.js')));

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

      $this->PAGE->addContent($p = new XPort("Regattas"));
      if (count($regattas) == 0) {
        $p->add("There are no regattas in the chosen season(s) for inclusion.");
        return;
      }

      $p->add($form = $this->createForm());
      $form->add($tab = new XQuickTable(array('id'=>'regtable', 'class'=>'regatta-list'),
                                        array("", "Name", "Type", "Part.", "Date", "Status")));

      $invalid_regattas = 0;
      $types = array('championship', 'conference-championship', 'intersectional');
      foreach ($regattas as $reg) {
        $chosen = false;

        $id = 'r'.$reg->id;
        $rattr = array();
        $cattr = array('id'=>$id);

        if ($reg->finalized === null ||
            ($this->AA['report-type'] == self::TYPE_COED && $reg->participant != Regatta::PARTICIPANT_COED) ||
            ($this->AA['report-role'] == RP::CREW && $reg->getRpManager()->getMaximumCrewsAllowed() == 0)) {
          $rattr['class'] = 'disabled';
          $cattr['disabled'] = 'disabled';
          $invalid_regattas++;
        }
        elseif (in_array($reg->type->id, $types) &&
                ($this->AA['report-type'] != self::TYPE_WOMEN || $reg->participant == Regatta::PARTICIPANT_WOMEN)) {
          $cattr['checked'] = 'checked';
        }
        $tab->addRow(array(new XCheckboxInput("regatta[]", $reg->id, $cattr),
                           new XLabel($id, $reg->name),
                           new XLabel($id, $reg->type),
                           new XLabel($id, ($reg->participant == Regatta::PARTICIPANT_WOMEN) ? "Women" : "Coed"),
                           new XLabel($id, $reg->start_time->format('Y/m/d H:i')),
                           new XLabel($id, ($reg->finalized) ? "Final" : "Pending")),
                     $rattr);
      }
      if (count($regattas) == $invalid_regattas)
	$form->add(new XP(array(), "There are no regattas that match the necessary criteria for inclusion in the report. Start over with a different set of parameters."));
      else {
	$form->add(new XP(array(), "Next, choose the sailors to incorporate into the report."));
	$form->add(new XSubmitP('set-regattas', sprintf("Choose %ss →", $this->AA['report-role'])));
      }

      Session::s('aa', $this->AA);
      return;
    }

    $prog->add(new XSubmitInput('unset-regattas', "Regattas"));

    // ------------------------------------------------------------
    // 2. Step two: Choose sailors
    // ------------------------------------------------------------
    if (count($this->AA['sailors']) == 0) {
      $prog->add(new XSubmitInput('unset-sailors', "Sailors", array('id'=>'progress-active', 'disabled'=>'disabled')));
      $prog->add(new XSpan("Download", array('class'=>'progress-disabled')));

      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa-table.js')));
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa-search.js')));
      if ($this->AA['report-type'] == self::TYPE_WOMEN)
        $this->PAGE->head->add(new XScript('text/javascript', null, 'AASearcher.prototype.womenOnly=true;'));

      // Determine automatically-qualifying sailors
      $sailors = array();
      $sailor_count = array();
      foreach ($this->AA['regattas'] as $regatta) {
        foreach ($this->getQualifyingSailors($regatta) as $sailor) {
          if (!isset($sailor_count[$sailor->id]))
            $sailor_count[$sailor->id] = 0;
          $sailors[$sailor->id] = $sailor;
          $sailor_count[$sailor->id]++;
        }
      }

      foreach ($sailor_count as $id => $num) {
        if ($num < $this->AA['report-min-regattas'])
          unset($sailors[$id]);
      }
      usort($sailors, 'Member::compare');

      // provide a list of sailors that are already included in the
      // list, and a search box to add new ones
      $this->PAGE->addContent($p = new XPort("Sailors in list"));
      $p->add(new XP(array(), sprintf("%d sailors meet the criteria for All-American inclusion based on the regattas chosen. Note that non-official sailors have been excluded. Use the bottom form to add more sailors to this list.",
                                      count($sailors))));

      $p->add(new XP(array(), array("Uncheck the sailor below to ", new XStrong("remove"), " from the list.")));

      $p->add($form = $this->createForm());
      $form->add($item = new XQuickTable(array('id'=>'sailortable'), array("", "Name", "Year", "School")));
      foreach ($sailors as $sailor) {
        $id = 's' . $sailor->id;
        $item->addRow(array(new XCheckboxInput('sailor[]', $sailor->id, array('id'=>$id, 'checked'=>'checked')),
                            new XLabel($id, $sailor->getName()),
                            new XLabel($id, $sailor->year),
                            new XLabel($id, $sailor->school)));
      }

      // Form to fetch and add sailors
      $form->add(new XSubmitP('set-sailors', "Generate report →"));
      return;
    }

    $prog->add(new XSubmitInput('unset-sailors', "Sailors"));
    $prog->add(new XSubmitInput('gen-report', "Download", array('id'=>'progress-active', 'disabled'=>'disabled')));

    // ------------------------------------------------------------
    // 3. Step three: Generate and review
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Review report"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), sprintf("The report contains %d regattas and %d sailors. Click the \"Download as CSV\" button below to generate a CSV version of the report that can be opened with most spreadsheet software.",
                                       count($this->AA['regattas']),
                                       count($this->AA['sailors']))));
    $form->add(new XSubmitP('gen-report', "Download as CSV"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Start over
    // ------------------------------------------------------------
    if (isset($args['unset-parameters'])) {
      Session::s('aa', null);
      return false;
    }

    // ------------------------------------------------------------
    // Unset regatta choices
    // ------------------------------------------------------------
    if (isset($args['unset-regattas'])) {
      $this->AA['regattas'] = array();
      $this->AA['sailors'] = array();
      Session::s('aa', $this->AA);
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
      $this->AA['report-type'] = DB::$V->reqKey($args, 'type', $this->types, "Invalid report type provided.");
      $this->AA['report-role'] = DB::$V->reqValue($args, 'role', array(RP::SKIPPER, RP::CREW), "Invalid boat role provided.");

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
      $pos_confs = array();
      foreach ($this->USER->getConferences() as $conf)
        $pos_confs[$conf->id] = $conf;
      if (isset($args['confs']) && is_array($args['confs'])) {
        foreach ($args['confs'] as $s) {
          if (isset($pos_confs[$s]))
            $this->AA['report-confs'][$s] = $pos_confs[$s];
        }
      }
      if (count($this->AA['report-confs']) == 0) {
        $this->AA['report-confs'] = $pos_confs;
      }

      // number of minimum regattas in order to include
      $this->AA['report-min-regattas'] = DB::$V->incInt($args, 'min-regattas', 1, 100, 2);

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
          $allow_other_ptcp = ($this->AA['report-type'] != self::TYPE_COED ||
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
      $ids = DB::$V->reqList($args, 'sailor', null, "No sailor list provided.");
      $this->AA['sailors'] = array();

      $errors = 0;
      foreach ($ids as $id) {
        if (($sailor = DB::getSailor($id)) !== null)
          $this->AA['sailors'][$sailor->id] = $sailor;
        else
          $errors++;
      }
      if (count($this->AA['sailors']) == 0)
        throw new SoterException("There must be at least one sailor in the list.");
      if ($errors > 0)
        Session::pa(new PA(sprintf("%s invalid sailor IDs were invalid and ignored.", $errors), PA::I));
      
      Session::pa(new PA("Set sailors for report."));
      Session::s('aa', $this->AA);
      return;
    }

    // ------------------------------------------------------------
    // Alas! Make the report
    // ------------------------------------------------------------
    if (isset($args['gen-report'])) {
      // is the regatta and sailor list set?
      if (count($this->AA['regattas']) == 0 || count($this->AA['sailors']) == 0) {
        Session::pa(new PA("No regattas or sailors for report.", PA::E));
        return false;
      }

      $header1 = array("ID", "Sailor", "YR", "School", "Conf");
      $header2 = array("", "", "", "", "Races/Div");
      $spacer  = array("", "", "", "", "");
      $rows = array();

      foreach ($this->AA['regattas'] as $reg) {
	$header1[] = $reg->getURL();
        if ($reg->scoring != Regatta::SCORING_TEAM)
          $header2[] = count($reg->getRaces(Division::A()));
        else
          $header2[] = "";
	$spacer[] = "";
      }

      foreach ($this->AA['sailors'] as $id => $sailor) {
        $row = array($sailor->id,
                     sprintf("%s %s", $sailor->first_name, $sailor->last_name),
                     $sailor->year,
                     $sailor->school->nick_name,
                     $sailor->school->conference);
	foreach ($this->AA['regattas'] as $reg) {
	  $rps = array();
	  $num_divisions = count($reg->getDivisions());
          $data = $reg->getRpData($sailor, null, $this->AA['report-role']);

          // For team racing, combine all divisions into one
          if ($reg->scoring == Regatta::SCORING_TEAM) {
            $teamRPs = array(); // indexed by team ID
            foreach ($data as $rp) {
              $id = $rp->team_division->team->id;
              if (!isset($teamRPs[$id]))
                $teamRPs[$id] = $rp;
              else {
                $races = array_merge($teamRPs[$id]->race_nums, $rp->race_nums);
                sort($races, SORT_NUMERIC);
                $teamRPs[$id]->race_nums = array_unique($races);
              }
            }
            $data = $teamRPs;
          }

	  foreach ($data as $rp) {
	    $rank = $rp->rank;
	    if ($reg->scoring == Regatta::SCORING_COMBINED)
	      $rank .= 'com';
	    elseif ($num_divisions > 1 && $reg->scoring != Regatta::SCORING_TEAM)
	      $rank .= $rp->team_division->division;

            if ($reg->scoring == Regatta::SCORING_TEAM) {
              $part_races = $reg->getRacesForTeam(Division::A(), $rp->team_division->team);
              if (count($part_races) != count($rp->race_nums))
                $rank .= sprintf(' (%d%%)', round(100 * count($rp->race_nums) / count($part_races)));
            }
	    elseif (count($rp->race_nums) != count($reg->getRaces(Division::get($rp->team_division->division))))
	      $rank .= sprintf(' (%s)', DB::makeRange($rp->race_nums));

	    $rps[] = $rank;
	  }
	  $row[] = implode(", ", $rps);
	}
	$rows[] = $row;
      }

      $csv = "";
      $this->rowCSV($csv, $header1);
      $this->rowCSV($csv, $header2);
      $this->rowCSV($csv, $spacer);
      foreach ($rows as $row)
        $this->rowCSV($csv, $row);

      $filename = sprintf('%s-aa-%s-%s.csv',
                          date('Y'),
                          $this->AA['report-type'],
                          $this->AA['report-role']);
      header("Content-type: application/octet-stream");
      header("Content-Disposition: attachment; filename=$filename");
      header("Content-Length: " . strlen($csv));
      echo $csv;
      exit;
    }
    return false;
  }

  /**
   * Get list of sailors that qualify for inclusion in report.
   *
   * The rules for such a feat include a top 5 finish in A division,
   * and top 4 in any other.
   *
   * 2011-12-10: Respect conference membership.
   *
   * @param Regatta $reg the regatta
   */
  private function getQualifyingSailors(Regatta $reg) {
    $list = array();
    foreach ($reg->getDivisions() as $div) {
      $max_places = 4;
      if ($reg->scoring == Regatta::SCORING_TEAM || $div == Division::A())
        $max_places = 5;

      $rps = DB::getAll(DB::$DT_RP,
                        new DBBool(array(new DBCond('rank', $max_places, DBCond::LE),
                                         new DBCond('boat_role', $this->AA['report-role']),
                                         new DBCondIn('team_division', $reg->getRanks($div)))));

      foreach ($rps as $rp) {
        if ($rp->sailor->icsa_id !== null &&
            ($this->AA['report-type'] != self::TYPE_WOMEN || $rp->sailor->gender == Sailor::FEMALE) &&
            isset($this->AA['report-confs'][$rp->sailor->school->conference->id])) {
          $list[$rp->sailor->id] = $rp->sailor;
        }
      }
    }

    return $list;
  }
}
?>