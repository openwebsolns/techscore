<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

require_once('rpwriter/AbstractRpForm.php');

/**
 * Class for writing RP forms for sloops
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
class IcsaRpFormSloops extends AbstractRpForm {

  /**
   * Creates a new form for two divisions
   *
   * @param String $name the name of the regatta
   * @param String $host the host of the regatta
   * @param String $date the date of the regatta
   */
  public function __construct($name, $host, $date) {
    parent::__construct($name, $host, $date, 3, 2, 6);
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

        // - write content: skippers for divisions A
        $x = 0.50;
        $Y = 8.9 - 3.05 * $within_page;
        // :A
        foreach ($block->skipper_A as $i => $s) {
          $y = $Y - (0.25 * $i);
          $year = substr($s->getSailorYear(), 2);
          $races = DB::makeRange($s->races_nums);
          $pc->add(sprintf($fmt, $x,        $y, $s->getSailorName()));
          $pc->add(sprintf($fmt, $x + 3.8,  $y, $year));
          $pc->add(sprintf($fmt, $x + 4.3, $y, $races));
        }

        // crews
        $x = 0.80;
        $Y = 8.38 - 3.05 * $within_page;
        foreach ($block->crew_A as $i => $s) {
          $y = $Y - (0.27 * $i);
          $year = substr($s->getSailorYear(), 2);
          $races = DB::makeRange($s->races_nums);
          $pc->add(sprintf($fmt, $x,        $y, $s->getSailorName()));
          $pc->add(sprintf($fmt, $x + 3.5,  $y, $year));
          $pc->add(sprintf($fmt, $x + 4.0, $y, $races));
        }

        // - update within page
        $within_page = ($within_page + 1) % $this->blocks_per_page;
      }
    } // end of blocks

    $inc = $this->getIncludeGraphics();
    $pages = array();
    foreach ($pics as $pic)
      $pages[] = sprintf("%s %s", $inc, $pic);

    $body = implode('\clearpage ', $pages);
    $body = str_replace("**num_pages**", count($pages), $body);
    return str_replace("&", "\&", $body);
  }

  public function getPdfName() {
    return __DIR__ . '/ICSA-RP-SLOOPS.pdf';
  }
}
?>