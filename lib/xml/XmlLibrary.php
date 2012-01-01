<?php
/**
 * XML and HTML classes to facilitate the making of HTML pages
 *
 * @author Dayan Paez
 * @version 2009-10-04
 * @package xml
 */

/**
 * Interface for HTML elements: specifies toXML method
 */
interface HTMLElement
{
  public function toXML();
  public function printXML();
}


/**
 * Generic object for HTML Element.
 *
 * Prescribes ways to get HTML as well as
 * attributes, and children nodes
 */
class GenericElement implements HTMLElement
{
  // Private variables
  protected $elemName;
  protected $elemAttrs;
  protected $elemChildren;

  public function __construct($e,
			      $child = array(),
			      $attrs = array()) {
    // Initialize variables
    $this->elemName = $e;

    $this->elemChildren = array();
    foreach ($child as $c)
      $this->add($c);
    $this->elemAttrs = array();
    foreach ($attrs as $k => $val) {
      if (!is_array($val))
	$val = array($val);
      foreach ($val as $v)
	$this->set($k,$v);
    }
  }

  public function getAttrs()    { return $this->elemAttrs; }
  public function getChildren() { return $this->elemChildren;}
  public function getName() { return $this->elemName; }

  public function add($e) {
    $this->elemChildren[] = $e;
  }
  public function set($name, $value) {
    $this->elemAttrs[$name][] = $value;
  }

  // Like adding attribute, except it does not append
  public function setAttr($name, $value) {
    $this->elemAttrs[$name] = array($value);
  }
  // Removes entire attribute from element
  public function removeAttr($name) {
    unset($this->elemAttrs[$name]);
  }

  // Generic toXML method
  public function toXML() {
    $str = "<" . $this->elemName;
    // Process attributes
    foreach ($this->getAttrs() as $attr => $value) {
      if (!empty($value)) {
	$str .= " $attr=\"";
	$str .= implode(" ", $value) . "\"";
      }
    }
    if (count($this->getChildren()) == 0)
      return $str . "/>";

    $str .= ">";
    $child_str = "";
    // Process children
    foreach ($this->getChildren() as $child) {
      $str .= $child->toXML();
    }
    // Close tag
    $str .= sprintf("</%s>", $this->elemName);
    return $str;
  }

  // Generic printXML method
  public function printXML() {
    echo "<" . $this->elemName;
    // Process attributes
    foreach ($this->getAttrs() as $attr => $value) {
      if (!empty($value)) {
	echo " $attr=\"";
	echo implode(" ", $value) . "\"";
      }
    }
    if (count($this->getChildren()) == 0) {
      echo "/>";
      return;
    }
    echo ">";
    // Process children
    foreach ($this->getChildren() as $child)
      $child->printXML();

    // Close tag
    echo sprintf("</%s>", $this->elemName);
  }

  /**
   * toString representation
   */
  public function __toString() {
    return sprintf("HTMLComp: %s", $this->elemName);
  }

  /**
   * creates clone of this object and its children
   */
  public function __clone() {
    foreach($this->elemChildren as $key => $child) {
      $this->elemChildren[$key] = clone($this->elemChildren[$key]);
    }
  }
}

/**
   Holds information in a "table" which can then be printed to HTML,
   or otherwise parsed through.

   @author Dayan Paez
   @version   February 3, 2009
*/

class Table extends GenericElement
{
  // Variables
  private $header; // <thead>
  private $body;   // <tbody>
  private $bodyrows;
  private $headrows;

  public function __construct($rows  = array(),
			      $attrs = array()) {
    parent::__construct("table",
			array(),
			$attrs);

    $this->header = new GenericElement("thead");
    $this->body   = new GenericElement("tbody");

    $this->add($this->header);
    $this->add($this->body);

    foreach ($rows as $r) {
      $this->addRow($r);
    }
  }

  // Add rows to the <tbody> element
  // takes multiple arguments
  public function addRow($row) {
    foreach (func_get_args() as $r) {
      // Check for valid entry
      if ( !($r instanceof Row) ) {
	trigger_error("Variable is not a Row object", E_USER_WARNING);
      }
      else {
	$this->body->add($r);
      }
    }
  }

  // Add rows to <thead> element
  // takes multiple arguments
  public function addHeader($row) {
    foreach (func_get_args() as $r) {
      // Check for valid entry
      if ( !($r instanceof Row) ) {
	trigger_error("Variable is not a Row object", E_USER_WARNING);
      }
      else {
	$this->header->add($r);
      }
    }
  }

  // Get rows
  public function getRows() {
    return $this->body->getChildren();
  }
  public function getHeaders() {
    return $this->header->getChildren();
  }
}




/**
   Represents a table row

   @author Dayan Paez
   @version   February 3, 2009
*/

class Row extends GenericElement
{
  // $cells is an array of cells to add to this row
  // $attrs are attributes to add to this row
  // see Cell constructor
  public function __construct($cells = array(),
			      $attrs = array()) {
    parent::__construct("tr",
			array(),
			$attrs);
    foreach ($cells as $c)
      $this->addCell($c);
  }

  public function addCell($cell) {
    foreach (func_get_args() as $c) {
      if (!($c instanceof Cell)) {
	throw new InvalidArgumentException("Argument is not a Cell object");
	trigger_error("Argument is not a Cell object", E_USER_WARNING);
      }
      else {
	$this->add($c);
      }
    }
  }
  public function getCells() {
    return $this->getChildren();
  }
}


/**
   Encapsulates a table Cell object

   @author Dayan Paez
   @version   February 3, 2009
*/
class Cell extends GenericElement
{
  // Constructor for cell
  // $value is cell value
  // $type  is 0 for <td> and 1 for <th>
  // $attrs is array of attributes, e.g.:
  // 
  //   $attrs = array("id"    => "somecell",
  //                  "title" => "celltitle");
  public function __construct($value = "",
			      $attrs = array(),
			      $type  = 0) {
    if ($type == 0)
      $t = "td";
    else
      $t = "th";
    parent::__construct($t, 
			array(),
			$attrs);
    if (is_object($value))
      $this->add($value);
    else
      $this->add(new XText($value));
  }

  public function addText($val) {
    $this->add(new XText($val));
  }
  
  public function is_header() {
    return ($this->type != 0);
  }

  // Returns new cell object of the specified type
  public static function td($value = "") {
    return new Cell($value);
  }

  public static function th($value = "") {
    return new Cell($value,array(),1);
  }

}
?>
