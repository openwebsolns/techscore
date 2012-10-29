<?php
/**
 * Different charts
 *
 * @author Dayan Paez
 * @version 2010-09-10
 * @package svg
 */

require_once(dirname(__FILE__).'/SVGLib.php');

/**
 * Generic SVGChart (provides common functionality)
 *
 * @author Dayan Paez
 * @version 2011-09-23
 */
abstract class SVGChart extends SVGG {
  protected $css, $width, $height, $title;

  /**
   * Creates a new chart
   *
   * @param String $id the ID of the group
   * @param int $width the width
   * @param int $height the height
   * @param String $title the title
   */
  public function __construct($id, $width, $height, $title) {
    parent::__construct($id);
    $this->width = $width;
    $this->height = $height;
    $this->title = $title;

    $this->css = "";

    $this->add(new SVGDesc($title));
  }

  /**
   * Draws the border (regardless of margin) as a rect with class
   * "border" and no fill by default
   *
   * @param int $rx the x-radius for rounded border
   * @param int $ry the y-radius for rounded border
   * @param int $inset how far from the edges of the chart
   */
  public function drawBorder($rx = 0, $ry = 0, $inset = 5) {
    $this->add(new SVGRect($inset,
			   $inset,
			   $this->width - 2 * $inset,
			   $this->height - 2 * $inset,
			   $rx, $ry,
			   array("class" => "border", "fill" => "none")));
  }

  /**
   * Draws the title associated with the chart (note that the title is
   * added to the SVG but is not drawn by default). The title is drawn
   * center-aligned.
   *
   * @param int|null $x the x location to draw (use null for 'center')
   * @param int $y the vertical-displacement value (from the top of the graph)
   */
  public function drawTitle($x = null, $y = 30) {
    if ($x === null) $x = $this->width / 2;
    $this->add(new SVGText($x, $y, $this->title, array("class"=>"title", "text-anchor"=>"middle")));
  }

  /**
   * Returns the CSS associated with this chart
   *
   */
  public function getCSS() {
    return $this->css;
  }
}
?>