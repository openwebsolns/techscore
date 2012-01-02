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
?>
