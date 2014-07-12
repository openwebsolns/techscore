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
 * Interface for serializing to a resource
 *
 */
interface Writeable {
  public function write($resource);
}

/**
 * Interface for producing JSON output
 *
 */
interface Jsonable {
  public function printJSON();
  public function toJSON();
}

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
class XElem implements Writeable, Xmlable, Jsonable {

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
    $this->attrs[(string)$key] = (string)$val;
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
    $res = fopen('php://temp', 'rw');
    $this->write($res);
    fseek($res, 0);
    $str = stream_get_contents($res);
    fclose($res);
    return $str;
  }

  /**
   * Implementation of Xmlable
   *
   * Forwards the request to write
   */
  public function printXML() {
    $res = fopen('php://output', 'w');
    $this->write($res);
    fclose($res);
  }

  /**
   * Implementation of Xmlable function
   *
   */
  public function write($resource) {
    fwrite($resource, '<' . $this->name);
    foreach ($this->attrs as $key => $val)
      fwrite($resource,
             sprintf(' %s="%s"',
                     htmlspecialchars($key, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8'),
                     htmlspecialchars($val, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8')));

    // check for empty
    if (count($this->child) == 0) {
      if ($this->non_empty)
        fwrite($resource, '></' . $this->name . '>');
      else
        fwrite($resource, ' />');
      return;
    }
    fwrite($resource, '>');

    // children
    foreach ($this->child as $c)
      $c->write($resource);

    fwrite($resource, '</' . $this->name  . '>');
  }

  /**
   * Implementation of Jsonable
   *
   * @return String the JSON string
   */
  public function toJSON() {
    $text = sprintf('{"%s":{"attrs":{', $this->name);
    $index = 0;
    foreach ($this->attrs as $key => $value) {
      if ($index++ > 0)
        $text .= ',';
      $text .= sprintf('"%s":"%s"', str_replace('"', '\\"', $key), str_replace('"', '\\"', $value));
    }
    $text .= '},"elems":[';

    $index = 0;
    foreach ($this->child as $c) {
      if ($index++ > 0)
        $text .= ',';
      $text .= $c->toJSON();
    }
    $text .= ']}}';
    return $text;
  }

  /**
   * Implementation of Jsonable
   *
   * Prints the JSON string
   */
  public function printJSON() {
    printf('{"%s":{"attrs":{', $this->name);
    $index = 0;
    foreach ($this->attrs as $key => $value) {
      if ($index++ > 0)
        echo ',';
      printf('"%s":"%s"', str_replace('"', '\\"', $key), str_replace('"', '\\"', $value));
    }
    echo '},"elems":[';

    $index = 0;
    foreach ($this->child as $c) {
      if ($index++ > 0)
        echo ',';
      $c->printJSON();
    }
    echo ']}}';
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
class XHeader implements Writeable, Xmlable {

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
   * Implementation of Xmlable function
   *
   * @return String the XML object
   */
  public function toXML() {
    $res = fopen('php://temp', 'rw');
    $this->write($res);
    fseek($res, 0);
    $str = stream_get_contents($res);
    fclose($res);
    return $str;
  }

  /**
   * Implementation of Xmlable
   *
   * Forwards the request to write
   */
  public function printXML() {
    $res = fopen('php://output', 'w');
    $this->write($res);
    fclose($res);
  }

  /**
   * Prints the header
   * @see toXML
   */
  public function write($resource) {
    fwrite($resource, '<?'.$this->tagname);
    foreach ($this->attrs as $key => $val) {
      fwrite($resource, ' ' . htmlspecialchars($key, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8'));
      fwrite($resource, '="'. htmlspecialchars($val, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8') . '"');
    }
    fwrite($resource, '?>');
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
  public function __construct($tag, $attrs = array(), $children = array(), $inc_header = true) {
    parent::__construct($tag, $attrs, $children);
    $this->headers = array();
    $this->header(new XHeader("xml", array("version" =>"1.0", "encoding"=>"UTF-8")));
    $this->setIncludeHeaders($inc_header);
  }

  /**
   * Appends the given header tag
   *
   * @param XHeader $header the header to add
   */
  public function header(XHeader $header) {
    $this->headers[] = $header;
  }

  private $inc_headers = true;

  /**
   * True to include XML headers and Content-type when printing XML
   *
   * @param boolean $flag true (default) to include headers
   */
  public function setIncludeHeaders($flag = true) {
    $this->inc_headers = ($flag != false);
  }

  /**
   * Prints the page suitably for a browser, along with the correct
   * header (application/xml)
   *
   */
  public function printXML() {
    if (!headers_sent() && $this->inc_headers)
      header("Content-type: " . $this->ct);
    parent::printXML();
  }

  public function write($resource) {
    if ($this->inc_headers) {
      foreach ($this->headers as $header)
        $header->write($resource);
    }
    parent::write($resource);
  }
}

/**
 * Text node for XML. Prints itself exactly. NOTE: use this carefully,
 * as the text content is not escaped!
 *
 * @author Dayan Paez
 * @date   2010-04-22
 */
class XRawText implements Writeable, Xmlable {

  protected $value;

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
  public function write($resource) {
    fwrite($resource, $this->value);
  }

  /**
   * Returns value within double quotes, escaping other double quotes
   *
   */
  public function toJSON() {
    return '"' . str_replace('"', '\\"', $this->value) . '"';
  }
  public function printJSON() {
    echo '"' . str_replace('"', '\\"', $this->value) . '"';
  }
}

class XCData extends XRawText {
  public function write($resource) {
    fwrite($resource, '<![CDATA[ ');
    parent::write($resource);
    fwrite($resource, ']]>');
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
  public function write($resource) {
    fwrite($resource, htmlspecialchars((string)$this->value, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8'));
  }
}

/**
 * XHTML page/HTML. Prints the 1.0 Strict DOCTYPE by default. Of
 * course, it is still possible to write non-legal HTML even though
 * the XML will most likely be valid.
 *
 * XHTML-1.0 doctype no longer prints the XML declaration
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
  private $doctypes = array("XHTML-1.0" =>'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
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
   * Prints the page back to the browser, feeding the correct header
   * (application/xml) content type
   *
   */
  public function printXML() {
    if (!headers_sent()) {
      if ($this->doctype == XPage::XHTML_1 && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml') !== false)
        header("Content-type: application/xhtml+xml;charset=UTF-8");
      else
        header("Content-type: text/html;charset=UTF-8");
    }
    parent::printXML();
  }

  public function write($resource) {
    if ($this->doctype == XPage::XHTML_1) {
      $this->set("xmlns", "http://www.w3.org/1999/xhtml");
      $this->set("xml:lang", "en");
      $this->set("lang",     "en");
    }
    fwrite($resource, $this->doctypes[$this->doctype]);
    parent::write($resource);
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
?>