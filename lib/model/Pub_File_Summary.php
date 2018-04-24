<?php
use \model\PublicData;
use \model\Publishable;

/**
 * Skeletal public site (missing filedata)
 *
 * @author Dayan Paez
 * @version 2013-10-04
 */
class Pub_File_Summary extends DBObject implements Writeable, Publishable {

  /**
   * Name of the init.js file
   * @see public/InitJs
   */
  const INIT_FILE = 'init';

  /**
   * Different options available
   */
  const AUTOLOAD_SYNC = 'auto-sync';
  const AUTOLOAD_ASYNC = 'auto-async';

  public $filetype;
  public $width;
  public $height;
  protected $options;
  public function db_name() { return 'pub_file'; }
  protected function db_order() { return array('filetype'=>true, 'id'=>true); }

  public function db_type($field) {
    if ($field == 'options')
      return array();
    return parent::db_type($field);
  }

  public function getFile() {
    return DB::get(DB::T(DB::PUB_FILE), $this->id);
  }

  public function __toString() {
    return $this->id;
  }

  /**
   * Creates an XImg object with width/height attrs (if available)
   *
   * @param String $src the source to use
   * @param String $alt the alt text to use
   * @param Array $attrs optional list of other attributes
   */
  public function asImg($src, $alt, Array $attrs = array()) {
    $img = new XImg($src, $alt, $attrs);
    if ($this->width !== null) {
      $img->set('width', $this->width);
      $img->set('height', $this->height);
    }
    return $img;
  }

  public function write($resource) {
    $file = $this->getFile();
    fwrite($resource, $file->filedata);
  }

  /**
   * Publishable interface requirement.
   *
   * @return String
   */
  public function getURL() {
    return self::getUrlFromFilename($this->id);
  }

  public function getPublicData() {
    return (new PublicData(PublicData::V1))
      ->with('id', $this->id)
      ->with('filetype', $this->filetype)
      ->with('height', $this->height)
      ->with('width', $this->width)
      ->with('options', $this->__get('options'))
      ;
  }

  /**
   * Calculates the path a file would have based on filename.
   *
   * @param String $filename basename of the file (with extension).
   * @return String
   */
  public static function getUrlFromFilename($filename) {
    $path = '/' . $filename;
    $tokens = explode('.', $filename);
    if (count($tokens) > 1) {
      $ext = array_pop($tokens);
      if ($ext == 'css')
        $path = '/inc/css/' . $filename;
      elseif ($ext == 'js')
        $path = '/inc/js/' . $filename;
      elseif (in_array($ext, array('png', 'gif', 'jpg', 'jpeg')))
        $path = '/inc/img/' . $filename;
    }
    return $path;
  }

  // ------------------------------------------------------------
  // Options API
  // ------------------------------------------------------------

  public function getOptions() {
    if ($this->options === null)
      $this->options = array();
    return $this->__get('options');
  }

  /**
   * Adds the option
   *
   * @param String $option to add
   * @return boolean true if option was not already present
   */
  public function addOption($option) {
    $options = $this->getOptions();
    if (in_array($option, $options))
      return false;
    $options[] = $option;
    sort($options);
    $this->options = $options;
    return true;
  }

  /**
   * Removes option from this file's set
   *
   * @param String $option the option to remove
   * @return boolean true if option existed
   */
  public function removeOption($option) {
    $options = $this->getOptions();
    $position = array_search($option, $options);
    if ($position === false)
      return false;
    array_splice($options, $position, 1);
    $this->options = $options;
    return true;
  }

  public function hasOption($option) {
    $options = $this->getOptions();
    return in_array($option, $options);
  }
}
