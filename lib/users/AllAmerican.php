<?php
/*
 * This file is part of TechScore
 *
 * @package users
 */

require_once('users/AbstractUserPane.php');
require_once('regatta/data/ScoresAnalyzer.php');
require_once('regatta/data/TeamDivision.php');

/**
 * Generates the all-american report. This pane is unlike any other
 * because, due to the complex nature of the report generation
 * process, some processing happens in fillHTML, and others in
 * process. You see, the report is too complex to be generated in one
 * step. And as such, the report generation process is divided into
 * multiple steps, each one separated into different subpages.
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
      $this->PAGE->addContent($p = new XPort("Choose report"));
      $p->add($form = new XForm('/aa-edit', XForm::POST));
      $form->add(new FItem("Participation:", XSelect::fromArray('participation',
								array(Regatta::PARTICIPANT_COED => "Coed",
								      Regatta::PARTICIPANT_WOMEN => "Women"))));

      $form->add(new FItem("Boat role:", XSelect::fromArray('role', array(RP::SKIPPER => "Skipper", RP::CREW => "Crew"))));

      $form->add(new FItem("Seasons:", $ul = new XUl(array('class'=>'inline-list'))));

      $form->add($fi = new FItem("Conferences:", $ul2 = new XUl(array('class'=>'inline-list'))));
      $fi->set('title', "Only choose sailors from selected conference(s) automatically. You can manually choose sailors from other divisions.");
      
      $now = new Season(new DateTime());
      $then = null;
      if ($now->getSeason() == Season::SPRING)
	$then = Season::parse(sprintf('f%0d', ($now->getTime()->format('Y') - 1)));
      foreach (Preferences::getActiveSeasons() as $season) {
	$ul->add(new XLi(array($chk = new XCheckboxInput('seasons[]', $season, array('id' => $season)),
			       new XLabel($season, $season->fullString()))));
	if ((string)$season == (string)$now || (string)$season == (string)$then)
	  $chk->set('checked', 'checked');
      }

      // Conferences
      foreach (Preferences::getConferences() as $conf) {
	$ul2->add(new XLi(array($chk = new XCheckboxInput('confs[]', $conf, array('id' => $conf->id)),
				new XLabel($conf->id, $conf))));
	$chk->set('checked', 'checked');
      }

      $form->add(new XSubmitInput('set-report', "Choose regattas >>"));

      $this->PAGE->addContent($p = new XPort("Special crew report"));
      $p->add($form = new XForm('/aa-edit', XForm::POST));
      $form->add(new XP(array(), "To choose crews from ALL regattas regardless of participation, click the button below."));

      $form->add(new FItem("Year:", XSelect::fromArray('year', Preferences::getYears())));
      $form->add(new XSubmitInput('set-special-report', "All crews >>"));
      return;
    }

    $this->PAGE->head->add(new LinkCSS('/inc/css/widescreen.css'));
    $this->PAGE->head->add(new LinkCSS('/inc/css/aa.css'));
    
    // ------------------------------------------------------------
    // 1. Step one: choose regattas. For women's reports, ICSA
    // requests that non-women's regattas may also be chosen for
    // inclusion. Note that male sailors should NOT be included in the
    // list of automatic sailors.
    // ------------------------------------------------------------
    if ($_SESSION['aa']['regattas-set'] === false) {
      // Add button to go back
      $this->PAGE->addContent($p = new XPort("Progress"));
      $p->add($form = new XForm('/aa-edit', XForm::POST));
      $form->add(new XSubmitInput('unset-regattas', "<< Start over"));

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

      $this->PAGE->addContent($p1 = new XPort("Classified regattas"));
      $this->PAGE->addContent($p2 = new XPort("Additional regattas"));
      if (count($regattas) == 0) {
	$p1->add("There are no regattas in the chosen season which classify for inclusion.");
	$p2->add("There are no regattas in the chosen season to add.");
	return;
      }

      $p2->add($form = new XForm("/aa-edit", XForm::POST));
      $tab = new XQuickTable(array('id'=>'regtable'), array("", "Name", "Type", "Part.", "Date", "Status"));
      
      $types = Preferences::getRegattaTypeAssoc();
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
	  $rattr = array();
	  $cattr = array('id'=>$id);
	  if ($reg->finalized === null ||
	      ($reg->participant != $_SESSION['aa']['report-participation'] &&
	       Regatta::PARTICIPANT_COED == $_SESSION['aa']['report-participation'])) {
	    $rattr['class'] = 'disabled';
	    $cattr['disabled'] = 'disabled';
	  }
	  $tab->addRow(array(new XCheckboxInput("regatta[]", $reg->id, $cattr),
			     new XLabel($id, $reg->name),
			     new XLabel($id, $types[$reg->type]),
			     new XLabel($id, ($reg->participant == Regatta::PARTICIPANT_WOMEN) ? "Women" : "Coed"),
			     new XLabel($id, $reg->start_time->format('Y/m/d H:i')),
			     new XLabel($id, ($reg->finalized) ? "Final" : "Pending")),
		       $rattr);
	  $addt_regattas++;
	}
      }
      if ($addt_regattas > 0) {
	$form->add(new XP(array(), "Choose the regattas you wish to include in the report from the list below."));
	$form->add($tab);
      }
      else
	$form->add(new XP(array(), "There are no other possible regattas to add to the report from for the chosen season."));
      $form->add(new XP(array(), "Next, choose the sailors to incorporate into the report."));
      $form->add(new XSubmitInput('set-regattas', sprintf("Choose %ss >>", $_SESSION['aa']['report-role'])));

      // are there any qualified regattas?
      if (count($qual_regattas) == 0)
	$p1->add(new XP(array(), "No regattas this season fulfill the requirement for inclusion."));
      else {
	$p1->add(new XP(array(), "The following regattas meet the criteria for inclusion in the report."));
	$p1->add($tab = new XQuickTable(array(), array("Name", "Type", "Part.", "Date", "Status")));
	foreach ($qual_regattas as $reg) {
	  $tab->addRow(array($reg->name,
			     $types[$reg->type],
			     ($reg->participant == Regatta::PARTICIPANT_WOMEN) ? "Women" : "Coed",
			     $reg->start_time->format('Y/m/d H:i'),
			     "Final"));
	}
      }
      return;
    }

    // ------------------------------------------------------------
    // 2. Step two: Choose sailors
    // ------------------------------------------------------------
    if ($_SESSION['aa']['params-set'] === false) {
      // Add button to go back
      $this->PAGE->addContent($p = new XPort("Progress"));
      $p->add($form = new XForm('/aa-edit', XForm::POST));
      $form->add(new XSubmitInput('unset-regattas', "<< Start over"));
      
      $regattas = $_SESSION['aa']['regattas'];
      // provide a list of sailors that are already included in the
      // list, and a search box to add new ones
      $this->PAGE->addContent($p = new XPort("Sailors in list"));
      $p->add(new XP(array(), sprintf("%d sailors meet the criteria for All-American inclusion based on the regattas chosen. Note that non-official sailors have been excluded. Use the bottom form to add more sailors to this list.",
				      count($_SESSION['aa']['sailors']))));
      $p->add($item = new XUl(array('id', 'inc-sailors')));
      foreach ($_SESSION['aa']['sailors'] as $sailor)
	$item->add(new XLi($sailor));

      // Form to fetch and add sailors
      $this->PAGE->head->add(new XScript('text/javascript', '/inc/js/aa.js'));
      $this->PAGE->addContent($p = new XPort("New sailors"));
      $p->add($form = new XForm('/aa-edit', XForm::POST));
      $form->add(new XNoScript(new XP(array(), "Right now, you need to enable Javascript to use this form. Sorry for the inconvenience, and thank you for your understanding.")));
      $form->add(new FItem('Name:', $search = new XTextInput('name-search', "")));
      $search->set('id', 'name-search');
      $form->add($ul = new XUl(array('id', 'aa-input'),
			       array(new XLi("No sailors.", array('class'=>'message')))));
      $form->add(new XSubmitInput('set-sailors', "Generate report >>"));

      return;
    }

    ini_set('memory_limit', '128M');
    ini_set('max_execution_time', 60);
    // ------------------------------------------------------------
    // 3. Step three: Generate and review
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort("Report"));
    $p->add($form = new XForm('/aa-edit', XForm::POST));
    $form->add(new XP(array(), "Please click only once:"));
    $form->add(new XSubmitInput('gen-report', "Download as CSV"));

    $p->add($form = new XForm('/aa-edit', XForm::POST));
    $form->add(new XSubmitInput('unset-sailors', "<< Go back"));
    
    $this->PAGE->addContent(new XTable(array('id'=>'aa-table'),
				       array(new XTHead(array(),
							array($hrow1 = new XTR(array(),
									       array(new XTH(array(), "ID"),
										     new XTH(array(), "Sailor"),
										     new XTH(array(), "YR"),
										     new XTH(array(), "School"),
										     new XTH(array(), "Conf."))),
							      $hrow2 = new XTR(array(),
									       array(new XTH(array(), ""),
										     new XTH(array(), ""),
										     new XTH(array(), ""),
										     new XTH(array(), ""),
										     new XTH(array(), "Races/Div"))))),
					     $table = new XTBody())));
    foreach ($_SESSION['aa']['regatta_races'] as $reg_id => $num) {
      $hrow1->add(new XTH(array('class'=>'rotate'), $reg_id));
      $hrow2->add(new XTH(array(), $num));
    }
    $TABLE = $_SESSION['aa']['table'];
    $row_num = 0;
    foreach ($_SESSION['aa']['sailors'] as $id => $sailor) {
      $table->add($row = new XTR(array('class'=>'row'.($row_num++ % 2)),
				 array(new XTD(array(), $sailor->id),
				       new XTD(array(), sprintf("%s %s", $sailor->first_name, $sailor->last_name)),
				       new XTD(array(), $sailor->year),
				       new XTD(array(), $sailor->school->nick_name),
				       new XTD(array(), $sailor->school->conference))));
      
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
	$row->add(new XTD(array(), implode("/", $_SESSION['aa']['table'][$reg_id][$id])));
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
	$this->announce(new PA("Invalid participation provided.", PA::E));
	return false;
      }
      if (!isset($args['role']) ||
	  !in_array($args['role'], array(RP::SKIPPER, RP::CREW))) {
	$this->announce(new PA("Invalid role provided.", PA::E));
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
	$this->announce(new PA("Proceeding with no added regattas."));
	$_SESSION['aa']['regattas-set'] = true;
	return false;
      }

      $regs = array();
      $errors = 0;
      foreach ($args['regatta'] as $id) {
	try {
	  $reg = new Regatta($id);
	  $allow_other_ptcp = ($_SESSION['aa']['report-participation'] != Regatta::PARTICIPANT_COED ||
			       $reg->get(Regatta::PARTICIPANT) == Regatta::PARTICIPANT_COED);
	  if ($reg->get(Regatta::TYPE) != Preferences::TYPE_PERSONAL && $allow_other_ptcp &&
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
	$this->announce(new PA("Some regattas specified are not valid.", PA::I));
      if (count($_SESSION['aa']['regattas']) > 0)
	$this->announce(new PA("Set regattas for All-American report."));
      $_SESSION['aa']['regattas-set'] = true;
      return $args;
    }

    // ------------------------------------------------------------
    // Set sailors
    // ------------------------------------------------------------
    if (isset($args['set-sailors'])) {
      if (!isset($args['sailor']) || !is_array($args['sailor']) || count($args['sailor']) == 0) {
	$this->announce(new PA("Proceeding with no additional sailors."));
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
	  $this->announce(new PA($e->getMessage(), PA::E));
	}
      }
      if ($errors > 0)
	$this->announce(new PA("Some invalid sailors were provided and ignored.", PA::I));
      $this->announce(new PA("Set sailors for report."));
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
	$this->announce(new PA("No regattas or sailors for report.", PA::E));
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
	    ($_SESSION['aa']['report-participation'] != Regatta::PARTICIPANT_WOMEN ||
	     $rp->sailor->gender == Sailor::FEMALE) &&
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