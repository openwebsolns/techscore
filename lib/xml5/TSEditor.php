<?php
/**
 * This file is part of TechScore
 */

require_once(dirname(__FILE__).'/DPEditor.php');

/**
 * DPEditor subclass for TS
 *
 * @author Dayan Paez
 * @created 2012-09-16
 */
class TSEditor extends DPEditor {

  public function __construct() {
    parent::__construct();
    $this->setFirstHeading(new XH3(""));
    $this->setSecondHeading(new XH4(""));
    $this->setThirdHeading(new XH5(""));
  }

  protected function preParse($inp) {
    // $inp = preg_replace('@( [[:alnum:]]+: *)(https?://[^\s]+)@m', ' {a:$2,$1}', $inp);
    $inp = preg_replace('@([^({a:)] *)(https?://[^\s]+)@', '$1{a:$2}', $inp);
    $inp = preg_replace('@^(https?://[^\s]+)@', '{a:$1}', $inp);
    return $inp;
  }
}
?>