<?php
/*
 * This file is part of Techscore
 */



/**
 * Full version of the document, includes the filedata
 *
 * @author Dayan Paez
 * @version 2013-11-21
 */
class Document extends Document_Summary {
  public $filedata;
  public function getFile() { return $this; }
}
