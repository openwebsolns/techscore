<?php
/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package rpwriter
 */



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
    foreach ($this->reg->getHosts() as $school) {
      $schools[] = $school->nick_name;
    }
    $this->host = implode("/", $schools);
  }

  /**
   * Generates a PDF file with a random name, and returns the filename
   *
   * @param string $tmpbase the basename of the random file
   * @return string $path the path to the generated PDF
   * @throws RuntimeException should something go wrong
   */
  public function makePDF($tmpbase = 'ts2') {
    $divisions = $this->reg->getDivisions();
    $rp        = $this->reg->getRpManager();

    $form = null;
    if ($this->reg->isSingleHanded()) {
      require_once('rpwriter/IcsaRpFormSingles.php');
      $form = new IcsaRpFormSingles($this->reg->get(Regatta::NAME),
				    $this->host,
				    $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 2) {
      require_once('rpwriter/IcsaRpFormAB.php');
      $form = new IcsaRpFormAB($this->reg->get(Regatta::NAME),
			       $this->host,
			       $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 3) {
      require_once('rpwriter/IcsaRpFormABC.php');
      $form = new IcsaRpFormABC($this->reg->get(Regatta::NAME),
				$this->host,
				$this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 4) {
      require_once('rpwriter/IcsaRpFormABCD.php');
      $form = new IcsaRpFormABCD($this->reg->get(Regatta::NAME),
				 $this->host,
				 $this->reg->get(Regatta::START_TIME)->format("Y-m-d"));
    }
    elseif (count($divisions) == 1) {
      require_once('rpwriter/IcsaRpFormSloops.php');
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
    $tmp = sys_get_temp_dir();
    $filename = tempnam($tmp, $tmpbase);
    $lat = $form->toLatex();
    $command = sprintf("pdflatex -output-directory='%s' -interaction=nonstopmode -jobname='%s' %s",
		       escapeshellarg($tmp),
		       escapeshellarg(basename($filename)),
		       escapeshellarg($lat));
    $output = array();
    exec($command, $output, $value);
    if ($value != 0) {
      throw new RuntimeException(sprintf("Unable to generate PDF file. Exit code $value:\nValue: %s\nOutput%s",
					 $value, implode("\n", $output)));
    }

    // clean up (including base created by tempnam call (last in list)
    foreach (array('.aux', '.log', '') as $suffix)
      unlink(sprintf('%s%s', $filename, $suffix));
    return sprintf('%s.pdf', $filename);
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


if (isset($argv) && basename(__FILE__) == basename($argv[0])) {
  // 132: four divisions
  // 101: sloops
  //  76: single-handed
  $writer = new RpFormWriter(new Regatta(76));
  print($writer->makePDF("new-reg"));
}
?>
