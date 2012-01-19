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
  }

  private function doSailors(Array $args) {
    if (isset($args['sailor'])) {
      if (!is_array($args['sailor'])) {
	Session::pa(new PA("Invalid parameter given for comparison.", PA::E));
	return false;
      }
      $list = $args['sailor'];
    }
    elseif (isset($args['sailors']))
      $list = explode(',', (string)$args['sailors']);

    require_once('regatta/PublicDB.php');
    DBME::setConnection(DB::connection());
    // get sailors
    $sailors = array();
    foreach ($list as $id) {
      $sailor = DB::getSailor($id);
      if ($sailor !== null && $sailor->icsa_id !== null)
	$sailors[] = $sailor;
      else
	Session::pa(new PA("Invalid sailor id given ($id). Ignoring.", PA::I));
    }
    if (count($sailors) < 2) {
      Session::pa(new PA("Need at least two valid sailors for comparison.", PA::E));
      return false;
    }

    // seasons. If none provided, choose the default
    $conds = array();
    if (isset($args['seasons']) && is_array($args['seasons'])) {
      foreach ($args['seasons'] as $s) {
	if (($season = DBME::parseSeason($s)) !== null)
	  $conds[] = new DBCond('season', (string)$season);
      }
    }
    else {
      $now = new DateTime();
      $season = DMBE::getSeason($now);
      $conds[] = new DBCond('season', (string)$season);
      if ($season->season == Season::SPRING) {
	$now->setDate($now->format('Y') - 1, 10, 1);
	$season = DBME::getSeason($now);
	$conds[] = new DBCond('season', (string)$season);
      }
    }
    if (count($conds) == 0) {
      Session::pa(new PA("There are no seasons provided for comparison.", PA::E));
      return false;
    }

    // get first sailor's participation (dt_rp objects)
    $first_sailor = array_shift($sailors);
    $regatta_cond = DBME::prepGetAll(DBME::$REGATTA, new DBBool($conds, DBBool::mOR));
    $regatta_cond->fields(array('id'), DBME::$REGATTA->db_name());
    $team_cond = DBME::prepGetAll(DBME::$TEAM, new DBCondIn('regatta', $regatta_cond));
    $team_cond->fields(array('id'), DBME::$TEAM->db_name());
    $dteam_cond = DBME::prepGetAll(DBME::$TEAM_DIVISION, new DBCondIn('team', $team_cond));
    $dteam_cond->fields(array('id'), DBME::$TEAM_DIVISION->db_name());
    $first_rps = DBME::getAll(DBME::$RP, new DBBool(array(new DBCond('sailor', $first_sailor->id),
							  new DBCondIn('team_division', $dteam_cond))));

    // (reg_id => (division => (sailor_id => <rank races>)))
    $table = array();
    $regattas = array();
    foreach ($first_rps as $rp) {
      if (!isset($table[$rp->team_division->team->regatta->id])) {
	$table[$rp->team_division->team->regatta->id] = array();
	$regattas[$rp->team_division->team->regatta->id] = $rp->team_division->team->regatta;
      }
      if (!isset($table[$rp->team_division->team->regatta->id][$rp->team_division->division]))
	$table[$rp->team_division->team->regatta->id][$rp->team_division->division] = array();

      $rank = sprintf('%d%s', $rp->team_division->rank, $rp->team_division->division);
      if (count($rp->race_nums) != $rp->team_division->team->regatta->num_races)
	$rank .= sprintf(' (%s)', DB::makeRange($rp->race_nums));
      $table[$rp->team_division->team->regatta->id][$rp->team_division->division][$rp->sailor->id] = $rank;
    }

    // Go through each of the remaining sailors, keeping only the
    // regatta and divisions which they have in common.
    foreach ($sailors as $sailor) {
      $copy = $table;
      foreach ($copy as $rid => $divs) {
	foreach ($divs as $div => $dteams) {
	  $rps = $regattas[$rid]->getParticipation($sailor, $div, Dt_Rp::SKIPPER);
	  if (count($rps) == 0)
	    unset($table[$rid][$div]);
	  else {
	    $rank = sprintf('%d%s', $rps[0]->team_division->rank, $div);
	    if (count($rp->race_nums) != $regattas[$rid]->num_races)
	      $rank .= sprintf(' (%s)', DB::makeRange($rp->race_nums)); 
	    $table[$rid][$div][$sailor->id] = $rank;
	  }
	}
	// Is there anything left for this RID?
	if (count($table[$rid]) == 0) {
	  unset($table[$rid]);
	  unset($regattas[$rid]);
	}
      }
    }

    // are there any regattas in common?
    if (count($table) == 0) {
      Session::pa(new PA(sprintf("The sailors provided (%s, %s) have not sailed head to head in any division in any of the regattas in the seasons specified.", $first_sailor, implode(", ", $sailors)), PA::I));
      return false;
    }

    // push the sailor back
    array_unshift($sailors, $first_sailor);
    $this->PAGE->addContent($p = new XPort("Compare sailors head-to-head"));
    $p->add(new XTable(array(), array(new XTHead(array(), array($row = new XTR())), $tab = new XTBody())));
    
    $row->add(new XTH(array(), "Regatta"));
    $row->add(new XTH(array(), "Season"));
    foreach ($sailors as $sailor)
      $row->add(new XTH($sailor));
    foreach ($table as $rid => $divs) {
      foreach ($divs as $list) {
	$tab->add($row = new XTR());
	$row->add(new XTD(array(), $regattas[$rid]->name));
	$row->add(new XTD(array(), $regattas[$rid]->season));
	foreach ($sailors as $sailor)
	  $row->add(new XTD(array(), $list[$sailor->id]));
      }
    }
    return true;
  }

  public function fillHTML(Array $args) {
    // Look for sailors as an array named 'sailors'
    if (isset($args['sailor']) || isset($args['sailors'])) {
      if ($this->doSailors($args))
	return;
      WebServer::go('/compare-sailors');
    }

    // ------------------------------------------------------------
    // Provide an input box to choose sailors using AJAX
    // ------------------------------------------------------------
    $this->PAGE->head->add(new LinkCSS('/inc/css/aa.css'));
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/aa.js'));
    $this->PAGE->addContent(new XP(array(), "Use this form to compare sailors head-to-head, showing the regattas that the sailors have sailed in common, and printing their place finish for each."));
    $this->PAGE->addContent($form = new XForm('/compare-sailors', XForm::GET));

    // Season selection
    $form->add($p = new XPort("Seasons to compare"));
    $p->add(new XP(array(), "Choose at least one season to compare from the list below, then choose the sailors in the next panel."));
    $p->add($ul = new XUl(array('style'=>'list-style-type:none')));

    $now = Season::forDate(DB::$NOW);
    $then = null;
    if ($now->season == Season::SPRING)
      $then = Season::parse(sprintf('f%0d', ($now->start_date->format('Y') - 1)));
    foreach (Preferences::getActiveSeasons() as $season) {
      $ul->add(new XLi(array($chk = new XCheckboxInput('seasons[]', $season, array('id' => $season)),
			     new XLabel($season, $season->fullString()))));
      if ((string)$season == (string)$now || (string)$season == (string)$then)
	$chk->set('checked', 'checked');
    }

    // Sailor search
    $form->add($p = new XPort("New sailors"));
    $p->add(new XNoScript(new XP(array(), "Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding.")));
    $p->add(new FItem('Name:', $search = new XTextInput('name-search', "")));
    $search->set('id', 'name-search');
    $p->add(new XUl(array('id'=>'aa-input'),
		    array(new XLi("No sailors.", array('class'=>'message')))));
    $form->add(new XSubmitInput('set-sailors', "Compare sailors"));
  }

  public function process(Array $args) {
    return false;
  }
}
?>