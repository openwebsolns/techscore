<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

require_once('rpwriter/AbstractRpForm.php');
require_once('rpwriter/LatexPic.php');
require_once('rpwriter/RpBlock.php');

/**
 * A convenience class for ICSA forms, this class will use LaTeX to
 * generate the PDF file.
 *
 * In this class, the forms are assumed to be grouped by blocks, with
 * each block having a space for a given number of skippers and crews
 * per division.
 *
 * Based on the size of those blocks (as overridden by the subclass),
 * the makePdf method will distribute the current RP information for
 * the given regatta into the necessary blocks. The subclass then is
 * responsible for drawing those blocks onto the page, by
 * instantiating the 'draw' method.
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
abstract class AbstractRpBlockForm extends AbstractRpForm {

  // Constants

  protected $num_skipper_A = 3;
  protected $num_skipper_B = 0;
  protected $num_skipper_C = 0;
  protected $num_skipper_D = 0;
  protected $num_crew_A    = 3;
  protected $num_crew_B    = 0;
  protected $num_crew_C    = 0;
  protected $num_crew_D    = 0;

  /**
   * The LaTeX preamble and open-document declaration
   */
  protected $HEAD = '\documentclass[letter,12pt]{article} \usepackage{graphicx} \usepackage[text={8.25in,11in},centering]{geometry} \usepackage[usenames]{color} \begin{document} \sffamily\color{blue} \setlength{\unitlength}{1in} \pagestyle{empty}';
  protected $TAIL = '\end{document}';

  /**
   * Corresponding associative array of blocks: team_id => Array<Block>
   */
  protected $blocks = array();

  public function makePdf(FullRegatta $reg, $basename = 'ts2') {
    $body = $this->getLatexCode($reg);

    // generate PDF
    $tmp = sys_get_temp_dir();
    $filename = tempnam($tmp, $basename);
    $command = sprintf("pdflatex -output-directory='%s' -interaction=nonstopmode -jobname='%s' %s",
                       escapeshellarg($tmp),
                       escapeshellarg(basename($filename)),
                       escapeshellarg($body));
    $output = array();
    exec($command, $output, $value);
    if ($value != 0) {
      throw new RuntimeException(sprintf("Unable to generate PDF file. Exit code $value:\nValue: %s\nOutput%s",
                                         $value, implode("\n", $output)));
    }

    // clean up (including base created by tempnam call (last in list)
    $data = file_get_contents($filename . '.pdf');
    foreach (array('.aux', '.log', '.pdf', '') as $suffix)
      unlink(sprintf('%s%s', $filename, $suffix));
    return $data;
  }

  /**
   * Generates PDF by attempting to connect to socket in $USE_SOCKET.
   *
   * @return mixed the binary PDF data
   * @throws InvalidArgumentException if unable to generate PDF
   */
  public function socketPdf(FullRegatta $reg, $socket) {
    if (($s = socket_create(AF_UNIX, SOCK_STREAM, 0)) === false)
      throw new InvalidArgumentException("Unable to create socket connection.");
    if (socket_connect($s, $socket) === false) {
      socket_close($s);
      throw new InvalidArgumentException("Unable to connect to socket at " . $socket);
    }
    $mess = $this->getLatexCode($reg);
    $full = sprintf('%08d%s', strlen($mess), $mess);
    if (socket_send($s, $full, strlen($full), 0) === false) {
      socket_close($s);
      throw new InvalidArgumentException("Unable to send data to socket at " . $socket);
    }
    $mess = ""; // clear the message

    // Get back the PDF binary data
    $size = "";
    if (socket_recv($s, $size, 8, 0) === false) {
      socket_close($s);
      throw new InvalidArgumentException("Unable to read data to socket at " . $socket);
    }
    $size = (int)$size;
    if ($size > 0) {
      $pack = "";
      while (strlen($mess) < $size) {
        if (socket_recv($s, $pack, 1024, 0) === false)
          break;
        $mess .= $pack;
      }
    }
    socket_close($s);
    if (is_numeric($mess))
      throw new InvalidArgumentException("Error while generating PDF: $mess");
    return $mess;
  }
  
  protected function getLatexCode(FullRegatta $reg) {
    $this->blocks = array();

    $host = array();
    foreach ($reg->getHosts() as $school)
      $host[$school->id] = $school->nick_name;
    $host = implode("/", $host);

    $this->fillBlocks($reg);

    $body = sprintf("%s %s %s", 
                    str_replace('#', '\#', $this->HEAD), 
                    str_replace('#', '\#', $this->draw($reg->name, $host, $reg->start_time->format('Y-m-d'), $this->blocks)), 
                    str_replace('#', '\#', $this->TAIL));

    return $body;
  }

  protected function fillBlocks(FullRegatta $reg) {
    $divisions = $reg->getDivisions();
    $rp = $reg->getRpManager();

    foreach ($reg->getTeams() as $team) {
      $representative = $rp->getRepresentative($team);

      // It may be necessary to use multiple RP blocks per team, due to
      // the fact that the number of skippers or crews exceeds the
      // allowed value for that field per block.
      $team_blocks = array();

      foreach ($divisions as $div) {
        foreach (array(RP::SKIPPER, RP::CREW) as $role) {
          $section = sprintf('%s_%s', $role, $div);
          $limit = 'num_' . $section;

          foreach ($rp->getRP($team, $div, $role) as $r) {

            // Find block to use
            $block = null;
            foreach ($team_blocks as $bl) {
              if (count($bl->$section) < $this->$limit) {
                $block = $bl;
                break;
              }
            }
            if ($block === null) {
              $block = new RpBlock();
              $block->team = $team;
              $block->representative = $representative;
              $this->blocks[] = $block;
              $team_blocks[] = $block;
            }

            array_push($block->$section, $r);
          }
        }
      }

      // Add an articifial block if none available for the team
      if (count($team_blocks) == 0) {
        $block = new RpBlock();
        $block->team = $team;
        $block->representative = $representative;
        $this->blocks[] = $block;
      }
    }
  }

  /**
   * Returns the LaTeX code for the body of this form
   *
   * @param String $regatta_name the name of the regatta
   * @param String $host the host string for event
   * @param String $date the date string to use for event
   * @param Array:RpBlock the RP units
   * @return String the LaTeX code
   */
  abstract protected function draw($regatta_name, $host, $date, Array $blocks);

  /**
   * Convenience method to create LaTeX includegraphics
   *
   * @return String includegraphics string using getPdfName
   * @see getPdfName
   */
  protected function getIncludeGraphics() {
    return sprintf('\includegraphics[width=\textwidth]{%s}', $this->getPdfName());
  }

  /**
   * Dump the contents of the blocks to standard output for debugging
   * purposes
   *
   */
  public function dump() {
    $fmt = "%-25s | %s | %2s | %s\n";
    foreach ($this->blocks as $block) {
      printf("Team: %25s\n Rep: %s\n", $block->team, $block->representative);
      foreach (array('A', 'B', 'C', 'D') as $div) {
        foreach (array(RP::SKIPPER, RP::CREW) as $role) {
          $section = sprintf('%s_%s', $role, $div);
          foreach ($block->$section as $s) {
            printf($fmt,
               $s->getSailorName(),
               $s->division,
               $s->getSailorYear(),
               DB::makeRange($s->races_nums));
          }
          print("\n");
        }
        print("--------------------\n");
      }
      print("--------------------\n");
    }
  }
}
?>
