<?php
/**
 * Generic SVG library for easily creating SVG documents, based on the
 * XML Library.  I strongly suggest http://www.w3.org/TR/SVG/struct.html
 *
 * @author Dayan Paez
 * @version 2010-09-10
 * @package svg
 */

require_once(dirname(__FILE__).'/XmlLib.php');

/**
 * Parent class for SVG elements. Keeps track of ID
 *
 */
abstract class SVGAbstractElem extends XElem {
  public static $namespace = null;
  public function __construct($tag, Array $attrs = array(), Array $child = array()) {
    parent::__construct((self::$namespace === null) ? $tag : self::$namespace . ':' . $tag, $attrs, $child);
  }

  protected $id;
  public function set($attr, $value) {
    if ($attr == "id")
      $this->id = $value;
    parent::set($attr, $value);
  }
  public function id() { return $this->id; }
}

/**
 * Parent class for all leaf elements (those that do not allow children)
 *
 */
abstract class SVGAbstractLeaf extends SVGAbstractElem {
  public function add($elem) {
    throw new InvalidArgumentException("Leaf element does not allow children");
  }
}

/**
 * The parent SVG document.
 *
 */
class SVGDoc extends XDoc {
  /**
   * Specify the title. Every document should have a title, after all
   *
   * @param String $width
   * @param String $height
   * @param String $title the title of the document
   */
  public function __construct($width, $height, $title = "") {
    parent::__construct("svg", array("width" => $width,
				     "height" => $height,
				     "xmlns" => "http://www.w3.org/2000/svg",
				     "xmlns:xlink" => "http://www.w3.org/1999/xlink"),
			array(new XElem("title", array(), array(new XText($title)))));
  }
}

class SVGDesc extends XElem {
  /**
   * Creates a description field
   *
   * @param String $content the string-able content of this description
   */
  public function __construct($content) {
    parent::__construct("desc", array(), array(new XText($content)));
  }
}

/**
 * Group element
 *
 */
class SVGG extends SVGAbstractElem {
  /**
   * @param String $id the id of this group
   */
  public function __construct($id, Array $attrs = array(), Array $child = array()) {
    parent::__construct("g", $attrs, $child);
    $this->set("id", $id);
  }
}

/**
 * Definition for reuse
 *
 * @author Dayan Paez
 * @version 2010-09-10
 */
class SVGDefs extends SVGAbstractElem {
  public function __construct(Array $attrs = array(), Array $child = array()) {
    parent::__construct("defs", $attrs, $child);
  }
}

/**
 * Gradient
 *
 */
class SVGLinGradient extends SVGAbstractElem {
  public function __construct(Array $attrs = array(), Array $stops = array()) {
    parent::__construct("linearGradient", $attrs, $stops);
  }
  /**
   * @param SVGStop $elem the gradient stop
   */
  public function add($elem) {
    if (!($elem instanceof SVGStop))
      throw new InvalidArgumentException("Child must be instance of SVGStop");
    parent::add($elem);
  }
}

/**
 * Gradient stop
 *
 * @author Dayan Paez
 * @version 2010-09-10
 */
class SVGStop extends SVGAbstractLeaf {
  /**
   * Creates a new stop at the given offset and color
   *
   * @param String $offset "20%", for instance
   * @param String $color  "#39F"
   */
  public function __construct($offset, $color) {
    parent::__construct("stop", array("offset" => $offset, "stop-color" => $color));
  }
}

/**
 * Symbol element, for ease of code repetition
 *
 */
class SVGSymbol extends SVGAbstractElem {
  /**
   * Creates a symbol with the given ID. Use the ID later in the
   * SVGUse element
   */
  public function __construct($id, Array $attrs = array(), Array $child = array()) {
    parent::__construct("symbol", $attrs, $child);
    $this->set("id", (string)$id);
  }
}

class SVGUse extends SVGAbstractLeaf {
  public function __construct($href, $x, $y) {
    parent::__construct("use", array("href" => $href, "x" => $x, "y" => $y));
  }
}

class SVGPath extends SVGAbstractLeaf {
  private $d;
  /**
   * Creates a path with the given path_data and optional attributes
   *
   * @param Array<SVGPData> the path data elements
   */
  public function __construct(Array $path_data = array(), Array $attrs = array()) {
    parent::__construct("path", $attrs);
    $this->d = implode(" ", $path_data);
    $this->set("d", $this->d);
  }
  public function addPath(SVGPData $d) {
    $this->d .= " $d";
    $this->set("d", $this->d);
  }
}

// ------------------------------------------------------------
// Path data objects
// ------------------------------------------------------------
class SVGPData {
  const MOVE = 'M';
  const LINE = 'L';
  const CUBIC_BEZIER = 'C';
  const QUAD_BEZIER = 'Q';
  const ARC = 'A';

  protected $type = "";
  protected $absolute;
  /**
   * @var Array
   */
  protected $x, $y;
  public function __construct($type, $x, $y, $absolute = true) {
    $this->type = $type;
    $this->absolute = $absolute;
    $this->add($x, $y);
  }
  public function add($x, $y) {
    $this->x[] = $x;
    $this->y[] = $y;
  }
  public function __toString() {
    $d = ($this->absolute) ? strtoupper($this->type) : strtolower($this->type);
    foreach ($this->x as $i => $x)
      $d .= (" $x,".$this->y[$i]);
    return $d;
  }
}
class SVGMoveto extends SVGPData {
  public function __construct($x, $y, $absolute = true) {
    parent::__construct(self::MOVE, $x, $y, $absolute);
  }
}
class SVGLineto extends SVGPData {
  public function __construct($x, $y, $absolute = true) {
    parent::__construct(self::LINE, $x, $y, $absolute);
  }
}
class SVGClosepath extends SVGPData {
  public function __construct() {}
  public function __toString() { return "Z"; }
}
/**
 * 'C' in the pathdata
 */
class SVGBezier extends SVGPData {
  public function __construct(Array $x, Array $y, $absolute = true) {
    parent::__construct(self::CUBIC_BEZIER, $x[0], $y[0], $absolute);
    foreach ($x as $i => $p)
      $this->add($p, $y[$i]);
  }
}
class SVGQuadratic extends SVGPData {
  public function __construct(Array $x, Array $y, $absolute = true) {
    parent::__construct(self::QUAD_BEZIER, $x[0], $y[0], $absolute);
    foreach ($x as $i => $p)
      $this->add($p, $y[$i]);
  }
}
class SVGElliptical extends SVGPData {
  private $rx, $ry, $xrot, $large_flag, $sweep;
  public function __construct($rx, $ry, $xrot, $large_flag, $sweep, $x, $y, $absolute = true) {
    parent::__construct(self::ARC, $x, $y, $absolute);
    $this->rx = $rx;
    $this->ry = $ry;
    $this->xrot = $xrot;
    $this->large_flag = (int)$large_flag;
    $this->sweep = (int)$sweep;
  }
  public function __toString() {
    return sprintf("%s %s,%s %s %d,%d %s,%s",
		   ($this->absolute) ? strtoupper($this->type) : strtolower($this->type),
		   $this->rx,
		   $this->ry,
		   $this->xrot,
		   $this->large_flag,
		   $this->sweep,
		   $this->x[0],
		   $this->y[0]);
  }
}

class SVGEllipse extends SVGAbstractLeaf {
  public function __construct($cx, $cy, $rx, $ry, Array $attrs = array()) {
    parent::__construct("circle", $attrs);
    $this->set("cx", $cx);
    $this->set("cy", $cy);
    $this->set("rx", $rx);
    $this->set("ry", $ry);
  }
}
class SVGCircle extends SVGAbstractLeaf {
  public function __construct($cx, $cy, $r, Array $attrs = array()) {
    parent::__construct("circle", $attrs);
    $this->set("cx", $cx);
    $this->set("cy", $cy);
    $this->set("r" , $r);
  }
}
class SVGRect extends SVGAbstractLeaf {
  public function __construct($x, $y, $width, $height, $rx = 0, $ry = 0, Array $attrs = array()) {
    parent::__construct("rect", $attrs);
    $this->set("x", $x);
    $this->set("y", $y);
    $this->set("width",  $width);
    $this->set("height", $height);
    $this->set("rx", $rx);
    $this->set("ry", $ry);
  }
}
class SVGText extends SVGAbstractElem {
  public function __construct($x, $y, $text, Array $attrs = array()) {
    parent::__construct("text", $attrs);
    $this->set("x", $x);
    $this->set("y", $y);
    
    if (is_array($text))
      foreach ($text as $elem)
	$this->add($elem);
    else
      $this->add($text);
  }
  public function add($tspan) {
    if ($tspan instanceof SVGTspan)
      parent::add($tspan);
    else
      parent::add(new SVGTspan($tspan));
  }
}
class SVGTspan extends SVGAbstractLeaf {
  private $text;
  public function __construct($text, Array $attrs = array()) {
    $this->text = new XText($text, $attrs);
  }
  public function toXML() { return $this->text->toXML(); }
  public function printXML() { return $this->text->printXML(); }
}
/**
 * @see XA
 */
class SVGA extends SVGAbstractElem {
  public function __construct($href, Array $child = array(), Array $attrs = array()) {
    parent::__construct("a", $attrs, $child);
    $this->set("xlink:href", $href);
  }
}
/**
 * @see XImg
 */
class SVGImage extends SVGAbstractElem {
  public function __construct($x, $y, $width, $height, $href, Array $attrs = array(), Array $child = array()) {
    parent::__construct("image", $attrs, $child);
    $this->set("x", $x);
    $this->set("y", $y);
    $this->set("width", $width);
    $this->set("height", $height);
    $this->set("xlink:href", $href);
  }
}
/**
 * Javascript suitable for SVG embedding
 *
 * @author Dayan Paez
 * @version 2012-10-29
 * @see XScript
 */
class SVGScript extends SVGAbstractElem {
  /**
   * Creates a script element of the given type, using the given
   * source and/or content
   *
   * @param String $type the type
   * @param String $src the "src" attribute. Use null to exclude
   * @param String $text the content
   */
  public function __construct($type, $src = null, $text = null) {
    parent::__construct("script", array("type"=>$type));
    $this->non_empty = true;
    if ($src !== null)
      $this->set("xlink:href", $src);
    if ($text !== null)
      $this->add(new XCData($text));
  }
}
/**
 * Embedded style element
 *
 * @author Dayan Paez
 * @version 2012-10-29
 * @see XStyle
 */
class SVGStyle extends SVGAbstractElem {
  /**
   * Creates a new style element
   *
   * @param String $type the type attribute
   * @param String $content the content
   */
  public function __construct($type, $content) {
    parent::__construct("style", array("type"=>$type), array(new XCData($content)));
  }
}
?>