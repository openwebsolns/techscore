<?php
require_once('HtmlLib.php');
/**
 * Like a multiple-select drop-down box, but using checkboxes
 *
 * @author Dayan Paez
 * @created 2013-06-19
 */
class XMultipleSelect extends XUl {
  protected $input_name;
  protected $counter = 0;

  public function __construct($name, Array $attrs = array()) {
    parent::__construct($attrs);
    $this->set('class', 'multiple-select');
    $this->input_name = (string)$name;
  }

  public function addOption($value, $display, $checked = false) {
    $id = $this->input_name . '-' . $this->counter;
    $this->counter++;

    $this->add(new XLi(array($chk = new XCheckboxInput($this->input_name, $value, array('id'=>$id)),
                             new XLabel($id, $display))));
    if ($checked !== false)
      $chk->set('checked', 'checked');
  }

  public function addOptgroup($label) {
    $this->add(new XLi($label,  array('class'=>'multiple-select-group')));
  }
}
?>