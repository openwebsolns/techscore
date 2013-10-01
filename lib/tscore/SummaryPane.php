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

    $can_mail = ((string)DB::getSetting(Setting::SEND_MAIL) == 1);

    $this->PAGE->addContent($xp = new XPort("About the daily summaries"));
    $xp->add($p = new XP(array(), "A text summary is required for each day of competition for all public regattas."));
    if ($this->REGATTA->private == null) {
      $p->add(" The summaries will be printed as part of the ");
      $txt = "regatta report";
      if ($this->REGATTA->dt_status != Regatta::STAT_SCHEDULED)
        $txt = new XA(sprintf('http://%s%s', Conf::$PUB_HOME, $this->REGATTA->getUrl()), $txt);
      $p->add($txt);
      $p->add(".");
      if ($can_mail)
          $p->add(" In addition, the summaries will be used in the daily e-mail message report, if the checkbox is selected below. Note that e-mails may only be sent once per day.");
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
    $form->add(new XP(array(), new XTextArea('summary', $summ, array('rows'=>30, 'id'=>'summary-textarea'))));

    if ($can_mail && ($summ === null || $summ->mail_sent === null)) {
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
      if (((string)DB::getSetting(Setting::SEND_MAIL) == 1) &&
          $summ->summary !== null && $summ->mail_sent === null && $this->REGATTA->private === null &&
          DB::$V->incInt($args, 'email', 1, 2, null) !== null) {

        $recips = array();
        if ($this->REGATTA->type->mail_lists !== null) {
          foreach ($this->REGATTA->type->mail_lists as $target)
            $recips[$target] = $target;
        }
        // Add participant conferences
        foreach ($this->REGATTA->getTeams() as $team) {
          $recips[strtoupper($team->school->conference->id)] = sprintf('%s@lists.collegesailing.org', strtolower($team->school->conference->id));
        }

        $this->sendMessage($recips, $summ);
        $summ->mail_sent = 1;
        DB::set($summ);
        Session::pa(new PA(sprintf("Sent e-mail message to the %s list%s.",
                                   implode(", ", array_keys($recips)),
                                   (count($recips) > 1) ? "s" : "")));
      }
    }
    return $args;
  }

  public function sendMessage(Array $recips, Daily_Summary $summ) {
    // ------------------------------------------------------------
    // text/plain
    $W = 70;
    $body = "";
    $body .= $this->centerInLine($this->REGATTA->name, $W) . "\n";
    $body .= $this->centerInLine($this->REGATTA->type, $W) . "\n";
    $body .= $this->centerInLine($this->REGATTA->getDataScoring(), $W) . "\n";

    $hosts = array();
    foreach ($this->REGATTA->getHosts() as $host)
      $hosts[] = $host->nick_name;
    $boats = array();
    foreach ($this->REGATTA->getBoats() as $boat)
      $boats[] = $boat;
    $hostline = sprintf("%s in %s", implode(", ", $hosts), implode(", ", $boats));
    $body .= $this->centerInLine($hostline, $W) . "\n";
    $url = sprintf('http://%s%s', Conf::$PUB_HOME, $this->REGATTA->getUrl());
    $body .= $this->centerInLine($url, $W) . "\n";
    $body .= "\n";

    $str = $summ->summary_date->format('l, F j');
    $body .= $str . "\n";
    for ($i = 0; $i < mb_strlen($str); $i++)
      $body .= "-";
    $body .= "\n";
    $body .= "\n";
    $paras = explode("\n\n", $summ);
    foreach ($paras as $para)
      $body .= wordwrap($para, $W, " \n") . "\n\n";

    $body .= "\n";
    $body .= sprintf("Top %d\n", min(5, count($this->REGATTA->getTeams())));
    $body .= "-----\n";
    $body .= "\n";
    $body .= wordwrap(sprintf("Visit %s for full results.", $url), $W, " \n");
    $body .= "\n\n";

    if ($this->REGATTA->hasFinishes()) {
      $body .= $this->getResultsTable($W) . "\n";
    }

    $body .= "\n";
    $body .= "-- \n";
    $body .= wordwrap(sprintf("This message sent by %s on behalf of %s.", Conf::$NAME, Conf::$USER), $W, " \n");

    $parts = array('text/plain; charset=utf8' => $body);
    
    // ------------------------------------------------------------
    // text/html
    require_once('xml5/TEmailPage.php');
    $body = new TEmailPage($this->REGATTA->name . " Summary");
    $body->head->add(new XStyle('text/css', '#header{text-align:center;margin-bottom:3ex}h1,h3{margin-bottom:1ex;}table{border-collapse:collapse;border:1px solid #ccc;}th,td{border:1px solid #ccc;}'));
    $body->body->add(new XDiv(array('id'=>'header'),
                              array(new XH1($this->REGATTA->name),
                                    new XH3($this->REGATTA->type),
                                    new XH3($this->REGATTA->getDataScoring()),
                                    new XH3($hostline))));

    require_once('xml5/TSEditor.php');
    $DPE = new TSEditor();
    $DPE->parse((string)$summ);
    $body->body->add(new XDiv(array('id'=>'summary'),
                              array(new XH2($summ->summary_date->format('l, F j')),
                                    new XRawText($DPE->toXML()))));

    if ($this->REGATTA->hasFinishes()) {
      $body->body->add(new XDiv(array('id'=>'scores'),
                                array(new XP(array(),
                                             array("Visit ",
                                                   new XA($url, $url),
                                                   " for full results.")),
                                      $this->getResultsHtmlTable())));
    }

    $parts['text/html; charset=utf8'] = $body->toXML();
    DB::multipartMail($recips, $this->REGATTA->name, $parts);
  }

  protected function centerInLine($str, $W) {
    $pad = ceil(($W + mb_strlen($str)) / 2);
    return sprintf('%' . $pad . 's', $str);
  }

  protected function getResultsTable($W = 70) {
    $table = array();
    $colwidths = array();
    $divisions = $this->REGATTA->getDivisions();

    function updateColwidths(Array $values, &$colwidths) {
      foreach ($values as $i => $value) {
        if (!isset($colwidths[$i]))
          $colwidths[$i] = 0;
        if (mb_strlen($value) > $colwidths[$i])
          $colwidths[$i] = mb_strlen($value);
      }
    }
    $ranks = $this->REGATTA->getRankedTeams();
    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      // Make table
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $row = array(($r + 1),
                     $rank->school->nick_name,
                     $rank->name);
        $tot = 0;
        foreach ($divisions as $div) {
          $div_rank = $rank->getRank($div);
          if ($div_rank === null) {
            $row[] = " "; // to account for header and leaderstar
            $row[] = "";
          }
          else {
            $row[] = $div_rank->score;
            $row[] = (string)$div_rank->penalty;
            $tot += $div_rank->score;
          }
        }
        $row[] = $tot;
        updateColwidths($row, $colwidths);
        $table[] = $row;
      }

      // Last cell is "TOT"
      if ($colwidths[count($colwidths) - 1] < 3)
        $colwidths[count($colwidths) - 1] = 3;

      // Alignment
      $alignment = array("", "", "-");
      foreach ($divisions as $div) {
        $alignment[] = "";
        $alignment[] = "-";
      }
      $alignment[] = "";

      // Column separator
      $sep = "    ";

      // Maximum row width
      $basewidth = 0;
      foreach ($colwidths as $width)
        $basewidth += $width;
      do {
        $sep = substr($sep, 0, -1);
        $rowwidth = $basewidth + strlen($sep) * (count($colwidths) - 1);
      } while ($rowwidth > $W && strlen($sep) > 1);

      // Line prefix
      $prefix = floor(($W - $rowwidth) / 2);

      // Generate table string, centered on $W
      $str = "";

      // Headers
      $str .= sprintf('%' . $prefix . 's', "");
      $str .= sprintf('%' . $colwidths[0] . 's', "#") . $sep;
      $span = ($colwidths[1] + $colwidths[2] + $sep);
      $pad = floor(($span + 4) / 2);
      $str .= sprintf('%' . $pad . 's', "") . "Team";
      $str .= sprintf('%' . ($span - $pad - 1) . 's', "") . $sep;
      $i = 3;
      foreach ($divisions as $j => $div) {
        $str .= sprintf('%' . $colwidths[$i + (2 * $j)] . 's', $div) . $sep;
        $str .= sprintf('%' . $colwidths[$i + (2 * $j) + 1] . 's',
                        $colwidths[$i + (2 * $j) + 1] > 0 ? "P" : "") . $sep;
      }
      $str .= sprintf('%' . $colwidths[count($colwidths) - 1] . 's', "TOT") . "\n";

      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "=";
      $str .= "\n";

      foreach ($table as $i => $row) {
        $str .= sprintf('%' . $prefix . 's', " ");
        foreach ($row as $j => $value) {
          if ($j > 0)
            $str .= $sep;

          $fmt = '%' . $alignment[$j] . $colwidths[$j] . 's';
          $str .= sprintf($fmt, $value);
        }
        $str .= "\n";
      }
      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "-";
      $str .= "\n";
      return $str;
    }
    else {
      // ------------------------------------------------------------
      // Team
      // ------------------------------------------------------------
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $row = array($rank->dt_rank,
                     $rank->school->nick_name,
                     $rank->name,
                     (int)$rank->dt_wins,
                     "-",
                     (int)$rank->dt_losses);
        updateColwidths($row, $colwidths);
        $table[] = $row;
      }

      // Alignment
      $alignment = array("", "", "-", "", "", "-");

      // Column separator
      $sep = "    ";

      // Maximum row width
      $basewidth = 0;
      foreach ($colwidths as $width)
        $basewidth += $width;
      do {
        $sep = substr($sep, 0, -1);
        $rowwidth = $basewidth + strlen($sep) * (count($colwidths) - 1);
      } while ($rowwidth > $W && strlen($sep) > 1);

      // Line prefix
      $prefix = floor(($W - $rowwidth) / 2);

      $str = "";

      // Headers
      $str .= sprintf('%' . $prefix . 's', "");
      $str .= sprintf('%' . $colwidths[0] . 's', "#") . $sep;
      $span = ($colwidths[1] + $colwidths[2] + $sep);
      $pad = floor(($span + 4) / 2);
      $str .= sprintf('%' . $pad . 's', "") . "Team";
      $str .= sprintf('%' . ($span - $pad - 1) . 's', "") . $sep;
      $str .= sprintf('%' . $colwidths[3] . 's', "W") . $sep;
      $str .= sprintf('%' . $colwidths[4] . 's', "-") . $sep;
      $str .= sprintf('%' . $colwidths[5] . 's', "L") . "\n";

      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "=";
      $str .= "\n";

      foreach ($table as $i => $row) {
        $str .= sprintf('%' . $prefix . 's', " ");
        foreach ($row as $j => $value) {
          if ($j > 0)
            $str .= $sep;

          $fmt = '%' . $alignment[$j] . $colwidths[$j] . 's';
          $str .= sprintf($fmt, $value);
        }
        $str .= "\n";
      }
      // ----------
      $str .= sprintf('%' . $prefix . 's', " ");
      for ($j = 0; $j < $rowwidth; $j++)
        $str .= "-";
      $str .= "\n";
      return $str;
    }
  }

  protected function getResultsHtmlTable() {
    $divisions = $this->REGATTA->getDivisions();
    $ranks = $this->REGATTA->getRankedTeams();

    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      $headers = array("#", "School", "Team");
      foreach ($divisions as $div) {
        $headers[] = $div;
        $headers[] = "";
      }
      $headers[] = "TOT";
      $table = new XQuickTable(array('class'=>'results'), $headers);
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $row = array(($r + 1),
                     $rank->school->nick_name,
                     $rank->name);
        $tot = 0;
        foreach ($divisions as $div) {
          $div_rank = $rank->getRank($div);
          if ($div_rank === null) {
            $row[] = "";
            $row[] = "";
          }
          else {
            $row[] = $div_rank->score;
            $row[] = (string)$div_rank->penalty;
            $tot += $div_rank->score;
          }
        }
        $row[] = $tot;
        $table->addRow($row, array('class'=>'row'.($r % 2)));
      }
    }
    else {
      // ------------------------------------------------------------
      // Team
      // ------------------------------------------------------------
      $table = new XQuickTable(array('class'=>'team-results'),
                               array("#", "School", "Team", "Win", "Loss"));
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $table->addRow(array($rank->dt_rank,
                             $rank->school->nick_name,
                             $rank->name,
                             (int)$rank->dt_wins,
                             "-",
                             (int)$rank->dt_losses),
                       array('class'=>'row'.($r % 2)));
      }
    }
    return $table;
  }
}
?>
