<?php
/**
 * @package xml
 * @version 0.9
 *
 * A library of XML goodies. A set of tools to CREATE, NOT PARSE Xml
 * documents in a very easy and straightforward way. In particular,
 * entire trees can be created on the fly.
 *
 * This version provides for printing the XML to standard output
 * {@link Xmlable::printXML} instead of generating the code as a string
 * {@link Xmlable::toXML}, which should save on a whole lot of memory for
 * largely nested elements.
 */

/**
 * Interface for XML objects requires toXML method
 *
 * @author Dayan Paez
 * @date   2010-03-16
 */
interface Xmlable {

  /**
   * Returns a textual representation of the XML
   *
   * @return String xml
   */
  public function toXML();

  /**
   * Echoes the XML representation to standard output
   *
   */
  public function printXML();
}

/**
 * Basic parent class for XML objects
 *
 * @author Dayan Paez
 * @date   2010-03-16
 */
class XElem implements Xmlable {

  protected $name;
  protected $child;
  protected $attrs;

  /**
   * Should never be empty, i.e. <td></td> vs. <td/>. Default false
   *
   * @param boolean
   */
  public $non_empty = false;

  /**
   * Creates the named XML object and optional attributes and children
   *
   * @param String $tag the tagname
   * @param Array<String,String> alist of attributes
   * @param Array<Xmlable> list of children
   * @throws InvalidArgumentException should something go wrong
   */
  public function __construct($tag, Array $attrs = array(), Array $child = array()) {
    $this->name = (string)$tag;
    $this->child = array();
    $this->attrs = array();

    foreach ($attrs as $key => $value)
      $this->set($key, $value);
    foreach ($child as $c)
      $this->add($c);
  }

  /**
   * Sets the given attribute
   *
   * @param String $key the key
   * @param String $val the value
   */
  public function set($key, $val) {
    $this->attrs[htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8')] = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
  }

  /**
   * Appends the given child
   *
   * @param Xmlable $child the child
   */
  public function add($child) {
    if (!($child instanceof Xmlable))
      throw new InvalidArgumentException("Child must be instance of Xmlable");
    $this->child[] = $child;
  }

  /**
   * Implementation of Xmlable function
   *
   * @return String the XML object
   */
  public function toXML() {
    $text = sprintf('<%s', $this->name);
    foreach ($this->attrs as $key => $value)
      $text .= sprintf(' %s="%s"', $key, $value);

    // check for empty
    if (count($this->child) == 0) {
      if ($this->non_empty)
        $text .= sprintf('></%s>', $this->name);
      else
        $text .= ' />';
      return $text;
    }
    $text .= '>';

    // children
    foreach ($this->child as $c)
      $text .= $c->toXML();

    $text .= sprintf('</%s>', $this->name);
    return $text;
  }

  /**
   * Implementation of Xmlable function
   *
   * @return String the XML object
   */
  public function printXML() {
    printf('<%s', $this->name);
    foreach ($this->attrs as $key => $value)
      echo sprintf(' %s="%s"', $key, $value);

    // check for empty
    if (count($this->child) == 0) {
      if ($this->non_empty)
        echo sprintf('></%s>', $this->name);
      else
        echo ' />';
      return;
    }
    echo '>';

    // children
    foreach ($this->child as $c)
      $c->printXML();

    printf('</%s>', $this->name);
  }

  /**
   * Retrieves an array of the children for this object.  This needs
   * to be overridden by those objects which delay their creation
   * until either <pre>toXML</pre> or <pre>printXML</pre> is called.
   *
   * @return Array<Xmlable> children
   */
  public function children() {
    return $this->child;
  }

  /**
   * Fetches the tag name for this element
   *
   * @return String the name
   */
  public function name() {
    return $this->name;
  }
}

/**
 * Xml header: like <?xml...?>
 *
 * @author Dayan Paez
 * @version 2010-04-30
 */
class XHeader implements Xmlable {

  private $tagname;
  private $attrs;

  /**
   * Create an Xml header with the given tagname and optional
   * attributes
   *
   * @param String $tag the tagname
   * @param Array<String,String> $attr the attributes
   */
  public function __construct($tag, $attrs) {
    $this->tagname = (string)$tag;
    if (empty($this->tagname))
      throw new InvalidArgumentException("Tagname must not be empty.");

    $this->attrs = array();
    foreach ($attrs as $key => $value) {
      $this->set($key, $value);
    }
  }

  /**
   * Sets the given attribute
   *
   * @param String $name the name
   * @param String $value the value
   */
  public function set($name, $value) {
    $this->attrs[(string)$name] = (string)$value;
  }

  /**
   * Gets String representation of XML
   *
   * @return String the XML
   */
  public function toXML() {
    $text = "";
    foreach ($this->attrs as $key => $value) {
      $text .= sprintf(' %s="%s"', $key, $value);
    }
    return sprintf('<?%s%s?>', $this->tagname, $text);
  }

  /**
   * Prints the header
   * @see toXML
   */
  public function printXML() {
    echo '<?'.$this->tagname;
    foreach ($this->attrs as $key => $value)
      printf(' %s="%s"', $key, $value);
    echo '?>';
  }
}

/**
 * A simple XML root node
 *
 * @author Dayan Paez
 * @version 2010-04-30
 */
class XDoc extends XElem {

  private $headers;

  /**
   * @var MIME the content type whose header to issue in printXML
   */
  protected $ct = 'application/xml';

  /**
   * Creates a new page with the given tag as root node
   *
   * @param String $tag the root tag name
   */
  public function __construct($tag, $attrs = array(), $children = array()) {
    parent::__construct($tag, $attrs, $children);
    $this->headers = array();
    $this->header(new XHeader("xml", array("version" =>"1.0",
                                           "encoding"=>"UTF-8")));
  }

  /**
   * Appends the given header tag
   *
   * @param XHeader $header the header to add
   */
  public function header(XHeader $header) {
    $this->headers[] = $header;
  }

  /**
   * Returns the <?xml?> declaration and the rest of the tree
   *
   * @return String the XML code
   */
  public function toXML() {
    $text = '';
    foreach ($this->headers as $header)
      $text .= sprintf("%s\n", $header->toXML());
    return $text . parent::toXML();
  }

  /**
   * Prints the page suitably for a browser, along with the correct
   * header (application/xml)
   *
   */
  public function printXML() {
    header("Content-type: " . $this->ct);
    foreach ($this->headers as $header)
      $header->printXML();
    parent::printXML();
  }
}

/**
 * Text node for XML. Prints itself exactly. NOTE: use this carefully,
 * as the text content is not escaped!
 *
 * @author Dayan Paez
 * @date   2010-04-22
 */
class XRawText implements Xmlable {

  private $value;

  /**
   * Creates a new raw text with the given value (default is empty)
   *
   * @param String $value
   */
  public function __construct($value = "") {
    $this->value = $value;
  }

  /**
   * Implements the Xmlable toXML method
   *
   * @return String the escaped XML
   */
  public function toXML() {
    return $this->value;
  }
  public function printXML() {
    echo $this->value;
  }
}

class XCData extends XRawText {
  public function __construct($value = "") {
    parent::__construct($value);
  }
  public function toXML() {
    return sprintf('<![CDATA[ %s ]]>', parent::toXML());
  }
  public function printXML() {
    echo '<![CDATA[ ';
    parent::printXML();
    echo ']]>';
  }
}

/**
 * Text node for XML. Prints itself, making sure to escape the content
 * appropriately.
 *
 * @author Dayan Paez
 * @date   2010-03-16
 */
class XText extends XRawText {

  /**
   * Creates a new text with the given value (defaults to empty)
   *
   * @param String $value
   */
  public function __construct($value = "") {
    parent::__construct(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
  }
}

/**
 * XHTML page/HTML. Prints the 1.0 Strict DOCTYPE by default. Of
 * course, it is still possible to write non-legal HTML even though
 * the XML will most likely be valid
 *
 * @author Dayan Paez
 * @version 2010-05-21
 */
class XPage extends XElem {

  private $head;
  private $body;

  // DOCTYPES
  const XHTML_1 = "XHTML-1.0";
  const HTML_4  = "HTML-4.01";
  const HTML_5  = "HTML-5";

  private $doctype = "XHTML-1.0";
  private $doctypes = array("XHTML-1.0" =>'<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
                            "HTML-4.01" =>'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">',
                            "HTML-5"    =>'<!DOCTYPE html>');

  /**
   * Creates a new page with the given title
   *
   * @param XRawText|String $title the title. If not a XRawText, will be cast into a XText
   */
  public function __construct($title) {
    parent::__construct("html");

    if (!($title instanceof XRawText))
      $title = new XText($title);
    $this->head = new XElem("head", array(),
                            array(new XElem("title", array(), array($title))));
    $this->body = new XElem("body");
    $this->body->non_empty = true;

    $this->add($this->head);
    $this->add($this->body);
  }

  /**
   * Fetches the given element of this page: either the head or the
   * body
   *
   * @param String $name either "head" or "body"
   */
  public function __get($name) {
    switch ($name) {
    case "head":
      return $this->head;
      break;

    case "body":
      return $this->body;
      break;

    default:
      throw new InvalidArgumentException("HTML page has no $name property.");
    }
  }

  /**
   * Overrides parent toXML method to include DOCTYPE declaration
   *
   * @return String the XML
   */
  public function toXML() {
    if ($this->doctype == XPage::XHTML_1) {
      $this->set("xmlns", "http://www.w3.org/1999/xhtml");
      $this->set("xml:lang", "en");
      $this->set("lang",     "en");
    }
    $text  = $this->doctypes[$this->doctype];
    return $text . "\n" . parent::toXML();
  }

  /**
   * Prints the page back to the browser, feeding the correct header
   * (application/xml) content type
   *
   */
  public function printXML() {
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml') !== false)
      header("Content-type: application/xhtml+xml;charset=UTF-8");
    else
      header("Content-type: text/html;charset=UTF-8");
    if ($this->doctype == XPage::XHTML_1) {
      $this->set("xmlns", "http://www.w3.org/1999/xhtml");
      $this->set("xml:lang", "en");
      $this->set("lang",     "en");
    }
    echo $this->doctypes[$this->doctype];
    parent::printXML();
  }

  /**
   * Set the doctype of this document to one of the class constants
   *
   * @param Const $doctype the doctype to use
   */
  public function setDoctype($doctype) {
    $this->doctype = $doctype;
  }
}

if (isset($argv[0]) && __FILE__ == $argv[0]) {
  $p = new XPage("My Title");
  $p->body->add(new XElem("p", array("class"=>"port"),
                          array(new XText("A statement."))));
  $p->printXML();
}
?>