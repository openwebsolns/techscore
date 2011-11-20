<?php
/**
 * Library for creating thumbnails using imagemagick
 * Specialized for the purposes of creating thumbnails for TechScore
 * @package thumb
 */

/**
 * Provides a method for resizing images using ImageMagick
 *
 * @author Dayan Paez
 * @version 2010-03-05
 */
class Thumbnailer
{
  // Private variables
  private $width;
  private $height;

  /**
   * Create an instance of this object. Thumbnails created with this
   * object will have size no bigger than width, height
   *
   * @param int $width the target width
   * @param int $height the target height
   * @throws InvalidArgumentException if either width or height is zero.
   */
  public function __construct($width, $height) {
    $this->width  = (int)$width;
    $this->height = (int)$height;
    if ($this->width == 0 || $this->height == 0) {
      throw new InvalidArgumentException("Height and width must be greater than 0.");
    }
  }

  /**
   * Use IM to convert the file whose name is $inFilename to another
   * file $outFilename. Also, remove white background, the cheap way
   *
   * executes: imagemagick $inFilename -resize $widthx$height
   * $outFilename
   *
   * @param String $inFilename  the input filename
   * @param String $outFilename the output filename
   * @return boolean whether or not the command was successful
   */
  public function resize($inFilename, $outFilename) {
    $command = sprintf("convert -alpha set -channel RGBA -fill none -opaque white %s -resize %sx%s %s",
		       $inFilename,
		       $this->width,
		       $this->height,
		       $outFilename);
    $output = array();
    exec($command, $output, $value);
    return ($value == 0);
  }
}

if (basename(__FILE__) == $argv[0]) {
  $tn = new Thumbnailer(100, 100);
  $rs = $tn->resize("/home/dayan/Desktop/wex-pics/coat.JPG", "/home/dayan/Desktop/wex-pics/coat-small.jpg");
  var_dump($rs);
}

?>