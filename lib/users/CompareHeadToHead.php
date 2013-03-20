<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Compares up to three sailors head to head across a season or more,
 * and include only the finishing record.
 *
 * @author Dayan Paez
 * @version 2011-03-29
 * @see CompareSailorsByRace
 */
class CompareHeadToHead extends AbstractUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(Account $user) {
    parent::__construct("Compare sailors head to head", $user);
    $this->page_url = 'compare-sailors';
  }

  /**
   * Return the list of RPs for the given sailors for the regattas in
   * either the given list of seasons, or the given list of regatta IDs
   *
   * @param Sailor $sailor the sailor whose RPs to fetch
   * @param String|null $role specify non-null to limit to skipper/crew
   * @param Array $regs the regatta IDs (or empty)
   * @param Array $seasons the seasons array
   */
  private function getRPs(Sailor $sailor, $role, Array $regs = array(), Array $seasons = array()) {
    if (count($seasons) == 0) {
      if (count($regs) == 0)
        return array();
    }
    else {
      $regs = DB::prepGetAll(DB::$REGATTA, $db = new DBBool(array(), DBBool::mOR), array('id'));
      foreach ($seasons as $season)
        $db->add(new DBCond('dt_season', (string)$season));
    }
    $team_cond = DB::prepGetAll(DB::$TEAM, new DBCondIn('regatta', $regs), array('id'));
    $dteam_cond = DB::prepGetAll(DB::$DT_TEAM_DIVISION, new DBCondIn('team', $team_cond), array('id'));
    $rp_cond = new DBBool(array(new DBCond('sailor', $sailor->id), new DBCondIn('team_division', $dteam_cond)));
    if ($role !== null)
      $rp_cond->add(new DBCond('boat_role', $role));
    return DB::getAll(DB::$DT_RP, $rp_cond);
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
        if (($season = DB::get(DB::$SEASON, $s)) !== null)
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
        $sailor = DB::get(DB::$SAILOR, $id);
        if ($sailor !== null && $sailor->icsa_id !== null)
          $sailors[$sailor->id] = $sailor;
        else
          Session::pa(new PA("Invalid sailor id given ($id). Ignoring.", PA::I));
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
      // set to true
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

        $the_rps = $this->getRPs($sailor, $role, $regs, $the_seasons);

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
          if ($regattas[$reg]->scoring == Regatta::SCORING_COMBINED)
            $rank .= 'com';
          else
            $rank .= $key;
          if (count($rp->race_nums) != $rp->team_division->team->regatta->dt_num_races)
            $rank .= sprintf(' (%s)', DB::makeRange($rp->race_nums));
          if ($role === null)
            $rank .= " " . ucwords(substr($rp->boat_role, 0, 4));
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
        $row = array("Regatta", "Season");
        foreach ($sailors as $sailor) {
          $row[] = $sailor;
          $form->add(new XHiddenInput('sailor[]', $sailor->id));
        }
        $p->add($tab = new XQuickTable(array(), $row));

        $rowid = 0;
        foreach ($table as $rid => $divs) {
          if ($grouped) {
            $row = array($regattas[$rid]->name, $regattas[$rid]->getSeason());
            foreach ($sailors as $sailor) {
              $part = array();
              foreach ($divs as $list) {
                if (isset($list[$sailor->id]))
                  $part[] = $list[$sailor->id];
              }
              $row[] = implode('/', $part);
            }
            $tab->addRow($row, array('class'=>'row'.($rowid++ % 2)));
          }
          else {
            foreach ($divs as $list) {
              $row = array($regattas[$rid]->name, $regattas[$rid]->getSeason());
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
      $p->add(new FItem('Name:', $search = new XTextInput('name-search', "", array('id'=>'name-search'))));
      $p->add(new XUl(array('id'=>'aa-input'), array(new XLi("No sailors", array('class'=>'message')))));
    }

    // Season selection
    $form->add($p = new XPort("2. Seasons to compare"));
    $p->add(new XP(array(), "Choose at least one season to compare from the list below, then choose the sailors in the next panel."));
    $p->add($ul = new XUl(array('style'=>'list-style-type:none')));

    foreach ($all_seasons as $season) {
      $ul->add(new XLi(array($chk = new XCheckboxInput('seasons[]', $season, array('id' => $season)),
                             new XLabel($season, $season->fullString()))));;
      if (in_array((string)$season, $seasons))
        $chk->set('checked', 'checked');
    }

    // Other options, and submit
    if (!isset($sailors) || count($sailors) > 1) {
      $form->add($p = new XPort("3. Submit"));
      $p->add(new XP(array(), "By default, the report includes every regatta each sailor participated. Check the box below to limit the list to the regattas in which all sailors participated."));
      $p->add(new FItem($chk = new XCheckboxInput('head-to-head', 1, array('id' => 'f-req')),
                        new XLabel('f-req', "Only include records in which all sailors participated head-to-head.")));
      if (!$fullreq)
        $chk->set('checked', 'checked');

      $p->add(new XP(array(), "Head to head compares sailors that race against each other, that is: in the same division in the same regatta. To compare the sailors' records within the regatta regardless of division, check the box below. Note that this choice is only applicable if using full-records."));

      $p->add(new FItem($chk = new XCheckboxInput('grouped', 1, array('id' => 'f-grp')),
                        new XLabel('f-grp', "Group separate divisions in the same regatta in one row, instead of separately.")));
      if ($grouped)
        $chk->set('checked', 'checked');

      $p->add(new XP(array(), "You may limit inclusion in the report to a specific boat role (skipper or crew). The default, \"Both roles\" will include the sailor's role next to their score."));
      $p->add(new FItem("Sailing as:", XSelect::fromArray('boat_role',
                                                          array("" => "Both roles",
                                                                RP::SKIPPER => "Skipper only",
                                                                RP::CREW => "Crew only"),
                                                          $role)));
    }

    $form->add(new XSubmitP('set-sailors', "Fetch records"));
  }

  public function process(Array $args) {
    return false;
  }
}
?>