<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

require_once('rpwriter/AbstractIcsaRpForm.php');

/**
 * Class for writing RP forms for sloops
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
class IcsaRpFormSloops extends AbstractIcsaRpForm {

  /**
   * Creates a new form for two divisions
   *
   * @param String $name the name of the regatta
   * @param String $host the host of the regatta
   * @param String $date the date of the regatta
   */
  public function __construct($name, $host, $date) {
    parent::__construct($name, $host, $date, 2, 2, 6);
    $this->INC = sprintf('\includegraphics[width=\textwidth]{%s}',
                         sprintf("%s/ICSA-RP-SLOOPS.pdf", dirname(__FILE__)));
  }

  /**
   * Returns the LaTeX code for the body of this form
   *
   * @return String the LaTeX code
   */
  protected function getBody() {
    $pics = array();
    $within_page = 0;
    $fmt = '\put(%0.2f, %0.2f){%s}';
    foreach ($this->blocks as $id => $list) {
      foreach ($list as $num => $block) {
        if ($within_page == 0) {
          $pc = new LatexPic(-0.25, 0);
          $pc->add(sprintf('\put(7.05, 10.33){\thepage} ' .
                           '\put(7.50, 10.33){**num_pages**} ' .
                           '\put(1.75, 10.05){%s} ' .
                           '\put(4.25, 10.05){%s} ' .
                           '\put(6.55, 10.05){%s} ',
                           $this->regatta_name,
                           $this->host,
                           $this->date));
          $pics[] = $pc;
        }

        // - team and representative
        $team = $this->teams[$id];
        $name = sprintf("%s %s", $team->school->nick_name, $team->name);
        if ($num > 0)
          $name .= sprintf(" (%d)", $num + 1);
        $team_X = 1.25;
        $team_Y = 9.70 - 3.05 * $within_page;
        $pc->add(sprintf($fmt, $team_X, $team_Y, $name));
        $pc->add(sprintf($fmt, $team_X + 4.6, $team_Y,
                         $this->representatives[$id]));

        // - write content: skippers for divisions A/B
        $x = 0.75;
        $Y = 8.88 - 3.05 * $within_page;
        // :A
        foreach ($block->skipper_A as $i => $s) {
          $y = $Y - (0.3 * $i);
          $year = substr($s->sailor->year, 2);
          $races = DB::makeRange($s->races_nums);
          $pc->add(sprintf($fmt, $x,        $y, $s->sailor->getName()));
          $pc->add(sprintf($fmt, $x + 1.9,  $y, $year));
          $pc->add(sprintf($fmt, $x + 2.33, $y, $races));
        }

        // crews
        $Y = 8.38 - 3.05 * $within_page;
        foreach ($block->crew_A as $i => $s) {
          $y = $Y - (0.3 * $i);
          $year = substr($s->sailor->year, 2);
          $races = DB::makeRange($s->races_nums);
          $pc->add(sprintf($fmt, $x,        $y, $s->sailor->getName()));
          $pc->add(sprintf($fmt, $x + 1.9,  $y, $year));
          $pc->add(sprintf($fmt, $x + 2.33, $y, $races));
        }

        // - update within page
        $within_page = ($within_page + 1) % $this->blocks_per_page;
      }
    } // end of blocks

    $pages = array();
    foreach ($pics as $pic)
      $pages[] = sprintf("%s %s", $this->INC, $pic);

    $body = implode('\clearpage ', $pages);
    $body = str_replace("**num_pages**", count($pages), $body);
    return str_replace("&", "\&", $body);
  }
}
?>