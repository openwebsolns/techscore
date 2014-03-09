<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package tscore
 */

require_once('tscore/AbstractPane.php');

/**
 * The "home" pane where the regatta's details are edited.
 *
 * 2010-02-24: Allowed scoring rules change
 *
 * 2012-12-30: Because team racing and fleet racing are so different,
 * do not allow switching from one to the other
 *
 * @author Dayan Paez
 * @version 2009-09-27
 */
class DetailsPane extends AbstractPane {

  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Settings", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    // ------------------------------------------------------------
    // Finalize regatta
    // ------------------------------------------------------------
    if ($this->REGATTA->end_date < DB::$NOW) {
      if ($this->REGATTA->finalized === null) {
        if (!$this->participant_mode) {
          if ($this->REGATTA->hasFinishes()) {
            $this->PAGE->addContent($p = new XPort("Finalize regatta"));
            $p->set('id', 'finalize');
            $p->add(new XP(array('class'=>'warning'),
                           array(new XStrong("Note:"), " all official regattas must be finalized, or they will be flagged as incomplete.")));
            $p->add(new XP(array(),
                           array("Once finalized, all unsailed races will be removed from the system. This means that no new races can be created. However, existing races can be re-scored, if needed. In addition, RP information will still be available for edits after finalization.")));
            $p->add(new XP(array(),
                           array("Once ready to finalize, visit the ", new XA($this->link('finalize'), "Finalize"), " pane to review the regatta.")));
          }
        }
      }
      else {
        $this->PAGE->addContent($p = new XPort("Finalize regatta"));
        $p->add(new XP(array('class'=>'valid'),
                       sprintf("This regatta was finalized on %s.", $this->REGATTA->finalized->format("l, F j Y"))));
      }
    }

    // ------------------------------------------------------------
    // Regatta details
    // ------------------------------------------------------------
    $this->PAGE->addContent($p = new XPort('Regatta details'));
    $p->addHelp('/node12.html');

    $p->add($reg_form = $this->createForm());
    // Name
    $value = $this->REGATTA->name;
    $reg_form->add(new FReqItem("Name:",
                                ($this->participant_mode) ?
                                new XStrong($value) :
                                new XTextInput("reg_name",
                                               $value,
                                               array("maxlength"=>35,
                                                     "size"     =>20))));

    // Private
    if (!$this->participant_mode) {
      $reg_form->add($fi = new FItem("Private:",
                                     $chk = new XCheckboxInput('private', 1, array('id'=>'chk-priv'))));
      $fi->add(new XLabel('chk-priv', "Private regattas are not published and are temporary."));
      if ($this->REGATTA->private)
        $chk->set('checked', 'checked');
    }

    // Date
    $start_time = $this->REGATTA->start_time;
    $reg_form->add(new FReqItem("Date:",
                             ($this->participant_mode) ?
                             new XStrong($start_time->format('Y-m-d')) :
                             new XDateInput('sdate', $start_time, null, null, null,
                                            array('size'=>20))));
    // Duration
    $value = $this->REGATTA->getDuration();
    $reg_form->add(new FReqItem("Duration (days):",
                             ($this->participant_mode) ?
                             new XStrong($value) :
                             new XNumberInput('duration', $value, 1, 99, 1,
                                              array('maxlength'=>2, 'size'=>2))));
    // On the water
    $reg_form->add(new FReqItem("On the water:",
                             ($this->participant_mode) ?
                             new XStrong($start_time->format('H:i')) :
                             new XTimeInput('stime', $start_time, null, null, null,
                                            array('size'=>8))));

    // Venue
    $venue = $this->REGATTA->venue;
    if ($this->participant_mode)
      $reg_form->add(new FItem("Venue:", new XStrong($venue)));
    else {
      $reg_form->add(new FItem("Venue:", $r_type = new XSelect("venue")));
      $r_type->add(new FOption("", "[Leave blank if not found]"));
      foreach (DB::getVenues() as $v) {
        $r_type->add($opt = new FOption($v->id, $v->name));
        if ($venue !== null && $venue->id == $v->id)
          $opt->set('selected', 'selected');
      }
    }

    // Regatta type
    $value = $this->REGATTA->type;
    if ($this->participant_mode)
      $reg_form->add(new FReqItem("Type:", new XStrong($value)));
    else {
      $reg_form->add(new FReqItem("Type:", $r_type = new XSelect('type')));
      $r_type->add(new FOption("", "[Choose type]"));
      foreach (DB::getAll(DB::$ACTIVE_TYPE) as $v) {
        $r_type->add($opt = new FOption($v->id, $v));
        if ($v->id == $value->id)
          $opt->set('selected', 'selected');
      }
    }

    // Regatta participation
    $value = $this->REGATTA->participant;
    $options = Regatta::getParticipantOptions();
    if ($this->participant_mode)
      $reg_form->add(new FReqItem("Participation:", new XStrong($options[$value])));
    else {
      $reg_form->add($item = new FReqItem("Participation:",
                                       XSelect::fromArray('participant',
                                                          $options,
                                                          $value)));
      // will changing this value affect the RP information?
      if ($value == Regatta::PARTICIPANT_COED)
        $item->add(new XMessage("Changing this value may affect RP info"));
    }

    // Scoring rules
    $options = Regatta::getScoringOptions();
    $value = $this->REGATTA->scoring;
    if ($this->REGATTA->scoring == Regatta::SCORING_TEAM)
      $reg_form->add(new FReqItem("Scoring:", new XStrong("Team racing")));
    else {
      if ($this->participant_mode)
        $reg_form->add(new FReqItem("Scoring:", new XStrong($options[$value])));
      else {
        unset($options[Regatta::SCORING_TEAM]);
        $reg_form->add($fi = new FReqItem("Scoring:", XSelect::fromArray('scoring', $options, $value)));
        if ($this->REGATTA->scoring != Regatta::SCORING_COMBINED &&
            $this->REGATTA->hasFinishes() &&
            isset($options[Regatta::SCORING_COMBINED]))
          $fi->add(new XMessage("Changing to \"Combined\" will remove incomplete finishes and rotations."));
      }
    }

    // Hosts: first add the current hosts, then the entire list of
    // schools in the affiliation ordered by conference
    $hosts = $this->REGATTA->getHosts();
    if ($this->participant_mode) {
      $val = array();
      foreach ($hosts as $school)
        $val[] = $school->nick_name;
      $reg_form->add(new FReqItem("Host:", new XStrong(implode(", ", $val))));
    }
    else {
      // special case that there is only one host AND the user has no
      // more than one school associated with them
      if (count($this->USER->getSchools()) == 1 && count($hosts) == 1) {
        $reg_form->add($fitem = new FReqItem("Host:", new XSpan($hosts[0]->nick_name)));
        $fitem->add(new XHiddenInput('host[]', $hosts[0]->id));
      }
      else {
        $reg_form->add($f_item = new FReqItem('Host(s):', $f_sel = new XSelectM("host[]", array('size'=>10))));

        $schools = array(); // track these so as not to include them later
        foreach ($hosts as $host) {
          $schools[$host->id] = $host;
        }
        $f_item->add(new XMessage("Hold down Ctrl to choose more than one"));

        // go through each conference
        foreach (DB::getConferences() as $conf) {
          $opts = array();
          foreach ($this->USER->getSchools($conf) as $school) {
            $opt = new FOption($school->id, $school);
            if (isset($schools[$school->id]))
              $opt->set('selected', 'selected');
            $opts[] = $opt;
          }
          if (count($opts) > 0)
            $f_sel->add(new FOptionGroup($conf, $opts));
        }
      }
    }

    // Update button
    if (!$this->participant_mode)
      $reg_form->add(new XP(array(), new XSubmitInput("edit_reg", "Edit")));
  }

  /**
   * Process edits to the regatta
   */
  public function process(Array $args) {
    if ($this->participant_mode)
      throw new SoterException("Insufficient permissions.");

    // ------------------------------------------------------------
    // Details
    if ( isset($args['edit_reg']) ) {
      $edited = false;
      $create_nick = false;
      $update_season = null;

      // Private?
      $private = DB::$V->incInt($args, 'private', 1, 2, null);
      if ($private !== $this->REGATTA->private) {
        $this->REGATTA->private = $private;
        $edited = true;
        $create_nick = ($private === null);
      }

      // Type
      if (DB::$V->hasID($V, $args, 'type', DB::$ACTIVE_TYPE) && $V != $this->REGATTA->type) {
        $this->REGATTA->type = $V;
        $edited = true;
      }

      // Name
      if (DB::$V->hasString($V, $args, 'reg_name', 1, 36) && $V !== $this->REGATTA->name) {
        $this->REGATTA->name = $V;
        $edited = true;
      }

      // Start time
      if (isset($args['sdate']) && isset($args['stime'])) {
        try {
          $sdate = new DateTime($args['sdate'] . ' ' . $args['stime']);
          if ($sdate != $this->REGATTA->start_time) {
            // Track a possible season change
            $cur_season = $this->REGATTA->getSeason();

            $this->REGATTA->start_time = $sdate;
            $edited = true;

            // If there's a season change, re-create nick
            $new_season = $this->REGATTA->getSeason();
            if ($new_season === null)
              throw new SoterException("There is no season for the newly chosen start time.");

            if ($new_season != $cur_season) {
              $create_nick = true;
              $update_season = $cur_season;
            }
          }
        } catch (Exception $e) {
          throw new SoterException("Invalid starting date and/or time.");
        }
      }

      // Duration
      if (DB::$V->hasInt($V, $args, 'duration', 1, 30) && $V != $this->REGATTA->getDuration()) {
        $edate = clone($this->REGATTA->start_time);
        $edate->add(new DateInterval(sprintf('P%dDT0H', ($V - 1))));
        $edate->setTime(0, 0);
        $this->REGATTA->end_date = $edate;
        // Is the end-date beyond the season end_date?
        $season = $this->REGATTA->getSeason();
        if ($season->end_date < $edate)
          throw new SoterException(sprintf("Regatta cannot end after the season: %s", $season->end_date->format('m/d/Y')));
        $edited = true;
      }

      $V = DB::$V->incID($args, 'venue', DB::$VENUE);
      if ($V != $this->REGATTA->venue) {
        $this->REGATTA->venue = $V;
        $edited = true;
      }

      // only allow scoring changes if NOT team racing
      if ($this->REGATTA->scoring != Regatta::SCORING_TEAM && DB::$V->hasKey($V, $args, 'scoring', Regatta::getScoringOptions()) && $V != $this->REGATTA->scoring) {
        $this->REGATTA->setScoring($V);
        $edited = true;

        $divs = $this->REGATTA->getDivisions();
        // Are there scores?
        if ($this->REGATTA->hasFinishes()) {
          // If going to combined scoring, delete incomplete races
          if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
            // list of divs organized by race number
            $scored_divs = array();
            foreach ($this->REGATTA->getScoredRaces() as $race) {
              if (!isset($scored_divs[$race->number]))
                $scored_divs[$race->number] = array();
              $scored_divs[$race->number][(string)$race->division] = $race;
            }

            $dropped_races = array();
            foreach ($scored_divs as $num => $list) {
              if (count($list) != count($divs)) {
                foreach ($list as $race) {
                  $this->REGATTA->deleteFinishes($race);
                  $dropped_races[] = $num;
                }
              }
            }

            if (count($dropped_races) > 0)
              Session::pa(new PA("Removed finishes for following races due to incompleteness: " . implode(", ", $dropped_races), PA::I));
          }
          
          $this->REGATTA->doScore();
          Session::pa(new PA("Re-scored the regatta."));
          UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SCORE);
        }
        // Are there rotations?
        if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
          $rot = $this->REGATTA->getRotation();
          if ($rot->isAssigned()) {
            // Remove rotation for any races for which there is a
            // conflict in the rotation
            if (count($rot->getDivisions()) != count($divs)) {
              $rot->reset();
              Session::pa(new PA("Rotations reset due to inconsistency.", PA::I));
              UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
            }
            else {
              $required_count = count($this->REGATTA->getTeams()) * count($divs);
              foreach ($rot->getRaces(Division::A()) as $race) {
                $sails = array();
                foreach ($rot->getCombinedSails($race) as $sail)
                  $sails[] = (string)$sail;
                $unique = array_unique($sails);
                if (count($unique) != $required_count) {
                  $rot->reset();
                  Session::pa(new PA("Rotations reset due to duplicate sails in race $race.", PA::I));
                  UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_ROTATION);
                  break;
                }
              }
            }
          }
        }
      }

      if (DB::$V->hasKey($V, $args, 'participant', Regatta::getParticipantOptions()) && $V != $this->REGATTA->participant) {
        $this->REGATTA->participant = $V;
        // affect RP accordingly
        if ($this->REGATTA->participant == Regatta::PARTICIPANT_WOMEN) {
          $rp = $this->REGATTA->getRpManager();
          if ($rp->hasGender(Sailor::MALE)) {
            $rp->removeGender(Sailor::MALE);
            Session::pa(new PA("Removed sailors from RP.", PA::I));
          }
        }
        $edited = true;
      }

      // Host(s): go through the list, ascertaining the validity. Once
      // we know we have at least one valid host for the regatta,
      // reset the hosts, and add each school, one at a time
      if (DB::$V->hasList($V, $args, 'host')) {
        $current_schools = array();
        foreach ($this->REGATTA->getHosts() as $host)
          $current_schools[$host->id] = $host;
        $changed = false;

        $hosts = array();
        foreach ($V as $id) {
          $school = DB::getSchool($id);
          $a = $this->USER->hasSchool($school);

          if ($school !== null && $this->USER->hasSchool($school)) {
            $hosts[] = $school;
            if (!isset($current_schools[$school->id]))
              $changed = true;
            unset($current_schools[$school->id]);
          }
        }
        if (count($current_schools) > 0)
          $changed = true;

        if (count($hosts) == 0)
          throw new SoterException("There must be at least one host for each regatta.");
        if ($changed) {
          $this->REGATTA->resetHosts();
          foreach ($hosts as $school)
            $this->REGATTA->addHost($school);
          Session::pa(new PA("Edited regatta host(s)."));
        }
      }

      if ($create_nick) {
        try {
          $url = $this->REGATTA->createNick();
          if ($url != $this->REGATTA->nick) {
            $this->REGATTA->nick = $url;
            UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_URL, $this->REGATTA->getURL());
            Session::pa(new PA("Regatta's public URL is now: " . $this->REGATTA->getURL()));
          }
        } catch (InvalidArgumentException $e) {
          throw new SoterException("Unable to publish the regatta. Most likely, you attempted to activate a regatta that is under the same name as another already-activated regatta in the same season. Before you can do that, please make sure that the other regatta with the same name as this one is removed or de-activated (made private) before proceeding.");
        }
      }

      if ($update_season !== null) {
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SEASON, (string)$update_season);
        Session::pa(new PA("New season for regatta: " . $new_season->fullString(), PA::I));
      }

      if ($edited) {
        $this->REGATTA->setData(); // implies regatta object update
        Session::pa(new PA("Edited regatta details."));
        UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_DETAILS);
      }
    }
  }
}
?>
