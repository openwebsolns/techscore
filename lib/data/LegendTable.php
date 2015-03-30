<?php
namespace data;

use \XQuickTable;
use \XTD;

require_once('xml5/HtmlLib.php');

/**
 * A scoring legend table, from supplied explanations.
 *
 * @author Dayan Paez
 * @version 2015-03-22
 */
class LegendTable extends XQuickTable {

  /**
   * Creates a tiebreaker legend table.
   *
   * @param Array $tiebreaker the associative array of symbol => explanation.
   * @param Array $outside_schools optional list of outside schools.
   */
  public function __construct(Array $tiebreakers, Array $outside_schools = array()) {
    parent::__construct(array('class'=>'tiebreaker'), array("Sym.", "Explanation"));
    foreach ($tiebreakers as $exp => $ast) {
      if ($exp != '') {
        $this->addRow(array($ast, new XTD(array('class'=>'explanation'), $exp)));
      }
    }
    foreach ($outside_schools as $exp => $ast) {
      $this->addRow(array($ast, new XTD(array('class'=>'explanation'), sprintf("%s sailor", $exp))));
    }
  }
}