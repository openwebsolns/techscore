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
    $complete = false;
    if (isset($args['sailor']))
      $complete = $this->doSailors($args);

    // ------------------------------------------------------------
    // Provide an input box to choose sailors using AJAX
    // ------------------------------------------------------------
    if (!$complete) {
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
    return;

    $cnt = count($_SESSION['compare-sailors']['sailors']);
    $chosen_school = null;
    if ($_SESSION['compare-sailors']['school'] !== null)
      $chosen_school = unserialize($_SESSION['compare-sailors']['school']);

    // ------------------------------------------------------------
    // Comparison
    // ------------------------------------------------------------
    if ($cnt > 0) {

      // if there is just one, initialize the list
      if ($cnt == 1) {
	$sailors = $_SESSION['compare-sailors']['sailors'];
	$sailor = unserialize(array_shift($sailors));

	// keep the regattas that apply to THIS sailor
	$copy = $_SESSION['compare-sailors']['regattas'];
	foreach ($copy as $id => $list) {
	  $reg = new Regatta($id);
	  $rpm = $reg->getRpManager();
	  $rps = $rpm->getParticipation($sailor, 'skipper');
	  if (count($rps) == 0)
	    unset($_SESSION['compare-sailors']['regattas'][$id]);
	  else {
	    foreach ($rps as $rp)
	      $_SESSION['compare-sailors']['regattas'][$id][(string)$rp->division] = $rp->races_nums;
	  }
	}
      }

      // remove the races that are not applicable
      if ($cnt > 1) {
	$sailors = $_SESSION['compare-sailors']['sailors'];
	array_shift($sailors);
	foreach ($sailors as $sailor) {
	  $sailor = unserialize($sailor);
	  $copy = $_SESSION['compare-sailors']['regattas'];
	  foreach ($copy as $id => $list) {
	    $reg = new Regatta($id);
	    $rpm = $reg->getRpManager();
	    $rps = $rpm->getParticipation($sailor, 'skipper');
	    if (count($rps) == 0)
	      unset($_SESSION['compare-sailors']['regattas'][$id]);
	    else {
	      $my_list = array();
	      foreach ($rps as $rp)
		$my_list[(string)$rp->division] = $rp->races_nums;
	      $lcopy = $list;
	      foreach ($lcopy as $div => $races_nums) {
		if (!isset($my_list[$div]))
		  unset($list[$div]);
		else {
		  // only keep the race numbers that are pertinent
		  foreach ($races_nums as $i => $num) {
		    if (!is_array($num, $my_list[$div]))
		      unset($list[$div][$i]);
		  }
		  if (count($list[$div]) == 0)
		    unset($list[$div]);
		}
	      }
	      if (count($list) == 0)
		unset($_SESSION['compare-sailors']['regattas'][$id]);
	      else
		$_SESSION['compare-sailors']['regattas'][$id] = $list;
	    }
	  }
	}

	// ------------------------------------------------------------
	// Print the comparison!
	// ------------------------------------------------------------
	print_r($_SESSION['compare-sailors']['regattas']); exit;
      }
    }

    // ------------------------------------------------------------
    // Current list
    if ($cnt > 0) {
      $this->PAGE->addContent($p = new Port("Current sailors"));
      $p->addChild($form = new Form("/compare-sailors-edit"));
      $text = "You may choose to remove any sailors from the list below.";
      if ($cnt > 1)
	$text .= " If you are done, click \"Compare\" to perform comparison.";
      $form->addChild(new Para($text));

      foreach ($_SESSION['compare-sailors']['sailors'] as $s) {
	$sailor = unserialize($s);
	$id = 's-'.$sailor->id;
	$form->addChild(new Div(array(new FCheckbox('sailor[]', $sailor->id, array('id'=>$id)),
				      new Label($id, $sailor)),
				array('id'=>'cmp-sailor')));
      }
      $form->addChild($fi = new Div(array(new FSubmit('remove-checked', "Remove checked"))));
    }

    // ------------------------------------------------------------
    // Add sailors
    $this->PAGE->addHead(new GenericElement('link', array(new Text("")),
					    array('type'=>'text/css',
						  'href'=>'/inc/css/aa.css',
						  'rel'=>'stylesheet')));
    $this->PAGE->addHead(new GenericElement('script', array(new Text("")), array('src'=>'/inc/js/aa-one.js')));
    $this->PAGE->addContent($p = new Port("Search sailor"));
    $p->addChild($form = new Form('/compare-sailors-edit'));
    $form->addChild(new GenericElement('noscript', array(new Para("Please enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding."))));
    $form->addChild(new FItem('Name:', $search = new FText('name-search', "")));
    $search->addAttr('id', 'name-search');
    $form->addChild(new FSubmit('add-sailor', "Add sailor"));

    /*
    // ------------------------------------------------------------
    // Add option to choose team
    if ($cnt < 3) {
      $this->PAGE->addContent($p = new Port("Choose team"));
      $p->addChild($form = new Form("/compare-sailors-edit"));
      $form->addChild(new Para("Use this form to compare up to three different sailors. The form will aggregate all the races that the three sailors participated at the same time during the current season. Select one sailor at a time by browsing the school list below."));
      $form->addChild($fi = new FItem("Choose school:", $sel = new FSelect("school", array($chosen_school))));
      $sel->addAttr('onchange', 'javascript:submit(\'this\')');

      foreach (Preferences::getConferences() as $conf) {
	// Get schools for that conference
	$schools = Preferences::getSchoolsInConference($conf);
	$schoolOptions = array();
	foreach ($schools as $school) {
	  if ($chosen_school === null)
	    $chosen_school = $school;
	  $schoolOptions[$school->id] = $school->name;
	}
	$sel->addOptionGroup($conf, $schoolOptions);
      }
      $fi->addChild(new FSubmit('choose-school', "Change"));

      // list of sailors
      $p->addChild($form = new Form("/compare-sailors-edit"));
      $form->addChild($fi = new FItem("Sailor:", $sel = new FSelect("sailor")));
      $sailors = RpManager::getSailors($chosen_school);
      $un_slrs = RpManager::getUnregisteredSailors($chosen_school);

      $sailor_optgroup = array();
      foreach ($sailors as $s)
	$sailor_optgroup[] = new Option($s->id, $s);
      $sailor_optgroup = new OptionGroup("Sailors", $sailor_optgroup);

      $u_sailor_optgroup = array();
      foreach ($un_slrs as $s)
	$u_sailor_optgroup[] = new Option($s->id, $s);
      $u_sailor_optgroup = new OptionGroup("Non-ICSA", $u_sailor_optgroup);

      $sel->addChild($sailor_optgroup);
      $sel->addChild($u_sailor_optgroup);
      $fi->addChild(new FSubmit('add-sailor', "Add Sailor"));
    }
    */

  }

  public function process(Array $args) {
    // ------------------------------------------------------------
    // Choose school
    // ------------------------------------------------------------
    if (isset($args['school'])) {
      $school = Preferences::getSchool($args['school']);
      if ($school === null) {
	$this->announce(new Announcement("Invalid school ID provided."));
	return $args;
      }
      $_SESSION['compare-sailors']['school'] = serialize($school);
      return $args;
    }
    // ------------------------------------------------------------
    // Add sailor
    // ------------------------------------------------------------
    if (isset($args['add-sailor'])) {
      if (!isset($args['sailor']) ||
	  ($sailor = RpManager::getSailor((int)$args['sailor'])) === null) {
	$this->announce(new Announcement("Invalid sailor ID provided."));
	return $args;
      }
      $_SESSION['compare-sailors']['sailors'][$sailor->id] = serialize($sailor);
      return $args;
    }
    // ------------------------------------------------------------
    // Remove sailor
    // ------------------------------------------------------------
    if (isset($args['remove-checked'])) {
      if (!isset($args['sailor']) || !is_array($args['sailor'])) {
	$this->announce(new Announcement("Invalid sailor ID provided for removal."));
	return $args;
      }
      foreach ($args['sailor'] as $id)
	unset($_SESSION['compare-sailors']['sailors'][$id]);
      return $args;
    }

    print_r($args);
    exit;
  }
}
?>