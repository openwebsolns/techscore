<?php
/*
 * This file is part of TechScore
 *
 * @package users-reports
 */

require_once('users/AbstractUserPane.php');

/**
 * Common parent for all report panes
 *
 * @author Dayan Paez
 * @created 2014-05-18
 */
abstract class AbstractReportPane extends AbstractUserPane {
  public function __construct($title, Account $user) {
    parent::__construct($title, $user);
  }

  /**
   * Concatenates a new CSV row to the given string
   *
   * @param String $name the string to which add new row
   * @param Array:String $cells the row to add
   */
  protected function rowCSV(&$csv, Array $cells) {
    $quoted = array();
    foreach ($cells as $cell) {
      if (is_numeric($cell))
        $quoted[] = $cell;
      else
        $quoted[] = sprintf('"%s"', str_replace('"', '""', $cell));
    }
    $csv .= implode(',', $quoted) . "\n";
  }

  /**
   * Helper method: return XUl of regatta types
   *
   * @param String $prefix to use for checkbox IDs
   * @param Array:Type $chosen the types to choose, or all if empty
   */
  protected function regattaTypeList($prefix, Array $chosen = array()) {
    require_once('xml5/XMultipleSelect.php');
    $ul = new XMultipleSelect('types[]', array(), array('style'=>'width:10em;'));
    foreach (DB::getAll(DB::$ACTIVE_TYPE) as $t) {
      $ul->addOption($t->id, $t, in_array($t, $chosen));
    }
    return $ul;
  }

  /**
   * Helper method: return XUl of conferences
   *
   * @param String $prefix to use for checkbox IDs
   * @param Array:Conference $chosen conferences to choose, or all if empty
   */
  protected function conferenceList($prefix, Array $chosen = array()) {
    require_once('xml5/XMultipleSelect.php');
    $ul = new XMultipleSelect('confs[]', array(), array('style'=>'width:10em;'));
    $confs = DB::getConferences();
    foreach ($confs as $conf) {
      $ul->addOption($conf->id, $conf, in_array($conf, $chosen));
    }
    return $ul;
  }

  /**
   * Helper method: return XUl of seasons
   *
   * @param String $prefix to use for checkbox IDs
   * @param Array:Season $preselect list of seasons to choose, indexed
   * by ID.
   * @return XUl
   */
  protected function seasonList($prefix, Array $preselect = array()) {
    require_once('xml5/XMultipleSelect.php');
    $ul = new XMultipleSelect('seasons[]', array(), array('style'=>'width:10em;'));
    foreach (Season::getActive() as $season) {
      $ul->addOption($season, $season->fullString(), isset($preselect[$season->id]));
    }
    return $ul;
  }
}
?>
