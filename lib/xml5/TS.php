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

  const CLASSNAME = 'port';

  private $title;

  /**
   * Create a port with the given title
   *
   * @param String $title the title
   */
  public function __construct($title, Array $children = array(), Array $attrs = array()) {
    parent::__construct($attrs);
    $this->add($this->title = new XH3(""));
    if (is_array($title)) {
      foreach ($title as $item)
        $this->title->add($item);
    }
    else
      $this->title->add($title);
    $this->set('class', self::CLASSNAME);
    foreach ($children as $child)
      $this->add($child);
  }
  public function addHelp($href) {
    $root = DB::g(STN::HELP_HOME);
    if ($root !== null)
      $this->title->add(new XHLink($root . $href));
  }
}

/**
 * Adds extra class "collapsible", which indicates that it should be
 * initially rendered "closed", or collapsed
 *
 * @author Dayan Paez
 * @version 2014-04-06
 */
class XCollapsiblePort extends XPort {

  const COLLAPSIBLE_CLASSNAME = 'collapsible';

  public function __construct($title, Array $children = array(), Array $attrs = array()) {
    parent::__construct($title, $children, $attrs);
    $this->set('class', array(self::CLASSNAME, self::COLLAPSIBLE_CLASSNAME));
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
 * An explanatory note
 *
 * @author Dayan Paez
 * @version 2014-03-15
 */
class XNote extends XDiv {
  public static $counter = 0;

  /**
   * Creates a new note
   *
   */
  public function __construct($content) {
    parent::__construct(array('class'=>'note'), array(
                          new XA("#n" . self::$counter, "?", array('class'=>'note-link', 'title'=>$content)),
                          new XDiv(array('class'=>'note-screen', 'id' => "n" . self::$counter),
                                   array(
                                     new XDiv(array('class'=>'note-body'), array(
                                                new XA("#_", "X", array('class'=>'note-close')),
                                                $content))))));
    self::$counter++;
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
    parent::__construct($href, "[?]", array("onclick"=>"this.target=\"help\"", 'class'=>'hlink'));
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
  public function __construct($message, $form_input, $expl = null) {
    parent::__construct(array('class'=>'form_entry'));
    if (is_string($message))
      $this->add(new XSpan($message, array('class'=>'form_h')));
    else
      $this->add($message);
    $this->add($form_input);
    if ($expl !== null)
      $this->add(new XNote($expl));
  }
}

/**
 * A required form entry
 *
 * @author Dayan Paez
 * @version 2014-03-08
 */
class FReqItem extends FItem {
  public function __construct($message, $form_input, $expl = null) {
    parent::__construct($message, $form_input, $expl);
    $this->set('class', 'form_entry required');
    if ($form_input instanceof XInput
        || $form_input instanceof XTextArea
	|| $form_input instanceof FCheckbox
        || $form_input instanceof XSelect) {
      $form_input->set('required', 'required');
    }
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
 * Submit button for delete actions
 *
 * @author Dayan Paez
 * @version 2014-03-09
 */
class XSubmitDelete extends XSubmitInput {

  const CLASSNAME = 'delete-button';

  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct($name, $value, $attrs);
    $this->set('class', self::CLASSNAME);
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

/**
 * Link element for CSS: the right way
 *
 * @author Dayan Paez
 * @version 2012-01-01
 */
class LinkCSS extends XLinkCSS {
  public function __construct($href, $media = 'screen', $rel = 'stylesheet') {
    parent::__construct('text/css', $href, $media, $rel);
  }
}

/**
 * XP wrapper around a submit input
 *
 * @author Dayan Paez
 * @version 2012-01-26
 */
class XSubmitP extends XP {

  const CLASSNAME = 'p-submit';

  /**
   * Creates a new paragraph wrapping a submit input
   *
   */
  public function __construct($name, $value, Array $attrs = array(), $delete = false) {
    parent::__construct(array('class' => self::CLASSNAME));
    if ($delete !== false)
      $this->add(new XSubmitDelete($name, $value, $attrs));
    else
      $this->add(new XSubmitInput($name, $value, $attrs));
  }
}

/**
 * Input field appropriate for races from a standard race (up to 4 divisions)
 *
 * @author Dayan Paez
 * @version 2014-03-09
 */
class XRaceInput extends XTextInput {
  public function __construct($name, $value, Array $attrs = array()) {
    parent::__construct($name, $value, $attrs);
    $this->set('pattern', '^[0-9]+[A-Da-d]$');
    $this->set('class', 'race-input');
  }
}

/**
 * Input field appropriate for sail values
 *
 * @author Dayan Paez
 * @version 2014-03-29
 */
class XSailInput extends XTextInput {
  public function __construct($name, $value, $required = true, Array $attrs = array()) {
    parent::__construct($name, $value, $attrs);
    if ($required !== false)
      $this->set('required', 'required');
    $this->set('class', 'sail-input');
    $this->set('size', 4);
    $this->set('maxlength', 15);
  }
}

/**
 * Input field to choose sail colors
 *
 */
class XSailColorInput extends XSelect {

  public static $COLORS = array(
    "#eee" => "White",
    "#ccc" => "Light Grey",
    "#666" => "Grey",
    "#000" => "Black",
    "#884B2A" => "Brown",
    "#f80" => "Orange",
    "#f00" => "Red",
    "#fcc" => "Pink",
    "#add8e6" => "Light Blue",
    "#00f" => "Blue",
    "#808" => "Purple",
    "#0f0" => "Lime Green",
    "#080" => "Green",
    "#ff0" => "Yellow"
  );

  public function __construct($name, $chosen = null, Array $attrs = array()) {
    parent::__construct($name, $attrs);
    $this->set('class', 'color-chooser');
    $this->add(new XOption("", array(), ""));
    foreach (self::$COLORS as $code => $title) {
      $attrs = array('style'=>sprintf('background:%1$s;color:%1$s;', $code));
      $this->add($opt = new XOption($code, $attrs, $title));
      if ($code == $chosen)
        $opt->set('selected', 'selected');
    }
  }
}

/**
 * Sail and color input combo
 *
 * @author Dayan Paez
 * @version 2014-04-07
 */
class XSailCombo extends XSpan {
  public function __construct($sail_name, $color_name, $sail_chosen = null, $color_chosen = null) {
    parent::__construct("", array('class'=>'sail-combo'));
    $this->add(new XSailInput($sail_name, $sail_chosen));
    $this->add(new XSailColorInput($color_name, $color_chosen));
  }
}

/**
 * Input field appropriate for races from combined/team racing
 *
 * @author Dayan Paez
 * @version 2014-03-09
 */
class XCombinedRaceInput extends XNumberInput {
  public function __construct($name, $value, $max = null, Array $attrs = array()) {
    parent::__construct($name, $value, 1, $max, 1, $attrs);
    $this->set('class', 'race-input');
  }
}

/**
 * Input element for a range of numbers
 *
 * @author Dayan Paez
 * @version 2014-04-26
 */
class XRangeInput extends XTextInput {
  public function __construct($name, $value, Array $possible_values = array(), Array $attrs = array()) {
    parent::__construct($name, $value, $attrs);
    $this->set('pattern', '^\s*([0-9]+\s*([-,]\s*[0-9]+)*)+\s*$');
    if (!isset($attrs['placeholder']) && count($possible_values) > 0)
      $this->set('placeholder', "E.g. " . DB::makeRange($possible_values));
  }
}

/**
 * Combination of checkbox input and sibling label, inside a span
 *
 * @author Dayan Paez
 * @version 2014-03-27
 */
class FCheckbox extends XSpan {
  private static $counter = 0;
  private $box = null;

  protected $classname = 'checkbox-span';

  public function __construct($name, $value, $label = "", $checked = false, Array $attrs = array()) {
    parent::__construct("");
    $this->set('class', $this->classname);
    $this->add($this->box = $this->createInput($name, $value, $attrs));
    if (!isset($attrs['id'])) {
      $id = 'chk-' . self::$counter++;
      $this->box->set('id', $id);
    }
    else {
      $id = $attrs['id'];
    }
    $this->add(new XLabel($id, $label));
    if ($checked)
      $this->box->set('checked', 'checked');
  }

  public function set($name, $value) {
    if (in_array($name, array('required', 'disabled', 'readonly')))
      $this->box->set($name, $value);
    else
      parent::set($name, $value);
  }

  protected function createInput($name, $value, Array $attrs) {
    return new XCheckboxInput($name, $value, $attrs);
  }
}

/**
 * Like FCheckbox, but with a radio button instead
 *
 * @author Dayan Paez
 * @version 2014-05-11
 */
class FRadio extends FCheckbox {
  protected $classname = 'radio-span';
  protected function createInput($name, $value, Array $attrs) {
    return new XRadioInput($name, $value, $attrs);
  }
}

/**
 * Table cell to represent a sail
 *
 * @author Dayan Paez
 * @version 2014-05-05
 */
class SailTD extends XTD {

  const CLASSNAME = 'sail';
  const NOBG_CLASSNAME = 'no-background';

  public function __construct(Sail $sail = null, Array $attrs = array()) {
    parent::__construct($attrs, array((string) $sail));
    $class = self::CLASSNAME;
    if (array_key_exists('class', $attrs)) {
      $class .= ' ' . $attrs['class'];
    }

    if ($sail !== null) {
      if ($sail->color !== null) {
        $this->add(
          new XSpan(
            "",
            array(
              'class'=>'sail-color',
              'style' => sprintf('background:%s;', $sail->color)
            )
          )
        );
      }
      else {
        $class .= ' ' . self::NOBG_CLASSNAME;
      }
    }
    $this->set('class', $class);
  }
}

/**
 * Datalist element
 *
 * @author Dayan Paez
 * @version 2014-12-20
 */
class XDataList extends XAbstractHtml {
  /**
   * Creates a new datalist
   *
   * @param String $id the ID of the element
   * @param Array:String $options simple list of options
   * @param Array $attrs
   */
  public function __construct($id, Array $options = array(), Array $attrs = array()) {
    parent::__construct('datalist', $attrs);
    $this->set('id', $id);
    foreach ($options as $option)
      $this->addOption($option);
  }
  public function addOption($option, $content = null) {
    $this->add(new XDataListOption($option, $content));
  }
}

/**
 * Option entry for datalist
 *
 * @author Dayan Paez
 * @version 2014-12-20
 */
class XDataListOption extends XAbstractHtml {
  public function __construct($value, $content = null, Array $attrs = array()) {
    parent::__construct('option', $attrs);
    $this->set('value', $value);
    if ($content !== null)
      $this->add($content);
  }
}

/**
 * DIV with the class 'form-group'
 *
 * @author Dayan Paez
 * @version 2015-02-21
 */
class FormGroup extends XDiv {
  public function __construct(Array $children = array(), Array $attrs = array()) {
    parent::__construct($attrs, $children);
    $this->set('class', 'form-group');
  }
}

/**
 * A paragraph element, as a warning.
 *
 * @author Dayan Paez
 * @version 2015-02-21
 */
class XWarning extends XP {
  public function __construct($content = '') {
    parent::__construct(array('class' => 'warning'), $content);
  }
}

/**
 * A paragraph element that warns, and is not to be printed.
 *
 * @author Dayan Paez
 * @version 2015-02-21
 */
class XNonprintWarning extends XWarning {
  public function __construct($content = '') {
    parent::__construct($content);
    $this->set('class', 'warning nonprint');
  }
}

/**
 * A paragraph for happy news, using the "valid" class.
 *
 * @author Dayan Paez
 * @version 2015-02-21
 */
class XValid extends XP {
  public function __construct($content = '') {
    parent::__construct(array('class' => 'valid bg-check'), $content);
  }
}

/**
 * DIV for rich textarea containment
 *
 * @author Dayan Paez
 * @version 2014-12-14
 */
class XTextEditor extends XDiv {

  public $textarea;

  /**
   * Creates a new rich-text area element
   *
   * @param String $name the name of the textarea form element
   * @param String $value the value of the textarea form element
   * @param Array $taAttrs extra attributes for textarea element
   * @param Array $dAttrs extra attributes for DIV wrapper element
   */
  public function __construct($id, $name, $value = '', Array $taAttrs = array(), Array $dAttrs = array()) {
    parent::__construct($dAttrs);
    $this->set('class', 'dpeditor-container');

    $taAttrs['id'] = $id;
    $taAttrs['rows'] = 16;
    $taAttrs['cols'] = 80;
    $this->textarea = new XTextArea($name, $value, $taAttrs);
    $this->add($this->textarea);
  }
}
?>