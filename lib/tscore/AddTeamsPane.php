<?php
/**
 * Edit pane for regatta's teams.
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package tscore
 */

require_once('AbstractTeamPane.php');

class AddTeamsPane extends AbstractTeamPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Add Team", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0) {
      $this->fillNewRegatta($args);
      return;
    }

    // Add teams
    $title = "Add team";
    if (($n = DB::g(STN::ORG_NAME)) !== null)
      $title = sprintf("Add team from %s school", $n);
    $this->PAGE->addContent($p = new XPort($title));
    $p->set('id', 'add');
    $p->add(new XP(array(), "Choose a school from which to add a new team. Because the regatta is under way, you may only add one team at a time."));

    $p->add($form = $this->createForm());
    $form->add(new FReqItem("Schools:", $this->newSchoolSelect()));

    // What to do with rotation?
    $form->add($exp = new XP());
    if ($this->has_rots) {
      $exp->add(new XText("The regatta already has rotations. By adding a team, the rotations will need to be fixed. Choose from the options below."));
      $form->add(new FItem("Delete rotation:",
			   new FCheckbox('del-rotation', 1, "Delete current rotation without affecting finishes.", true)));
    }

    // What to do with scores?
    if ($this->has_scores) {
      $exp->add(new XText("The regatta already has finishes entered. After adding the new teams, what should their score be?"));
      $form->add(new FReqItem("New score:", XSelect::fromArray('new-score', array('DNS' => 'DNS', 'BYE' => 'BYE'))));
    }
    $form->add(new XSubmitP("invite", "Register team"));
  }

  /**
   * Edit details about teams
   */
  public function process(Array $args) {
    $teams = $this->REGATTA->getTeams();
    if (count($teams) == 0)
      return $this->processNewRegatta($args);

    // ------------------------------------------------------------
    // Add team
    if (isset($args['invite'])) {
      $school = DB::$V->reqID($args, 'school', DB::$SCHOOL, "Invalid or missing school to add.");

      // Also validate rotation and finish option, if applicable
      if ($this->has_rots && !isset($args['del-rotation']))
        throw new SoterException("Please choose an action to take with new rotation.");

      if ($this->has_scores) {
        $new_score = DB::$V->reqValue($args, 'new-score', array(Penalty::DNS, Breakdown::BYE), "Please choose an appropriate action to take with new scores.");
        $new_score = ($new_score == Penalty::DNS) ? new Penalty(Penalty::DNS) : new Breakdown(Breakdown::BYE);
      }

      $team = new Team();
      $team->school = $school;
      $changed = $this->calculateTeamName($team);
      $this->REGATTA->addTeam($team);

      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_TEAM, $team->school);

      foreach ($changed as $other)
        DB::set($other);

      // If there are already races, then update details
      if (count($this->REGATTA->getDivisions()) > 0)
        $this->REGATTA->setData();

      if (isset($args['del-rotation'])) {
        $rot = $this->REGATTA->getRotation();
        $rot->reset();
        Session::pa(new PA("Rotation has been reset.", PA::I));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
      }

      // Scores
      $scored_races = $this->REGATTA->getScoredRaces();
      if (count($scored_races) > 0) {
        $finishes = array();
        foreach ($scored_races as $race) {
          $finish = $this->REGATTA->createFinish($race, $team);
          $finish->entered = new DateTime();
          $finish->setModifier($new_score);
          $finishes[] = $finish;
        }
        $this->REGATTA->commitFinishes($finishes);
        $this->REGATTA->doScore();
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
      }

      // RP Log
      $rpManager = $this->REGATTA->getRpManager();
      $rpManager->updateLog();

      // Messages
      Session::pa(new PA("Added team $team."));
    }
    return array();
  }

  // ------------------------------------------------------------
  // Create teams
  // ------------------------------------------------------------

  private function fillNewRegatta(Array $args) {
    if (!$this->PAGE->isMobile())
      $this->PAGE->head->add(new XScript('text/javascript', WS::link('/inc/js/addTeams.js'), null, array('async'=>'async', 'defer'=>'defer')));

    $confs = DB::getConferences();
    $title = "Add teams";
    if (($n = DB::g(STN::ORG_NAME)) !== null)
      $title = sprintf("Add teams from %s schools", $n);
    $this->PAGE->addContent($p = new XPort($title));
    $p->add(new XP(array('id'=>'explanation'), "Choose schools which are participating by indicating how many teams are invited from each school."));
    $p->add($form = $this->createForm());
    $form->add($list = new XUl(array('class'=>'assoc-list', 'id'=>'teams-list')));

    foreach ($confs as $conf) {
      $list->add(new XLi(array(new XHeading($conf, array('id'=>$conf->id)), $sub = new XUl())));
      foreach ($this->getConferenceSchools($conf) as $school) {
        $sub->add(new XLi(array(new XHiddenInput('school[]', $school->id),
                                new XNumberInput('number[]', "", 0, null, 1, array('id'=>$school->id)),
                                new XLabel($school->id, $school,
					   array('onclick'=>sprintf('var o=document.getElementById("%s");o.value=Number(o.value)+1;', $school->id))))));
      }
    }
    $form->add(new XSubmitP('set-teams', "Register teams"));
  }

  public function processNewRegatta(Array $args) {
    // the only thing to do: register me some teams!
    $map = DB::$V->reqMap($args, array('school', 'number'), null, "Bad input. Please try again.");

    $teams_added = array();
    foreach ($map['school'] as $i => $id) {
      $number = (int)$map['number'][$i];
      if ($number > 0 && ($school = DB::getSchool($id)) !== null) {
        $names = $school->getTeamNames();
        $name = (count($names) == 0) ? $school->nick_name : $names[0];

        for ($num = 0; $num < $number; $num++) {
          $suf = " " . ($num + 1);
          if ($number == 1)
            $suf = "";

          $team = new Team();
          $team->school = $school;
          $team->name = $name . $suf;
          $teams_added[] = $team;
        }
      }
    }
    // need two teams for a regatta
    if (count($teams_added) < 2)
      throw new SoterException("Please add at least two teams to proceed.");

    foreach ($teams_added as $team)
      $this->REGATTA->addTeam($team);

    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM) {
      Session::pa(new PA(array(sprintf("Added %d teams. Next, ", count($teams_added)),
                               new XA($this->link('races'), "setup the rounds"),
                               ".")));
      $this->redirect('races');
    }

    Session::pa(new PA(array(sprintf("Added %d teams. Next, ", count($teams_added)),
                             new XA($this->link('races'), "setup the races"),
                             ".")));
    $this->redirect('races');
  }
}
?>
