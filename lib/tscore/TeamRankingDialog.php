<?php
/*
 * This file is part of TechScore
 *
 * @package tscore-dialog
 */

require_once('tscore/AbstractScoresDialog.php');

/**
 * Displays the overall rankings for a team regatta
 *
 * @author Dayan Paez
 * @version 2013-02-19
 */
class TeamRankingDialog extends AbstractScoresDialog {
  /**
   * Creates a new team ranking dialog
   *
   * @param FullRegatta $reg the regatta
   */
  public function __construct(FullRegatta $reg) {
    parent::__construct("Rankings", $reg);
  }

  /**
   * Retrieves the short version of the table (without RP)
   *
   */
  public function getSummaryTable($link_schools = false) {
    $tab = new XTable(array('class'=>'teamranking results'),
		      array(new XTHead(array(),
				       array(new XTR(array(),
						     array(new XTH(array(), "#"),
							   new XTH(array('title'=>'School mascot')),
							   new XTH(array(), "School"),
							   new XTH(array('class'=>'teamname'), "Team"),
							   new XTH(array('title'=>"Winning record across all rounds"), "Rec."),
							   new XTH(array('title'=>"Winning percentage"), "%"))))),
			    $b = new XTBody()));

    $season = $this->REGATTA->getSeason();
    foreach ($this->REGATTA->getRanker()->rank($this->REGATTA) as $i => $rank) {
      $mascot = "";
      if ($rank->team->school->burgee !== null) {
	$url = sprintf('/inc/img/schools/%s.png', $rank->team->school->id);
	$mascot = new XImg($url, $rank->team->school->id, array('height'=>'30px'));
      }
      $school = (string)$rank->team->school;
      if ($link_schools !== false)
	$school = new XA(sprintf('/schools/%s/%s/', $rank->team->school->id, $season), $school);

      $b->add($row = new XTR(array('class'=>'topborder row' . ($i % 2)),
			     array(new XTD(array('title'=>$rank->explanation), ($i + 1)),
				   new XTD(array(), $mascot),
				   new XTD(array(), $school),
				   new XTD(array('class'=>'teamname'), new XStrong($rank->team->getQualifiedName())),
				   new XTD(array(), $rank->getRecord()),
				   new XTD(array(), sprintf('%0.1f', (100 * $rank->getWinPercentage()))))));
    }

    return array($tab);
  }

  /**
   * Fetches the rankings table
   *
   * @param String $link_schools true to include link to schools' season
   * @return Array the table element(s)
   */
  public function getTable($link_schools = false) {
    $tab = new XTable(array('class'=>'teamranking results'),
		      array(new XTHead(array(),
				       array(new XTR(array(),
						     array(new XTH(array(), "#"),
							   new XTH(array('title'=>'School mascot')),
							   new XTH(array(), "School"),
							   new XTH(array('class'=>'teamname'), "Team"),
							   new XTH(array('title'=>"Winning record across all rounds"), "Rec."),
							   new XTH(array('title'=>"Winning percentage"), "%"),
							   new XTH(array('class'=>'sailor'), "Skippers"),
							   new XTH(array('class'=>'sailor'), "Crews"))))),
			    $b = new XTBody()));
    $divs = $this->REGATTA->getDivisions();

    $season = $this->REGATTA->getSeason();
    $rpm = $this->REGATTA->getRpManager();
    foreach ($this->REGATTA->getRanker()->rank($this->REGATTA) as $i => $rank) {
      $skips = array();
      $crews = array();
      foreach ($divs as $div) {
	foreach ($rpm->getRP($rank->team, $div, RP::SKIPPER) as $s)
	  $skips[$s->sailor->id] = $s->sailor;
	foreach ($rpm->getRP($rank->team, $div, RP::CREW) as $s)
	  $crews[$s->sailor->id] = $s->sailor;
      }

      $mascot = "";
      if ($rank->team->school->burgee !== null) {
	$url = sprintf('/inc/img/schools/%s.png', $rank->team->school->id);
	$mascot = new XImg($url, $rank->team->school->id, array('height'=>'30px'));
      }
      $school = (string)$rank->team->school;
      if ($link_schools !== false)
	$school = new XA(sprintf('/schools/%s/%s/', $rank->team->school->id, $season), $school);

      $rowspan = max(1, count($skips), count($crews));
      $rowindex = 'row' . ($i % 2);
      $b->add($row = new XTR(array('class'=>'topborder ' . $rowindex),
			     array(new XTD(array('rowspan'=>$rowspan, 'title'=>$rank->explanation), ($i + 1)),
				   new XTD(array('rowspan'=>$rowspan), $mascot),
				   new XTD(array('rowspan'=>$rowspan), $school),
				   new XTD(array('class'=>'teamname', 'rowspan'=>$rowspan), new XStrong($rank->team->getQualifiedName())),
				   new XTD(array('rowspan'=>$rowspan), $rank->getRecord()),
				   new XTD(array('rowspan'=>$rowspan), sprintf('%0.1f', (100 * $rank->getWinPercentage()))))));
      // Special case: no RP information
      if (count($skips) + count($crews) == 0) {
	$row->add(new XTD());
	$row->add(new XTD());
	continue;
      }

      // Add RP information
      $rprows = array($row);
      for ($i = 0; $i < $rowspan - 1; $i++) {
        $b->add($row = new XTR(array('class'=>$rowindex)));
        $rprows[] = $row;
      }
      $row_number = 0;
      foreach ($skips as $sailor) {
        $rprows[$row_number]->add(new XTD(array('class'=>'sailor'), $sailor));
        if (count($crews) <= $row_number)
          $rprows[$row_number]->add(new XTD());
        $row_number++;
      }
      $row_number = 0;
      foreach ($crews as $sailor) {
        if (count($skips) <= $row_number)
          $rprows[$row_number]->add(new XTD());
        $rprows[$row_number]->add(new XTD(array('class'=>'sailor'), $sailor));
        $row_number++;
      }
    }

    return array($tab);
  }

  public function fillHTML(Array $args) {
    $this->PAGE->addContent($p = new XPort("Rankings"));
    foreach ($this->getTable() as $elem)
      $p->add($elem);
  }
}
?>