<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

/**
 * Compares up to three sailors head to head across a season
 *
 * @author Dayan Paez
 * @version 2011-03-29
 */
class CompareSailors extends AbstractUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(User $user) {
    parent::__construct("Compare sailors", $user);
  }

  private function doSailors(Array $args) {
    if (isset($args['sailor'])) {
      if (!is_array($args['sailor'])) {
	$this->announce(new Announcement("Invalid parameter given for comparison.", Announcement::ERROR));
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
	$sailor = RpManager::getSailor($id);
	if ($sailor->icsa_id !== null)
	  $sailors[] = $sailor;
      }
      catch (InvalidArgumentException $e) {
	$this->PAGE->addAnnouncement(new Announcement("Invalid sailor id given ($id). Ignoring.", Announcement::WARNING));
      }
    }
    if (count($sailors) < 2) {
      $this->announce(new Announcement("Need at least two valid sailors for comparison.", Announcement::ERROR));
      return false;
    }

    // select all the pertinent regattas
    $now = new DateTime();
    $season = new Season($now);
    $regattas = $season->getRegattas();
    if ($season->getSeason() == Season::SPRING) {
      $now->setDate($now->format('Y') - 1, 10, 1);
      $season = new Season($now);
      $regattas = array_merge($regattas, $season->getRegattas());
    }
    if (count($regattas) == 0) {
      $this->announce(new Announcement("There are no regattas in the past year to consider for comparison.", Announcement::ERROR));
      return false;
    }

    // the array is organized as $regatta_id => array($div => array($race_num))
    $reg_races = array();
    $reg_teams = array();
    // populate the list with the first sailor
    $first_sailor = array_shift($sailors);
    foreach ($regattas as $regatta) {
      $reg = new Regatta($regatta->id);
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
	$reg = new Regatta($regatta_id);
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
      $this->announce(new Announcement(sprintf("The sailors provided (%s) have not sailed head to head in any race in any regatta in the past year.", implode(", ", $sailors)), Announcement::WARNING));
      return false;
    }

    /*
    echo "<pre>"; print_r($reg_races); print_r($reg_teams); echo "</pre>";
    exit;
    */
    // push the sailor back
    array_unshift($sailors, $first_sailor);
    $scores = array(); // track scores
    $this->PAGE->addContent($p = new Port("Races sailed head-to-head"));
    $p->addChild($tab = new Table());
    $tab->addHeader($head = new Row(array(Cell::th("Regatta"), Cell::th("Race"))));
    foreach ($sailors as $sailor) {
      $head->addChild(Cell::th($sailor));
      $scores[$sailor->id] = 0;
    }
    // total row
    $tab->addHeader($tot = new Row(array(new Cell(""), Cell::th("Total"))));
    // each race
    foreach ($reg_races as $reg_id => $div_list) {
      $regatta = new Regatta($reg_id);
      foreach ($div_list as $div => $races_nums) {
	$index = 0;
	foreach ($races_nums as $num) {
	  $tab->addRow($row = new Row());
	  if ($index++ == 0) {
	    $cell = Cell::th($regatta->get(Regatta::NAME));
	    $cell->addAttr('rowspan', count($races_nums));
	    $row->addChild($cell);
	  }
	  $row->addChild(Cell::th(sprintf("%d%s", $num, $div)));
	  foreach ($sailors as $sailor) {
	    $finish = $regatta->getFinish($regatta->getRace(Division::get($div), $num),
					  $reg_teams[$reg_id][$div][$sailor->id]);
	    $row->addChild(new Cell($finish->place));
	    $scores[$sailor->id] += $finish->score;
	  }
	}
      }
    }
    foreach ($sailors as $sailor)
      $tot->addChild(Cell::th($scores[$sailor->id]));
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
    $this->PAGE->addHead(new GenericElement('link', array(new Text("")),
					    array('type'=>'text/css',
						  'href'=>'/inc/css/aa.css',
						  'rel'=>'stylesheet')));
    $this->PAGE->addHead(new GenericElement('script', array(new Text("")), array('src'=>'/inc/js/aa.js')));
    $this->PAGE->addContent($p = new Port("New sailors"));
    $p->addChild($form = new Form('/compare-sailors', "get"));
    $form->addChild(new GenericElement('noscript', array(new Para("Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding."))));
    $form->addChild(new FItem('Name:', $search = new FText('name-search', "")));
    $search->addAttr('id', 'name-search');
    $form->addChild($ul = new Itemize());
    $ul->addAttr('id', 'aa-input');
    $ul->addItems(new LItem("No sailors.", array('class' => 'message')));
    $form->addChild(new FSubmit('set-sailors', "Compare sailors"));
  }

  public function process(Array $args) {
    return false;
  }
}
?>