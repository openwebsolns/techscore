<?php
/**
 * Components to create charts, which can then be printed to SVG, or
 * other possible output.
 * 
 * @package xml
 * @author  Dayan Paez
 * @version 2009-05-22
 */

/**
 * Interface
 */
interface SVGWriteable
{
  public function toSVG();
}

/**
 * Class for generic XMLElements, takes care of nested nature of such
 * documents.
 */
class XMLElement
{
  protected $name;
  protected $attrs;
  protected $children;

  public function __construct($e,
                              $child = array(),
                              $attrs = array()) {
    $this->name = $e;
    $this->attrs = array();
    $this->children = array();
    foreach ($child as $c)
      $this->add($c);
    foreach ($attrs as $k => $val) {
      if (!is_array($val))
        $val = array($val);
      foreach ($val as $v)
        $this->set($k,$v);
    }
  }

  public function getAttrs()    { return $this->attrs; }
  public function getChildren() { return $this->children;}

  public function add($e) {
    if (!($e instanceof XMLElement))
      trigger_error(sprintf("%s is not a valid XMLElement.", $e),
                    E_USER_ERROR);
    $this->children[] = $e;
  }

  public function addAttr($name, $value) {
    $this->attrs[$name][] = $value;
  }

  public function toXML() {
    // Open tag
    $str = sprintf("\n<%s", $this->name);
    // Process attributes
    foreach ($this->getAttrs() as $attr => $value)
      if (!empty($value))
        $str .= sprintf("\n%s%s=\"%s\"",
                        str_repeat(" ", 4),
                        $attr,
                        implode(" ", $value));

    // Empty tag?
    if (count($this->getChildren()) == 0)
      return str_replace("\n",
                         sprintf("\n%s", str_repeat(" ", 2)),
                         ($str . "/>"));

    $str .= ">";
    $child_str = "";
    // Process children
    foreach ($this->getChildren() as $child) {
      $child_str .= $child->toXML();
    }
    // Close tag
    $str .= sprintf("%s\n</%s>",
                    $child_str,
                    $this->name);

    return str_replace("\n",
                       sprintf("\n%s", str_repeat(" ", 2)),
                       $str);
  }
}

class XMLText extends XMLElement
{
  private $content;

  public function __construct($content) {
    $this->content = $content;
  }

  // Override parent toSVG method
  public function toXML() {
    return sprintf("\n%s%s",
                   str_repeat(" ", 2),
                   $this->content);
  }
}

/**
 * Graph class for SVG elements. Represents a line graph capable of
 * plotting multiple datasets, each of which should be an example of a
 * LineData object. The axis limits are figured out automatically, or
 * can be manually set. A number of attributes are available for
 * customizing the graph.
 *
 * @author Dayan Paez
 * @version   April 2009
 */
class LineGraph implements SVGWriteable
{
  // Private variables
  private $attrs = array("background"  => "#F7F7F7",
                         "border-color"=> "#666666",
                         "border-width"=> "1",
                         "text"        => "Verdana",
                         "text-color"  => "#666666",
                         "show-x-axis" => true,
                         "show-y-axis" => true,
                         "show-legend" => false,
                         "x-label"     => "",
                         "y-label"     => "",
                         "title"       => "",
                         "width"       => "400",
                         "height"      => "300",
                         "vX"          => "60",
                         "vY"          => "25",
                         "v-width"     => "325",
                         "v-height"    => "225",
                         "axes"        => array("top"    => null,
                                                "left"   => null,
                                                "right"  => null,
                                                "bottom" => null,
                                                "x-res"  => null,
                                                "y-res"  => null,
                                                "x-form" => null,
                                                "y-form" => null));

  private $data = array(); // Sets of LineData

  public function __construct($title = "") {
    $this->attrs["title"] = $title;

    // Default values
    $this->x = array();
    $this->y = array();
  }

  public function addData($ldata) {
    $this->data[] = $ldata;
  }

  public function attrs($attrs) {
    $posAttrs = array_keys($this->attrs);
    foreach ($attrs as $key => $value) {
      if (in_array($key, $posAttrs))
        $this->attrs[$key] = $value;
      else
        trigger_error(sprintf("Invalid graph attribute %s ignored.", $value),
                      E_USER_WARNING);
    }
  }

  /**
   * Sets the X-axis for this chart. If not set, or if the parameters
   * passed are null, then calculate axes automatically.
   */
  public function setXaxis($low, $high, $step = null) {
    if ($low >= $high) {
      trigger_error(sprintf("Low value %s must be lower than high value %s.",
                            $low, $high),
                    E_USER_ERROR);
    }
    if (!is_numeric($low) ||
        !is_numeric($high) ||
        !is_numeric($step)) {
      trigger_error(sprintf("X axis dimensions must be numeric: [%s:%s:%s].",
                            $low, $high, $step),
                    E_USER_ERROR);
    }

    $this->attrs["axes"]["left"]  = $low;
    $this->attrs["axes"]["right"] = $high;
    $this->attrs["axes"]["x-res"] = $step;
  }

  /**
   * Sets the Y-axis for this chart. If not set, or if the parameters
   * passed are null, then calculate axes automatically.
   */
  public function setYaxis($low, $high, $step = null) {
    if ($low >= $high) {
      trigger_error(sprintf("Low value %s must be lower than high value %s.",
                            $low, $high),
                    E_USER_ERROR);
    }
    if (!is_numeric($low) ||
        !is_numeric($high) ||
        !is_numeric($step)) {
      trigger_error(sprintf("Y axis dimensions must be numeric: [%s:%s:%s].",
                            $low, $high, $step),
                    E_USER_ERROR);
    }      
    $this->attrs["axes"]["bottom"] = $low;
    $this->attrs["axes"]["top"]    = $high;
    $this->attrs["axes"]["y-res"]  = $step;
  }

  private function getSVGViewport() {
    $port_props = array("fill"        =>$this->attrs["background"],
                        "stroke"      =>$this->attrs["border-color"],
                        "stroke-width"=>$this->attrs["border-width"],
                        "width"       =>$this->attrs["v-width"],
                        "height"      =>$this->attrs["v-height"],
                        "x"           =>$this->attrs["vX"],
                        "y"           =>$this->attrs["vY"],
                        "id"          =>"viewport",
                        "desc"        =>"Chart Viewport",
                        "title"       =>"",
                        "rx"          =>"2",
                        "ry"          =>"2");
    return new XMLElement("rect", array(), $port_props);
  }

  private function getSVGTitle() {
    $props = array("id"         =>"title",
                   "font-size"  =>"11px",
                   "text-anchor"=>"middle",
                   "stroke"     =>"none",
                   "fill"       =>$this->attrs["text-color"],
                   "font-family"=>$this->attrs["text"],
                   "x"          =>($this->attrs["v-width"] / 2.0 +
                                   $this->attrs["vX"]),
                   "text-align" =>"center",
                   "y"          =>"18");
    return new XMLElement("text",
                          array(new XMLText($this->attrs["title"])),
                          $props);
  }

  private function getSVGxLabel() {
    $x = ($this->attrs["v-width"] / 2.0) + $this->attrs["vX"];
    $y = ($this->attrs["height"] + $this->attrs["v-height"]) / 2 + 20;
    $props = array("id"         =>"x-label",
                   "font-size"  =>"10px",
                   "text-anchor"=>"middle",
                   "stroke"     =>"none",
                   "text-align" =>"center",
                   "fill"       =>$this->attrs["text-color"],
                   "font-family"=>$this->attrs["text"],
                   "x"          =>$x,
                   "y"          =>$y);
    return new XMLElement("text",
                          array(new XMLText($this->attrs["x-label"])),
                          $props);
  }

  private function getSVGyLabel() {
    $x = 10;
    $y = ($this->attrs["v-height"] / 2.0) + $this->attrs["vY"];
    $props = array("id"         =>"y-label",
                   "font-size"  =>"10px",
                   "text-anchor"=>"middle",
                   "stroke"     =>"none",
                   "text-align" =>"center",
                   "fill"       =>$this->attrs["text-color"],
                   "font-family"=>$this->attrs["text"],
                   "x"          =>$x,
                   "y"          =>$y,
                   "transform"  =>"rotate(-90 $x $y)");
    return new XMLElement("text",
                          array(new XMLText($this->attrs["y-label"])),
                          $props);
  }

  public function toSVG($ind = 0) {
    // Create SVG document
    $root = new XMLElement("svg",
                           array(),
                           array("xmlns"=>"http://www.w3.org/2000/svg",
                                 "width"=>$this->attrs["width"],
                                 "height"=>$this->attrs["height"]));
    // Title
    $root->add($this->getSVGTitle());
    // Add viewport
    $root->add($port = $this->getSVGViewport());

    // Path(s)
    $top   = $this->attrs["vY"];
    $left  = $this->attrs["vX"];
    $width = $this->attrs["v-width"];
    $height= $this->attrs["v-height"];

    // ...determine graph limits
    $this->updateAxisLims();
    $lims = $this->attrs["axes"];
    foreach ($this->data as $d) {
      $xMin = min($d->X());
      $xMax = max($d->X());
      $yMin = min($d->Y());
      $yMax = max($d->Y());
      $xRange = ($xMax - $xMin);
      $yRange = ($yMax - $yMin);

      $dtop = ($lims["top"] - $yMax) * $height /
        ($lims["top"] - $lims["bottom"]) + $top;
      $dleft= ($lims["left"] - $xMin) * $width /
        ($lims["right"] - $lims["left"]) + $left;
      $dwidth = $xRange / ($lims["right"] - $lims["left"]) * $width;
      $dheight= $yRange / ($lims["top"] - $lims["bottom"]) * $height;

      $root->add($d->toSVG($dleft, $dtop, $dwidth, $dheight));
    }

    // Axes
    $root->add($this->getSVGxAxis($lims));
    $root->add($this->getSVGyAxis($lims));

    // Axis labels
    $root->add($this->getSVGxLabel());
    $root->add($this->getSVGyLabel());

    // Legend
    if ($this->attrs["show-legend"])
      $root->add($this->getLegend());

    // Print head
    return str_replace("\n",
                       sprintf("\n%s",
                               str_repeat(" ", $ind)),
                       $root->toXML());
  }

  /**
   * Helper method. Returns X-axis as a group
   */
  private function getSVGxAxis($lims) {
    $g = new XMLElement("g", array(), array("id"=>"x-axis"));
    $props = array("font-size"  =>"10px",
                   "font-family"=>$this->attrs["text"],
                   "fill"       =>$this->attrs["text-color"],
                   "text-anchor"=>"middle",
                   "text-align" =>"center",
                   "y"          =>($this->attrs["vY"] +
                                   $this->attrs["v-height"] + 15));
    $t_props=array("fill"        =>"none",
                   "stroke"      =>$this->attrs["border-color"],
                   "stroke-width"=>$this->attrs["border-width"],
                   "y1"          =>($this->attrs["vY"] +
                                    $this->attrs["v-height"]),
                   "y2"          =>($this->attrs["vY"] +
                                    $this->attrs["v-height"] - 5));
    $xRange = ($lims["right"] - $lims["left"]);
    if (!($xScale = @($this->attrs["v-width"] / $xRange)))
      $xScale = 1;

    $xRes = $lims["x-res"];

    for ($point  = $lims["left"];
         $point <= $lims["right"];
         $point += $xRes) {
      $value = ($point == (int)$point) ? $point : sprintf("%0.2f", $point);
      $xPos = ($this->attrs["vX"] + ($point - $lims["left"]) * $xScale);
      $pos =  array("x" => $xPos);
      $t_pos =array("x1"=> $xPos, "x2" => $xPos);
      $g->add(new XMLElement("text",
                             array(new XMLText($value)),
                             array_merge($props, $pos)));
      // Tickmark
      $g->add(new XMLElement("line",
                             array(),
                             array_merge($t_props, $t_pos)));
    }

    return $g;
  }

  /**
   * Helper method. Returns X-axis as a group
   */
  private function getSVGyAxis($lims) {
    $g = new XMLElement("g", array(), array("id"=>"y-axis"));
    $props = array("font-size"  =>"10px",
                   "font-family"=>$this->attrs["text"],
                   "fill"       =>$this->attrs["text-color"],
                   "text-anchor"=>"middle",
                   "text-align" =>"right",
                   "x"          =>$this->attrs["vX"] - 20);
    $t_props=array("fill"        =>"none",
                   "stroke"      =>$this->attrs["border-color"],
                   "stroke-width"=>$this->attrs["border-width"],
                   "x1"          =>$this->attrs["vX"],
                   "x2"          =>$this->attrs["vX"] + 5);
    $yRange = ($lims["top"] - $lims["bottom"]);
    if (!($yScale = @($this->attrs["v-height"] / $yRange)))
      $yScale = 1;

    $yRes = $lims["y-res"];
    $yOff = $this->attrs["vY"] + $this->attrs["v-height"];
    $start= $lims["bottom"];
    $end  = $lims["top"];
    for ($point  = $start;
         $point <= $end;
         $point += $yRes) {
      $value = ($point == (int)$point) ? $point : sprintf("%0.2f", $point);
      $yPos = $yOff - ($point - $lims["bottom"]) * $yScale;
      $pos =  array("y" => $yPos);
      $t_pos =array("y1"=> $yPos, "y2" => $yPos);
      $g->add(new XMLElement("text",
                             array(new XMLText($value)),
                             array_merge($props, $pos)));
      // Tickmark
      $g->add(new XMLElement("line",
                             array(),
                             array_merge($t_props, $t_pos)));
    }

    return $g;
  }

  /**
   * Returns the legend for this object, located in the north-east
   * corner of the graph body.
   */
  private function getLegend() {

  }

  /**
   * Helper method. Determines the limits on the axes. Updates the
   * list of parameters with keys "top", "left", "bottom", "right",
   * and "x-res", "y-res" with smallest resolution, heeding the manual
   * values set, if any. Also, update the format of the axis values.
   */
  private function updateAxisLims() {
    $top   = $this->attrs["vY"];
    $left  = $this->attrs["vX"];
    $width = $this->attrs["v-width"];
    $height= $this->attrs["v-height"];

    $lims["right"] = null;
    $lims["left"]  = null;
    $lims["top"]   = null;
    $lims["bottom"]= null;
    $lims["x-res"] = null;
    $lims["y-res"] = null;
    foreach ($this->data as $d) {
      $x = $d->X();
      $y = $d->Y();
      $lims["right"] = ($lims["right"] == null) ? max($x) : max($lims["right"], max($x));
      $lims["left"]  = ($lims["left"] == null)  ? min($x) : min($lims["left"], min($x));
      $lims["top"]   = ($lims["top"] == null) ? max($y)   : max($lims["top"], max($y));
      $lims["bottom"] = ($lims["bottom"] == null) ? min($y) : min($lims["bottom"], min($y));
      $lims["x-res"] = ($lims["x-res"] == null) ? $d->xRes(): min($lims["x-res"], $d->xRes());
      $lims["y-res"] = ($lims["y-res"] == null) ? $d->yRes(): min($lims["y-res"], $d->yRes());
    }
    $lims["x-form"]  = "%d";
    $lims["y-form"]  = "%0.2f";
    foreach ($this->attrs["axes"] as $spec => $val) {
      if ($val === null)
        $this->attrs["axes"][$spec] = $lims[$spec];
    }
  }
}

/**
 * LineData: represents a set of x and y points and associated
 * attributes, which are in this case publicly available.
 */
class LineData implements SVGWriteable
{
  private $attrs = array("line-width"  => "2",
                         "line-style"  => "solid",
                         "line-color"  => "#993333",
                         "show-dots"   => false,
                         "legend"      => "");
  private $x;
  private $y;

  public function __construct($x, $y, $attrs = array()) {
    if (count($x) != count($y))
      trigger_error("X and Y must arrays must have equal length.",
                    E_USER_ERROR);
    $this->x = $x;
    $this->y = $y;

    $this->attrs($attrs);
  }

  public function attrs($attrs) {
    $posAttrs = array_keys($this->attrs);
    foreach ($attrs as $key => $value) {
      if (in_array($key, $posAttrs))
        $this->attrs[$key] = $value;
      else
        trigger_error(sprintf("Invalid line attribute %s ignored.", $value),
                      E_USER_WARNING);
    }
  }

  public function getAttrs() { return $this->attrs; }
  public function X()        { return $this->x; }
  public function Y()        { return $this->y; }

  public function xRes() {
    // Return x resolution
    return $this->x[1] - $this->x[0];
  }
  public function yRes() {
    // Return x resolution
    return $this->y[1] - $this->y[0];
  }

  public function toSVG($left = 0,
                        $top = 0,
                        $width = null,
                        $height = null) {

    $x = $this->x;
    $y = $this->y;
    $attrs = $this->attrs;

    if (empty($x) || empty($y))
      return new XMLText("");

    $xOff = $left;
    $yOff = $top + $height;

    // Determine limits
    $xMin = min($x);
    $xMax = max($x);
    $yMin = min($y);
    $yMax = max($y);
    $xRange = ($xMax - $xMin);
    $yRange = ($yMax - $yMin);
    if (!($xScale = @($width / $xRange)))
      $xScale = 1;
    if (!($yScale = @($height / $yRange)))
      $yScale = 1;

    // Draw each point
    $pX = $xOff + (array_shift($x) - $xMin) * $xScale;
    $pY = $yOff - (array_shift($y) - $yMin) * $yScale;
    $def = sprintf("M %7.2f,%7.2f", $pX, $pY);
    while (!empty($x) && !empty($y)) {
      $pX = $xOff + (array_shift($x) - $xMin) * $xScale;
      $pY = $yOff - (array_shift($y) - $yMin) * $yScale;
      $def .= sprintf(" L %7.2f,%7.2f", $pX, $pY);
    }

    // Create SVG element
    return new XMLElement("path", array(),
                          array("title"     =>$attrs["legend"],
                                "stroke"    =>$attrs["line-color"],
                                "stroke-width"=>$attrs["line-width"],
                                "stroke-linecap" =>"round",
                                "stroke-linejoin"=>"round",
                                "fill"           =>"none",
                                "d"              =>$def));
  }
}
?>