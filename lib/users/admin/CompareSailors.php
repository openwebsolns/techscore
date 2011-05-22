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
class CompareSailors extends AbstractAdminUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(User $user) {
    parent::__construct("Compare sailors", $user);
  }

  private function doSailors(Array $args) {
    if (!is_array($args['sailor'])) {
      $this->announce(new Announcement("Invalid parameter given for comparison.", Announcement::ERROR));
      return false;
    }
    $sailors = array();
    foreach ($args['sailor'] as $id) {
      try {
	$sailor = RpManager::getSailor($id);
	if ($sailor->icsa_id !== null)
	  $sailors[] = $sailor;
      }
      catch (InvalidArgumentException $e) {
	$this->announce(new Announcement("Invalid sailor id given ($id). Ignoring.", Announcement::WARNING));
      }
    }
    if (count($sailors) == 0) {
      $this->announce(new Announcement("No valid sailors provided for comparison.", Announcement::ERROR));
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
    foreach ($regattas as $regatta)
      $reg_races[$regatta->id] = array();
    
    // keep only the regattas (and the races within them) where all
    // the given sailors have participated
    foreach ($sailors as $sailor) {
      $copy = $regattas;
      foreach ($copy as $i => $regatta) {
	$reg = new Regatta($regatta->id);
	$rpm = $reg->getRpManager();
	$rps = $rpm->getParticipation($sailor, 'skipper');
	if (count($rps) == 0) {
	  unset($reg_races[$regatta->id]);
	  unset($regattas[$i]);
	}
	else {
	  foreach ($rps as $rp) {
	    $key = (string)$rp->division;
	    if (!isset($reg_races[$regatta->id][$key]))
	      $reg_races[$regatta->id][$key] = $rp->races_nums;
	    else {
	      // only keep the ones that we have in common
	      $race_copy = $reg_races[$regatta->id][$key];
	      foreach ($race_copy as $j => $num) {
		if (!in_array($num, $rp->races_nums))
		  unset($reg_races[$regatta->id][$key][$j]);
	      }
	      if (count($reg_races[$regatta->id][$key]) == 0)
		unset($reg_races[$regatta->id][$key]);
	    }
	  }
	  if (count($reg_races[$regatta->id]) == 0)
	    unset($reg_races[$regatta->id]);
	}
      }
    }

    // are there any regattas in common?
    if (count($reg_races) == 0) {
      $this->announce(new Announcement(sprintf("The sailors provided (%s) have not sailed head to head in any race in any regatta in the past year.", implode(", ", $sailors)), Announcement::WARNING));
      return false;
    }

    echo "<pre>"; print_r($reg_races); "</pre>";
    exit;

    return true;
  }

  public function fillHTML(Array $args) {
    // Look for sailors as an array named 'sailors'
    if (isset($args['sailor'])) {
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