<?php
namespace ui;

use \XDiv;
use \XFileInput;
use \XImg;

/**
 * An input element that offers image previews.
 *
 * @author Dayan Paez
 * @version 2015-11-10
 */
class ImageInputWithPreview extends XDiv {

  const CONTAINER_CLASSNAME = 'image-input-with-preview';
  const INPUT_CLASSNAME = 'image-input-with-preview-input';
  const PREVIEW_CLASSNAME = 'image-input-with-preview-preview';

  /**
   * Create a new input.
   *
   * @param String $name the name of the input element.
   * @param String|XImg $src the src argument to the img preview.
   * @param int $width the max width to use for the preview.
   * @param int $height the max height to use for preview.
   */
  public function __construct($name, $src = null) {
    parent::__construct(array('class' => self::CONTAINER_CLASSNAME));
    $this->add(
      new XFileInput($name, array('class' => self::INPUT_CLASSNAME))
    );
    if ($src instanceof XImg) {
      $src->set('class', self::PREVIEW_CLASSNAME);
      $this->add($src);
    }
    else {
      $this->add(
        new XImg($src, "Input preview", array('class' => self::PREVIEW_CLASSNAME))
      );
    }
  }

}