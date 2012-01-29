<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');

/**
 * Compares up to three sailors head to head across a season or more,
 * and include races in common.
 *
 * @author Dayan Paez
 * @version 2011-03-29
 */
class CompareSailorsByRace extends AbstractUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(Account $user) {
    parent::__construct("Compare sailors by race", $user);
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

    // get sailors
    $sailors = array();
    foreach ($list as $id) {
      try {
	$sailor = DB::getSailor($id);
	if ($sailor->icsa_id !== null)
	  $sailors[] = $sailor;
      }
      catch (InvalidArgumentException $e) {
	Session::pa(new PA("Invalid sailor id given ($id). Ignoring.", PA::I));
      }
    }
    if (count($sailors) < 2) {
      Session::pa(new PA("Need at least two valid sailors for comparison.", PA::E));
      return false;
    }

    // seasons. If none provided, choose the default
    $seasons = array();
    if (isset($args['seasons']) && is_array($args['seasons'])) {
      foreach ($args['seasons'] as $s) {
	if (($season = DB::getSeason($s)) !== null)
	  $seasons[] = $season;
      }
    }
    else {
      $seasons[] = Season::forDate(DB::$NOW);
      if ($seasons[0]->season == Season::SPRING)
	$seasons[] = DB::getSeason('f' . ($seasons[0]->start_date->format('Y') - 1));
    }
    $regattas = Season::getRegattasInSeasons($seasons);
    if (count($regattas) == 0) {
      Session::pa(new PA("There are no regattas in the given seasons to consider for comparison.", PA::E));
      return false;
    }

    // the array is organized as $regatta_id => array($div => array($race_num))
    $reg_races = array();
    $reg_teams = array();
    // populate the list with the first sailor
    $first_sailor = array_shift($sailors);
    foreach ($regattas as $regatta) {
      $reg = DB::getRegatta($regatta->id);
      $rpm = $reg->getRpManager();
      $rps = $rpm->getParticipation($first_sailor, 'skipper');
      if (count($rps) > 0) {
	$reg_races[$regatta->id] = array();
	$reg_teams[$regatta->id] = array();
	foreach ($rps as $rp) {
	  $key = (string)$rp->division;
	  $reg_teams[$regatta->id][$key] = array($rp->sailor->id => $rp->team);
	  $reg_races[$regatta->id][$key] = array();
	  foreach ($rp->races_nums as $num)
	    $reg_races[$regatta->id][$key][$num] = $num;
	}
      }
    }
    unset($regattas);
    
    // keep only the regattas (and the races within them) where all
    // the other sailors have also participated
    foreach ($sailors as $sailor) {
      $copy = $reg_races;
      foreach ($copy as $regatta_id => $div_list) {
	$reg = DB::getRegatta($regatta_id);
	$rpm = $reg->getRpManager();
	foreach ($div_list as $div => $races_nums) {
	  $rps = $rpm->getParticipation($sailor, 'skipper', Division::get($div));
	  if (count($rps) == 0) {
	    unset($reg_races[$regatta_id][$div]);
	    unset($reg_teams[$regatta_id][$div]);
	  }
	  else {
	    $reg_teams[$regatta_id][$div][$sailor->id] = $rps[0]->team;
	    foreach ($races_nums as $i => $num) {
	      if (!in_array($num, $rps[0]->races_nums))
		unset($reg_races[$regatta_id][$div][$i]);
	    }
	    if (count($reg_races[$regatta_id][$div]) == 0) {
	      unset($reg_races[$regatta_id][$div]);
	      unset($reg_teams[$regatta_id][$div]);
	    }
	  }
	}
	if (count($reg_races[$regatta_id]) == 0) {
	  unset($reg_races[$regatta_id]);
	  unset($reg_teams[$regatta_id]);
	}
      }
    }

    // are there any regattas in common?
    if (count($reg_races) == 0) {
      Session::pa(new PA(sprintf("The sailors provided (%s, %s) have not sailed head to head in any race in any regatta in the seasons specified.", $first_sailor, implode(", ", $sailors)), PA::I));
      return false;
    }

    // push the sailor back
    array_unshift($sailors, $first_sailor);
    $scores = array(); // track scores
    $this->PAGE->addContent($p = new XPort("Races sailed head-to-head"));
    $p->add(new XTable(array(),
		       array(new XTHead(array(),
					array($head = new XTR(array(),
							      array(new XTH(array(), "Regatta"),
								    new XTH(array(), "Race"))),
					      $tot  = new XTR(array(),
							      array(new XTH(array(), ""),
								    new XTH(array(), "Total"))))),
			     $tab = new XTBody())));
    foreach ($sailors as $sailor) {
      $head->add(new XTH(array(), $sailor));
      $scores[$sailor->id] = 0;
    }
    // each race
    foreach ($reg_races as $reg_id => $div_list) {
      $regatta = DB::getRegatta($reg_id);
      foreach ($div_list as $div => $races_nums) {
	$index = 0;
	foreach ($races_nums as $num) {
	  $tab->add($row = new XTR());
	  if ($index++ == 0) {
	    $row->add(new XTH(array('rowspan'=>count($races_nums)),
			      sprintf('%s (%s)', $regatta->name, $regatta->getSeason()->fullString())));
	  }
	  $row->add(new XTH(array(), sprintf("%d%s", $num, $div)));
	  foreach ($sailors as $sailor) {
// @TODO getRace()
	    $finish = $regatta->getFinish($regatta->getRace(Division::get($div), $num),
					  $reg_teams[$reg_id][$div][$sailor->id]);
	    $row->add(new XTD(array(), $finish->getPlace()));
	    $scores[$sailor->id] += $finish->score;
	  }
	}
      }
    }
    foreach ($sailors as $sailor)
      $tot->add(new XTH(array(), $scores[$sailor->id]));
    return true;
  }

  public function fillHTML(Array $args) {
    // Look for sailors as an array named 'sailors'
    if (isset($args['sailor']) || isset($args['sailors'])) {
      if ($this->doSailors($args))
	return;
      WS::go('/compare-by-race');
    }

    // ------------------------------------------------------------
    // Provide an input box to choose sailors using AJAX
    // ------------------------------------------------------------
    $this->PAGE->head->add(new LinkCSS('/inc/css/aa.css'));
    $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/aa.js'));
    $this->PAGE->addContent($form = new XForm('/compare-by-race', XForm::GET));

    // Season selection
    $form->add($p = new XPort("Seasons to compare"));
    $p->add(new XP(array(), "Choose at least one season to compare from the list below, then choose the sailors in the next panel."));
    $p->add($ul = new XUl(array('style'=>'list-style-type:none;')));

    $now = Season::forDate(DB::$NOW);
    $then = null;
    if ($now->season == Season::SPRING)
      $then = DB::getSeason(sprintf('f%0d', ($now->start_date->format('Y') - 1)));
    foreach (Season::getActive() as $season) {
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