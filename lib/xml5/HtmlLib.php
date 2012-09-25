<?php
/**
 * Set of classes which implement the basic HTML standard. These
 * classes extend XElem to provide shorthand equivalents when creating
 * HTML documents. Note that the classes don't validate for HTML
 * validity. You are free to nest block-level elements inside
 * inline-elements, etc.
 *
 * Some minor validation is done where indicated. For instance,
 * XOptionGroup will only accept XOption's as its children.
 *
 * @author Dayan Paez
 * @version 2010-07-26
 * @package html
 */

require_once(dirname(__FILE__).'/XmlLib.php');

/**
 * Super class for HTML tags to distinguish them from other possible
 * XML tags
 *
 * @author Dayan Paez
 * @version 2010-06-02
 */
class XAbstractHtml extends XElem {

  /**
   * Overrides XElem method to automatically wrap text in XText and
   * enable adding arrays at a time
   *
   * @param String|Xmlable|Array the item to add
   */
  public function add($item) {
    if ($item instanceof Xmlable)
      parent::add($item);
    else
      parent::add(new XText($item));
  }
}

/**
 * An anchor
 */
class XA extends XAbstractHtml {
  /**
   * Creates a new anchor
   *
   * @param String $href where the anchor links to
   * @param String|Xmlable the content of the anchor
   * @param Array $attrs the attributes
   */
  public function __construct($href, $link, Array $attrs = array()) {
    parent::__construct("a", $attrs, array($link));
    $this->set("href", $href);
  }
}

/**
 * Big text
 */
class XBig extends XAbstractHtml {
  /**
   * Creates a new big text, with the given content, and optinal attributes
   *
   */
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("big", $attrs);
    if (is_array($content)) {
      foreach ($content as $c)
        $this->add($c);
    }
    else
      $this->add($content);
  }
}

/**
 * Blockquotes
 *
 */
class XBlockQuote extends XAbstractHtml {
  public function __construct($text, Array $attrs = array()) {
    parent::__construct("blockquote", $attrs, array($text));
  }
}

/**
 * Line break
 *
 */
class XBr extends XAbstractHtml {
  public function __construct(Array $attrs = array()) {
    parent::__construct("br", $attrs);
  }
}

/**
 * Button, as in a form
 *
 */
class XButton extends XAbstractHtml {
  public function __construct($attrs = array(), $items = array()) {
    parent::__construct("button", $attrs, $items);
  }
}

/**
 * Table caption
 *
 */
class XCaption extends XAbstractHtml {
  public function __construct($text, Array $attrs = array()) {
    parent::__construct("caption", $attrs, array($text));
  }
}

/**
 * Division element
 *
 */
class XDiv extends XAbstractHtml {
  public function __construct(Array $attrs = array(), $elems = array()) {
    parent::__construct("div", $attrs, $elems);
    $this->non_empty = true;
  }
}

/**
 * Fieldset
 *
 */
class XFieldSet extends XAbstractHtml {
  /**
   * Creates a new fieldset with the given legend
   *
   * @param String $legend the legend to add
   */
  public function __construct($legend, Array $attrs = array(), Array $items = array()) {
    parent::__construct("fieldset", $attrs);
    $this->add(new XAbstractHtml("legend", array(), array($legend)));
    foreach ($items as $item)
      $this->add($item);
  }
}

/**
 * Input element
 *
 */
class XInput extends XAbstractHtml {
  /**
   * Creates a new input element with the given type, name, and value,
   * and optional attributes
   *
   * @param String $type the type of the input element
   * @param String $name the name
   * @param String $value the content of the value attribute
   * @param Array $attrs the optional attributes
   */
  public function __construct($type, $name, $value, Array $attrs = array()) {
    parent::__construct("input", $attrs);
    $this->set("type", $type);
    $this->set("name", $name);
    $this->set("value", $value);
  }
}

/**
 * A file input
 *
 */
class XFileInput extends XInput {
  public function __construct($name, Array $attrs = array()) {
    parent::__construct("file", $name, "", $attrs);
  }
}

/**
 * Form
 *
 */
class XForm extends XAbstractHtml {
  const POST = "post";
  const GET  = "get";

  /**
   * Creates a new form with the given action and method, and optional
   * attributes and content
   *
   * @param String $action the action
   * @param POST|GET $method the method
   * @param Array $attrs the attributes
   * @param Array $items the children
   */
  public function __construct($action, $method, Array $attrs = array(), $items = array()) {
    parent::__construct("form", $attrs);
    $this->set("action", $action);
    $this->set("method", $method);
    foreach ($items as $item)
      $this->add($item);
  }
}

/**
 * A form to be used when uploading files
 *
 */
class XFileForm extends XForm {
  public function __construct($action, Array $attrs = array(), $items = array()) {
    parent::__construct($action, XForm::POST, $attrs, $items);
    $this->set("enctype", "multipart/form-data");
  }
}

/**
 * Heading1
 *
 */
class XH1 extends XAbstractHtml {
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("h1", $attrs, array($content));
    $this->non_empty = true;
  }
}

/**
 * Heading2
 *
 */
class XH2 extends XAbstractHtml {
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("h2", $attrs, array($content));
    $this->non_empty = true;
  }
}

/**
 * Heading3
 *
 */
class XH3 extends XAbstractHtml {
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("h3", $attrs, array($content));
    $this->non_empty = true;
  }
}

/**
 * Heading4
 *
 */
class XH4 extends XAbstractHtml {
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("h4", $attrs, array($content));
    $this->non_empty = true;
  }
}

/**
 * Heading5
 *
 */
class XH5 extends XAbstractHtml {
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("h5", $attrs, array($content));
    $this->non_empty = true;
  }
}

/**
 * Heading6
 *
 */
class XH6 extends XAbstractHtml {
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("h6", $attrs, array($content));
    $this->non_empty = true;
  }
}

/**
 * Hidden form elements
 *
 */
class XHiddenInput extends XAbstractHtml {
  public function __construct($name, $value) {
    parent::__construct("input", array("type"=>"hidden", "name"=>$name, "value"=>$value));
  }
}

/**
 * Horizontal rule
 *
 */
class XHr extends XAbstractHtml {
  public function __construct(Array $attrs = array()) {
    parent::__construct("hr", $attrs);
  }
}

/**
 * Image
 *
 */
class XImg extends XAbstractHtml {
  /**
   * Creates a new image with the given source and alternate text, and
   * optional attributes
   *
   * @param String $src the source
   * @param String $alt the alternate text
   * @param Array $attrs the optional other attributes
   */
  public function __construct($src, $alt = "", Array $attrs = array()) {
    parent::__construct("img", $attrs);
    $this->set("src", $src);
    $this->set("alt", $alt);
  }
}

/**
 * Text input element
 *
 */
class XTextInput extends XInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct("text", $name, $value, $attrs);
  }
}

/**
 * Password input element
 *
 */
class XPasswordInput extends XInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct("password", $name, $value, $attrs);
  }
}

/**
 * A submit type button (stupid IE)
 *
 */
class XSubmitInput extends XInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct("submit", $name, $value, $attrs);
  }
}

/**
 * Radiobutton input element
 *
 */
class XRadioInput extends XInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct("radio", $name, $value, $attrs);
  }
}

/**
 * Checkbox input element
 *
 */
class XCheckboxInput extends XInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct("checkbox", $name, $value, $attrs);
  }
}

/**
 * A form label
 *
 */
class XLabel extends XAbstractHtml {
  /**
   * Creates a new label for the given item using the given content
   *
   * @param String $for the content of the "for" attribute
   * @param mixed $content the content
   * @param Array $attrs the optional attributes
   */
  public function __construct($for, $content, Array $attrs = array()) {
    parent::__construct("label", $attrs, array($content));
    $this->set("for", $for);
  }
}

/**
 * A list item
 *
 */
class XLi extends XAbstractHtml {
  /**
   * Creates a new list item
   *
   * @param mixed $content can be either a string, an Xmlable object,
   * or an array of either of the two
   */
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("li", $attrs);
    if (is_array($content)) {
      foreach ($content as $c)
        $this->add($c);
    }
    else
      $this->add($content);
  }
}

/**
 * Link
 *
 */
class XLink extends XAbstractHtml {
  /**
   * Creates a new link with the given type, rel, and href values
   *
   * @param Array $attrs other attributes
   */
  public function __construct(Array $attrs = array()) {
    parent::__construct("link", $attrs);
  }
}

/**
 * Convenience class for Linking stylesheets
 *
 */
class XLinkCSS extends XLink {
  /**
   * Specifiy the media type and the "rel" attribute (optional)
   */
  public function __construct($type, $href, $media, $rel = null) {
    parent::__construct(array("media" => $media, "type"=>$type, "href"=>$href));
    if ($rel !== null)
      $this->set("rel", $rel);
  }
}

/**
 * Ordered list
 *
 */
class XOl extends XAbstractHtml {
  /**
   * Creates a new list with the given elements
   *
   */
  public function __construct(Array $attrs = array(), Array $items = array()) {
    parent::__construct("ol", $attrs, $items);
    $this->non_empty = true;
  }
  public function add($item) {
    if (!($item instanceof XLi))
      throw new InvalidArgumentException("Child must be instance of XLi");
    parent::add($item);
  }
}

/**
 * Option group
 *
 */
class XOptionGroup extends XAbstractHtml {
  /**
   * Creates a new option group with the given label
   *
   * @param String $label the label for the option group
   * @param Array<Option> the array of options
   * @throws InvalidArgumentException if any of the options are invalid
   */
  public function __construct($label, Array $attrs = array(), Array $options = array()) {
    parent::__construct("optgroup", $attrs);
    $this->set("label", $label);
    foreach ($options as $opt) {
      if (!($opt instanceof XOption))
        throw new InvalidArgumentException("Option group must contain Option elements");
      $this->add($opt);
    }
  }
}

/**
 * Option in select statements
 *
 */
class XOption extends XAbstractHtml {
  /**
   * Creates a new option element with the given value and content
   *
   * @param String $value the value
   * @param String $content the content (can technically be Xmlable)
   */
  public function __construct($value, Array $attrs = array(), $content = "") {
    parent::__construct("option", $attrs, array($content));
    $this->set("value", $value);
  }
}

/**
 * An awesome paragraph element. Unlike most (all?) other elements,
 * the argument can either be a Xmlable, a String, or a straight-up
 * array in order to facilitate creating paragraphs on the fly
 *
 */
class XP extends XAbstractHtml {
  /**
   * Creates a new paragraph.
   *
   * @param Array $attrs the attributes (NOT optional!)
   * @param String|Xmlable|Array the content
   */
  public function __construct(Array $attrs = array(), $content = "") {
    parent::__construct("p", $attrs);
    if (is_array($content)) {
      foreach ($content as $c)
        $this->add($c);
    }
    else
      $this->add($content);
  }
}

/**
 * Preformatted text
 *
 */
class XPre extends XAbstractHtml {
  /**
   * Preformatted text
   *
   * @param String $content the content
   */
  public function __construct($content) {
    parent::__construct("pre", array(), array(new XText($content)));
    $this->non_empty = true; // implied in constructor
  }
}

/**
 * A simple Script. The content is NOT HTML-encoded
 *
 */
class XScript extends XAbstractHtml {
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
      $this->set("src", $src);
    if ($text !== null)
      $this->add(new XRawText($text));
  }
}

/**
 * NOSCRIPT tag
 *
 */
class XNoScript extends XAbstractHtml {
  /**
   * Creates a new noscript element with the given content and attributes.
   *
   * @param Array|Xmlable|String $content the content
   * @param Array $attrs the attributes
   */
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("noscript", $attrs);
    if (is_array($content)) {
      foreach ($content as $cont)
        $this->add($cont);
    }
    else
      $this->add($content);
  }
}

/**
 * A drop-down box (select element). Allows only XOption's and
 * XOptionGroup's
 *
 */
class XSelect extends XAbstractHtml {
  /**
   * Creates a new select group with the given name
   *
   * @param String $name the name of the select group
   * @param Array $attrs the other attributes
   * @param Array<XOption|XOptionGroup> the options
   */
  public function __construct($name, Array $attrs = array(), Array $items = array()) {
    parent::__construct("select", $attrs, $items);
    $this->non_empty = true;
    $this->set("name", $name);
  }
  /**
   * @param XOption|XOptionGroup the element to add
   * @throws InvalidArgumentException if bogus element
   */
  public function add($elem) {
    if (!($elem instanceof XOption) && !($elem instanceof XOptionGroup))
      throw new InvalidArgumentException("XSelect must have XOption|XOptionGroup as children");
    parent::add($elem);
  }

  /**
   * Creates a XSelect element with given name and options.
   *
   * The given options must be a map of option value => labels. The
   * optional $chosen should be an array or a string of the values to
   * automatically mark as 'selected'.
   *
   * The $opts argument could be a nested list to designate
   * optgroups. Thus, given a "simple map":
   *
   * {us: "USA", mex: "Mexico", sp: "Spain"}
   *
   * The result is a drop down list with three options:
   *
   *   - us:  USA
   *   - mex: Mexico
   *   - sp:  Spain
   *
   * However, given the following nested map:
   *
   * {"North America": {us: "USA", mex: "Mexico"}, sp: "Spain"}
   *
   * The output would be:
   *
   *    - North America
   *        - us:  USA
   *        - mex: Mexico
   *    - sp: Spain
   *
   * @param String $name the name of the select element
   * @param Array:String $opts a map of option values and labels
   * @param Array|String $chosen the list or item to select
   * @param Array $attrs the optional attributes to add
   */
  public static function fromArray($name, Array $opts, $chosen = null, Array $attrs = array(), $strict = false) {
    if (!is_array($chosen))
      $chosen = array($chosen);
    $sel = new XSelect($name, $attrs);
    foreach ($opts as $k => $v) {
      if (is_array($v)) {
        $sel->add($grp = new XOptionGroup($k));
        foreach ($v as $kk => $vv) {
          $grp->add($opt = new XOption($kk, array(), $vv));
          if (in_array($kk, $chosen))
            $opt->set('selected', 'selected');
        }
      }
      else {
        $sel->add($opt = new XOption($k, array(), $v));
        if (in_array($k, $chosen, $strict))
          $opt->set('selected', 'selected');
      }
    }
    return $sel;
  }
}

/**
 * Multiple selects
 *
 * @author Dayan Paez
 * @version 2011-12-31
 */
class XSelectM extends XSelect {
  public function __construct($name, Array $attrs = array(), Array $items = array()) {
    parent::__construct($name, $attrs, $items);
    $this->set('multiple', 'multiple');
  }

  public static function fromArray($name, Array $opts, $chosen = null, Array $attrs = array(), $strict = false) {
    $sel = XSelect::fromArray($name, $opts, $chosen, $attrs, $strict);
    $sel->set('multiple', 'multiple');
    return $sel;
  }
}

/**
 * Span
 *
 */
class XSpan extends XAbstractHtml {
  /**
   * Creates a new span with the given content and optional attributes
   *
   */
  public function __construct($content, Array $attrs = array()) {
    parent::__construct("span", $attrs, array($content));
  }
}

/**
 * Strong element
 *
 */
class XStrong extends XAbstractHtml {
  public function __construct($content, $attrs = array()) {
    parent::__construct("strong", $attrs, array($content));
  }
}

/**
 * Emphasis element
 *
 */
class XEm extends XAbstractHtml {
  public function __construct($content, $attrs = array()) {
    parent::__construct("em", $attrs, array($content));
  }
}

/**
 * Indicates an instance of a computer code variable or program argument.
 */
class XVar extends XAbstractHtml {
  public function __construct($content, $attrs = array()) {
    parent::__construct('var', $attrs, array($content));
  }
}

/**
 * Strikethrough (deleted) element
 *
 */
class XDel extends XAbstractHtml {
  public function __construct($content, $attrs = array()) {
    parent::__construct("del", $attrs, array($content));
  }
}

/**
 * Style element. Contents are processed using XRawText.
 *
 */
class XStyle extends XAbstractHtml {
  /**
   * Creates a new style element
   *
   * @param String $type the type attribute
   * @param String $content the content
   */
  public function __construct($type, $content) {
    parent::__construct("style", array("type"=>$type), array(new XRawText($content)));
  }
}

/**
 * Table element. Provides for putting captions at the very top of the
 * children list. Chilren elements must be one of
 * XCaption|XTR|XTHead|XTBody|XTFoot.
 *
 */
class XTable extends XAbstractHtml {
  private $caption;

  /**
   * Creates a new table with the given attributes and children
   *
   * @param Array $attrs the map of attributes
   * @param Array $items the children, must be valid type
   */
  public function __construct(Array $attrs = array(), Array $items = array()) {
    parent::__construct("table", $attrs);
    foreach ($items as $item)
      $this->add($item);
  }

  /**
   * Overrides XElem
   *
   * @throws InvalidArgumentException if the element is not permitted
   */
  public function add($elem) {
    if ($elem instanceof XCaption) {
      $this->caption = $elem;
      return;
    }
    if ($elem instanceof XTR    ||
        $elem instanceof XTHead ||
        $elem instanceof XTBody ||
        $elem instanceof XTFoot) {
      parent::add($elem);
      return;
    }
    throw new InvalidArgumentException("XTable children must be one of XHead|XBody|XTR");
  }
  public function toXML() {
    $e = new XElem("table", $this->attrs);
    if ($this->caption !== null)    $e->add($this->caption);
    foreach ($this->child as $c) $e->add($c);
    return $e->toXML();
  }
  public function printXML() {
    $e = new XElem("table", $this->attrs);
    if ($this->caption !== null)    $e->add($this->caption);
    foreach ($this->child as $c) $e->add($c);
    $e->printXML();
  }
  public function children() {
    $a = $this->child;
    if ($this->caption !== null)
      array_unshift($a, $this->caption);
    return $a;
  }

  /**
   * Creates a new table from the given map(s).
   *
   * @param Array $rows a list of lists, i.e. rows of cells
   * @param Array $headers optional. If provided, the rows for the head
   * @param Array $attrs the attribute map, as usual
   */
  public static function fromArray(Array $rows, Array $headers = array(), Array $attrs = array()) {
    $t = new XTable($attrs);
    if (count($headers) > 0) {
      $t->add($h = new XTHead());
      foreach ($headers as $header) {
        $h->add($r = new XTR());
        foreach ($header as $c)
          $r->add(new XTH(array(), $c));
      }
    }
    $t->add($h = new XTBody());
    foreach ($rows as $header) {
      $h->add($r = new XTR());
      foreach ($header as $c)
        $r->add(new XTD(array(), $c));
    }
    return $t;
  }
}

/**
 * tbody element
 *
 */
class XTBody extends XAbstractHtml {
  public function __construct(Array $attrs = array(), Array $items = array()) {
    parent::__construct("tbody", $attrs, $items);
  }
  /**
   * Adds XTR element
   *
   */
  public function add($elem) {
    if (!($elem instanceof XTR))
      throw new InvalidArgumentException("Child must be instance of XTR");
    parent::add($elem);
  }
}

/**
 * table data element
 *
 */
class XTD extends XAbstractHtml {
  public function __construct(Array $attrs = array(), $item = "") {
    parent::__construct("td", $attrs);
    $this->non_empty = true;
    if (is_array($item)) {
      foreach ($item as $i)
        $this->add($i);
    }
    else
      $this->add($item);
  }
}

/**
 * Textarea element
 *
 */
class XTextArea extends XAbstractHtml {
  /**
   * Creates a new textarea element with the given name
   *
   * @param String $name the name attribute
   * @param String $content the content of the element
   */
  public function __construct($name, $content, Array $attrs = array()) {
    parent::__construct("textarea", array("rows"=>6, "cols"=>40), array(new XText($content)));
    $this->non_empty = true;
    $this->single_line = true;
    foreach ($attrs as $key => $value)
      $this->set($key, $value);
    $this->set("name", $name);
  }
}

/**
 * Table foot
 *
 * @see XTBody
 */
class XTFoot extends XAbstractHtml {
  public function __construct(Array $attrs = array(), Array $items = array()) {
    parent::__construct("tfoot", $attrs, $items);
  }
  /**
   * Adds XTR element
   *
   */
  public function add($elem) {
    if (!($elem instanceof XTR))
      throw new InvalidArgumentException("Child must be instance of XTR");
    parent::add($elem);
  }
}

/**
 * Table header element
 *
 * @see XTD
 */
class XTH extends XAbstractHtml {
  public function __construct(Array $attrs = array(), $item = "") {
    parent::__construct("th", $attrs);
    $this->non_empty = true;
    if (is_array($item)) {
      foreach ($item as $i)
        $this->add($i);
    }
    else
      $this->add($item);
  }
}

/**
 * Table head (thead)
 *
 * @see XTBody
 */
class XTHead extends XAbstractHtml {
  public function __construct(Array $attrs = array(), Array $items = array()) {
    parent::__construct("thead", $attrs, $items);
  }
  /**
   * Adds XTR element
   *
   * @param XTR|XRawText the latter was added so that client users
   * could dynamically build up rows by replacing <td> with <th> for
   * instance. See the DPEditor for inspiration. If using XRawText,
   * make sure you know what you are doing!
   *
   * @throws InvalidArgumentException if invalid children provided
   */
  public function add($elem) {
    if (!($elem instanceof XTR || $elem instanceof XRawText))
      throw new InvalidArgumentException("Child must be instance of XTR");
    parent::add($elem);
  }
}

/**
 * Title element
 *
 */
class XTitle extends XAbstractHtml {
  /**
   * @param String $content the content
   */
  public function __construct($content) {
    parent::__construct("title", array(), array(new XText($content)));
  }
}

/**
 * Table row. Allows only XTD|XTH elements as children
 *
 */
class XTR extends XAbstractHtml {
  public function __construct(Array $attrs = array(), Array $cells = array()) {
    parent::__construct("tr", $attrs, $cells);
  }
  /**
   * @param XTD|XTH|XRawText the latter was added so that client users
   * could dynamically build up rows by replacing <td> with <th> for
   * instance. See the DPEditor for inspiration. If using XRawText,
   * make sure you know what you are doing!
   *
   * @throws InvalidArgumentException if the parameter is not of the
   * right type, 
   */
  public function add($cell) {
    if ($cell instanceof XTD || $cell instanceof XTH || $cell instanceof XRawText) {
      parent::add($cell);
      return;
    }
    throw new InvalidArgumentException("Row elements must be one of XTH|XTD");
  }
}

/**
 * Unordered list
 *
 * @see XOl
 */
class XUl extends XAbstractHtml {
  /**
   * Creates a new list with the given elements
   *
   */
  public function __construct(Array $attrs = array(), Array $items = array()) {
    parent::__construct("ul", $attrs, $items);
    $this->non_empty = true;
  }
  public function add($item) {
    if (!($item instanceof XLi))
      throw new InvalidArgumentException("Child must be instance of XLi");
    parent::add($item);
  }
}

// ------------------------------------------------------------
// Quick tables
// ------------------------------------------------------------

/**
 * Generates a simple table with one THEAD and one TBODY
 *
 * @author Dayan Paez
 * @version 2010-08-16
 */
class XQuickTable extends XTable {

  protected $thead;
  protected $tbody;

  /**
   * Creates a new table with the attributes given as the first
   * argument and the headers in the second (optional) argument. The
   * second argument should contain Xmlable elements or Strings for
   * the header title.
   *
   * @param Array $attrs the associative array of the attributes
   * @param Array<Xmlable|String> $headers the elements which comprise
   * the header of this table (will be wrapped in a XTHead element)
   */
  public function __construct(Array $attrs = array(), Array $headers = array()) {
    parent::__construct($attrs);
    if (count($headers) > 0) {
      $this->add($this->thead = new XTHead(array(), array($tr = new XTR())));
      foreach ($headers as $head) {
        $tr->add(new XTH(array(), $head));
      }
    }
    $this->add($this->tbody = new XTBody());
  }

  /**
   * Adds a new row with each cell's content as given in the first argument
   *
   * @param Array<Xmlable|String> $cells the content to put in each cell
   * @param Array $attrs the (optional) attributes for the new row
   */
  public function addRow(Array $cells, Array $attrs = array()) {
    $this->tbody->add($tr = new XTR($attrs));
    foreach ($cells as $cell) {
      if ($cell instanceof XTD || $cell instanceof XTH)
        $tr->add($cell);
      else
        $tr->add(new XTD(array(), $cell));
    }
  }

  /**
   * Use this to gain read access to the thead and tbody elements of
   * this class.
   *
   * @param thead|tbody $field the field to get, should be "thead" or "tbody"
   * @return XAbstractHtml the appropriate element
   * @throws InvalidArgumentException if attempting to retrieve
   * invalid object
   */
  public function __get($field) {
    switch ($field) {
    case "thead": return $this->thead;
    case "tbody": return $this->tbody;
    default: throw new InvalidArgumentException("No such property in XQuickTable ($field).");
    }
  }
}

/**
 * An embedded object
 *
 */
class XObject extends XAbstractHtml {
  public function __construct($data, $type, $width, $height, Array $attrs = array(), Array $children = array()) {
    parent::__construct("object", $attrs, $children);
    $this->set("data", $data);
    $this->set("type", $type);
    $this->set("width",  $width);
    $this->set("height", $height);
  }
}

/**
 * Parameter for embedded objects
 *
 * @author Dayan Paez
 * @version 2012-09-12
 */
class XParam extends XAbstractHtml {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct("param", $attrs);
    $this->set('name', (string)$name);
    $this->set('value', (string)$value);
  }
}

/**
 * Base element for pages
 *
 * @author Dayan Paez
 * @version 2010-11-03
 */
class XBase extends XAbstractHtml {
  /**
   * Creates a new base element, to be added to the head of an XPage.
   *
   * @param String $href the reference of the page
   */
  public function __construct($href) {
    parent::__construct("base", array("href" => $href));
  }
}

/**
 * Address field, alas
 *
 * @author Dayan Paez
 * @version 2011-03-31
 */
class XAddress extends XAbstractHtml {
  /**
   * Creates a new address with the given text or other inline element
   *
   * @param Array $attrs optional attributes
   * @param Array:String|Array:XAbstractHtml content optional
   */
  public function __construct(Array $attrs = array(), Array $content = array()) {
    parent::__construct('address', $attrs, $content);
  }
}

/**
 * Meta tag, takes in a name and content, no other attributes, no children
 *
 * @author Dayan Paez
 * @version 2011-04-11
 */
class XMeta extends XAbstractHtml {
  public function __construct($name, $content) {
    parent::__construct('meta', array('name'=>$name, 'content'=>$content));
  }
}

class XMetaHTTP extends XAbstractHtml {
  public function __construct($http_equiv, $content) {
    parent::__construct('meta', array('http-equiv' => $http_equiv, 'content' => $content));
  }
}
?>