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
  protected function preParse($inp) {
    $inp = preg_replace('@[^({a:)] *(https?://[^\s]+)@', '{a:$1}', $inp);
    return $inp;
  }
}
?>