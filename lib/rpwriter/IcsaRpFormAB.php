<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

require_once('rpwriter/AbstractRpForm.php');

/**
 * This file is a new visualization of the RP form writing process. In
 * particular, the work progress has changed in this version so that
 * entire blocks are allocated into memory, before they are rendered
 * by the class into the corresponding LaTeX code, rather than writing
 * the LaTeX code on the fly as it was before.
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
class IcsaRpFormAB extends AbstractRpForm {

  /**
   * Creates a new form for two divisions
   *
   * @param String $name the name of the regatta
   * @param String $host the host of the regatta
   * @param String $date the date of the regatta
   */
  public function __construct($name, $host, $date) {
    parent::__construct($name, $host, $date, 3, 3, 3, 3, 3);
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
                           '\put(1.75,  9.98){%s} ' .
                           '\put(4.25,  9.98){%s} ' .
                           '\put(6.55,  9.98){%s} ',
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
        $team_Y = 9.65 - 3.0 * $within_page;
        $pc->add(sprintf($fmt, $team_X, $team_Y, $name));
        $pc->add(sprintf($fmt, $team_X + 4.6, $team_Y,
                         $this->representatives[$id]));

        // - write content: skippers
        $X = 0.75;
        $Y = 8.55 - 3.0 * $within_page;
        // :A then :B
        foreach (array("skipper_A", "skipper_B") as $div_num => $div) {
          $x = $X + (3.5 * $div_num);
          foreach ($block->$div as $i => $s) {
            $y = $Y - (0.3 * $i);
            $year = substr($s->getSailorYear(), 2);
            $races = DB::makeRange($s->races_nums);
            $pc->add(sprintf($fmt, $x,        $y, $s->getSailorName()));
            $pc->add(sprintf($fmt, $x + 1.9,  $y, $year));
            $pc->add(sprintf($fmt, $x + 2.33, $y, $races));
          }
        }

        // crews
        $X = 0.75;
        $Y = 7.65 - 3.0 * $within_page;
        foreach (array("crew_A", "crew_B") as $div_num => $div) {
          $x = $X + (3.5 * $div_num);
          foreach ($block->$div as $i => $s) {
            $y = $Y - (0.3 * $i);
            $year = substr($s->getSailorYear(), 2);
            $races = DB::makeRange($s->races_nums);
            $pc->add(sprintf($fmt, $x,        $y, $s->getSailorName()));
            $pc->add(sprintf($fmt, $x + 1.9,  $y, $year));
            $pc->add(sprintf($fmt, $x + 2.33, $y, $races));
          }
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
    return __DIR__ . '/ICSA-RP-AB.pdf';
  }
}
?>