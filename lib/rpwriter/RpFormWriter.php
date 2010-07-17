<?php
/**
 * This file is part of TechScore
 *
 * @version 2.0
 * @package rpwriter
 */

require_once('conf.php');
/**
 * Creates PDF of the RP forms for two divisions using ICSA standards
 *
 *
 */
class RpFormWriter {

  private $reg;
  private $host;

  /**
   * Basic constructor: specify the regatta for which to write the RP form
   *
   * @param Regatta $reg
   */
  public function __construct(Regatta $reg) {
    $this->reg   = $reg;
    $this->teams = array();

    // create host string: MIT
    $schools = array();
    foreach ($this->reg->getHosts() as $account) {
      $schools[] = $account->school->nick_name;
    }
    $this->host = implode("/", $schools);
  }

  /**
   * Generates a PDF file with the given basename
   *
   * @param string $filename the basename of the file (sans extension)
   * @throws RuntimeException should something go wrong
   */
  public function makePDF($filename) {
    $divisions = $this->reg->getDivisions();
    $rp        = $this->reg->getRpManager();

    $form = null;
    if ($this->reg->isSingleHanded()) {
      $form = new IcsaRpFormSingles($this->reg->get(Regatta::NAME),
				    $this->host,
				    $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 2) {
      $form = new IcsaRpFormAB($this->reg->get(Regatta::NAME),
			       $this->host,
			       $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 3) {
      $form = new IcsaRpFormABC($this->reg->get(Regatta::NAME),
				$this->host,
				$this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 4) {
      $form = new IcsaRpFormABCD($this->reg->get(Regatta::NAME),
				 $this->host,
				 $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 1) {
      $form = new IcsaRpFormSloops($this->reg->get(Regatta::NAME),
				   $this->host,
				   $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    else
      throw new InvalidArgumentException("Regattas of this type are not supported.");
    
    foreach ($this->reg->getTeams() as $team) {
      $form->addRepresentative($team, $rp->getRepresentative($team));
      foreach ($divisions as $div) {
	foreach (array(RP::SKIPPER, RP::CREW) as $role) {
	  foreach ($rp->getRP($team, $div, $role) as $r)
	    $form->append($r);
	}
      }
    }

    // generate PDF
    $arg = escapeshellarg($form->toLatex());
    $command = sprintf("pdflatex -jobname='%s' %s", $filename, $arg);
    $output = array();
    exec($command, $output, $value);
    if ($value != 0)
      throw new RuntimeException(sprintf("Unable to generate PDF file. Exit code $value:\nCommand: %s\nArgument%s",
					 $command, $arg));

    // clean up
    unlink($filename . ".aux");
    unlink($filename . ".log");
    return $filename . ".pdf";
  }


  /**
   * Static comparator for RP data. Sorts according to team name
   *
   * @param Team s1 the first object
   * @param Team s2 the second
   * @return < 0 if first comes earlier alphabetically
   * @return > 0 if first comes later alphabetically
   * @return == 0 otherwise
   */
  public static function cmpTeams(Team $s1, Team $s2) {
    $cmp = strcmp($s1->school->name, $s2->school->name);
    if ($cmp != 0) return $cmp;
    return strcmp($s1->name, $s2->name);
  }
}


if (basename(__FILE__) == basename($argv[0])) {
  // 132: four divisions
  // 101: sloops
  //  76: single-handed
  $writer = new RpFormWriter(new Regatta(76));
  print($writer->makePDF("new-reg"));
}
?>