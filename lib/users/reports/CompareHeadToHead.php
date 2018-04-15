<?php
use \users\reports\AbstractReportPane;

/**
 * Compares up to three sailors head to head across a season or more,
 * and include only the finishing record.
 *
 * @author Dayan Paez
 * @version 2011-03-29
 * @see CompareSailorsByRace
 */
class CompareHeadToHead extends AbstractReportPane {
  /**
   * Creates a new pane
   */
  public function __construct(Account $user) {
    parent::__construct("Compare sailors head to head", $user);
  }

  /**
   * Get list of RPs for sailor in the regattas in list of seasons
   *
   * For team racing regattas, create a faux RP entry that combines
   * all the divisions and the different race numbers.
   *
   * @param Sailor $sailor the sailor whose RPs to fetch
   * @param String|null $role specify non-null to limit to skipper/crew
   * @param Array $seasons the seasons array
   */
  private function getRPs(Sailor $sailor, $role, Array $seasons = array()) {
    if (count($seasons) == 0)
        return array();

    $scond = new DBBool(array(), DBBool::mOR);
    foreach ($seasons as $season)
      $scond->add(new DBCond('dt_season', $season));

    // NON-TEAM SCORING REGATTAS
    $regs1 = DB::prepGetAll(
      DB::T(DB::PUBLIC_REGATTA),
      new DBBool(
        array(
          new DBCond('scoring', Regatta::SCORING_TEAM, DBCond::NE),
          $scond
        )
      ),
      array('id')
    );

    $team_cond1 = DB::prepGetAll(DB::T(DB::TEAM), new DBCondIn('regatta', $regs1), array('id'));
    $dteam_cond1 = DB::prepGetAll(DB::T(DB::DT_TEAM_DIVISION), new DBCondIn('team', $team_cond1), array('id'));
    $rp_cond1 = new DBBool(array(new DBCond('sailor', $sailor->id), new DBCondIn('team_division', $dteam_cond1)));
    if ($role !== null)
      $rp_cond1->add(new DBCond('boat_role', $role));
    $res1 = DB::getAll(DB::T(DB::DT_RP), $rp_cond1);

    // TEAM SCORING REGATTAS
    $regs2 = DB::prepGetAll(
      DB::T(DB::PUBLIC_REGATTA),
      new DBBool(
        array(
          new DBCond('scoring', Regatta::SCORING_TEAM),
          $scond
        )
      ),
      array('id')
    );

    $team_cond2 = DB::prepGetAll(DB::T(DB::TEAM), new DBCondIn('regatta', $regs2), array('id'));
    $dteam_cond2 = DB::prepGetAll(DB::T(DB::DT_TEAM_DIVISION), new DBCondIn('team', $team_cond2), array('id'));
    $rp_cond2 = new DBBool(array(new DBCond('sailor', $sailor->id), new DBCondIn('team_division', $dteam_cond2)));
    if ($role !== null)
      $rp_cond2->add(new DBCond('boat_role', $role));
    $res2 = DB::getAll(DB::T(DB::DT_RP), $rp_cond2);

    if (count($res2) == 0)
      return $res1;

    // COMBINE THE TWO LISTS
    $list = array();
    foreach ($res1 as $rp)
      $list[] = $rp;

    $entries = array(); // map of <teamID-boatRole>  => "master rp"
    foreach ($res2 as $rp) {
      $id = $rp->team_division->team->id . '-' . $rp->boat_role;
      if (!isset($entries[$id])) {
        $entries[$id] = $rp;
        $rp->team_division->division = "All";
        $list[] = $rp;
      }
      else {
        $nums = array_merge($entries[$id]->race_nums, $rp->race_nums);
        sort($nums, SORT_NUMERIC);
        $entries[$id]->race_nums = array_unique($nums);
      }
    }
    return $list;
  }

  public function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Parse parameters
    // ------------------------------------------------------------
    $fullreq = !isset($args['head-to-head']);
    $grouped = isset($args['grouped']);

    // seasons. If none provided, choose the current season, and
    // if now is spring, then also choose last fall
    $all_seasons = Season::getActive();
    if (count($all_seasons) == 0) {
      $this->PAGE->addContent(new XP(array(), "There are no seasons in the database for comparison."));
      return;
    }

    $seasons = array();
    if (isset($args['seasons']) && is_array($args['seasons'])) {
      foreach ($args['seasons'] as $s) {
        if (($season = DB::getSeason($s)) !== null)
          $seasons[] = $season;
      }
    }
    else {
      $now = new DateTime();
      $season = Season::forDate($now);
      if ($season !== null) {
        $seasons[] = $season;
        if ($season->season == Season::SPRING) {
          $season = $season->previousSeason();
          if ($season !== null)
            $seasons[] = $season;
        }
      }
      else {
        // Fetch the last season/year
        foreach ($all_seasons as $season) {
          $seasons[] = $season;
          if ($season->season == Season::FALL)
            break;
        }
      }
    }

    // specific role?
    $role = DB::$V->incKey($args, 'boat_role', array(RP::SKIPPER => "Skipper only", RP::CREW => "Crew only"));

    $this->PAGE->head->add(new XLinkCSS('text/css', '/inc/css/aa.css', 'screen', 'stylesheet'));
    $this->PAGE->addContent(new XP(array(), "Use this form to compare sailors head-to-head, showing the regattas that the sailors have sailed in common, and printing their place finish for each."));
    $this->PAGE->addContent($form = $this->createForm(XForm::GET));

    // Sailor search
    // Look for sailors as an array named 'sailors'
    if (isset($args['sailor']) || isset($args['sailors'])) {
      if (isset($args['sailor'])) {
        if (!is_array($args['sailor'])) {
          Session::pa(new PA("Invalid parameter given for comparison.", PA::E));
          WS::go('/compare-sailors');
        }
        $list = $args['sailor'];
      }
      elseif (isset($args['sailors']))
        $list = explode(',', (string)$args['sailors']);

      // get sailors
      $sailors = array();
      foreach ($list as $id) {
        $sailor = DB::get(DB::T(DB::SAILOR), $id);
        if ($sailor !== null && $sailor->isRegistered()) {
          $sailors[$sailor->id] = $sailor;
        }
        else {
          Session::pa(new PA("Invalid sailor id given ($id). Ignoring.", PA::I));
        }
      }

      // Any sailors at all?
      if (count($sailors) == 0) {
        $this->PAGE->addContent(new XP(array(), "No valid sailors provided for comparison."));
        return;
      }

      $form->add(new XP(array(), new XA(WS::link('/compare-sailors'), "← Start over")));
      // (reg_id => (division => (sailor_id => <rank races>))) Go
      // through each of the sailors, keeping only the regatta and
      // divisions which they have in common, UNLESS full-record is
      // set to true.
      $table = array();
      $regattas = array();
      $sailors = array_values($sailors);
      $the_seasons = $seasons;
      foreach ($sailors as $s_num => $sailor) {
        if ($s_num == 0)
          $regs = array();
        elseif (!$fullreq) {
          $regs = array_keys($regattas);
          $the_seasons = array();
        }

        $the_rps = $this->getRPs($sailor, $role, $the_seasons);

        $my_table = array();
        foreach ($the_rps as $rp) {
          $key = $rp->team_division->division;
          $reg = $rp->team_division->team->regatta->id;
          $my_table[$reg] = array();

          if (!isset($table[$reg])) {
            $table[$reg] = array();
            $regattas[$reg] = DB::getRegatta($reg);
          }
          if (!isset($table[$reg][$key]))
            $table[$reg][$key] = array();

          $rank = $rp->rank;
          if ($rp->team_division->team->regatta->scoring == Regatta::SCORING_STANDARD)
            $rank .= $key;
          if ($rp->team_division->team->regatta->scoring == Regatta::SCORING_COMBINED)
            $rank .= 'com';
          $rank = array(new XA(sprintf('http://%s%sfull-scores/', Conf::$PUB_HOME, $regattas[$reg]->getURL()), $rank,
                               array('onclick'=>'this.target="scores";')));

          if ($regattas[$reg]->scoring == Regatta::SCORING_TEAM) {
            $part_races = $regattas[$reg]->getTeamRacesFor($rp->team_division->team);
            if (count($part_races) != count($rp->race_nums))
              $rank[] = sprintf(' (%d%%)', round(100 * count($rp->race_nums) / count($part_races)));
          }
          elseif (count($rp->race_nums) != $rp->team_division->team->regatta->dt_num_races) {
            $rank[] = sprintf(' (%s)', DB::makeRange($rp->race_nums));
          }

          if ($role === null)
            $rank[] = " " . ucwords(substr($rp->boat_role, 0, 4));
          $table[$reg][$key][$rp->sailor->id] = $rank;
          $my_table[$reg][$key] = $rank;
        }

        // If not full records, then remove unused regattas
        if ($s_num > 0 && !$fullreq) {
          $copy = $table;
          foreach ($copy as $id => $val) {
            if (!isset($my_table[$id])) {
              unset($table[$id]);
              unset($regattas[$id]);
            }
            elseif (!$grouped) {
              foreach ($val as $div => $vall) {
                if (!isset($my_table[$id][$div])) {
                  unset($table[$id][$div]);
                  if (count($table[$id]) == 1) {
                    unset($table[$id]);
                    unset($regattas[$id]);
                  }
                }
              }
            }
          }
          unset($copy);
        }
      }

      // are there any regattas in common?
      $title = "Compare sailors head-to-head";
      if ($role !== null)
        $title .= " as " . $role;
      $form->add($p = new XPort($title));
      if (count($table) == 0) {
        $p->add(new XP(array(), sprintf("The sailors provided (%s) have not sailed head to head %s in any division in any of the regattas in the seasons specified.",
                                        implode(", ", $sailors),
                                        ($role === null) ? "" : ("as  " . $role))));
      }
      else {
	// Sort regattas by start time
	uasort($regattas, 'Regatta::cmpStart');
        $row = array("Regatta", "Season");
        foreach ($sailors as $sailor) {
          $row[] = $sailor;
          $form->add(new XHiddenInput('sailor[]', $sailor->id));
        }
        $p->add($tab = new XQuickTable(array(), $row));

        $rowid = 0;
	foreach ($regattas as $rid => $regatta) {
	  $divs = $table[$rid];
          if ($grouped) {
            $row = array(new XStrong($regattas[$rid]->name), $regattas[$rid]->getSeason()->fullString());
            foreach ($sailors as $sailor) {
              $cell = new XTD();
              $i = 0;
              foreach ($divs as $list) {
                if (isset($list[$sailor->id])) {
                  if ($i++ > 0)
                    $cell->add(", ");
                  foreach ($list[$sailor->id] as $sub)
                    $cell->add($sub);
                }
              }
              $row[] = $cell;
            }
            $tab->addRow($row, array('class'=>'row'.($rowid++ % 2)));
          }
          else {
            foreach ($divs as $list) {
              $row = array(new XStrong($regattas[$rid]->name), $regattas[$rid]->getSeason()->fullString());
              foreach ($sailors as $sailor) {
                if (isset($list[$sailor->id]))
                  $row[] = $list[$sailor->id];
                else
                  $row[] = "";
              }
              $tab->addRow($row, array('class'=>'row'.($rowid++ % 2)));
            }
          }
        }
      }
    }
    else {
      // ------------------------------------------------------------
      // Provide an input box to choose sailors using AJAX
      // ------------------------------------------------------------
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/aa.js')));
      $form->add($p = new XPort("1. Choose sailors"));
      $p->add(new XNoScript(new XP(array(), "Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding.")));
      $p->add(new FItem('Name:', $search = new XSearchInput('name-search', "", array('id'=>'name-search'))));
      $p->add(new XUl(array('id'=>'aa-input'), array(new XLi("No sailors", array('class'=>'message')))));
    }

    // Season selection
    require_once('xml5/XMultipleSelect.php');
    $form->add($p = new XPort("2. Seasons to compare"));
    $p->add(new XP(array(), "Choose at least one season to compare from the list below, then choose the sailors in the next panel."));
    $p->add(new FReqItem("Seasons:", $ul = new XMultipleSelect('seasons[]')));

    foreach ($all_seasons as $season) {
      $ul->addOption($season, $season->fullString(), in_array((string)$season, $seasons));
    }

    // Other options, and submit
    if (!isset($sailors) || count($sailors) > 1) {
      $form->add($p = new XPort("3. Submit"));
      $mes = "By default, the report includes every regatta in which each sailor participated. Check the box below to limit the list to the regattas in which all sailors participated.";
      $p->add(new FItem("Limit regattas:", new FCheckbox('head-to-head', 1, "Only include records in which all sailors participated head-to-head.", !$fullreq), $mes));

      $mes = "Head to head compares sailors that race against each other, that is: in the same division in the same regatta. To compare the sailors' records within the regatta regardless of division, check the box. Note that this choice is only applicable if using full-records.";
      $p->add($fi = new FItem("Group divisions:", new FCheckbox('grouped', 1, "Group separate divisions in the same regatta in one row, instead of separately.", $grouped), $mes));

      $mes = "You may limit inclusion in the report to a specific boat role (skipper or crew). The default, \"Both roles\" will include the sailor's role next to their score.";
      $p->add(new FItem("Sailing as:", XSelect::fromArray('boat_role',
                                                          array("" => "Both roles",
                                                                RP::SKIPPER => "Skipper only",
                                                                RP::CREW => "Crew only"),
                                                          $role),
			$mes));
    }

    $form->add(new XSubmitP('set-sailors', "Fetch records"));
  }

  public function process(Array $args) {
    throw new SoterException("Nothing to process here.");
  }
}
?>
