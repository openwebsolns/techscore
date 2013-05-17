<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

/**
 * Manage the rank groups for team racing regattas
 *
 * @author Dayan Paez
 * @created 2013-05-16
 */
class TeamRankGroupPane extends AbstractPane {
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Rank groups", $user, $reg);
    if ($reg->scoring != Regatta::SCORING_TEAM)
      throw new SoterException("Pane only available for team racing regattas.");
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Set groups
    // ------------------------------------------------------------
    $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/tr-rank-group.js')));
    $this->PAGE->addContent($p = new XPort("Group teams"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "Use this form to limit the minimum and maximum ranks allowed for each team. This is done by placing the teams into \"rank groups\". These limits will be taken into consideration when auto-ranking the teams, as is the case with every new score entered."));

    $form->add(new XNoScript(array(new XP(array(), "Place the teams in groups by placing the group number next to that team. For example, a \"1\" next to four teams means that those teams will be assigned first through fourth place."))));

    $form->add($tab = new XQuickTable(array('id'=>'tr-rankgroup-table'),
                                      array("Group #", "Name", "Record")));
    $has_locked = false;
    foreach ($this->REGATTA->getRankedTeams() as $team) {
      $tab->addRow(array(new XTextInput('group[]', $team->rank_group, array('size'=>2)),
                         array(new XHiddenInput('team[]', $team->id), $team),
                         $team->getRecord()));
      if ($team->lock_rank !== null)
        $has_locked = true;
    }
    if ($has_locked)
      $form->add(new XP(array('class'=>'warning'),
                        array(new XStrong("Warning:"),
                              " Teams whose ranks are locked may have to be unlocked if the locked rank lies outside range of possible ranks assigned above.")));
    $form->add(new XSubmitP('set-groups', "Set groups", array('id'=>'submit-input')));

    // ------------------------------------------------------------
    // Remove groups
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Dissolve groups"));
    $p->add($form = $this->createForm());
    $form->add(new XP(array(), "To remove all rank groups, click the button below. In this case, teams will be ranked as if they all belonged to one group."));
    $form->add(new XSubmitP('dissolve-groups', "Remove groups"));
  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Set groups
    // ------------------------------------------------------------
    if (isset($args['set-groups'])) {
      // all teams must be present
      $teams = array();
      foreach ($this->REGATTA->getTeams() as $team)
        $teams[$team->id] = $team;

      $ids = DB::$V->reqList($args, 'team', count($teams), "Invalid list of teams provided.");
      $grp = DB::$V->reqList($args, 'group', count($teams), "Missing group list.");
      array_multisort($grp, SORT_NUMERIC, $ids);

      $groups = array(); // indexed by group "number"
      foreach ($ids as $i => $team_id) {
        if (!isset($teams[$team_id]))
          throw new SoterException("Invalid team ID provided: $team_id");

        $team = $teams[$team_id];
        unset($teams[$team_id]);

        $num = DB::$V->reqInt($grp, $i, 1, 1000, sprintf("Invalid group number provided for team %s.", $team));

        if (!isset($groups[$num]))
          $groups[$num] = array();
        $groups[$num][] = $team;
      }

      // every group must have at least 2 entries
      foreach ($groups as $num => $list) {
        if (count($list) < 2)
          throw new SoterException("Each rank group must have at least 2 teams.");
      }

      // dissolve the groups, then create new ones
      $this->REGATTA->dissolveRankGroups();

      if (count($groups) > 1) {
        ksort($groups, SORT_NUMERIC);
        $num = 1;
        $min = 1;
        $max = null;
        foreach ($groups as $list) {
          $max = $min + count($list) - 1;
          foreach ($list as $team) {
            $team->rank_group = $num;
            if ($team->dt_rank !== null && $team->lock_rank &&
                ($team->dt_rank < $min || $team->dt_rank > $max))
              $team->lock_rank = null;
            DB::set($team);
          }
          $min = $max + 1;          
          $num++;
        }
        Session::pa(new PA(sprintf("Grouped the teams into %d groups.", count($groups))));
      }
      else
        Session::pa(new PA("Dissolved all rank groups."));

      // Re rank teams
      $this->REGATTA->setRanks();
    }

    // ------------------------------------------------------------
    // Dissolve groups
    // ------------------------------------------------------------
    if (isset($args['dissolve-groups'])) {
      $this->REGATTA->dissolveRankGroups();
      $this->REGATTA->setRanks();
      Session::pa(new PA("Removed all groups and re-ranked the regatta."));
    }
  }
}
?>