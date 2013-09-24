<?php
/*
 * This file is part of TechScore
 *
 * @package tscore
 */

require_once('conf.php');

/**
 * Changes the daily summaries for the regatta
 *
 * @author Dayan Paez
 * @version 2010-03-24
 */
class SummaryPane extends AbstractPane {

  /**
   * Creates a new editing pane
   *
   */
  public function __construct(Account $user, Regatta $reg) {
    parent::__construct("Summaries", $user, $reg);
  }

  protected function fillHTML(Array $args) {
    $this->PAGE->head->add(new LinkCSS('/inc/css/sum.css'));

    $duration = $this->REGATTA->getDuration();
    $f = $this->createForm(XForm::GET);
    $f->add($prog = new XP(array('id'=>'progressdiv')));
    if ($duration > 1)
      $this->PAGE->addContent($f);

    $this->PAGE->addContent($xp = new XPort("About the daily summaries"));
    $xp->add($p = new XP(array(), "A text summary is required for each day of competition for all public regattas."));
    if ($this->REGATTA->private == null) {
      $p->add(" The summaries will be printed as part of the ");
      $txt = "regatta report";
      if ($this->REGATTA->dt_status != Regatta::STAT_SCHEDULED)
        $txt = new XA(sprintf('http://%s%s', Conf::$PUB_HOME, $this->REGATTA->getUrl()), $txt);
      $p->add($txt);
      $p->add(". In addition, the summaries will be used in the daily e-mail message report, if the checkbox is selected below. Note that e-mails may only be sent once per day.");
    }
    $xp->add(new XP(array(), "Tips for writing summaries:"));
    $xp->add(new XUl(array(),
                                    array(new XLi("Write directly in the form below, or copy and paste from Notepad or similar plain-text editor. Some Office Productivity Suites add invalid encoding characters that may not render properly."),
                                          new XLi("Leave an empty line to create a new paragraph. Short paragraphs are easier to read."),
                                          new XLi("Good summaries consist of a few sentences (at least 3) that describe the event, race conditions, and acknowledge the staff at the event."),
                                          new XLi("Do not include a reference to the day in the summary, as this is automatically included in all reports and is thus redundant."),
                                          new XLi("Do not include hyperlinks to the scores site, as these can change and should be generated only by the program."))));

    $this->PAGE->addContent($p = new XPort("Daily summary"));
    $p->add($form = $this->createForm());

    // Which day's summary?
    $s = clone($this->REGATTA->start_time);
    $s->setTime(0, 0);
    $e = clone($this->REGATTA->end_date);
    $e->setTime(23, 59, 59);
    $day = DB::$V->incDate($args, 'day', $s, $e, null);
    if ($day === null) {
      $now = clone(DB::$NOW);
      $now->setTime(0, 0);
      $diff = $now->diff($s);
      if ($now <= $s)
        $day = $s;
      elseif ($diff->days >= $duration)
        $day = $e;
      else {
        $day = clone($s);
        $day->add(new DateInterval(sprintf('P%dDT0H', $diff->days)));
      }
    }
    $summ = $this->REGATTA->getSummary($day);
    $form->add(new XHiddenInput('day', $day->format('Y-m-d')));
    $form->add(new XH4($day->format('l, F j')));
    $form->add(new XP(array(), new XTextArea('summary', $summ, array('rows'=>30, 'style'=>'width:100%'))));

    if ($summ === null || $summ->mail_sent === null) {
      $form->add($fi = new FItem("Send e-mail:", new XCheckboxInput('email', 1, array('id'=>'chk-mail'))));
      $fi->add(new XLabel('chk-mail', "Click to send e-mail to appropriate mailing lists with regatta details."));
    }
    $form->add(new XSubmitP('set_comment', 'Save summary'));

    $day = $day->format('Y-m-d');
    for ($i = 0; $i < $duration; $i++) {
      if ($s->format('Y-m-d') == $day)
        $prog->add(new XSpan($s->format('l, F j')));
      else {
        $prog->add($sub = new XSubmitInput('day', $s->format('l, F j')));
      }
      $s->add(new DateInterval('P1DT0H'));
    }
  }

  /**
   * Processes changes to daily summaries
   *
   */
  public function process(Array $args) {
    if (isset($args['set_comment'])) {
      $s = clone($this->REGATTA->start_time);
      $s->setTime(0, 0);
      $e = clone($this->REGATTA->end_date);
      $e->setTime(23, 59, 59);
      $day = DB::$V->reqDate($args, 'day', $s, $e, "No date provided for summary.");
      $summ = $this->REGATTA->getSummary($day);
      if ($summ === null)
        $summ = new Daily_Summary();
      $summ->summary = DB::$V->incString($args, 'summary', 1, 16000, null);
      $this->REGATTA->setSummary($day, $summ);
      Session::pa(new PA(sprintf("Updated summary for %s.", $day->format('l, F j'))));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SUMMARY);

      // Send mail?
      if ($summ->summary !== null && $summ->mail_sent === null && $this->REGATTA->private === null &&
          DB::$V->incInt($args, 'email', 1, 2, null) !== null) {
        // @TODO
        $recips = array();
        switch ($this->REGATTA->type->id) {
        case 'conference-championship':
        case 'championship':
          $recips['alumni'] = 'alumni@lists.collegesailing.org';

        case 'intersectional':
        case 'promotional':
          $recips['ICSA'] = 'icsa@lists.collegesailing.org';

        case 'conference':
          $confs = array();
          foreach ($this->REGATTA->getHosts() as $host) {
            $recips[strtoupper($host->conference->id)] = sprintf('%s@lists.collegesailing.org', $host->conference->id);
          }
          break;
        }
        if ($this->REGATTA->type->id == 'two-conference') {
          foreach ($this->REGATTA->getTeams() as $team) {
            $recips[strtoupper($team->school->conference->id)] = sprintf('%s@lists.collegesailing.org', $team->school->conference->id);
          }
        }
        if (count($recips) > 0) {
          $this->sendMessage($recips, $summ);
          $summ->mail_sent = 1;
          DB::set($summ);
          Session::pa(new PA(sprintf("Sent e-mail message to the %s list%s.",
                                     implode(", ", array_keys($recips)),
                                     (count($recips) > 1) ? "s" : "")));
        }
      }
    }
    return $args;
  }

  protected function sendMessage(Array $recips, Daily_Summary $summ) {
    $W = 72;
    $body = "";
    $body .= $this->centerInLine($this->REGATTA->name, $W) . "\r\n";
    $body .= $this->centerInLine($this->REGATTA->type, $W) . "\r\n";
    $body .= $this->centerInLine($this->REGATTA->getDataScoring(), $W) . "\r\n";

    $hosts = array();
    foreach ($this->REGATTA->getHosts() as $host)
      $hosts[] = $host->nick_name;
    $boats = array();
    foreach ($this->REGATTA->getBoats() as $boat)
      $boats[] = $boat;
    $body .= $this->centerInLine(sprintf("%s in %s", implode(", ", $hosts), implode(", ", $boats)), $W) . "\r\n";
    $body .= "\r\n";

    $body .= $summ->summary_date->format('l, F j') . "\r\n";
    $body .= "\r\n";
    $body .= wordwrap($summ, $W, " \r\n") . "\r\n";
    $body .= "\r\n";
    
    $has_scores = false;
    $scored = array();
    $divs = ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) ?
      $this->REGATTA->getDivisions() :
      array(Division::A());
    foreach ($divs as $div) {
      $num = count($this->REGATTA->getScoredRaces($div));
      if ($num > 0) {
        $has_scores = true;
        $str = sprintf("%d races", $num);
        if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD)
          $str .= sprintf(" in %s division", $div);
        $scored[(string)$div] = $str;
      }
    }
    if ($has_scores) {
      $tms = $this->REGATTA->getRankedTeams();
      $str = sprintf("Leader:     %s after %s.", $tms[0], implode(", ", $scored));
      $body .= wordwrap($str, $W, " \r\n") . "\r\n";
    }

    if ($this->REGATTA->scoring == Regatta::SCORING_STANDARD) {
      $ranker = $this->REGATTA->getDivisionRanker();
      foreach ($scored as $div => $mes) {
        $tms = $ranker->rank($this->REGATTA, $this->REGATTA->getScoredRaces(Division::get($div)));
        $body .= sprintf("%s division: %s (-%d points)", $div, $tms[0]->team, ($tms[0]->score - $tms[1]->score)) . "\r\n";
      }
      if (count($scored) > 0)
        $body .= "\r\n";
    }
    if ($this->REGATTA->scoring == Regatta::SCORING_COMBINED) {
      $ranker = $this->REGATTA->getDivisionRanker();
      $tms = $ranker->rank($this->REGATTA);
      $body .= sprintf("Combined leader: %s, %s division (-%d points)", $tms[0]->team, $tms[0]->division, ($tms[0]->score - $tms[1]->score)) . "\r\n";
    }

    $body .= wordwrap(sprintf("Visit http://%s%s for up to the minute results.", Conf::$PUB_HOME, $this->REGATTA->getUrl()), $W, " \r\n") . "\r\n";
    $body .= "-- \r\n";
    $body .= wordwrap(sprintf("This message sent by %s on behalf of %s.", Conf::$NAME, Conf::$USER), 78, " \r\n");

    foreach ($recips as $recip)
      DB::mail($recip, $this->REGATTA->name, $body);
  }

  protected function centerInLine($str, $W) {
    $pad = ceil(($W + mb_strlen($str)) / 2);
    return sprintf('%' . $pad . 's', $str);
  }
  
}
?>
