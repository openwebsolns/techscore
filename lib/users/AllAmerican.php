<?php
/**
 * This file is part of TechScore
 *
 */

require_once('conf.php');

/**
 * Generates the all-american report, hopefully
 *
 * @author Dayan Paez
 * @version 2011-03-29
 */
class AllAmerican extends AbstractUserPane {
  /**
   * Creates a new pane
   */
  public function __construct(User $user) {
    parent::__construct("All-American", $user);
    if (!isset($_SESSION['aa']))
      $_SESSION['aa'] = array('regatta_ids' => array(),
			      'regattas' => array(),
			      'regatta_races' => array(),
			      'teams' => array(),
			      'sailors' => array(),

			      'report-participation' => null,
			      'report-role' => null,
			      
			      'regattas-set' => false,
			      'params-set' => false);
  }

  public function fillHTML(Array $args) {
    
    // ------------------------------------------------------------
    // 0. Choose participation and role
    // ------------------------------------------------------------
    if ($_SESSION['aa']['report-participation'] === null) {
      $this->PAGE->addContent($p = new Port("Choose report"));
      $p->addChild($form = new Form('/aa-edit'));
      $form->addChild(new FItem("Participation:", $sel = new FSelect('participation', array())));
      $sel->addOptions(array(Regatta::PARTICIPANT_COED => "Coed",
			     Regatta::PARTICIPANT_WOMEN => "Women"));

      $form->addChild(new FItem("Boat role:", $sel = new FSelect('role', array())));
      $sel->addOptions(array(RP::SKIPPER => "Skipper", RP::CREW    => "Crew"));

      $form->addChild(new FSubmit('set-report', "Choose regattas >>"));

      $this->PAGE->addContent($p = new Port("Special crew report"));
      $p->addChild($form = new Form('/aa-edit'));
      $form->addChild(new Para("To choose crews from ALL regattas regardless of participation, click the button below."));
      $form->addChild(new FSubmit('set-special-report', "All crews >>"));
      return;
    }

    $this->PAGE->addHead(new GenericElement('link', array(), array('rel'=>'stylesheet',
								   'type'=>'text/css',
								   'media'=>'screen',
								   'href'=>'/inc/css/widescreen.css')));
    $this->PAGE->addHead(new GenericElement('link', array(new Text("")),
					    array('type'=>'text/css',
						  'href'=>'/inc/css/aa.css',
						  'rel'=>'stylesheet')));
    
    // ------------------------------------------------------------
    // 1. Step one: choose regattas
    // ------------------------------------------------------------
    if ($_SESSION['aa']['regattas-set'] === false) {
      // Reset lists
      $_SESSION['aa']['regattas'] = array();
      $_SESSION['aa']['regatta_ids'] = array();
      $_SESSION['aa']['regatta_races'] = array();
      $_SESSION['aa']['teams'] = array();
      $_SESSION['aa']['sailors'] = array();

      $now = new DateTime();
      $season = new Season($now);
      $regattas = $season->getRegattas();
      // also include fall regattas
      if ($season->getSeason() == Season::SPRING) {
	$now->setDate($now->format('Y') - 1, 10, 1);
	$season = new Season($now);
	$regattas = array_merge($regattas, $season->getRegattas());
      }
      $qual_regattas = array();

      $this->PAGE->addContent($p1 = new Port("Classified regattas"));
      $this->PAGE->addContent($p2 = new Port("Additional regattas"));
      if (count($regattas) == 0) {
	$p1->addChild("There are no regattas in the current season which classify for inclusion.");
	$p2->addChild("There are no regattas in the current season to add.");
	return;
      }

      $p2->addChild($form = new Form("/aa-edit"));
      $form->addChild(new Para("Choose the regattas you wish to include in the report from the list below."));
      $form->addChild($tab = new Table());
      $tab->addAttr('id', 'regtable');

      $types = Preferences::getRegattaTypeAssoc();
      $tab->addHeader(new Row(array(Cell::th(""),
				    Cell::th("Name"),
				    Cell::th("Type"),
				    Cell::th("Part."),
				    Cell::th("Date"),
				    Cell::th("Status"))));
      foreach ($regattas as $reg) {
	if ($reg->finalized !== null &&
	    ($reg->participant == $_SESSION['aa']['report-participation'] ||
	     'special' == $_SESSION['aa']['report-participation']) &&
	    in_array($reg->type, array(Preferences::TYPE_CHAMPIONSHIP,
				       Preferences::TYPE_CONF_CHAMPIONSHIP,
				       Preferences::TYPE_INTERSECTIONAL))) {
	  $this->populateSailors(new Regatta($reg->id));
	  $qual_regattas[] = $reg;
	}
	else {
	  // present these regattas for choosing
	  $id = 'r'.$reg->id;
	  $r = new Row(array(new Cell($chk = new FCheckbox("regatta[]", $reg->id, array('id'=>$id))),
			     new Cell(new Label($id, $reg->name),
				      array('class'=>'left')),
			     new Cell(new Label($id, $types[$reg->type])),
			     new Cell(new Label($id,
						($reg->participant == Regatta::PARTICIPANT_WOMEN) ?
						"Women" : "Coed")),
			     new Cell(new Label($id, $reg->start_time->format('Y/m/d H:i'))),
			     new Cell(new Label($id, ($reg->finalized) ? "Final" : "Pending"))));
	  $tab->addRow($r);
	  if ($reg->finalized === null ||
	      ($reg->participant != $_SESSION['aa']['report-participation'] &&
	       'special' != $_SESSION['aa']['report-participation'])) {
	    $r->addAttr('class', 'disabled');
	    $chk->addAttr("disabled", "disabled");
	  }
	}
      }
      $form->addChild(new Para("Next, choose the sailors to incorporate into the report."));
      $form->addChild(new FSubmit('set-regattas', sprintf("Choose %ss >>", $_SESSION['aa']['report-role'])));

      // are there any qualified regattas?
      if (count($qual_regattas) == 0)
	$p1->addChild(new Para("No regattas this season fulfill the requirement for inclusion."));
      else {
	$p1->addChild(new Para("The following regattas meet the criteria for inclusion in the report."));
	$p1->addChild($tab = new Table());
	$tab->addHeader(new Row(array(Cell::th("Name"),
				      Cell::th("Type"),
				      Cell::th("Part."),
				      Cell::th("Date"),
				      Cell::th("Status"))));

	foreach ($qual_regattas as $reg) {
	  $tab->addRow(new Row(array(new Cell($reg->name, array('class'=>'left')),
				     new Cell($types[$reg->type]),
				     new Cell(($reg->participant == Regatta::PARTICIPANT_WOMEN) ?
					      "Women" : "Coed"),
				     new Cell($reg->start_time->format('Y/m/d H:i')),
				     new Cell("Final"))));
	}
      }
      return;
    }

    // ------------------------------------------------------------
    // Choose sailors
    // ------------------------------------------------------------
    if ($_SESSION['aa']['params-set'] === false) {
      // Add button to go back
      $this->PAGE->addContent($p = new Port("Progress"));
      $p->addChild($form = new Form('/aa-edit'));
      $form->addChild(new FSubmit('unset-regattas', "<< Start over"));
      
      $regattas = $_SESSION['aa']['regattas'];
      // provide a list of sailors that are already included in the
      // list, and a search box to add new ones
      $this->PAGE->addContent($p = new Port("Sailors in list"));
      $p->addChild(new Para("The following sailors meet the criteria for All-American inclusion based on the regattas chosen. Note that non-official sailors have been excluded. Use the bottom form to add more sailors to this list."));
      $p->addChild($item = new Itemize());
      $item->addAttr('id', 'inc-sailors');
      foreach ($_SESSION['aa']['sailors'] as $sailor)
	$item->addItems(new LItem($sailor));

      // Form to fetch and add sailors
      $this->PAGE->addHead(new GenericElement('script', array(new Text("")), array('src'=>'/inc/js/aa.js')));
      $this->PAGE->addContent($p = new Port("New sailors"));
      $p->addChild($form = new Form('/aa-edit'));
      $form->addChild(new GenericElement('noscript', array(new Para("Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding."))));
      $form->addChild(new FItem('Name:', $search = new FText('name-search', "")));
      $search->addAttr('id', 'name-search');
      $form->addChild($ul = new Itemize());
      $ul->addAttr('id', 'aa-input');
      $ul->addItems(new LItem("No sailors.", array('class' => 'message')));
      $form->addChild(new FSubmit('set-sailors', "Generate report >>"));

      return;
    }

    ini_set('memory_limit', '128M');
    ini_set('max_execution_time', 60);
    // ------------------------------------------------------------
    // Review and download
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port("Report"));
    $p->addChild($form = new Form('/aa-edit'));
    $form->addChild(new Para("Please click only once:"));
    $form->addChild(new FSubmit('gen-report', "Download as CSV"));
    
    $p->addChild($form = new Form('/aa-edit'));
    $form->addChild(new FSubmit('unset-sailors', "<< Go back"));
    
    $this->PAGE->addContent($table = new Table());
    $table->addAttr('id', 'aa-table');
    $table->addHeader($hrow1 = new Row(array(Cell::th("ID"),
					     Cell::th("Sailor"),
					     Cell::th("YR"),
					     Cell::th("School"))));
    $table->addHeader($hrow2 = new Row(array(Cell::td(""),
					     Cell::td("# Races/Div"),
					     Cell::td(""),
					     Cell::td(""))));
    foreach ($_SESSION['aa']['regattas'] as $reg_id => $team_list) {
      $hrow1->addChild(new Cell($reg_id, array('class'=>'rotate'), 1));
      $hrow2->addChild(new Cell($_SESSION['aa']['regatta_races'][$reg_id]));
    }
    foreach ($_SESSION['aa']['sailors'] as $id => $sailor) {
      $table->addRow($row = new Row(array(new Cell($sailor->id),
					  new Cell(sprintf("%s %s", $sailor->first_name, $sailor->last_name)),
					  new Cell($sailor->year),
					  new Cell($sailor->school->nick_name))));
      foreach ($_SESSION['aa']['regattas'] as $reg_id => $team_list) {
	$cell = array();
	foreach ($team_list as $team) {
	  if (isset($_SESSION['aa']['teams'][$team->id][$sailor->id])) {
	    $content = $team->rank . $team->division;
	    $num_races = count($_SESSION['aa']['teams'][$team->id][$sailor->id]->races_nums);
	    if ($num_races != $_SESSION['aa']['regatta_races'][$reg_id])
	      $content .= sprintf("(%s)", Utilities::makeRange($_SESSION['aa']['teams'][$team->id][$sailor->id]->races_nums));
	    $cell[] = $content;
	  }
	}
	// fix: if content is empty, then try hard-checking
	if (count($cell) == 0) {
	  $reg = new Regatta($_SESSION['aa']['regatta_ids'][$reg_id]);
	  $rpm = $reg->getRpManager();
	  $rps = $rpm->getParticipation($sailor, $_SESSION['aa']['report-role']);
	  foreach ($rps as $rp) {
	    $team = ScoresAnalyzer::getTeamDivision($rp->team, $rp->division);
	    $content = $team->rank . $team->division;
	    $num_races = count($_SESSION['aa']['teams'][$team->id][$sailor->id]->races_nums);
	    if ($num_races != count($rp->races_nums))
	      $content .= sprintf("(%s)", Utilities::makeRange($rp->races_nums));

	    $cell[] = $content;
	  }
	}
	$row->addChild(new Cell(implode("/", $cell)));
      }
    }
    return;

    $this->PAGE->addContent($p = new Port("Review input for report"));
    $p->addChild(new Para("Please review the information which will be included in the report below. When you are done, click \"Make report\" to generate the report. Because this can be a resource intensive operation, please have patience and do not generate too many reports."));

    $p->addChild(new Heading("Regattas"));
    $p->addChild($tab = new Table());
    $tab->addHeader(new Row(array(Cell::th("Name"),
				  Cell::th("Type"),
				  Cell::th("Part."),
				  Cell::th("Date"),
				  Cell::th("Status"))));
    $types = Preferences::getRegattaTypeAssoc();
    foreach (array_merge($_SESSION['aa']['regattas'],
			 $_SESSION['aa']['added_regattas']) as $id) {
      try {
	$reg = new Regatta($id);
	$tab->addRow(new Row(array(new Cell($reg->get(Regatta::NAME), array('class'=>'left')),
				   new Cell($types[$reg->get(Regatta::TYPE)]),
				   new Cell(($reg->get(Regatta::PARTICIPANT) == Regatta::PARTICIPANT_WOMEN) ?
					    "Women" : "Coed"),
				   new Cell($reg->get(Regatta::START_TIME)->format('Y/m/d H:i')),
				   new Cell("Final"))));
      } catch (Exception $e) {}
    }

    $p->addChild(new Heading("Sailors"));
    $p->addChild($tab = new Table());
    $tab->addHeader(new Row(array(Cell::th("Name"),
				  Cell::th("School"),
				  Cell::th("Year"),
				  Cell::th("Gender"))));
    foreach (array_merge($_SESSION['aa']['sailors'],
			 $_SESSION['aa']['added_sailors']) as $id) {
      try {
	$sailor = RpManager::getSailor($id);
	$tab->addRow(new Row(array(new Cell($sailor),
				   new Cell(Preferences::getSchool($sailor->school)->nick_name),
				   new Cell($sailor->year),
				   new Cell($sailor->gender))));
      }
      catch (Exception $e) {}
    }
    $p->addChild($form = new Form('/aa-edit'));
    $form->addChild(new Para("Please click only once:"));
    $form->addChild(new FSubmit('gen-report', "Make report"));
    
    $this->PAGE->addContent($form = new Form('/aa-edit'));
    $form->addChild(new FSubmit('unset-sailors', "<< Go back"));
  }

  public function process(Array $args) {

    // ------------------------------------------------------------
    // Unset regatta choice (start over)
    // ------------------------------------------------------------
    if (isset($args['unset-regattas'])) {
      unset($_SESSION['aa']);
      return false;
    }

    // ------------------------------------------------------------
    // Unset sailor choices
    // ------------------------------------------------------------
    if (isset($args['unset-sailors'])) {
      $_SESSION['aa']['params-set'] = false;
      return false;
    }

    // ------------------------------------------------------------
    // Choose report
    // ------------------------------------------------------------
    if (isset($args['set-report'])) {
      if (!isset($args['participation']) ||
	  !in_array($args['participation'], array(Regatta::PARTICIPANT_COED, Regatta::PARTICIPANT_WOMEN))) {
	$this->announce(new Announcement("Invalid participation provided.", Announcement::ERROR));
	return false;
      }
      if (!isset($args['role']) ||
	  !in_array($args['role'], array(RP::SKIPPER, RP::CREW))) {
	$this->announce(new Announcement("Invalid role provided.", Announcement::ERROR));
	return false;
      }
      $_SESSION['aa']['report-participation'] = $args['participation'];
      $_SESSION['aa']['report-role'] = $args['role'];
      return false;
    }
    // Special report
    if (isset($args['set-special-report'])) {
      $_SESSION['aa']['report-participation'] = 'special';
      $_SESSION['aa']['report-role'] = 'crew';
      return false;
    }

    // ------------------------------------------------------------
    // Choose regattas
    // ------------------------------------------------------------
    if (isset($args['set-regattas'])) {
      if (!isset($args['regatta']) || !is_array($args['regatta']) || count($args['regatta']) == 0) {
	$this->announce(new Announcement("Proceeding with no added regattas."));
	$_SESSION['aa']['regattas-set'] = true;
	return false;
      }

      $regs = array();
      $errors = 0;
      foreach ($args['regatta'] as $id) {
	try {
	  $reg = new Regatta($id);
	  if ($reg->get(Regatta::TYPE) != Preferences::TYPE_PERSONAL &&
	      $reg->get(Regatta::PARTICIPANT) == $_SESSION['aa']['report-participation'] &&
	      $reg->get(Regatta::FINALIZED) !== null)
	    $this->populateSailors($reg);
	  else
	    $errors++;
	}
	catch (Exception $e) {
	  $errors++;
	}
      }
      if ($errors > 0)
	$this->announce(new Announcement("Some regattas specified are not valid.", Announcement::WARNING));
      if (count($_SESSION['aa']['regattas']) > 0)
	$this->announce(new Announcement("Set regattas for All-American report."));
      $_SESSION['aa']['regattas-set'] = true;
      return $args;
    }

    // ------------------------------------------------------------
    // Set sailors
    // ------------------------------------------------------------
    if (isset($args['set-sailors'])) {
      if (!isset($args['sailor']) || !is_array($args['sailor']) || count($args['sailor']) == 0) {
	$this->announce(new Announcement("Proceeding with no additional sailors."));
	$_SESSION['aa']['params-set'] = true;
	return false;
      }

      // Add sailors, if not already in the 'sailors' list
      $errors = 0;
      foreach ($args['sailor'] as $id) {
	try {
	  if (!isset($_SESSION['aa']['sailors'][$id])) {
	    $sailor = RpManager::getSailor($id);
	    // reverse populate tables, after determining that this
	    // sailor even participated in any of the chosen regattas
	    $participated = false;

	    foreach ($_SESSION['aa']['regattas'] as $reg_id => $list) {
	      $regatta = new Regatta($reg_id);
	      $rpm = $regatta->getRpManager();
	      $rps = $rpm->getParticipation($sailor, $_SESSION['aa']['report-participation']);
	      if (count($rps) > 0) $participated = true;
	      foreach ($rps as $rp) {
		$team = ScoresAnalayser::getTeamDivision($rp->team, $rp->division);
		if (!isset($_SESSION['aa']['teams'][$team->id])) {
		  $_SESSION['aa']['teams'][$team->id] = array();
		  $_SESSION['aa']['regattas'][$regatta->id()][] = $team;
		}
		if (!isset($_SESSION['aa']['teams'][$team->id][$sailor->id]))
		  $_SESSION['aa']['teams'][$team->id][$sailor->id] = $rp;
	      }
	    }
	    if ($participated)
	      $_SESSION['aa']['sailors'][$sailor->id] = $sailor;
	    else
	      $errors++;
	  }
	} catch (Exception $e) {
	  $errors++;
	}
      }
      if ($errors > 0)
	$this->announce(new Announcement("Some invalid sailors were provided and ignored.", Announcement::WARNING));
      $this->announce(new Announcement("Set sailors for report."));
      $_SESSION['aa']['params-set'] = true;
      return false;
    }

    // ------------------------------------------------------------
    // Alas! Make the report
    // ------------------------------------------------------------
    if (isset($args['gen-report'])) {
      ini_set('memory_limit', '128M');
      // is the regatta and sailor list set?
      if (count($_SESSION['aa']['regattas']) == 0 ||
	  count($_SESSION['aa']['sailors']) == 0) {
	$this->announce(new Announcement("No regattas or sailors for report.", Announcement::ERROR));
	return false;
      }

      $header1 = array("ID", "Sailor", "YR", "School");
      $header2 = array("", "# Races/Div", "", "");
      $spacer  = array("", "", "", "");
      $rows = array();

      foreach ($_SESSION['aa']['sailors'] as $id => $sailor) {
	$row = array($sailor->id,
		     sprintf("%s %s", $sailor->first_name, $sailor->last_name),
		     $sailor->year,
		     $sailor->school->nick_name);
	foreach ($_SESSION['aa']['regattas'] as $reg_id => $team_list) {
	  $cell = array();
	  foreach ($team_list as $team) {
	    if (isset($_SESSION['aa']['teams'][$team->id][$sailor->id])) {
	      $content = $team->rank . $team->division;
	      $num_races = count($_SESSION['aa']['teams'][$team->id][$sailor->id]->races_nums);
	      if ($num_races != $_SESSION['aa']['regatta_races'][$reg_id])
		$content .= sprintf("(%s)", Utilities::makeRange($_SESSION['aa']['teams'][$team->id][$sailor->id]->races_nums));
	      $cell[] = $content;
	    }
	  }
	  // fix: if content is empty, then try hard-checking
	  if (count($cell) == 0) {
	    $reg = new Regatta($_SESSION['aa']['regatta_ids'][$reg_id]);
	    $rpm = $reg->getRpManager();
	    $rps = $rpm->getParticipation($sailor, $_SESSION['aa']['report-role']);
	    foreach ($rps as $rp) {
	      $team = ScoresAnalyzer::getTeamDivision($rp->team, $rp->division);
	      $content = $team->rank . $team->division;
	      $num_races = count($_SESSION['aa']['teams'][$team->id][$sailor->id]->races_nums);
	      if ($num_races != count($rp->races_nums))
		$content .= sprintf("(%s)", Utilities::makeRange($rp->races_nums));

	      $cell[] = $content;
	    }
	  }
	  $header1[$reg_id] = $reg_id;
	  $header2[$reg_id] = $_SESSION['aa']['regatta_races'][$reg_id];
	  $row[] = implode('/', $cell);
	}
	$rows[] = $row;
      }

      $this->csv = "";
      $this->rowCSV($header1);
      $this->rowCSV($header2);
      $this->rowCSV($spacer);
      foreach ($rows as $row)
	$this->rowCSV($row);

      $filename = sprintf('%s-aa-%s-%s.csv',
			  date('Y'),
			  $_SESSION['aa']['report-participation'],
			  $_SESSION['aa']['report-role']);
      header("Content-type: application/octet-stream");
      header("Content-Disposition: attachment; filename=$filename");
      header("Content-Length: " . strlen($this->csv));
      echo $this->csv;
      exit;
    }
    return false;
  }

  private $csv = "";
  private function rowCSV(Array $cells) {
    $quoted = array();
    foreach ($cells as $cell) {
      if (is_numeric($cell))
	$quoted[] = $cell;
      else
	$quoted[] = sprintf('"%s"', str_replace('"', '""', $cell));
    }
    $this->csv .= implode(',', $quoted) . "\n";
  }

  /**
   * Merges the information for the given regatta with the rest of the
   * table data being tracked. Note that somebody else is responsible
   * for resetting the necessary $_SESSION variables that this
   * function populates.
   *
   * @param Regatta $reg the regatta whose information to incorporate
   * into the table
   */
  private function populateSailors(Regatta $reg) {
    // use season/nick-name to sort
    $id = sprintf('%s/%s', $reg->get(Regatta::SEASON), $reg->get(Regatta::NICK_NAME));
    $_SESSION['aa']['regatta_races'][$id] = count($reg->getRaces(Division::A()));
    $_SESSION['aa']['regatta_ids'][$id] = $reg->id();
	  
    // get top finishing teams
    $_SESSION['aa']['regattas'][$id] = array();
    foreach ($reg->getDivisions() as $div) {
      $place = ($div == Division::A()) ? 5 : 4;
      $teams = ScoresAnalyzer::getHighFinishingTeams($reg, $div, $place);
      $_SESSION['aa']['regattas'][$id] = array_merge($_SESSION['aa']['regattas'][$id], $teams);
    }

    // get sailors participating in those lucky teams
    $rpm = $reg->getRpManager();
    foreach ($_SESSION['aa']['regattas'][$id] as $team) {
      $_SESSION['aa']['teams'][$team->id] = array();
      foreach ($rpm->getRP($reg->getTeam($team->team),
			   $team->division,
			   $_SESSION['aa']['report-role']) as $rp) {
	
	if ($rp->sailor->icsa_id !== null) {
	  $_SESSION['aa']['teams'][$team->id][$rp->sailor->id] = $rp;
	  if (!isset($_SESSION['aa']['sailors'][$rp->sailor->id]))
	    $_SESSION['aa']['sailors'][$rp->sailor->id] = $rp->sailor;
	}
      }
    }
  }
}
?>