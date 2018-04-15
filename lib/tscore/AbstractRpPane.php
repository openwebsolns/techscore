<?php
namespace tscore;

use \tscore\utils\RpPaneParams;

use \AbstractPane;

use \DB;
use \Division;
use \Regatta;
use \RP;
use \STN;
use \Sailor;
use \Season;
use \Team;

use \FItem;
use \FOption;
use \FOptionGroup;
use \FReqItem;
use \XA;
use \XCollapsiblePort;
use \XHiddenInput;
use \XForm;
use \XP;
use \XPort;
use \XSelect;
use \XSelectM;
use \XSubmitP;
use \XTH;
use \XTD;
use \XTHead;
use \XTBody;
use \XTable;
use \XTR;
use \XSubmitAccessible;
use \XValid;

/**
 * Shared element of entering RP.
 *
 * @author Dayan Paez
 * @version 2015-05-09
 */
abstract class AbstractRpPane extends AbstractPane {

  const NO_SAILOR_OPTION = '';
  const NO_SHOW_OPTION_GROUP = "No-show";
  /**
   * Sailor "ID" used to indicate "no-show".
   */
  const NO_SHOW_ID = 'NULL';

  /**
   * Retrieve list of teams, depending on participant_mode.
   *
   * @return Array:Team the teams, indexed by team ID.
   */
  protected function getTeamOptions() {
    if ($this->cachedTeams == null) {
      $possibleTeams = array();
      if ($this->participant_mode) {
        foreach ($this->getUserSchools() as $school) {
          foreach ($this->REGATTA->getTeams($school) as $team) {
            $possibleTeams[] = $team;
          }
        }
      }
      else {
        foreach ($this->REGATTA->getTeams() as $team) {
          $possibleTeams[] = $team;
        }
      }

      // keep only teams that have races (applicable for team racing)
      $this->cachedTeams = array();
      $isTeamRacing = $this->REGATTA->scoring === Regatta::SCORING_TEAM;
      foreach ($possibleTeams as $team) {
        if (!$isTeamRacing || count($this->REGATTA->getTeamRacesFor($team)) > 0) {
          $this->cachedTeams[$team->id] = $team;
        }
      }
    }
    return $this->cachedTeams;
  }
  private $cachedTeams = null;

  /**
   * Gets introductory message for the pane.
   *
   */
  protected function getIntro() {
    return new XP(
      array(),
      array(
        "Use the form below to enter RP information. If a sailor does not appear in the selection box, it means they are not in the ",
        DB::g(STN::ORG_NAME),
        " database, and they have to be manually added to a temporary list in the ",
        new XA($this->link('unregistered'), "Unregistered form"),
        "."
      )
    );
  }

  /**
   * Create port with message about no available teams.
   *
   * @return XPort
   */
  protected function createNoTeamPort() {
    $p = new XPort("No teams registered");
    if (!$this->participant_mode) {
      $p->add(
        new XP(
          array(),
          array(
            "In order to register sailors, you will need to ",
            new XA($this->link('team'), "register teams"),
            " first."
          )
        )
      );
    }
    return $p;
  }

  protected function getRpPaneParams(Array $args) {
    $divisions = $this->REGATTA->getDivisions();
    $params = new RpPaneParams();

    $teamOptions = $this->getTeamOptions();
    $teamIds = array_keys($teamOptions);
    $params->chosenTeam = $teamOptions[$teamIds[0]];
    if (array_key_exists('chosen_team', $args) && array_key_exists($args['chosen_team'], $teamOptions)) {
      $params->chosenTeam = $teamOptions[$args['chosen_team']];
    }

    $params->rps = array();
    $params->participatingSailorsById = array();
    $params->participatingSchoolsById = array();
    $params->schoolsById = array();

    // Fill out the current schools
    $params->schoolsById[$params->chosenTeam->school->id] = $params->chosenTeam->school;

    $roles = array(RP::SKIPPER, RP::CREW);
    $rpManager = $this->REGATTA->getRpManager();

    foreach ($divisions as $division) {
      $d = (string) $division;
      $params->rps[$d] = array();
      foreach ($roles as $role) {
        $lst = $rpManager->getRP($params->chosenTeam, $division, $role);
        foreach ($lst as $entry) {
          if ($entry->sailor !== null) {
            $params->schoolsById[$entry->sailor->school->id] = $entry->sailor->school;
            $params->participatingSchoolsById[$entry->sailor->school->id] = $entry->sailor->school;
            $params->participatingSailorsById[$entry->sailor->id] = $entry->sailor;
          }
        }
        $params->rps[$d][$role] = $lst;
      }
    }

    // Requested schools (for cross RP)
    $params->requestedSchoolsById = array();
    foreach (DB::$V->incList($args, 'schools') as $id) {
      $school = DB::getSchool($id);
      if ($school !== null) {
        $params->requestedSchoolsById[$id] = $school;
        $params->schoolsById[$id] = $school;
      }
    }

    // ------------------------------------------------------------
    // - Create option lists
    $season = $this->REGATTA->getSeason();
    $gender = null;
    if ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) {
      $gender = Sailor::FEMALE;
    }

    // Sailors
    $params->sailorOptions = array(self::NO_SAILOR_OPTION => '');
    $params->attendeeOptions = array();
    foreach ($params->schoolsById as $school) {
      $key = $school->nick_name;

      foreach ($school->getSailorsInSeason($season, $gender, true) as $s) {
        if (!array_key_exists($key, $params->sailorOptions)) {
          $params->sailorOptions[$key] = array();
          $params->attendeeOptions[$key] = array();
        }
        $params->sailorOptions[$key][$s->id] = (string)$s;
        $params->attendeeOptions[$key][$s->id] = (string)$s;
      }
      $key .= ' (Unregistered)';
      foreach ($school->getUnregisteredSailors($gender) as $s) {
        if (!array_key_exists($key, $params->sailorOptions)) {
          $params->sailorOptions[$key] = array();
          $params->attendeeOptions[$key] = array();
        }
        $params->sailorOptions[$key][$s->id] = (string)$s;
        $params->attendeeOptions[$key][$s->id] = (string)$s;
      }
    }

    // Include sailors who are already participating, even if they are
    // not allowed.
    foreach ($params->participatingSailorsById as $s) {
      $key = $s->school->nick_name;
      if (!array_key_exists($key, $params->sailorOptions)) {
        $params->sailorOptions[$key] = array();
      }
      $params->sailorOptions[$key][$s->id] = (string)$s;
    }

    // Sort each list
    foreach ($params->sailorOptions as $key => $list) {
      if (is_array($params->sailorOptions[$key])) {
        asort($params->sailorOptions[$key]);
      }
    }

    // No show option
    $params->sailorOptions[self::NO_SHOW_OPTION_GROUP] = array(self::NO_SHOW_ID => "No show");

    return $params;
  }

  /**
   * Create and return form for switching teams.
   *
   * @param Array $teams the list of teams indexed by ID.
   * @param Team $chosen the chosen team, if any.
   * @return XPort
   */
  protected function createChooseTeamPort(Array $teams, Team $chosen = null) {
    if (count($teams) < 2) {
      return null;
    }

    $chosen_id = null;
    if ($chosen !== null) {
      $chosen_id = $chosen->id;
    }

    $p = new XPort("Choose a team");

    $p->add($form = $this->createForm(XForm::GET));
    $form->add(new FReqItem("Team:", $sel = XSelect::fromArray('chosen_team', $teams, $chosen_id)));
    $sel->set('onchange', 'submit(this);');
    $form->add(new XSubmitAccessible('change_team', "Get form"));

    return $p;
  }

  /**
   * Create the port for missing RP information.
   *
   * @param Team $chosen whose information to use.
   */
  protected function createMissingPort(Team $chosen) {
    if (count($this->REGATTA->getScoredRacesForTeam(Division::A(), $chosen)) == 0) {
      return null;
    }

    $p = new XCollapsiblePort(sprintf("What's missing from %s", $chosen));
    $p->add(new XP(array(), "This port shows what information is missing for this team. Note that only scored races are considered."));

    $this->fillMissing($p, $chosen);
    return $p;
  }

  protected function isCrossRpAllowed() {
    return (!$this->REGATTA->isSingleHanded() && DB::g(STN::ALLOW_CROSS_RP) !== null);
  }

  /**
   * Create the port for cross RP information, if allowed.
   *
   * @param Team $chosen whose school is already participating.
   * @param Array $schools map of schools already participating, indexed by ID.
   * @param Array $requestedSchools additional schools, indexed by ID.
   */
  protected function createCrossRpPort(Team $chosen, Array $schools, Array $requestedSchools) {
    $p = new XCollapsiblePort("Include sailors from other schools?");
    $p->add($f = $this->createForm(XForm::GET));
    $f->add(new XHiddenInput('chosen_team', $chosen->id));
    $f->add(new FItem("Other schools:", $ul = new XSelectM('schools[]', array('size' => '10'))));
    foreach (DB::getConferences() as $conf) {
      $opts = array();
      foreach ($this->getConferenceSchools($conf) as $school) {
        if ($school->id == $chosen->school->id) {
          continue;
        }

        $opt = new FOption($school->id, $school);
        if (array_key_exists($school->id, $schools)) {
          $opt->set('selected', 'selected');
          $opt->set('disabled', 'disabled');
          $opt->set('title', "There are already sailors from this school in the RP form.");
        }
        elseif (array_key_exists($school->id, $requestedSchools)) {
          $opt->set('selected', 'selected');
        }
        $opts[] = $opt;
      }
      if (count($opts) > 0)
        $ul->add(new FOptionGroup($conf, $opts));
    }
    $f->add(new XSubmitP('go', "Fetch sailors"));

    return $p;
  }

  /**
   * Fill provided port with information about what's missing from given team.
   *
   * @param XPort $p the port to fill.
   * @param Team $chosen_team whose data to populate.
   */
  protected function fillMissing(XPort $p, Team $chosen_team) {
    $divisions = $this->REGATTA->getDivisions();
    $rpManager = $this->REGATTA->getRpManager();

    $header = new XTR(array(), array(new XTH(array(), "#")));
    $rows = array();
    foreach ($divisions as $divNumber => $div) {
      $name = "Division " . $div;
      if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
        $name = $div->getLevel(count($divisions));
      $header->add(new XTH(array('colspan'=>2), $name));

      foreach ($this->REGATTA->getScoredRacesForTeam($div, $chosen_team) as $race) {
        // get missing info
        $skip = null;
        $crew = null;
        if (count($rpManager->getRpEntries($chosen_team, $race, RP::SKIPPER)) == 0)
          $skip = "Skipper";
        $diff = $race->boat->min_crews - count($rpManager->getRpEntries($chosen_team, $race, RP::CREW));
        if ($diff > 0) {
          if ($race->boat->min_crews == 1)
            $crew = "Crew";
          else
            $crew = sprintf("%d Crews", $diff);
        }

        if ($skip !== null || $crew !== null) {
          if (!isset($rows[$race->number]))
            $rows[$race->number] = array(new XTH(array(), $race->number));
          // pad the row with previous division
          for ($i = count($rows[$race->number]) - 1; $i < $divNumber * 2; $i += 2) {
            $rows[$race->number][] = new XTD();
            $rows[$race->number][] = new XTD();
          }
          $rows[$race->number][] = new XTD(array(), $skip);
          $rows[$race->number][] = new XTD(array(), $crew);
        }
      }
    }

    if (count($rows) > 0) {
      $p->add(new XTable(array('class'=>'missingrp-table'),
                         array(new XTHead(array(), array($header)),
                               $bod = new XTBody())));
      $rowIndex = 0;
      foreach ($rows as $row) {
        for ($i = count($row); $i < count($divisions) * 2 + 1; $i++)
          $row[] = new XTD();
        $bod->add(new XTR(array('class'=>'row' . ($rowIndex++ % 2)), $row));
      }
    }
    else
      $p->add(new XValid("Information is complete."));
  }
}
