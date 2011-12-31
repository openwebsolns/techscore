<?php
/**
 * This file is part of TechScore
 */

require_once(dirname(__FILE__).'/HtmlLib.php');

/**
 * A div with class Port and an H3 heading
 *
 * @author Dayan Paez
 * @version 2011-03-09
 */
class XPort extends XDiv {

  /**
   * Create a port with the given title
   *
   * @param String $title the title
   */
  public function __construct($title, Array $children = array(), Array $attrs = array()) {
    parent::__construct($attrs, array($h3 = new XH3("")));
    if (is_array($title)) {
      foreach ($title as $item)
	$h3->add($item);
    }
    else
      $h3->add($title);
    $this->set('class', 'port');
    foreach ($children as $child)
      $this->add($child);
  }
}

/**
 * A span of class 'message'
 *
 */
class XMessage extends XSpan {
  /**
   * Creates a new such message
   *
   * @see XSpan::__construct
   */
  public function __construct($content, Array $attrs = array()) {
    parent::__construct($content, $attrs);
    $this->set('class', 'message');
  }
}

/**
 * Encapsulates a page title (using h2)
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class XPageTitle extends XH2 {
  public function __construct($title = "", Array $attrs = array()) {
    parent::__construct($title, $attrs);
  }
}

/**
 * Heading (implemented as an H4)
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class XHeading extends XH4 {
  /**
   * Creates a new heading suitable for a port
   */
  public function __construct($title = "", Array $attrs = array()) {
    parent::__construct($title, $attrs);
  }
}

/**
 * Link for the user manual. Implemented as a 'span'
 *
 */
class XHLink extends XA {
  public function __construct($href) {
    parent::__construct("../help/html/$href", "[?]", array("target"=>"tshelp", 'class'=>'hlink'));
  }
}

/**
 * Encapsulates a form entry: a prefix label and some input element
 * (implemented here as a 'div')
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class FItem extends XDiv {
  /**
   * Creates a new form item with given prefix and form input
   *
   * @param mixed $message any possible child of XDiv
   * @param mixed $form_input ditto.
   */
  public function __construct($message, $form_input) {
    parent::__construct(array('class'=>'form_entry'));
    if (is_string($message))
      $this->add(new XSpan($message, array('class'=>'form_h')));
    else
      $this->add($message);
    $this->add($form_input);
  }
}

/**
 * Submit button for accessibility for non-javascript pages
 * Automatically adds class "accessible" to submit button
 */
class XSubmitAccessible extends XSubmitInput {
  public function __construct($name, $value) {
    parent::__construct($name, $value, array("class"=>"accessible"));
  }
}

/**
 * Encapsulates a Reset button
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class XReset extends XInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct('reset', $name, $value, $attrs);
  }
}

/**
 * Port for logical separation of information in a page (as a div)
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class Port extends XDiv {
  private $title;
  /**
   * Creates a new port with the given title (h3)
   *
   * @param String $title the title
   * @param Array $value the list of values
   * @param Array $attrs the attribute map
   */
  public function __construct($title, Array $value = array(), Array $attrs = array()) {
    parent::__construct(array(), $attrs);
    $this->set('class', 'port');
    
    $this->title = new XH3($title);
    $this->add($this->title);
    foreach ($value as $v)
      $this->add($v);
  }
  public function addHelp($href) {
    $this->title->add(new XHLink($href));
  }
}

/**
 * XSelect has its arguments in a weird order. This fixes that.
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class Select extends XSelect {
  public function __construct($name, Array $items = array(), Array $attrs = array()) {
    parent::__construct($name, $attrs, $items);
  }
}

/**
 * XOptionGroup has its arguments in a weird order. This fixes that.
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class FOptionGroup extends XOptionGroup {
  public function __construct($label, Array $options = array(), Array $attrs = array()) {
    parent::__construct($label, $attrs, $options);
  }
}

/**
 * XOption has its arguments in a weird order. This fixes that.
 *
 * @author Dayan Paez
 * @version 2011-12-30
 */
class FOption extends XOption {
  public function __construct($value, $content = "", Array $attrs = array()) {
    parent::__construct($value, $attrs, (string)$content);
  }
}
?>