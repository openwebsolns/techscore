<?php
namespace ui;

use \XHiddenInput;
use \XNumberInput;
use \XQuickTable;
use \XTD;

/**
 * A table whose rows can be re-ordered by the user.
 *
 * @author Dayan Paez
 * @version 2015-10-27
 * @see TableSorter.js
 */
class SortableTable extends XQuickTable {

  const TABLE_CNAME = 'tablesorter';
  const ROW_CNAME = 'tablesorter-sortable';
  const CELL_CNAME = 'tablesorter-drag';

  /**
   * @var String the input name to use when adding a sortable row.
   */
  private $order_input_name;
  /**
   * @var Array list of order inputs created.
   */
  private $order_inputs;

  public function __construct(Array $columns, $narrow = false, $order_input_name = 'order') {
    parent::__construct(
      array('class' => self::TABLE_CNAME),
      $columns
    );
    if ($narrow !== false) {
      $this->set('class', self::TABLE_CNAME . ' narrow');
    }
    $this->order_input_name = (string) $order_input_name;
    $this->order_inputs = array();
  }

  /**
   * Use this method to add a new sortable row.
   *
   * @param Array $columns all columns excluding the input column.
   */
  public function addSortableRow($input_name, $input_value, Array $columns) {

    $input_name = $input_name . '[]';
    $input = new XNumberInput(
      sprintf('%s[]', $this->order_input_name),
      count($this->order_inputs) + 1,
      1,     // minimum
      null,  // maximum
      1,     // step
      array('class'=>'small', 'size'=>2)
    );
    $this->order_inputs[] = $input;

    $cells = array(
      new XTD(array(), array($input, new XHiddenInput($input_name, $input_value)))
    );
    foreach ($columns as $column) {
      if (!($column instanceof XTD)) {
        $column = new XTD(array('class' => self::CELL_CNAME), $column);
      }
      $cells[] = $column;
    }
    $this->addRow($cells, array('class' => self::ROW_CNAME));
    $this->updateNumberInputMaximums();
  }

  private function updateNumberInputMaximums() {
    $max = count($this->order_inputs);
    foreach ($this->order_inputs as $input) {
      $input->set('max', $max);
    }
  }
}