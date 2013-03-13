<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

require_once('rpwriter/AbstractIcsaRpForm.php');

/**
 * Draws RP forms for team racing regatta
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
class IcsaRpFormTeam extends AbstractIcsaRpForm {

  private $regatta;

  /**
   * Creates a new form for two team racing
   *
   * @param FullRegatta $reg the regatta in question
   * @param String $host the host of the regatta
   * @param String $date the date of the regatta
   */
  public function __construct(FullRegatta $reg, $host, $date) {
    parent::__construct($reg->name, $host, $date, 2, 5, 6, 5, 6, 5, 6);
    $this->num_skipper_total = 5;
    $this->num_crew_total = 6;
    $this->regatta = $reg;
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
        $team_Y = 9.65 - 4.48 * $within_page;
        $pc->add(sprintf($fmt, $team_X, $team_Y, $name));
        $pc->add(sprintf($fmt, $team_X + 4.6, $team_Y,
                         $this->representatives[$id]));

        $teamRaces = $this->regatta->getRacesForTeam(Division::A(), $team);

        // - write content: skippers across all divisions
        $X = 0.75;
        $Y = 8.65 - 4.48 * $within_page;
        // :A then :B then :C, first column, then second
        $skipIndex = 0;
        foreach (array("skipper_A", "skipper_B", "skipper_C") as $div_num => $div) {
          foreach ($block->$div as $s) {
            $y = $Y - (0.3 * $skipIndex);
            $skipIndex++;

            $year = substr($s->sailor->year, 2);
            if (count($s->races_nums) == count($teamRaces))
              $races = "All";
            else
              $races = DB::makeRange($s->races_nums);
            $pc->add(sprintf($fmt, $X,        $y, $s->sailor->getName()));
            $pc->add(sprintf($fmt, $X + 3.0,  $y, $year));
            $pc->add(sprintf($fmt, $X + 3.4, $y, $races));
          }
        }

        // crews
        $X = 0.75;
        $Y = 7.15 - 4.48 * $within_page;
        $crewIndex = 0;
        foreach (array("crew_A", "crew_B", "crew_C") as $div_num => $div) {
          foreach ($block->$div as $s) {
            $y = $Y - (0.3 * $crewIndex);
            $crewIndex++;

            $year = substr($s->sailor->year, 2);
            if (count($s->races_nums) == count($teamRaces))
              $races = "All";
            else
              $races = DB::makeRange($s->races_nums);
            $pc->add(sprintf($fmt, $X,        $y, $s->sailor->getName()));
            $pc->add(sprintf($fmt, $X + 3.0,  $y, $year));
            $pc->add(sprintf($fmt, $X + 3.4, $y, $races));
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
    return 'ICSA-RP-TEAM.pdf';
  }
}
?>