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

    $duration = $this->REGATTA->getDuration();

    // Which day's summary?
    $summary_day = clone($this->REGATTA->start_time);
    if ($duration > 1) {
      $now = new DateTime();
      $day = DB::$V->incInt($args, 'day', 1, $duration + 1, null);
      if ($day === null) {
        // pick the one closest to today
        $diff = $now->diff($summary_day);
        if ($diff->invert == 0)
          $day = 1;
      }

      $prog = new XP(array('id'=>'progressdiv'));
      $this->PAGE->addContent($prog);

      $s = clone($this->REGATTA->start_time);
      $now = $now->format('Y-m-d');
      for ($i = 1; $i <= $duration; $i++) {
        if ($day === null && $s->format('Y-m-d') == $now)
          $day = $i;

        if ($i == $day) {
          $prog->add(new XSpan($s->format('l, F j, Y'), array('class'=>'current')));
          $summary_day = clone($s);
        }
        else {
          $prog->add(new XA($this->link('summary', array('day'=>$i)), $s->format('l, F j, Y')));
        }
        $s->add(new DateInterval('P1DT0H'));
      }
    }

    $can_mail = ($this->REGATTA->private === null) && ((string)DB::g(STN::SEND_MAIL) == 1);
    $can_rp_mail = ($this->REGATTA->private === null) 
      && (DB::g(STN::MAIL_RP_REMINDER) !== null)
      && (DB::T(DB::NOW)->format('Y-m-d') == $summary_day->format('Y-m-d'));

    $this->PAGE->addContent($xp = new XCollapsiblePort("About the daily summaries"));
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

    $summ = $this->REGATTA->getSummary($summary_day);
    $form->add(new XHiddenInput('day', $summary_day->format('Y-m-d')));
    $form->add(new XH4($summary_day->format('l, F j')));
    $form->add(new XP(array(), new XTextArea('summary', $summ, array('rows'=>30, 'id'=>'summary-textarea'))));

    if ($can_mail && ($summ === null || $summ->mail_sent === null)) {
      $form->add(new FItem("Send e-mail report:", new FCheckbox('email', 1, "Click to send e-mail to appropriate mailing lists with regatta details.")));
    }
    if ($can_rp_mail && ($summ === null || $summ->rp_mail_sent === null)) {
      $form->add(new FItem("Send RP reminder:", new FCheckbox('rp_email', 1, "Click to send RP reminder e-mail to participants"), "These messages may be sent only once per day, preferably at the end."));
    }
    $form->add(new XSubmitP('set_comment', 'Save summary'));
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
      $min_length = 20;
      $summ->summary = DB::$V->incString($args, 'summary', 1, 16000, null);
      if ($summ->summary !== null && strlen($summ->summary) < $min_length)
        throw new SoterException("Insufficient summary. Please include a few sentences describing the event conditions, staff involved, winning teams, and other note-worthy observations.");
      $this->REGATTA->setSummary($day, $summ);
      Session::pa(new PA(sprintf("Updated summary for %s.", $day->format('l, F j'))));
      UpdateManager::queueRequest($this->REGATTA, UpdateRequest::ACTIVITY_SUMMARY);

      // Send mail?
      if (((string)DB::g(STN::SEND_MAIL) == 1) &&
          $summ->summary !== null && $summ->mail_sent === null && $this->REGATTA->private === null &&
          DB::$V->incInt($args, 'email', 1, 2, null) !== null) {

        $recips = array();
        if ($this->REGATTA->type->mail_lists !== null) {
          foreach ($this->REGATTA->type->mail_lists as $target)
            $recips[$target] = $target;
        }
        // Add participant conferences
        $checked_confs = array();
        foreach ($this->REGATTA->getTeams() as $team) {
          $id = $team->school->conference->id;
          if (!isset($checked_confs[$id])) {
            $checked_confs[$id] = 1;
            if ($team->school->conference->mail_lists !== null) {
              foreach ($team->school->conference->mail_lists as $target)
                $recips[$target] = $target;
            }
          }
        }

        $this->sendMessage($recips, $summ);
        $summ->mail_sent = 1;
        DB::set($summ);
        Session::pa(new PA(sprintf("Sent e-mail message to the %s list%s.",
                                   implode(", ", array_keys($recips)),
                                   (count($recips) > 1) ? "s" : "")));
      }

      // Send RP mail?
      if (($template = DB::g(STN::MAIL_RP_REMINDER)) !== null &&
          $summ->summary !== null && $summ->rp_mail_sent === null && $this->REGATTA->private === null &&
          DB::$V->incInt($args, 'rp_email', 1, 2, null) !== null) {

        $users = array();
        $teams = array(); // map of user ID => list of teams
        foreach ($this->REGATTA->getSchools() as $school) {
          $my_teams = $this->REGATTA->getTeams($school);
          $my_users = $school->getUsers(Account::STAT_ACTIVE, false);
          foreach ($my_users as $user) {
            if (!isset($users[$user->id])) {
              $users[$user->id] = $user;
              $teams[$user->id] = array();
            }
            foreach ($my_teams as $team)
              $teams[$user->id][] = $team;
          }
        }

        // Send each message to each user
        $rp_sent = 0;
        foreach ($users as $id => $user) {
          $subject = sprintf("[%s] Update RP for %s", DB::g(STN::APP_NAME), $this->REGATTA->name);
          $mes = str_replace(
            '{BODY}',
            $this->getRpMessage($user, $teams[$id]),
            DB::keywordReplace($template, $user, $user->getFirstSchool())
          );
          DB::mail($user->email, $subject, $mes);
          $rp_sent++;
        }

        $summ->rp_mail_sent = 1;
        DB::set($summ);
        Session::pa(new PA(sprintf("Sent RP reminder message to %d user%s.",
                                   $rp_sent,
                                   ($rp_sent > 1) ? "s" : "")));
      }
    }

    // Tweet?
    if ($summ->summary !== null &&
        $summ->tweet_sent === null &&
        $this->REGATTA->private === null &&
        $this->REGATTA->type->tweet_summary !== null &&
        count($this->REGATTA->getScoredRaces()) > 0 &&
        $day->format('Y-m-d') == DB::T(DB::NOW)->format('Y-m-d') &&
        $day->format('Y-m-d') != $this->REGATTA->end_date->format('Y-m-d')) {

      require_once('twitter/TweetFactory.php');
      $fac = new TweetFactory();
      $twt = $fac->create(TweetFactory::DAILY_SUMMARY, $this->REGATTA, $day);
      if ($twt !== null) {
        DB::tweet($twt);
        Session::pa(new PA("Tweeted: " . $twt));
        $summ->tweet_sent = 1;
        DB::set($summ);
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
    $paras = explode("\r\n\r\n", $summ);
    foreach ($paras as $para)
      $body .= wordwrap(preg_replace('/\s+/', ' ', $para), $W, " \n") . "\n\n";

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
    $body .= wordwrap(sprintf("This message sent by %s on behalf of %s.", DB::g(STN::APP_NAME), $this->USER), $W, " \n");

    $parts = array('text/plain; charset=utf8' => $body);
    
    // ------------------------------------------------------------
    // text/html
    require_once('xml5/TEmailPage.php');
    $body = new TEmailPage($this->REGATTA->name . " Summary");

    $body->body->set('style', 'font-family:Georgia,serif;max-width:45em;margin:0 auto;padding:0 1em;');
    $h1args = array('style'=>'margin:0.5ex 0;font-family:Arial,Helvetica,sans-serif;font-variant:small-caps;font-size:160%;');
    $h2args = array('style'=>'font-size:120%;');
    $h3args = array('style'=>'margin:0.5ex 0;font-size:110%;color:#48484A;');

    $body->body->add(new XDiv(array('id'=>'header','style'=>'text-align:center;margin-bottom:3ex;'),
                              array(new XH1($this->REGATTA->name, $h1args),
                                    new XH3($this->REGATTA->type, $h3args),
                                    new XH3($this->REGATTA->getDataScoring(), $h3args),
                                    new XH3($hostline, $h3args),
                                    new XH3(new XA($url, "View full report"), $h3args))));

    require_once('xml5/TSEditor.php');
    $DPE = new TSEditor();
    $list = $DPE->parse((string)$summ);
    array_unshift($list, new XH2($summ->summary_date->format('l, F j'), $h2args));
    $body->body->add(new XDiv(array('id'=>'summary'), $list));

    if ($this->REGATTA->hasFinishes()) {
      $body->body->add(new XDiv(array('id'=>'scores'),
                                array(new XH2(sprintf("Top %d\n", min(5, count($this->REGATTA->getTeams()))), $h2args),
                                      new XP(array(),
                                             array("Visit ",
                                                   new XA($url, $url),
                                                   " for full results.")),
                                      $this->getResultsHtmlTable())));
    }
    $body->body->add(new XAddress(array('style'=>'color:#555;border-top:1px solid #555;margin-top:4ex;padding:1ex 0;'),
                                  array(sprintf("This message sent by %s on behalf of %s.", DB::g(STN::APP_NAME), $this->USER))));

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

        $row = array(($r + 1), $rank);
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
      $alignment = array("", "-");
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
      $str .= sprintf('%-' . $colwidths[1] . 's', "Team") . $sep;
      $i = 2;
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
                     $rank,
                     (int)$rank->dt_wins,
                     "-",
                     (int)$rank->dt_losses);
        updateColwidths($row, $colwidths);
        $table[] = $row;
      }

      // Alignment
      $alignment = array("", "-", "", "", "-");

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
      $str .= sprintf('%-' . $colwidths[1] . 's', "Team") . $sep;
      $str .= sprintf('%' . $colwidths[2] . 's', "W") . $sep;
      $str .= sprintf('%' . $colwidths[3] . 's', "-") . $sep;
      $str .= sprintf('%' . $colwidths[4] . 's', "L") . "\n";

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
    $tdArgs = array('style'=>'padding:0.5ex;');
    $trArgs = array('style'=>'border:1px solid #ccc;');
    $tdRight = array('style'=>'padding:0.5ex;text-align:right;');
    $tabArgs = array('style'=>'margin:1ex auto;border-collapse:collapse;border:1px solid #ccc;');

    $divisions = $this->REGATTA->getDivisions();
    $ranks = $this->REGATTA->getRankedTeams();

    if ($this->REGATTA->scoring != Regatta::SCORING_TEAM) {
      $headers = array("#", "Team");
      foreach ($divisions as $div) {
        $headers[] = $div;
        $headers[] = "";
      }
      $headers[] = "TOT";
      $table = new XQuickTable($tabArgs, $headers);

      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $row = array(new XTD($tdArgs, ($r + 1)),
                     new XTD($tdArgs, $rank));
        $tot = 0;
        foreach ($divisions as $div) {
          $div_rank = $rank->getRank($div);
          if ($div_rank === null) {
            $row[] = "";
            $row[] = "";
          }
          else {
            $row[] = new XTD($tdRight, $div_rank->score);
            $row[] = new XTD($tdArgs, (string)$div_rank->penalty);
            $tot += $div_rank->score;
          }
        }
        $row[] = new XTD($tdRight, new XStrong($tot));
        $table->addRow($row, $trArgs);
      }
    }
    else {
      // ------------------------------------------------------------
      // Team
      // ------------------------------------------------------------
      $table = new XQuickTable(array('style'=>'margin:1ex auto;border-collapse:collapse;border:1px solid #ccc;'),
                               array("#", "Team", "Win", "Loss"));
      foreach ($ranks as $r => $rank) {
        if ($r >= 5)
          break;

        $table->addRow(array($rank->dt_rank,
                             new XTD($tdArgs, $rank),
                             new XTD($tdRight, (int)$rank->dt_wins),
                             new XTD($tdArgs, (int)$rank->dt_losses)),
                       $trArgs);
      }
    }
    return $table;
  }

  private function getRpMessage(Account $user, Array $teams) {
    $body = sprintf("*%s* https://%s/score/%s/missing\n", 
                    $this->REGATTA->name, 
                    Conf::$HOME, 
                    $this->REGATTA->id);
    foreach ($teams as $team) {
      $body .= sprintf("\n - %s%s",
                       $team,
                       ($team->dt_complete_rp) ? ""  : " (incomplete)");
    }
    return $body;
  }
}
?>
