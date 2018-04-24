<?php
use \model\PublicData;
use \model\Publishable;

/**
 * Burgees: primary key matches with (and is a foreign key) to school.
 *
 * @author Dayan Paez
 * @version 2012-01-07
 */
class Burgee extends DBObject implements Writeable, Publishable {

  const FILEROOT = '/inc/img/schools';

  // Sizes availabble
  const SIZE_FULL = 'full';
  const SIZE_SMALL = 'small';
  const SIZE_SQUARE = 'square';

  // Dimensions in pixels

  const FULL_WIDTH = 180;
  const FULL_HEIGHT = 120;
  const SMALL_WIDTH = 60;
  const SMALL_HEIGHT = 40;
  const SQUARE_LENGTH = 120;

  public $filedata;
  public $width;
  public $height;
  protected $last_updated;
  protected $school;
  public $updated_by;

  public function db_type($field) {
    switch ($field) {
    case 'last_updated': return DB::T(DB::NOW);
    case 'school': return DB::T(DB::SCHOOL);
    default:
      return parent::db_type($field);
    }
  }

  public function write($resource) {
    fwrite($resource, base64_decode($this->filedata));
  }

  /**
   * Return the URL to this burgee, based on dimensions.
   *
   * By default, link to the full-size image.
   *
   * @return String
   */
  public function getURL() {
    if ($this->school === null)
      throw new InvalidArgumentException("School required for burgee URL.");
    $school = $this->__get('school');

    if ($this->width !== null && $this->height !== null) {
      if ($this->width == self::SMALL_WIDTH && $this->height == self::SMALL_HEIGHT) {
        return self::getUrlForSize($school, self::SIZE_SMALL);
      }
      if ($this->width == self::SQUARE_LENGTH && $this->height == self::SQUARE_LENGTH) {
        return self::getUrlForSize($school, self::SIZE_SQUARE);
      }
    }
    return self::getUrlForSize($school, self::SIZE_FULL);
  }

  public function getPublicData() {
    return (new PublicData(PublicData::V1))
      ->with('url', $this->getURL())
      ->with('width', $this->width)
      ->with('height', $this->height)
      ->with('last_updated', $this->__get('last_updated'))
      ->with('school', $this->__get('school'));
  }

  /**
   * Return the URL to use for given school and burgee size
   *
   * @param School $school
   * @param $size One of the class SIZE_* constants.
   * @return String
   * @throws InvalidArgumentException if unrecognized size.
   */
  public static function getUrlForSize(School $school, $size) {
    switch ($size) {
    case self::SIZE_FULL:
      return sprintf('%s/%s.png', self::FILEROOT, $school->id);
    case self::SIZE_SMALL:
      return sprintf('%s/%s-40.png', self::FILEROOT, $school->id);
    case self::SIZE_SQUARE:
      return sprintf('%s/%s-sq.png', self::FILEROOT, $school->id);
    default:
      throw new InvalidArgumentException("Unknown size: $size.");
    }
  }
}
