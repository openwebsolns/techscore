<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */

require_once('xml5/SVGConnectedGraph.php');

/**
 * SVG diagram of race progress
 *
 * @author Dayan Paez
 * @version 2012-10-29
 */
class RaceProgressChart {

  private $regatta;

  /**
   * Creates a new progress chart creator for the given regatta
   *
   * @param Regatta $reg the regatta to draw upon
   */
  public function __construct(Regatta $reg) {
    $this->regatta = $reg;
  }

  /**
   * Get the SVGDoc for the progress chart in the given division.
   *
   * If division is null, then rank across all divisions
   *
   * @param Array:Race $races the ordered list of races to include
   * @throws InvalidArgumentException if there aren't enough races
   */
  public function getChart($races) {
    if (count($races) < 2)
      throw new InvalidArgumentException("There must be at least 2 races for chart.");

    // Prepare data
    $teams = $this->regatta->getTeams();

    $palette = array("#eeb211", "#b2bb1e", "#d31145", "#3b9bb9", "#f58025", "#8177b7",
		     "#00b085", "#bb8c0a", "#98002e", "#06347a", "#766441", "#4c2432",
		     "#939ba2", "#5d9732", "#ffe384", "#4f2683", "#386351", "#935433",
		     "#1fbec9", "#5fff96");
    $data = array(); // associative map of team id => ranks
    $fills = array();
    foreach ($teams as $i => $team) {
      $fills[$team->id] = $palette[$i % count($palette)];
      $data[$team->id] = array();
    }
    $num_teams = count($teams);

    $racelist = array();

    $xRMargin = 300;
    $xLMargin = 50;
    $xspacing = max(30, 660 / (count($races) - 1));
    $x = $xLMargin;
    $yStart = 60;
    $height = max(500, (60 + ($num_teams - 1) * 35));

    $raceLabels = array();
    $raceAxes = array();
    foreach ($races as $race) {
      $raceLabels[] = new SVGText($x, $yStart - 20, $race, array('class'=>'y-axis-label'));
      $raceLabels[] = new SVGPath(array(new SVGMoveto($x, $yStart), new SVGLineto($x, ($height - 20 - $yStart))), array('class'=>'y-grid'));

      $racelist[] = $race;
      $ranks = $this->regatta->scorer->rank($this->regatta, $racelist);

      $yMax = $ranks[$num_teams - 1]->score;
      $yMin = $ranks[0]->score;
      $yrange = $yMax - $yMin;
      if ($yrange == 0)
	$yrange = 1;
      $yscale = ($height - 20 - $yStart) / $yrange;
      foreach ($ranks as $i => $rank) {
	$y = $yStart + ($rank->score - $yMin) * $yscale;
	$data[$rank->team->id][] = new SVGNode($x, $y, 7,
					       array('fill' => $fills[$rank->team->id],
						     'stroke'=> $fills[$rank->team->id],
						     'class'=>'chart-node',
						     'title'=>sprintf("%d: %s (%s points)", ($i + 1), $rank->team, $rank->score)));
      }
      $x += $xspacing;
    }

    $width = (count($races) - 1) * $xspacing + $xRMargin + $xLMargin;
    $P = new SVGDoc($width, $height, $this->regatta->name);
    $P->add(new SVGDesc(sprintf("Score history for %s", $this->regatta->name)));
    $P->add(new SVGDefs(array(), array(new SVGStyle('text/css', file_get_contents(__DIR__ . '/chart.css')))));
    $P->add(new SVGScript('text/javascript', null, 'function highlight(id) {
    var elem = document.getElementById(id);
    if (elem)
	elem.classList.add("focus");
}
function unhighlight(id) {
    var elem = document.getElementById(id);
    if (elem)
	elem.classList.remove("focus");
}'));

    $ch = new SVGConnectedGraph('scores-history', $width, $height, sprintf("History score for %s", $this->regatta->name));
    $ch->drawBorder(5, 5);
    // ------------------------------------------------------------
    // Axis + label
    // ------------------------------------------------------------
    $ch->add(new SVGPath(array(new SVGMoveto($xLMargin - 25, $yStart + 20),
			       new SVGLineto($xLMargin - 25, $yStart),
			       new SVGLineto($width + 20 - $xRMargin, $yStart)),
			 array('class'=>'x-axis', 'title'=>'Running winner')));
    $ch->add(new SVGText(0, 0, "Running winner",
			 array('transform'=>sprintf('translate(%s %s) rotate(90)', $xLMargin - 30, $yStart + 30),
			       'class'=>'x-axis-label')));

    foreach ($raceLabels as $label)
      $ch->add($label);
    $ch->add(new SVGText($width - $xRMargin + 25, $yStart - 20, "Rank", array('class'=>'rank-label')));

    // finishing ranks label
    $ySpacing = ($height - 20 - $yStart) / (count($ranks) - 1);
    $y = $yStart - 15;
    foreach ($ranks as $rank) {
      $x = $width - $xRMargin + 20;
      $img = new SVGText(15, 0, "");
      if ($rank->team->school->burgee !== null)
	$img = new SVGImage(15, 0, 30, 30, sprintf('/inc/img/schools/%s.png', $rank->team->school->id));
      $ch->add(new SVGG(sprintf('scores-history-team-%s', $rank->team->id),
			array('class'=>'team-label-group',
			      'transform'=>sprintf('translate(%s %s)', $x, $y),
			      'onmouseover'=>sprintf('highlight("scores-history-series-%s")', $rank->team->id),
			      'onmouseout'=>sprintf('unhighlight("scores-history-series-%s")', $rank->team->id)),
			array(new SVGRect(0, 0, 10, 30, 5, 5, array('class'=>'team-label-box', 'fill'=>$fills[$rank->team->id])),
			      $img,
			      new SVGText(50, 20, $rank->team, array('class'=>'team-label')))));
  
      $y += $ySpacing;
    }

    foreach ($data as $id => $nodes) {
      $path = $ch->connect(sprintf('scores-history-series-%s', $id), array('class'=>'linegraph-group'), $nodes);
      $path->set('id', sprintf('scores-history-linegraph-%s', $id));
      $path->set('title', $this->regatta->getTeam($id));
      $path->set('stroke', $fills[$id]);
      $path->set('fill', 'none');
      $path->set('class', 'chart-line');
      $path->set('onmouseover', sprintf('highlight("scores-history-team-%s")', $id));
      $path->set('onmouseout', sprintf('unhighlight("scores-history-team-%s")', $id));
    }

    $P->add($ch);
    return $P;
  }
}
?>