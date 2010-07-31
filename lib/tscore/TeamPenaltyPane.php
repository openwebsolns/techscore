<?php
/**
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once("conf.php");

/**
 * Enter and drop team penalties
 *
 * @author Dayan Paez
 * @created 2010-01-25
 */
class TeamPenaltyPane extends AbstractPane {

  public function __construct(User $user, Regatta $reg) {
    parent::__construct("Team penalty", $user, $reg);
  }

  protected function fillHTML(Array $args) {

    $divisions = array();
    foreach ($this->REGATTA->getDivisions() as $div)
      $divisions[(string)$div] = $div;
    $teams = array();
    foreach ($this->REGATTA->getTeams() as $team)
      $teams[$team->id] = $team;
    
    $this->PAGE->addContent($p = new Port("Team penalties per division"));
    $p->addChild(new Para("These penalties will be added to the final " .
			  "team score after all race finishes have been " .
			  "totaled. The penalty is <strong>+20 points " .
			  "per division</strong>."));

    if (count($teams) == 0) {
      $p->addChild(new Heading("No teams have been registered."));
      return;
    }

    $p->addChild($form = $this->createForm());
    $form->addChild(new FItem("Team:",
			      $f_sel = new FSelect("team", array(""))));
    $f_sel->addOptions($teams);

    $form->addChild(new FItem("Division(s):<br/>" .
			      "<small>Hold down <kbd>Ctrl</kbd> " .
			      "to select multiple</small>",
			      $f_sel = new FSelect("division[]",
						   array(),
						   array("multiple"=>"multiple"))));
    $f_sel->addOptions($divisions);

    // Penalty type
    $form->addChild(new FItem("Penalty type:",
			      $f_sel = new FSelect("penalty", array())));

    $f_sel->addOptions(array_merge(array(""=>""), TeamPenalty::getList()));

    $form->addChild(new FItem("Comments:",
			      new FTextarea("comments", "",
					    array("rows"=>"2",
						  "cols"=>"15"))));

    $form->addChild(new FSubmit("t_submit", "Enter team penalty"));

    
    // ------------------------------------------------------------
    // Existing penalties
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new Port("Team penalties"));
    $penalties = $this->REGATTA->getTeamPenalties();

    if (count($penalties) == 0)
      $p->addChild(new Para("There are no team penalties."));
    else {
      $p->addChild($tab = new Table());
      $tab->addAttr("class", "narrow");

      $tab->addHeader(new Row(array(Cell::th("Team name"),
				    Cell::th("Division"),
				    Cell::th("Penalty"),
				    Cell::th("Comments"),
				    Cell::th("Action"))));

      foreach ($penalties as $p) {
	$tab->addRow(new Row(array(new Cell($p->team, array("class"=>"strong")),
				   new Cell($p->division),
				   new Cell($p->type),
				   new Cell($p->comments, array("width"=>"10em",
								"style"=>"text-align: left")),
				   new Cell($form = $this->createForm()))));

	$form->addChild(new FHidden("r_team", $p->team->id));
	$form->addChild(new FHidden("r_div",  $p->division));
	$form->addChild($sub = new FSubmit("t_remove", "Drop",
					   array("class"=>"thin")));
      }
    }
  }

  
  public function process(Array $args) {

    // ------------------------------------------------------------
    // Add penalty
    // ------------------------------------------------------------
    if (isset($args['t_submit'])) {
      $team = Preferences::getObjectWithProperty($this->REGATTA->getTeams(),
						 "id",
						 $args['team']);
      // - validate team
      if ($team == null) {
	$mes = sprintf("Invalid or missing team (%s).", $args['team']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // - validate penalty
      $pnty = $args['penalty'];
      if (!in_array($pnty, array_keys(TeamPenalty::getList()))) {
	$mes = sprintf("Invalid or missing penalty (%s).", $args['penalty']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
	return $args;
      }

      // - validate division
      $comm = addslashes(htmlspecialchars($args['comments']));
      if (isset($args['division']) &&
	  is_array($args['division']) &&
	  count($args['division']) > 0) {

	$penalty = new TeamPenalty();
	$penalty->team = $team;
	$penalty->type = $pnty;
	$penalty->comments = $comm;
	
	$divisions = $this->REGATTA->getDivisions();
	foreach ($args['division'] as $div) {
	  if (in_array($div, $divisions)) {
	    $penalty->division = new Division($div);
	    $this->REGATTA->setTeamPenalty($penalty);
	  }
	}
	$this->announce(new Announcement("Added team penalty."));
      }
      else {
	$mes = "Invalid or missing division(s).";
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
    }

    
    // ------------------------------------------------------------
    // Drop penalty
    // ------------------------------------------------------------
    if (isset($args['t_remove'])) {

      // - validate team
      $teams = $this->REGATTA->getTeams();
      $team = Preferences::getObjectWithProperty($teams, "id", $args['r_team']);

      // - validate division
      $divisions = $this->REGATTA->getDivisions();
      if ($team != null && in_array($args['r_div'], $divisions)) {

	$this->REGATTA->dropTeamPenalty($team, new Division($args['r_div']));

	$mes = sprintf("Dropped team penalty for %s in %s.", $team, $div);
	$this->announce(new Announcement($mes));
      }
      else {
	$mes = sprintf("Invalid or missing team (%s) or division (%s).",
		       $args['r_team'], $args['r_div']);
	$this->announce(new Announcement($mes, Announcement::ERROR));
      }
    }

    return $args;
  }

  public function isActive() {
    return (count($this->REGATTA->getDivisions()) > 0 &&
	    count($this->REGATTA->getTeams())     > 1);
  }
}