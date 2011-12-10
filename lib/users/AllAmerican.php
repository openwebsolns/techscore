<?php
/*
 * This file is part of TechScore
 *
 * @package users
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
      $_SESSION['aa'] = array('table' => array(),
			      'regattas' => array(),
			      'regatta_races' => array(),
			      'sailors' => array(),

			      'report-participation' => null,
			      'report-role' => null,
			      'report-seasons' => null,
			      'report-confs' => null,
			      
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

      $form->addChild(new FItem("Seasons:", $ul = new Itemize()));
      $ul->addAttr('class', 'inline-list');

      $form->addChild($fi = new FItem("Conferences:", $ul2 = new Itemize()));
      $ul2->addAttr('class', 'inline-list');
      $fi->addAttr('title', "Only choose sailors from selected conference(s) automatically. You can manually choose sailors from other divisions.");
      
      $now = new Season(new DateTime());
      $then = null;
      if ($now->getSeason() == Season::SPRING)
	$then = Season::parse(sprintf('f%0d', ($now->getTime()->format('Y') - 1)));
      foreach (Preferences::getActiveSeasons() as $season) {
	$ul->addChild($li = new LItem());
	$li->addChild($chk = new FCheckbox('seasons[]', $season, array('id' => $season)));
	$li->addChild(new Label($season, $season->fullString()));

	if ((string)$season == (string)$now || (string)$season == (string)$then)
	  $chk->addAttr('checked', 'checked');
      }

      // Conferences
      foreach (Preferences::getConferences() as $conf) {
	$ul2->addChild($li = new LItem());
	$li->addChild($chk = new FCheckbox('confs[]', $conf, array('id' => $conf->id)));
	$li->addChild(new Label($conf->id, $conf));
	$chk->addAttr('checked', 'checked');
      }

      $form->addChild(new FSubmit('set-report', "Choose regattas >>"));

      $this->PAGE->addContent($p = new Port("Special crew report"));
      $p->addChild($form = new Form('/aa-edit'));
      $form->addChild(new Para("To choose crews from ALL regattas regardless of participation, click the button below."));

      $form->addChild(new FItem("Year:", $sel = new FSelect('year', array())));
      $sel->addOptions(Preferences::getYears());
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
      // Add button to go back
      $this->PAGE->addContent($p = new Port("Progress"));
      $p->addChild($form = new Form('/aa-edit'));
      $form->addChild(new FSubmit('unset-regattas', "<< Start over"));

      // Reset lists
      $_SESSION['aa']['table'] = array();
      $_SESSION['aa']['regattas'] = array();
      $_SESSION['aa']['regatta_races'] = array();
      $_SESSION['aa']['sailors'] = array();

      $regattas = array();
      foreach ($_SESSION['aa']['report-seasons'] as $season) {
	$s = Season::parse($season);
	$regattas = array_merge($regattas, $s->getRegattas());
      }
      $qual_regattas = array();

      $this->PAGE->addContent($p1 = new Port("Classified regattas"));
      $this->PAGE->addContent($p2 = new Port("Additional regattas"));
      if (count($regattas) == 0) {
	$p1->addChild("There are no regattas in the chosen season which classify for inclusion.");
	$p2->addChild("There are no regattas in the chosen season to add.");
	return;
      }

      $p2->addChild($form = new Form("/aa-edit"));
      $tab = new Table();
      $tab->addAttr('id', 'regtable');

      $types = Preferences::getRegattaTypeAssoc();
      $tab->addHeader(new Row(array(Cell::th(""),
				    Cell::th("Name"),
				    Cell::th("Type"),
				    Cell::th("Part."),
				    Cell::th("Date"),
				    Cell::th("Status"))));
      $addt_regattas = 0;
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
	  $addt_regattas++;
	}
      }
      if ($addt_regattas > 0) {
	$form->addChild(new Para("Choose the regattas you wish to include in the report from the list below."));
	$form->addChild($tab);
      }
      else
	$form->addChild(new Para("There are no other possible regattas to add to the report from for the chosen season."));
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
    // 2. Step two: Choose sailors
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
      $p->addChild(new Para(sprintf("%d sailors meet the criteria for All-American inclusion based on the regattas chosen. Note that non-official sailors have been excluded. Use the bottom form to add more sailors to this list.",
				    count($_SESSION['aa']['sailors']))));
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
    // 3. Step three: Generate and review
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
					     Cell::th("School"),
					     Cell::th("Conf."))));
    $table->addHeader($hrow2 = new Row(array(Cell::td(""),
					     Cell::td(""),
					     Cell::td(""),
					     Cell::td(""),
					     Cell::td("Races/Div"))));
    foreach ($_SESSION['aa']['regatta_races'] as $reg_id => $num) {
      $hrow1->addChild(new Cell($reg_id, array('class'=>'rotate'), 1));
      $hrow2->addChild(new Cell($num));
    }
    $TABLE = $_SESSION['aa']['table'];
    foreach ($_SESSION['aa']['sailors'] as $id => $sailor) {
      $table->addRow($row = new Row(array(new Cell($sailor->id),
					  new Cell(sprintf("%s %s", $sailor->first_name, $sailor->last_name)),
					  new Cell($sailor->year),
					  new Cell($sailor->school->nick_name),
					  new Cell($sailor->school->conference))));
      foreach ($TABLE as $reg_id => $sailor_list) {
	if (!isset($sailor_list[$id])) {
	  $_SESSION['aa']['table'][$reg_id][$id] = array();

	  // "Reverse" populate table
	  $regatta = new Regatta($_SESSION['aa']['regattas'][$reg_id]);
	  $rpm = $regatta->getRpManager();
	  $rps = $rpm->getParticipation($sailor, $_SESSION['aa']['report-role']);

	  foreach ($rps as $rp) {
	    $team = ScoresAnalyzer::getTeamDivision($rp->team, $rp->division);
	    $content = sprintf('%d%s', $team->rank, $team->division);
	    if (count($rp->races_nums) != $_SESSION['aa']['regatta_races'][$reg_id])
	      $content .= sprintf(' (%s)', Utilities::makeRange($rp->races_nums));

	    $_SESSION['aa']['table'][$reg_id][$id][] = $content;
	  }
	}
	$row->addChild(new Cell(implode("/", $_SESSION['aa']['table'][$reg_id][$id])));
      }
    }
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
      
      // seasons. If none provided, choose the default
      $_SESSION['aa']['report-seasons'] = array();
      if (isset($args['seasons']) && is_array($args['seasons'])) {
	foreach ($args['seasons'] as $s) {
	  if (($season = Season::parse($s)) !== null)
	    $_SESSION['aa']['report-seasons'][] = (string)$season;
	}
      }
      if (count($_SESSION['aa']['report-seasons']) == 0) {
	$now = new DateTime();
	$season = new Season($now);
	$_SESSION['aa']['report-seasons'][] = (string)$season;
	if ($season->getSeason() == Season::SPRING) {
	  $now->setDate($now->format('Y') - 1, 10, 1);
	  $_SESSION['aa']['report-seasons'][] = (string)$season;
	}
      }

      // conferences. If none provided, choose ALL
      $_SESSION['aa']['report-confs'] = array();
      if (isset($args['confs']) && is_array($args['confs'])) {
	foreach ($args['confs'] as $s) {
	  if (($conf = Preferences::getConference($s)) !== null)
	    $_SESSION['aa']['report-confs'][$conf->id] = $conf->id;
	}
      }
      if (count($_SESSION['aa']['report-confs']) == 0) {
	foreach (Preferences::getConferences() as $conf)
	  $_SESSION['aa']['report-confs'][$conf->id] = $conf->id;
      }
      
      $_SESSION['aa']['report-participation'] = $args['participation'];
      $_SESSION['aa']['report-role'] = $args['role'];
      return false;
    }
    // Special report
    if (isset($args['set-special-report'])) {
      // seasons. If none provided, choose the default
      $_SESSION['aa']['report-seasons'] = array();
      if (isset($args['seasons']) && is_array($args['seasons'])) {
	foreach ($args['seasons'] as $s) {
	  if (($season = Season::parse($s)) !== null)
	    $_SESSION['aa']['report-seasons'][] = (string)$season;
	}
      }
      if (count($_SESSION['aa']['report-seasons']) == 0) {
	$now = new DateTime();
	$season = new Season($now);
	$_SESSION['aa']['report-seasons'][] = $season;
	if ($season->getSeason() == Season::SPRING) {
	  $now->setDate($now->format('Y') - 1, 10, 1);
	  $_SESSION['aa']['report-seasons'][] = (string)$season;
	}
      }
      
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
	  $sailor = RpManager::getSailor($id);
	  $_SESSION['aa']['sailors'][$sailor->id] = $sailor;
	} catch (Exception $e) {
	  $errors++;
	  $this->announce(new Announcement($e->getMessage(), Announcement::ERROR));
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
      // is the regatta and sailor list set?
      if (count($_SESSION['aa']['table']) == 0 ||
	  count($_SESSION['aa']['sailors']) == 0) {
	$this->announce(new Announcement("No regattas or sailors for report.", Announcement::ERROR));
	return false;
      }

      $filename = sprintf('%s-aa-%s-%s.csv',
			  date('Y'),
			  $_SESSION['aa']['report-participation'],
			  $_SESSION['aa']['report-role']);
      header("Content-type: application/octet-stream");
      header("Content-Disposition: attachment; filename=$filename");

      $header1 = array("ID", "Sailor", "YR", "School", "Conf");
      $header2 = array("", "", "", "", "Races/Div");
      $spacer  = array("", "", "", "", "");
      $rows = array();

      foreach ($_SESSION['aa']['sailors'] as $id => $sailor) {
	$row = array($sailor->id,
		     sprintf("%s %s", $sailor->first_name, $sailor->last_name),
		     $sailor->year,
		     $sailor->school->nick_name,
		     $sailor->school->conference);
	foreach ($_SESSION['aa']['table'] as $reg_id => $sailor_list) {
	  if (isset($sailor_list[$id]))
	    $row[] = implode("/", $sailor_list[$id]);
	  else
	    $row[] = "";
	  $header1[$reg_id] = $reg_id;
	  $header2[$reg_id] = $_SESSION['aa']['regatta_races'][$reg_id];
	}
	$rows[] = $row;
      }

      $this->csv = "";
      $this->rowCSV($header1);
      $this->rowCSV($header2);
      $this->rowCSV($spacer);
      foreach ($rows as $row)
	$this->rowCSV($row);

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
   * Determines the sailors who, based on their performance in the
   * given regatta, merit inclusion in the report.
   *
   * The rules for such a feat include a top 5 finish in A division,
   * and top 4 in any other. This method will also fill the
   * appropriate Session variables with the pertinent information
   * regarding this regatta, such as number of races.
   *
   * 2011-12-10: Respect conference membership.
   *
   * @param Regatta $reg the regatta whose information to incorporate
   * into the table
   */
  private function populateSailors(Regatta $reg) {
    // use season/nick-name to sort
    $id = sprintf('%s/%s', $reg->get(Regatta::SEASON), $reg->get(Regatta::NICK_NAME));
    $_SESSION['aa']['regatta_races'][$id] = count($reg->getRaces(Division::A()));
    $_SESSION['aa']['regattas'][$id] = $reg->id();
    if (!isset($_SESSION['aa']['table'][$id]))
      $_SESSION['aa']['table'][$id] = array();
	  
    // grab a list of lucky teams
    $teams = array(); 
    foreach ($reg->getDivisions() as $div) {
      $place = ($div == Division::A()) ? 5 : 4;
      foreach (ScoresAnalyzer::getHighFinishingTeams($reg, $div, $place) as $team)
	$teams[] = $team;
    }

    // get sailors participating in those lucky teams
    $rpm = $reg->getRpManager();
    $sng = $reg->isSingleHanded();
    foreach ($teams as $team) {
      foreach ($rpm->getRP($reg->getTeam($team->team),
			   $team->division,
			   $_SESSION['aa']['report-role']) as $rp) {
	
	if ($rp->sailor->icsa_id !== null &&
	    isset($_SESSION['aa']['report-confs'][$rp->sailor->school->conference->id])) {
	  $content = ($sng) ? $team->rank : sprintf('%d%s', $team->rank, $team->division);
	  if (count($rp->races_nums) != $_SESSION['aa']['regatta_races'][$id])
	    $content .= sprintf(' (%s)', Utilities::makeRange($rp->races_nums));

	  if (!isset($_SESSION['aa']['table'][$id][$rp->sailor->id]))
	    $_SESSION['aa']['table'][$id][$rp->sailor->id] = array();
	  $_SESSION['aa']['table'][$id][$rp->sailor->id][] = $content;
	  
	  if (!isset($_SESSION['aa']['sailors'][$rp->sailor->id]))
	    $_SESSION['aa']['sailors'][$rp->sailor->id] = $rp->sailor;
	}
      }
    }
  }
}
?>