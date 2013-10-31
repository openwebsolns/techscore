<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

require_once('rpwriter/LatexPic.php');
require_once('rpwriter/RpBlock.php');

/**
 * This class is a new visualization of the RP form writing
 * process. In particular, the work progress has changed in this
 * version so that entire blocks are allocated into memory, before
 * they are rendered by the class into the corresponding LaTeX code,
 * rather than writing the LaTeX code on the fly as it was before.
 *
 * All RP forms should inherit this class and must implement one
 * method: toLatex(), which returns a string representation of the
 * form to be processed by an appropriate PDFLaTeX engine.
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
abstract class AbstractIcsaRpForm {

  // Constants

  protected $blocks_per_page = 3;
  protected $num_skipper_A = 3;
  protected $num_skipper_B = 3;
  protected $num_skipper_C = 0;
  protected $num_skipper_D = 0;
  protected $num_crew_A    = 3;
  protected $num_crew_B    = 3;
  protected $num_crew_C    = 0;
  protected $num_crew_D    = 0;

  /**
   * The LaTeX preamble and open-document declaration
   */
  protected $HEAD;
  protected $TAIL = '\end{document}';

  protected $regatta_name;
  protected $host;
  protected $date;

  /**
   * Associative array: team_id => team
   */
  protected $teams = array();

  /**
   * Corresponding associative array of blocks: team_id => Array<Block>
   */
  protected $blocks = array();

  /**
   * Corresponding associative array of team reps: team_id => Sailor
   */
  protected $representatives = array();

  /**
   * Corresponding index for placement of skippers. For instance, if
   * $skipper_A[team_id] = 1, then the Block to add the next skipper
   * for the given team in the given division is index "1":
   * i.e. $this->blocks[team_id][1].
   */
  protected $skipper_A = array();
  protected $skipper_B = array();
  protected $skipper_C = array();
  protected $skipper_D = array();
  protected $crew_A    = array();
  protected $crew_B    = array();
  protected $crew_C    = array();
  protected $crew_D    = array();

  /**
   * Creates a new two-division form for RP for a regatta with the
   * given name, host, and date
   *
   * @param String $name the name of the regatta
   * @param String $host the host of the regatta
   * @param String $date the date of the regatta
   * @param int $num_blocks the number of blocks per page (default to 3)
   * @param int optional arguments in the following order describe the
   * basic parameters of the form:
   * <ul>
   *   <li>blocks_per_page (3)</li>
   *   <li>num_skipper_A (0)</li>
   *   <li>num_crew_A (0)</li>
   *   <li>num_skipper_B (0)</li>
   *   <li>...</li>
   * </ul>
   * If not provided, the values default to the parenthetical amount
   */
  public function __construct($name, $host, $date, $num_blocks = 3) {
    $this->HEAD = ('\documentclass[letter,12pt]{article} ' .
                   '\usepackage{graphicx} ' .
                   '\usepackage[text={8.25in,11in},centering]{geometry} ' .
                   '\usepackage[usenames]{color} ' .
                   '\begin{document}  ' .
                   '\sffamily\color{blue}  ' .
                   '\setlength{\unitlength}{1in} ' .
                   '\pagestyle{empty}');

    $this->regatta_name = (string)$name;
    $this->host         = (string)$host;
    $this->date         = (string)$date;
    $this->blocks_per_page = (int)$num_blocks;

    $list = array("num_skipper_A", "num_crew_A",
                  "num_skipper_B", "num_crew_B",
                  "num_skipper_C", "num_crew_C",
                  "num_skipper_D", "num_crew_D");
    $max = min(func_num_args(), 12);
    for ($i = 4; $i < $max; $i++) {
      $name = array_shift($list);
      $this->$name = (int)func_get_arg($i);
    }
  }


  /**
   * Sets the given representative for the specified team
   *
   * @param Team $team the team to add
   * @param Sailor $rep the sailor who represents this team
   */
  public function addRepresentative(Team $team, Representative $sailor = null) {
    if (!isset($this->teams[$team->id]))
      $this->add($team);
    $this->representatives[$team->id] = $sailor;
  }

  /**
   * Appends the given RP to this form
   *
   * @param RP $rp the RP entry to add. The form takes care of
   * managing the team, division, etc.
   */
  public function append(RP $rp) {
    $team = $rp->team;
    $div  = $rp->division;
    $role = $rp->boat_role; // either "skipper" or "crew"
    if (!isset($this->teams[$team->id]))
      $this->add($team);

    // determine whether a new block is necessary
    $var_name  = sprintf("%s_%s",  $role, $div);
    $var_count = sprintf("num_%s", $var_name);

    // get block, and create a new if necessary
    $list  = $this->$var_name;
    $block = $this->blocks[$team->id][$list[$team->id]];
    if (count($block->$var_name) == $this->$var_count) {
      $block = new RpBlock();
      $this->blocks[$team->id][] = $block;
      $list[$team->id]++;
      $this->$var_name = $list;
    }

    array_push($block->$var_name, $rp);
  }

  /**
   * Appends the team and increment the appropriate indices
   *
   * @param Team $team the team to add
   */
  protected function add(Team $team) {
    $this->teams[$team->id]  = $team;
    $this->blocks[$team->id] = array(new RpBlock());
    $this->skipper_A[$team->id] = 0;
    $this->skipper_B[$team->id] = 0;
    $this->skipper_C[$team->id] = 0;
    $this->skipper_D[$team->id] = 0;
    $this->crew_A[$team->id] = 0;
    $this->crew_B[$team->id] = 0;
    $this->crew_C[$team->id] = 0;
    $this->crew_D[$team->id] = 0;
  }

  /**
   * Returns the LaTeX code for the body of this form
   *
   * @return String the LaTeX code
   */
  abstract protected function getBody();

  /**
   * Gets the basename of the file to use as background
   *
   * @return String the filename
   */
  abstract public function getPdfName();

  /**
   * Convenience method to create LaTeX includegraphics
   *
   * @return String includegraphics string using getPdfName
   * @see getPdfName
   */
  protected function getIncludeGraphics() {
    return sprintf('\includegraphics[width=\textwidth]{%s}',
                   sprintf('%s/www/inc/rp/%s', dirname(dirname(__DIR__)), $this->getPdfName()));
  }

  /**
   * Returns the LaTeX code for this form
   *
   * @return String the LaTeX code
   */
  public function toLatex() {
    return sprintf("%s %s %s", 
                   str_replace('#', '\#', $this->HEAD), 
                   str_replace('#', '\#', $this->getBody()), 
                   str_replace('#', '\#', $this->TAIL));
  }

  /**
   * Dump the contents of the blocks to standard output for debugging
   * purposes
   *
   */
  public function dump() {
    $fmt = "%-25s | %s | %2s | %s\n";
    foreach ($this->blocks as $id => $list) {
      print(sprintf("Team: %25s\n Rep: %s\n",
                    $this->teams[$id],
                    $this->representatives[$id]));
      foreach ($list as $block) {
        print("---------\n");
        foreach ($block->skipper_A as $s)
          print(sprintf($fmt,
                        $s->getSailorName(),
                        $s->division,
                        $s->getSailorYear(),
                        DB::makeRange($s->races_nums)));
        print("\n");
        foreach ($block->crew_A as $s)
          print(sprintf($fmt,
                        $s->getSailorName(),
                        $s->division,
                        $s->getSailorYear(),
                        DB::makeRange($s->races_nums)));

        print("---------\n");
        foreach ($block->skipper_B as $s)
          print(sprintf($fmt,
                        $s->getSailorName(),
                        $s->division,
                        $s->getSailorYear(),
                        DB::makeRange($s->races_nums)));
        print("\n");
        foreach ($block->crew_B as $s)
          print(sprintf($fmt,
                        $s->getSailorName(),
                        $s->division,
                        $s->getSailorYear(),
                        DB::makeRange($s->races_nums)));
      }
      print("==========\n");
    }
  }
}
?>
