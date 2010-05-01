<?php
/*
 * This file is part of TechScore
 *
 * @package regatta
 */
require_once('conf.php');

/**
 * Interface for changes to finishes. Only one method is supported:
 * finishChanged which takes in two arguments: a change type, and the
 * finish which changed
 *
 * @author Dayan Paez
 * @created 2010-01-30
 */
interface FinishListener {

  const PENALTY = "penalty";
  const SCORE   = "score";
  const ENTERED = "entered";

  public function finishChanged($type, Finish $finish);
}
?>