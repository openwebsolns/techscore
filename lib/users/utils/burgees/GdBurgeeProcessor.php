<?php
namespace users\utils\burgees;

use \FInfo;
use \Burgee;
use \SoterException;

/**
 * Uses the GD library.
 *
 * @author Dayan Paez
 * @version 2015-11-11
 */
class GdBurgeeProcessor implements BurgeeProcessor {

  private static $allowedMimeTypes = array(
    'image/png',
    'image/gif',
  );

  private $imageSource;
  private $widthSource;
  private $heightSource;

  /**
   * Sets the image to use to generate burgees.
   *
   * @param String $filename full path to the local file.
   */
  public function init($filename) {
    $finfo = new FInfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($filename);
    if (!in_array($mime, self::$allowedMimeTypes)) {
      throw new SoterException("Only PNG and GIF images are allowed.");
    }

    set_error_handler(
      function($n, $s) {
        throw new SoterException("Invalid image file.");
      },
      E_WARNING
    );
    $this->imageSource = @imagecreatefromstring(file_get_contents($filename));
    restore_error_handler();

    if ($this->imageSource === false) {
      throw new SoterException("Invalid image file.");
    }

    $size = getimagesize($filename);
    if ($size[0] < 32 || $size[1] < 32) {
      throw new SoterException("Image too small.");
    }
    $this->widthSource = $size[0];
    $this->heightSource = $size[1];
  }

  /**
   * Creates a burgee using the file set in setBaseImage.
   *
   * @param int $widthTarget the width to fit into.
   * @param int $heightTarget the height to fit into.
   * @return Burgee
   */
  public function createBurgee($widthTarget, $heightTarget) {
    $width = $this->widthSource;
    $height = $this->heightSource;

    $ratio = min(
      ($widthTarget / $this->widthSource),
      ($heightTarget / $this->heightSource)
    );

    if ($ratio < 1) {
      $width = floor($ratio * $width);
      $height = floor($ratio * $height);
    }

    $dstX = floor(($widthTarget - $width) / 2);
    $dstY = floor(($heightTarget - $height) / 2);

    // create transparent destination image
    $dst = imagecreatetruecolor($widthTarget, $heightTarget);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $trans = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefill($dst, 0, 0, $trans);

    $result = imagecopyresampled(
      $dst, $this->imageSource,
      $dstX, $dstY,      // destination upper-left
      0, 0,              // source upper-left
      $width, $height,   // destination
      $this->widthSource, $this->heightSource
    );
    if ($result === false) {
      throw new SoterException("Unable to create new burgee image.");
    }

    ob_start();
    imagepng($dst, null, 9, PNG_ALL_FILTERS);
    $txt = ob_get_contents();
    ob_end_clean();
    imagedestroy($dst);

    if ($txt == "") {
      throw new SoterException("Unable to resample the burgee image.");
    }

    $burgee = new Burgee();
    $burgee->filedata = base64_encode($txt);
    $burgee->width = $widthTarget;
    $burgee->height = $heightTarget;
    return $burgee;
  }

  public function cleanup() {
    imagedestroy($this->imageSource);
  }
}