<?php
/**
 * XML and HTML classes to facilitate the making of HTML pages
 *
 * @author Dayan Paez
 * @created 2009-10-04
 * @package xml
 */

/**
 * Interface for HTML elements: specifies toHTML method
 */
interface HTMLElement
{
  public function toHTML();
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
      $this->addChild($c);
    $this->elemAttrs = array();
    foreach ($attrs as $k => $val) {
      if (!is_array($val))
	$val = array($val);
      foreach ($val as $v)
	$this->addAttr($k,$v);
    }
  }

  public function getAttrs()    { return $this->elemAttrs; }
  public function getChildren() { return $this->elemChildren;}
  public function getName() { return $this->elemName; }

  public function addChild($e) {
    if (!$e instanceof HTMLElement) {
      trigger_error(sprintf("%s is not a valid HTMLElement", $e),
		    E_USER_ERROR);
    }
    $this->elemChildren[] = $e;
  }

  // Add a new attribute value to the attribute name
  public function addAttr($name, $value) {
    $this->elemAttrs[$name][] = $value;
  }
  // Like adding attribute, except it does not append
  public function setAttr($name, $value) {
    $this->elemAttrs[$name] = array($value);
  }
  // Pops attribute value from list for attribute name
  public function popAttr($name, $value) {
    unset($this->elemAttrs[$name][$value]);
  }
  // Removes entire attribute from element
  public function removeAttr($name) {
    unset($this->elemAttrs[$name]);
  }

  // Generic toHTML method
  public function toHTML() {
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
      $str .= $child->toHTML();
    }
    // Close tag
    $str .= sprintf("</%s>", $this->elemName);
    return $str;
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
 * Generic HTML Page, with head and body elements built in
 */
class WebPage extends GenericElement
{
  // Variables
  protected $head; // head element
  protected $body; // body element

  public function __construct() {
    parent::__construct("html",
			array(),
			array("xmlns"=>"http://www.w3.org/1999/xhtml",
			      "xml:lang"=>"en",
			      "lang"=>"en"));
    $this->head = new GenericElement("head");
    $this->body = new GenericElement("body");

    $this->addChild($this->head);
    $this->addChild($this->body);
  }

  public function addHead($e) {
    $this->head->addChild($e);
  }
  public function addBody($e) {
    $this->body->addChild($e);
  }

  public function __get($e) {
    if ($e == "body" || $e == "head")
      return $this->$e;
  }

  public function toHTML() {
    $str = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
    $str .= parent::toHTML();
    return $str;
  }
}

/**
 * Division component, <div>
 */
class Div extends GenericElement
{
  public function __construct($children = array(),
			      $attrs    = array()) {
    parent::__construct("div", $children, $attrs);
  }
			      
}

/**
 * Menu div
 */
class MenuDiv extends Div
{
  public function __construct($name,
			      $attrs = array()) {
    parent::__construct(array(), $attrs);
    $this->addAttr("class","menu");
    $this->addChild(new PortTitle($name, array("class"=>"hidden")));
  }

  // Adds list to menu, $list = GenericList
  public function addSubmenu($title, $list) {
    if (!$list instanceof GenericList)
      trigger_error("Submenu is not a valid list", E_CORE_ERROR);
    else {
      $this->addChild(new Heading($title));
      $this->addChild($list);
    }
  }
}


/**
   Holds information in a "table" which can then be printed to HTML,
   or otherwise parsed through.

   @author Dayan Paez
   @date   February 3, 2009
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

    $this->addChild($this->header);
    $this->addChild($this->body);

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
	trigger_error("Variable is not a Row object", E_CORE_WARNING);
      }
      else {
	$this->body->addChild($r);
      }
    }
  }

  // Add rows to <thead> element
  // takes multiple arguments
  public function addHeader($row) {
    foreach (func_get_args() as $r) {
      // Check for valid entry
      if ( !($r instanceof Row) ) {
	trigger_error("Variable is not a Row object", E_CORE_WARNING);
      }
      else {
	$this->header->addChild($r);
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
   @date   February 3, 2009
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
	$this->addChild($c);
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
   @date   February 3, 2009
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
    if (($value instanceof GenericElement))
      $this->addChild($value);
    else
      $this->addChild(new Text($value));
  }

  public function addText($val) {
    $this->addChild(new Text($val));
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

/**
Placeholder for images
*/
class Image extends GenericElement
{
  // Variables
  public function __construct($source = "",
			      $attrs  = array()) {
    parent::__construct("img",
			array(),
			$attrs);
    $this->addAttr("src",$source);
  }
}

/**
 * Paragraph element <p>
 */
class Para extends GenericElement
{
  public function __construct($val,
			      $attrs = array()) {
    parent::__construct("p",
			array(new Text($val)),
			$attrs);
  }
}

/**
 * Text object
 */
class Text implements HTMLElement
{
  // Private variables
  private $value;
  
  public function __construct($v = "") {
    $this->value = $v;
  }
  public function toHTML() {
    return $this->value;
  }
}

/**
 * Div object, of CSS class "port" used 
 * to organize pages
 */
class Port extends GenericElement
{
  private $title;
  public function __construct($title = "", 
			      $value = array(), 
			      $attrs = array()) {
    parent::__construct("div",
			array_merge(array($this->title = new PortTitle($title)),
				    $value),
			$attrs);
    $this->addAttr("class","port");
  }

  public function addHelp($href) {
    $this->title->addChild(new HLink($href));
  }
}

/**
 * Portlets, instead of ports, which have CSS class "small"
 */
class Portlet extends Port
{
  public function __construct($title = "",
			      $value = array(),
			      $attrs = array()) {
    parent::__construct($title, $value, $attrs);
    $this->addAttr("class","small");
  }
}

/**
 * Title elements (H3)
 */
class PortTitle extends GenericElement
{
  public function __construct($title,
			      $attrs = array()) {
    parent::__construct("h3",
			array(new Text($title)),
			$attrs);
  }
}

/**
 * Simple heading (H4)
 */
class Heading extends GenericElement
{
  public function __construct($title = "",
			      $attrs = array()) {
    parent::__construct("h4",
			array(new Text($title)),
			$attrs);
  }
  
  public function addHelp($href) {
    $this->addChild(new HLink($href));
  }
}

/**
 * Generic List,  entries must be LItem's
 */
class GenericList extends GenericElement
{
  public function __construct($type = "ul",
			      $items = array(),
			      $attrs   = array()) {
    parent::__construct($type,
			$items,
			$attrs);
  }

  public function addItems($li) {
    foreach (func_get_args() as $item) {
      if (($item instanceof LItem))
	$this->addChild($item);
    }
  }

  public function getItems() { return $this->getChildren(); }
}

/**
 * Itemize: Wrapper for List of type (ul)
 */
class Itemize extends GenericList
{
  public function __construct($items = array(),
			      $attrs = array()) {
    parent::__construct("ul",
			$items,
			$attrs);
  }
}

/**
 * Enumerate: Wrapper for List of type (ol)
 */
class Enumerate extends GenericList
{
  public function __construct($items = array(),
			      $attrs = array()) {
    parent::__construct("ol",
			$items,
			$attrs);
  }
}


/**
 * List item (li)
 */
class LItem extends GenericElement
{
  public function __construct($value = "",
			      $attrs = array()) {
    if (($value instanceof GenericElement))
      parent::__construct("li",
			  array($value),
			  $attrs);
    else
      parent::__construct("li",
			  array(new Text($value)),
			  $attrs);
  }
}


/**
 * Span wrapper to include as FGenericElement in FItem
 */
class Span extends GenericElement
{
  public function __construct($value = array(),
			      $attrs = array()) {
    parent::__construct("span",
			$value,
			$attrs);
  }
}


/**
 * Form element. Creates form element for web
 */
class Form extends GenericElement
{
  // Use common provides standard prefixes
  public function __construct($action,
			      $method = "post",
			      $attrs = array()) {
    parent::__construct("form",
			array(),
			$attrs);
    $this->addAttr("action", $action);
    $this->addAttr("method", $method);
  }
}

/**
 * Form item element. This is comprised of a message and a form
 * input. This form input should be a subclass of FGenericElement
 */
class FItem extends GenericElement
{
  public function __construct($message,
			      $form_input) {
    parent::__construct("div",
			array(),
			array("class"=>"form_entry"));
    // Add span form_h
    if (is_string($message)) {
      $this->addChild(new GenericElement("span",
					 array(new Text($message)),
					 array("class"=>"form_h")));
    }
    elseif (($message instanceof GenericElement)) {
      $this->addChild($message);
      $message->addAttr("class","form_h");
    }
    else {
      trigger_error("Argument is neither string or GenericElement",
		    E_USER_ERROR);
    }

    // Add form_input
    if ($form_input instanceof GenericElement)
      $this->addChild($form_input);
    else
      trigger_error("Argument is not a valid GenericElement", E_USER_ERROR);
  }
}

/**
 * Generic form input
 */
class FGenericElement extends GenericElement
{
  // private variables
  protected $formName;
  protected $formValue; // array
  
  // $t is the tagname of the form element
  // $n is the name attribute of the form elment
  // $v is the (default) value
  public function __construct($t,
			      $n,
			      $v = array(),
			      $attrs = array()) {
    parent::__construct($t,
			array(),
			$attrs);

    // Initialize value variable empty array
    $this->formValue = array();

    $this->setName($n);
    foreach ($v as $val)
      $this->addValue($val);
  }
  public function getFormName() { return $this->formName; }
  public function getDefaultValue(){ return $this->formValue;}

  public function setName($n) {
    $this->formName = $n;
    $this->addAttr("name",$n);
  }
  public function addValue($v) {
    $this->formValue[] = $v;
    $this->addAttr("value",$v);
  }

  public function enable($enable = true) {
    if ($enable)
      $this->removeAttr("disabled");
    else
      $this->setAttr("disabled", "disabled");
  }
}

/**
 * Form text input
 */
class FText extends FGenericElement
{
  public function __construct($name,
			      $value = "",
			      $attrs = array()) {
    parent::__construct("input",
			$name,
			array($value),
			$attrs);

    $this->addAttr("type","text");
  }
}

class FFile extends FGenericElement
{
  public function __construct($name,
			      $attrs = array()) {
    parent::__construct("input",
			$name,
			array(),
			$attrs);
    $this->addAttr("type", "file");
  }
}

/**
 * Textarea <textarea>
 */
class FTextarea extends FGenericElement
{
  public function __construct($name,
			      $value = "",
			      $attrs = array()) {
    parent::__construct("textarea",
			$name,
			array(""),
			$attrs);
    $this->addChild(new Text($value));
  }
}


/**
 * Span wrapper to include as FGenericElement in FItem
 */
class FSpan extends FGenericElement
{
  public function __construct($value,
			      $attrs = array()) {
    parent::__construct("span",
			"",
			array(),
			$attrs);
    if ($value instanceof GenericElement)
      $this->addChild($value);
    else
      $this->addChild(new Text($value));
  }
}


/**
 * Wrapper for <input type="checkbox"/>
 */
class FCheckbox extends FGenericElement
{
  public function __construct($name,
			      $value,
			      $attrs = array()) {
    parent::__construct("input",
			$name,
			array($value),
			$attrs);
    $this->addAttr("type", "checkbox");
  }
}

/**
 * Wrapper for <label for"...">
 */
class Label extends GenericElement
{
  public function __construct($for,
			      $value = "",
			      $attrs = array()) {
    parent::__construct("label",
			array(new Text($value)),
			$attrs);
    $this->addAttr("for", $for);
  }
}


/**
 * From select input (allows multiple selects)
 */
class FSelect extends FGenericElement
{
  public function __construct($name,
			      $value = array(),
			      $attrs = array()) {
    parent::__construct("select",
			$name,
			$value,
			$attrs);
  }

  // Options should be given as keyed arrays with keys equal to option
  // "value" and array value equal to option "label", all strings
  public function addOptions($opt) {
    foreach ($opt as $val => $label) {
      $this->addChild(new Option($val, $label));
    }
  }

  // Adds option group <optgroup>, with label and options
  public function addOptionGroup($label, $opt) {
    $o = new OptionGroup($label);
    foreach ($opt as $val => $label) {
      $o->addChild(new Option($val, $label));
    }
    $this->addChild($o);
  }

  // Overrides addChild method
  public function addChild($e) {
    if (($e instanceof OptionGroup)) { // children MUST be options
      foreach ($e->getChildren() as $option) {
	if (in_array($option->getOptionValue(),
		     $this->getDefaultValue()))
	  $option->select();
	else
	  $option->select(false);
      }
    }
    elseif ($e instanceof Option) {
      if (in_array($e->getOptionValue(),
		   $this->getDefaultValue()))
	$e->select();
      else
	$e->select(false);
    }
    else {
      echo get_class($e);
      // Children must be either options or optiongroups
      trigger_error("Select element must have option or option " . 
		    "groups as children",
		    E_USER_ERROR);
    }
    // Add, finally
    parent::addChild($e);
  }

}

/**
 * Option group <optgroup>.
 */
class OptionGroup extends GenericElement
{
  public function __construct($label,
			      $value = array(),
			      $attrs = array()) {
    parent::__construct("optgroup",
			$value,
			$attrs);
    $this->addAttr("label", $label);
  }

}

/**
 * Option for select elements <option>
 */
class Option extends GenericElement
{
  public function __construct($value = "",
			      $content = "",
			      $attrs = array()) {
    parent::__construct("option",
			array(new Text($content)),
			$attrs);
    $this->addAttr("value", $value);
  }

  // Selects/deselects this option
  public function select($state = true) {
    if ($state)
      $this->setAttr("selected", "selected");
    else
      $this->removeAttr("selected");
  }

  public function getOptionValue() {
    return $this->elemAttrs["value"][0];
  }
}

/**
 * Submit buttons
 */
class FSubmit extends FGenericElement
{
  public function __construct($name,
			      $value,
			      $attrs = array()) {
    parent::__construct("input",
			$name,
			array($value),
			$attrs);
    $this->addAttr("type", "submit");
  }
}

/**
 * Reset button
 */
class FReset extends FGenericElement
{
  public function __construct($name,
			      $value,
			      $attrs = array()) {
    parent::__construct("input",
			$name,
			array($value),
			$attrs);
    $this->addAttr("type","reset");
  }
}

/**
 * Submit button for accessibility for non-javascript pages
 * Automatically adds class "accessible" to submit button
 */
class FSubmitAccessible extends FSubmit
{
  public function __construct($name="") {
    parent::__construct($name,
			"Update",
			array("class"=>"accessible"));
  }
}

/**
 * Represents a hidden input value <input type="hidden"
 */
class FHidden extends FGenericElement
{
  public function __construct($name,
			      $value) {
    parent::__construct("input",
			$name,
			array($value),
			array("type"=>"hidden"));
  }
}

/**
 * Represents a password input <input type="password"
 */
class FPassword extends FGenericElement
{
  public function __construct($name,
			      $value,
			      $attrs = array()) {
    parent::__construct("input",
			$name,
			array($value),
			$attrs);

    $this->addAttr("type", "password");
  }
}

/**
 * <a> element
 */
class Link extends GenericElement
{
  public function __construct($href, $link, $attrs = array()) {
    parent::__construct("a",
			array(),
			$attrs);
    if (!($link instanceof HTMLElement))
      $link = new Text($link);
    $this->addChild($link);
    $this->addAttr("href", $href);
  }
}

class HLink extends Span
{
  public function __construct($href) {
    parent::__construct(array(new Link(sprintf("%s/%s",
					       "../help/html",
					       $href), "[ ? ]",
				       array("target"=>"_blank"))),
			array("class"=>"hlink"));
  }
	 
}

/**
 * Bookmark, or empty <a> element with a name
 */
class Bookmark extends GenericElement
{
  public function __construct($name) {
    parent::__construct("a", array(), array("name"=>$name));
  }
}

/**
 * Encapsulates the page title (h2 element)
 *
 * @author Dayan Paez
 * @version 2.0
 */
class PageTitle extends GenericElement {
  
  /**
   * Creates a new title object (h2) with the specified text
   *
   */
  public function __construct($text) {
    parent::__construct("h2", array(new Text($text)));
  }
}

/**
 * Pagination links
 *
 * @author Dayan Paez
 * @version 2010-07-24
 */
class PageDiv extends Div {

  /**
   * Creates a smart pagination div for the give number of pages,
   * using the prefix in the links. Pagination is 1-based
   *
   * @param int $num_pages the total number of pages
   * @param int $current the current page number
   * @param String $prefix the prefix for the links
   */
  public function __construct($num_pages, $current, $prefix) {
    parent::__construct(array(), array("class"=>"navlinks"));
    
    // for now, list all the pages
    for ($i = 1; $i <= $num_pages; $i++) {
      $this->addChild($l = new Link(sprintf("%s|%d", $prefix, $i), $i));
      $this->addChild(new Text(" "));
      if ($i == $current) {
	$l->addAttr("class", "current");
      }
    }
  }
}

?>
