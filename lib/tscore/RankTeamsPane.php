<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Provide visual organization of teams to rank, applicable to team
 * racing only.
 *
 * 2013-01-10: Provide interface for ignoring races from record
 *
 * @author Dayan Paez
 * @version 2013-01-05
 */
class RankTeamsPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Rank teams", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new SoterException("Pane only available for team racing regattas.");
  }

  protected function fillTeam(Team $team) {
    require_once('regatta/Rank.php');

    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/team-rank.js')));
    $this->PAGE->addContent($p = new XPort("Race record for " . $team));
    $p->add($f = $this->createForm());

    $divisions = $this->REGATTA->getDivisions();
    $races = $this->REGATTA->getRacesForTeam(Division::A(), $team);
    $f->add(new XP(array(), "Use this pane to specify which races should be accounted for when creating the overall win-loss record for " . $team . ". Greyed-out races are currently being ignored."));

    $rows = array(); // the row WITHIN the table
    $cells = array(); // cells for a given row, indexed by team name
    $records = array(); // the TeamRank object for the given round
    $recTDs = array(); // the record cell for each table
    foreach ($races as $race) {
      $fr_finishes = $this->REGATTA->getFinishes($race);
      if (count($fr_finishes) == 0)
        continue;

      // determine if this is team1 or team2
      $ignoreProp = ($team->id == $race->tr_team1->id) ? 'tr_ignore1' : 'tr_ignore2';

      $finishes = array();
      foreach ($fr_finishes as $finish)
        $finishes[] = $finish;
      for ($i = 1; $i < count($divisions); $i++) {
        foreach ($this->REGATTA->getFinishes($this->REGATTA->getRace($divisions[$i], $race->number)) as $finish)
          $finishes[] = $finish;
      }

      if (!isset($rows[$race->round->id])) {
        $records[$race->round->id] = new TeamRank($team, 0, 0, 0, $team->dt_explanation);
        $recTDs[$race->round->id] = new XTD(array('class'=>'rank-record'), "");
        $rows[$race->round->id] = new XTR(array(), array($recTDs[$race->round->id], new XTH(array(), $team)));
        $cells[$race->round->id] = array();

        $f->add(new XH4("Round: " . $race->round));
        $f->add(new XTable(array('class'=>'rank-table'), array($rows[$race->round->id])));
      }

      $row = $rows[$race->round->id];
      $record = $records[$race->round->id];

      $myScore = 0;
      $theirScore = 0;
      foreach ($finishes as $finish) {
        if ($finish->team->id == $team->id)
          $myScore += $finish->score;
        else
          $theirScore += $finish->score;
      }
      if ($myScore < $theirScore) {
        $className = 'rank-win';
        $display = 'W';
        if ($race->$ignoreProp === null)
          $record->wins++;
      }
      elseif ($myScore > $theirScore) {
        $className = 'rank-lose';
        $display = 'L';
        if ($race->$ignoreProp === null)
          $record->losses++;
      }
      else {
        $className = 'rank-tie';
        $display = 'T';
        if ($race->$ignoreProp === null)
          $record->ties++;
      }
      $display .= sprintf(' (%s)', $myScore);
      $other_team = $race->tr_team1;
      if ($other_team->id == $team->id)
        $other_team = $race->tr_team2;
      $id = sprintf('r-%s', $race->id);
      $cell = new XTD(array(),
                      array($chk = new XCheckboxInput('race[]', $race->id, array('id'=>$id, 'class'=>$className)),
                            $label = new XLabel($id, $display . " vs. ")));
      $cells[$race->round->id][(string)$other_team] = $cell;

      $label->add(new XBr());
      $label->add(sprintf('%s (%s)', $other_team, $theirScore));
      $label->set('class', $className);

      if ($race->$ignoreProp === null)
        $chk->set('checked', 'checked');
    }

    // add all the rows
    foreach ($cells as $id => $list) {
      ksort($list);
      foreach ($list as $cell)
        $rows[$id]->add($cell);
    }

    // update all recTDs
    foreach ($records as $id => $record)
      $recTDs[$id]->add($record->getRecord());

    $f->add($p = new XSubmitP('set-records', "Set race records"));
    $p->add(new XHiddenInput('team', $team->id));
    $p->add(" ");
    $p->add(new XA($this->link('rank'), "Return to rank list"));
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Specific team?
    // ------------------------------------------------------------
    if (isset($args['team'])) {
      if (($team = $this->REGATTA->getTeam($args['team'])) === null) {
        Session::pa(new PA("Invalid team chosen.", PA::E));
        $this->redirect('rank');
      }
      $this->fillTeam($team);
      return;
    }

    // ------------------------------------------------------------
    // All ranks
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Manually rank the teams"));
    $p->add(new XP(array(), "Use this form to set the rank for the teams in the regatta. By default, teams are ranked by the system according to win percentage; followed by number of head-to-head wins; followed by total number of points when tied teams met. After that, the tie stands, and must be broken manually."));
    $p->add(new XP(array(), "To edit a particular team's record by setting which races count towards their record, click on the win-loss record for that team. Remember to click \"Set ranks\" to save the order before editing a team's record."));
    $p->add(new XP(array(), "Use the \"Lock\" checkbox to lock/unlock a team's rank in the regatta. When locked, the rank will not change when new finishes are entered."));
    $p->add(new XP(array('class'=>'warning'), sprintf("Please note that %s will re-rank the teams with every new race scored.", DB::g(STN::APP_NAME))));

    // group the teams
    $groups = array();
    $ungrouped = array();
    foreach ($this->REGATTA->getRankedTeams() as $team) {
      if ($team->rank_group === null)
        $ungrouped[] = $team;
      else {
        if (!isset($groups[$team->rank_group]))
          $groups[$team->rank_group] = array();
        $groups[$team->rank_group][] = $team;
      }
    }
    if (count($ungrouped) > 0)
      $groups[] = $ungrouped;

    $p->add($f = $this->createForm());
    $f->add($tab = new XQuickTable(array('id'=>'ranktable', 'class'=>'teamtable'),
                                   array("#", "Record", "Team", "Explanation", "Lock")));
    $min = 1;
    $i = 0;
    foreach ($groups as $group) {
      $max = $min + count($group) - 1;
      if (count($groups) > 1)
        $tab->addRow(array(new XTD(array('colspan'=>5, 'class'=>'tr-rank-group'), sprintf("Ranks %d-%d", $min, $max))));
      foreach ($group as $team) {
        $tab->addRow(array(new XTD(array(), array(new XNumberInput('rank[]', $team->dt_rank, $min, $max, 1, array('size'=>2)),
                                                  new XHiddenInput('team[]', $team->id))),
                           new XA($this->link('rank', array('team' => $team->id)), $team->getRecord()),
                           new XTD(array('class'=>'drag'), $team),
                           new XTextInput('explanation[]', $team->dt_explanation, array('size'=>40)),
                           $chk = new FCheckbox('lock_rank[]', $team->id, "", $team->lock_rank !== null)),
                     array('class'=>'sortable row' . ($i++ % 2)));
      }

      $min = $max + 1;
    }
    $f->add(new XSubmitP('set-ranks', "Set ranks"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Set records
    // ------------------------------------------------------------
    if (isset($args['set-records'])) {
      $team = DB::$V->reqTeam($args, 'team', $this->REGATTA, "Invalid team whose records to set.");
      $ids = DB::$V->reqList($args, 'race', null, "No list of races provided.");

      $other_divisions = $this->REGATTA->getDivisions();
      array_shift($other_divisions);

      $affected = 0;
      foreach ($this->REGATTA->getRacesForTeam(Division::A(), $team) as $race) {
        if (count($this->REGATTA->getFinishes($race)) == 0)
          continue;

        // determine if this is team1 or team2
        $ignoreProp = ($team->id == $race->tr_team1->id) ? 'tr_ignore1' : 'tr_ignore2';

        $ignore = (in_array($race->id, $ids)) ? null : 1;

        if ($ignore != $race->$ignoreProp) {
          $affected++;
          $race->$ignoreProp = $ignore;
          DB::set($race);
          foreach ($other_divisions as $div) {
            $r = $this->REGATTA->getRace($div, $race->number);
            $r->$ignoreProp = $ignore;
            DB::set($r);
          }
        }
      }

      if ($affected == 0)
        Session::PA(new PA("No races affected.", PA::I));
      else {
        $this->REGATTA->setRanks();
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RANK, $team->school->id);
        Session::pa(new PA(sprintf("Updated %d races.", count($affected))));
      }
    }

    // ------------------------------------------------------------
    // Set ranks
    // ------------------------------------------------------------
    if (isset($args['set-ranks'])) {
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $teams[$team->id] = $team;
      $tids = DB::$V->reqList($args, 'team', count($teams), "Invalid list of teams provided.");
      $exps = DB::$V->reqList($args, 'explanation', count($teams), "Missing list of explanations.");
      $rank = DB::$V->reqList($args, 'rank', count($teams), "Missing list of ranks.");
      array_multisort($rank, SORT_NUMERIC, $tids, $exps);

      // get list of locked teams
      $locked = DB::$V->incList($args, 'lock_rank', null, array());

      // rank groups help ascertain that assigned rank is not outside range
      $ranked = array();
      $groups = array();
      $ungrouped = array();
      // Fetch the old rankings as we need these objects to update the
      // division rankings
      $default_rankings = array();
      foreach ($this->REGATTA->getRanker()->rank($this->REGATTA) as $r) {
        $default_rankings[$r->team->id] = $r;
        if ($r->team->rank_group === null)
          $ungrouped[] = $r->team;
        else {
          if (!isset($groups[$r->team->rank_group]))
            $groups[$r->team->rank_group] = array();
          $groups[$r->team->rank_group][] = $r->team;
        }
        $ranked[$r->team->id] = $r->team;
      }

      // Any teams not ranked by ranker?
      foreach ($teams as $team) {
        if (!isset($ranked[$team->id]))
          $ungrouped[] = $team;
      }

      if (count($ungrouped) > 0)
        $groups[] = $ungrouped;


      $min_ranks = array();
      $max_ranks = array();
      $min = 1;
      foreach ($groups as $group) {
        $max = $min + count($group) - 1;
        foreach ($group as $team) {
          $min_ranks[$team->id] = $min;
          $max_ranks[$team->id] = $max;
        }
        $min = $max + 1;
      }


      $divisions = $this->REGATTA->getDivisions();
      $ranks = array();
      $prevRank = 1;
      $nextRank = 1;
      foreach ($tids as $i => $id) {
        if (!isset($teams[$id]))
          throw new SoterException("Invalid team provided.");
        if ($rank[$i] != $prevRank && $rank[$i] != $nextRank)
          throw new SoterException("Invalid order provided.");
        if ($rank[$i] < $min_ranks[$id] || $rank[$i] > $max_ranks[$id])
          throw new SoterException(sprintf("Provided rank for %s is outside allowed range.", $teams[$id]));

        $nextRank++;
        $prevRank = $rank[$i];

        $new_rank = $rank[$i];
        $new_expl = DB::$V->incString($exps, $i, 1, 101, null);
        $new_lock = (in_array($id, $locked)) ? 1 : null;

        if ($new_rank != $teams[$id]->dt_rank || $new_expl != $teams[$id]->dt_explanation) {
          $teams[$id]->dt_rank = $new_rank;
          $teams[$id]->dt_explanation = $new_expl;
          $ranks[$id] = $teams[$id];

          $default_rankings[$id]->rank = $new_rank;
          $default_rankings[$id]->explanation = $new_expl;
          // also update all division ranks
          foreach ($divisions as $div)
            $this->REGATTA->setDivisionRank($div, $default_rankings[$id]);
        }
        if ($new_lock != $teams[$id]->lock_rank) {
          $teams[$id]->lock_rank = $new_lock;
          $ranks[$id] = $teams[$id];
        }
      }

      if (count($ranks) == 0) {
        Session::pa(new PA("No change in rankings.", PA::I));
        return;
      }

      // Set the rank and issue update request
      foreach ($ranks as $team) {
        DB::set($team);
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RANK, $team->school->id);
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_RP, $team->school->id);
      }
      Session::pa(new PA("Ranks saved."));
    }
  }
}
?>