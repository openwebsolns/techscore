<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2
 * @version 2010-04-19
 * @package tscore
 */

require_once('users/AbstractUserPane.php');

/**
 * Create a new regatta
 *
 */
class NewRegattaPane extends AbstractUserPane {

  /**
   * Create a pane for creating regattas
   *
   * @param Account $user the user creating the regatta
   */
  public function __construct(Account $user) {
    parent::__construct("New regatta", $user);
  }

  private function defaultRegatta() {
    $types = DB::getAll(DB::$ACTIVE_TYPE);
    $day = new DateTime('next Saturday');
    return array('name' => '',
                 'private' => null,
                 'start_date' => $day->format('m/d/Y'),
                 'start_time'  => DB::g(STN::DEFAULT_START_TIME),
                 'duration' => 2,
                 'venue' => '',
                 'scoring' => Regatta::SCORING_STANDARD,
                 'type' => $types[count($types) - 1]->id,
                 'participant' => Regatta::PARTICIPANT_COED,
                 'num_divisions' => 2,
                 'num_races' => '18');
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Create"));
    $p->add($f = $this->createForm());

    $r = $this->defaultRegatta();
    // Replace with values from $args
    foreach ($r as $key => $value) {
      if (isset($args[$key]))
        $r[$key] = $args[$key];
    }

    $f->add(new FReqItem("Name:", new XTextInput('name', $r['name'], array('maxlength'=>35))));
    $f->add(new FItem("Private:", new FCheckbox('private', 1, "Private regattas are not published and are temporary.", $r['private'] !== null)));
    $f->add(new FReqItem("Start date:", new XDateInput('start_date', new DateTime($r['start_date']))));
    $f->add(new FReqItem("On the water:", new XTimeInput('start_time', new DateTime($r['start_time']))));
    $f->add(new FReqItem("Duration (days):", new XNumberInput('duration', $r['duration'], 1, 99, 1)));
    $f->add(new FItem("Venue:",   $sel = new XSelect("venue")));
    $f->add(new FReqItem("Scoring:", XSelect::fromArray("scoring", Regatta::getScoringOptions(), $r["scoring"])));
    $f->add(new FReqItem("Type:", XSelect::fromDBM('type', DB::getAll(DB::$ACTIVE_TYPE), $r['type'])));
    $f->add(new FReqItem("Participation:", XSelect::fromArray("participant", Regatta::getParticipantOptions(),
                                                           $r["participant"])));

    // select
    $sel->add(new FOption("", ""));
    foreach (DB::getVenues() as $venue) {
      $sel->add($opt = new FOption($venue->id, $venue));
      if ($venue->id == $r["venue"])
        $opt->set('selected', 'selected');
    }

    // host: if it has more than one host, otherwise send it hidden
    $confs = array(); // array of conference choices
    $schools = $this->USER->getSchools();
    if (count($schools) == 1) {
      $school = $schools[0];
      $f->add(new FReqItem("Host:", new XSpan($school)));
      $f->add(new XHiddenInput('host[]', $school->id));
    }
    else {
      $confs = array();
      foreach ($schools as $school) {
        if (!isset($confs[$school->conference->id]))
          $confs[$school->conference->id] = array();
        $confs[$school->conference->id][$school->id] = $school;
      }
      $f->add(new FReqItem("Host(s):", $sel = XSelectM::fromArray('host[]', $confs), "There must be at least one"));
      $sel->set('size', 10);
    }
    $f->add(new XSubmitP("new-regatta", "Create"));
  }

  /**
   * Creates the new regatta
   *
   */
  public function process(Array $args) {
    if (isset($args['new-regatta'])) {
      $error = false;
      // 1. Check name
      if (!DB::$V->hasString($name, $args, 'name', 1, 36)) {
        Session::pa(new PA("Invalid name.", PA::E));
        $error = true;
      }
      // 1b: Private?
      $private = DB::$V->incInt($args, 'private', 1, 2, null);
      // 2. Check date
      if (!DB::$V->hasDate($sdate, $args, 'start_date')) {
        Session::pa(new PA("Invalid date given.", PA::E));
        $error = true;
      }
      // 3. Check time
      if (!DB::$V->hasDate($stime, $args, 'start_time')) {
        Session::pa(new PA("Invalid start time.", PA::E));
        $error = true;
      }
      // 4. Check duration
      if (!DB::$V->hasInt($duration, $args, 'duration', 1, 100)) {
        Session::pa(new PA("Invalid duration.", PA::E));
        $error = true;
      }
      // 5. Venue
      $venue = DB::$V->incID($args, 'venue', DB::$VENUE);
      // 6. Scoring
      if (!DB::$V->hasKey($scoring, $args, 'scoring', Regatta::getScoringOptions())) {
        Session::pa(new PA("Invalid regatta type.", PA::E));
        $error = true;
      }
      // 7. Type
      if (!DB::$V->hasID($type, $args, 'type', DB::$ACTIVE_TYPE)) {
        Session::pa(new PA("Invalid regatta type.", PA::E));
        $error = true;
      }
      // 8. Participation
      if (!DB::$V->hasKey($participant, $args, 'participant', Regatta::getParticipantOptions())) {
        Session::pa(new PA("Invalid regatta participation.", PA::E));
        $error = true;
      }
      // 9. Host(s)
      if (!DB::$V->hasList($thehosts, $args, 'host') || count($thehosts) == 0) {
        Session::pa(new PA("No hosts supplied.", PA::E));
        $error = true;
      }
      else {
        $hosts = array();
        $schools = $this->USER->getSchools();
        foreach ($thehosts as $id) {
          $school = DB::getSchool($id);
          if ($school !== null && isset($schools[$school->id])) 
            $hosts[] = $school;
        }
        if (count($hosts) == 0) {
          Session::pa(new PA("No valid hosts supplied.", PA::E));
          $error = true;
        }
      }

      // Submit?
      if ($error)
        return $args;

      $sdate->setTime(0, 0);
      $end = clone($sdate);
      $sdate->add(new DateInterval(sprintf('P0DT%dH%dM', $stime->format('G'), $stime->format('i'))));
      $end->add(new DateInterval(sprintf('P%dD', ($duration - 1))));

      // If there is no season, then quit
      $season = Season::forDate($sdate);
      if ($season === null)
        throw new SoterException("There is no season in the database for the requested regatta. Please contact the administrators for more information.");
      try {
        $reg = Regatta::createRegatta($name,
                                      $sdate,
                                      $end,
                                      $type,
                                      $scoring,
                                      $participant,
                                      $private);
        $reg->venue = $venue;
        $reg->creator = $this->USER;
        $reg->addScorer($this->USER);

        // sponsor?
        if (DB::g(STN::REGATTA_SPONSORS)) {
          $reg->sponsor = $season->sponsor;
        }

        DB::set($reg);
        foreach ($hosts as $school)
          $reg->addHost($school);
      } catch (InvalidArgumentException $e) {
        // This should be reached ONLY because of a nick-name mismatch
        Session::pa(new PA("It seems that there is already an active regatta with this name for the current season. This is likely the result of a previous regatta that was not deleted or changed to \"Private\". If you are a scorer for the other regatta, please delete it or de-activate it before creating this one. Otherwise, you may need to create the current one under a different name.", PA::I));
        return $args;
      }

      // Move to new regatta
      Session::pa(new PA(sprintf("Created new regatta \"%s\". Please add teams now.", $reg->name)));
      $this->redirect(sprintf('score/%s/teams', $reg->id));
    }
    return array();
  }
}
?>
