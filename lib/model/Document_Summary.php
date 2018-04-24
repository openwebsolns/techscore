<?php
use \model\PublicData;
use \model\Publishable;

/**
 * All information about a regatta document, except for the data.
 *
 * @author Dayan Paez
 * @version 2013-11-21
 */
class Document_Summary extends DBObject implements Writeable, Publishable {
  public $name;
  public $description;
  public $url;
  public $filetype;
  public $relative_order;
  public $category;
  public $width;
  public $height;
  protected $regatta;
  protected $author;
  protected $last_updated;

  const CATEGORY_NOTICE = 'notice';
  const CATEGORY_PROTEST = 'protest';
  const CATEGORY_COURSE_FORMAT = 'course_format';

  public function db_name() { return 'regatta_document'; }
  protected function db_order() { return array('relative_order'=>true); }
  public function db_type($field) {
    switch ($field) {
    case 'regatta':
      return DB::T(DB::REGATTA);
    case 'author':
      return DB::T(DB::ACCOUNT);
    case 'last_updated':
      return DB::T(DB::NOW);
    default:
      return parent::db_type($field);
    }
  }

  public function getFile() {
    return DB::get(DB::T(DB::REGATTA_DOCUMENT), $this->id);
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
   * Returns the URL to this document.
   *
   * @return String.
   * @throws InvalidArgumentException if no regatta exists.
   */
  public function getURL() {
    if ($this->regatta === null)
      throw new InvalidArgumentException("Documents must be associated with regattas for URLs to exist.");
    if ($this->url === null)
      throw new InvalidArgumentException("Documents must have URL property set.");
    $reg = $this->__get('regatta');
    return sprintf('%snotices/%s', $reg->getURL(), $this->url);
  }

  public function getPublicData() {
    return (new PublicData(PublicData::V1))
      ->with('category', $this->category)
      ->with('description', $this->description)
      ->with('filetype', $this->filetype)
      ->with('height', $this->height)
      ->with('id', $this->id)
      ->with('name', $this->name)
      ->with('regatta', $this->__get('regatta'))
      ->with('relative_order', $this->relative_order)
      ->with('width', $this->width)
      ;
  }

  public static function getCategories() {
    return array(self::CATEGORY_NOTICE => "General notice",
                 self::CATEGORY_PROTEST => "Protest",
                 self::CATEGORY_COURSE_FORMAT => "Course format",
    );
  }
}
