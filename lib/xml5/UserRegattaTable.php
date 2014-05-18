<?php
/**
 * Convenience table to display a user's regattas
 *
 * @author Dayan Paez
 * @created 2013-06-14
 */
class UserRegattaTable extends XTable {

  protected $user;
  protected $body;
  protected $counter;
  protected $schools;

  /**
   * Creates a new such table for the provided user
   *
   */
  public function __construct(Account $user, $current = false) {
    parent::__construct(array('class'=>'regatta-list'));
    if ($current !== false)
      $this->set('class', 'regatta-list-current');
    $this->add(new XTHead(array(), array($row = new XTR())));
    $this->body = new XTBody();
    $this->add($this->body);

    $this->user = $user;
    $this->schools = $this->user->getSchools();

    $row->add(new XTH(array('title'=>"Involvement in regatta"), "Inv."));
    $row->add(new XTH(array('class'=>'reg-name'), "Name"));
    $row->add(new XTH(array('class'=>'reg-host'), "Host(s)"));
    $row->add(new XTH(array('class'=>'reg-date'), "Date"));
    $row->add(new XTH(array('class'=>'reg-type'), "Type"));
    $row->add(new XTH(array('class'=>'reg-scoring'), "Scoring"));
    $row->add(new XTH(array('class'=>'reg-finalized'), "Finalized"));

    $this->counter = 0;
  }

  /**
   * Adds a new row.
   *
   * Ignores private regattas unless user is scorer
   */
  public function addRegatta(Regatta $reg) {
    $is_participant = false;
    $inv = new XImg(WS::link('/inc/img/scoring.png'), "Scoring", array('title'=>"You are a scorer for this regatta"));
    if (!$this->user->hasJurisdiction($reg)) {

      // Do not allow private regattas for non-scorers
      if ($reg->private !== null)
        return;

      $is_participant = true;
      $inv = new XImg(WS::link('/inc/img/part.png'), "Part.", array('title'=>"Your school is a participant in this regatta"));
    }
    $link = new XA(WS::link('/score/' . $reg->id), $reg->name);
    $row = array(new XTD(array(), $inv), new XTD(array('class'=>'reg-name'), $link));

    $hosts = array();
    foreach ($reg->getHosts() as $host)
      $hosts[$host->id] = $host->nick_name;
    $row[] = new XTD(array('class'=>'reg-host'), implode("/", $hosts));

    $finalized = '--';
    if ($reg->finalized !== null) {
      $is_complete = true;

      if ($is_participant) {
        foreach ($this->schools as $school) {
          foreach ($reg->getTeams($school) as $team) {
            if ($team->dt_complete_rp === null) {
              $finalized = new XA(WS::link(sprintf('/score/%s/rp?chosen_team=%s', $reg->id, $team->id)), "Missing RP",
                                  array('class'=>'stat missing-rp',
                                        'title'=>"At least one skipper/crew is missing."));
              $is_complete = false;
              break;
            }
          }
          if (!$is_complete)
            break;
        }
      }
      elseif (!$reg->isRpComplete()) {
        $is_complete = false;
        $finalized = new XA(WS::link(sprintf('/score/%s/missing-rp', $reg->id)), "Missing RP",
                            array('class'=>'stat missing-rp',
                                  'title'=>"At least one skipper/crew is missing."));
      }
      if ($is_complete)
        $finalized = new XSpan("Finalized", array('class'=>'stat finalized'));
    }
    elseif ($reg->end_date < DB::$NOW) {
      if (count($reg->getTeams()) == 0 || count($reg->getRaces()) == 0)
        $finalized = new XSpan("Incomplete", array('class'=>'stat incomplete', 'title'=>"Missing races or teams."));
      elseif (!$reg->hasFinishes())
        $finalized = new XA(WS::link(sprintf('/score/%s/finishes', $reg->id)), "No finishes",
                            array('class'=>'stat empty',
                                  'title'=>"No finishes entered"));
      else
        $finalized = new XA(WS::link('/score/'.$reg->id.'#finalize'), "Pending",
                            array('title'=>'Regatta must be finalized!',
                                  'class'=>'stat pending'));
    }

    $scoring = ucfirst($reg->scoring);
    if ($reg->isSinglehanded())
      $scoring = "Singlehanded";
    $row[] = new XTD(array('class'=>'reg-date'), $reg->start_time->format("Y-m-d"));
    $row[] = new XTD(array('class'=>'reg-type'), $reg->type);
    $row[] = new XTD(array('class'=>'reg-scoring'), $scoring);
    $row[] = new XTD(array('class'=>'reg-finalized'), $finalized);

    $class = "";
    if ($reg->private)
      $class = 'personal-regatta ';

    $this->body->add(new XTR(array('class'=>$class . 'row'.($this->counter++ % 2)), $row));
  }

  public function count() {
    return $this->counter;
  }
}
?>