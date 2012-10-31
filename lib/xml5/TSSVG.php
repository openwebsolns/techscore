<?php
/*
 * This file is part of TechScore
 *
 * @package svg
 */

require_once('xml5/SVGLib.php');

/*
 * Collection of TS-specific SVG goodies
 *
 * @author Dayan Paez
 * @created 2012-10-31
 */

/**
 * A data bubble pointing to a spot above. Displays underneath 0, 0.
 *
 * @author Dayan Paez
 * @version 2012-10-31
 */
class SVGPointAbove extends SVGPath {
  /**
   * Creates a new bubble
   *
   * @param String $id the ID to use
   * @param int $width the width of the bubble
   * @param int $height the height of the bubble
   * @param int $point_height the height of the pointer
   */
  public function __construct($id, $width = 40, $height = 40, $point_height = 10) {
    parent::__construct(array(), array('id'=>$id));
    $shoulder = max(0, ($width - 2 * $point_height) / 2);
    $point_width = ($width - 2 * $shoulder) / 2;

    $this->addPath(new SVGMoveto(-1 * $width / 2, $point_height, false));
    $this->addPath(new SVGLineto(0, $height, false));
    $this->addPath(new SVGLineto($width, 0, false));
    $this->addPath(new SVGLineto(0, -1 * $height, false));
    
    $this->addPath(new SVGLineto(-1 * $shoulder, 0, false));
    $this->addPath(new SVGLineto(-1 * $point_width, -1 * $point_height, false));
    $this->addPath(new SVGLineto(-1 * $point_width, $point_height, false));
    $this->addPath(new SVGClosepath());
  }
}
/**
 * A data bubble pointing to a spot below. Displays above 0, 0
 *
 * @author Dayan Paez
 * @version 2012-10-31
 */
class SVGPointBelow extends SVGPath {
  /**
   * Creates a new bubble
   *
   * @param String $id the ID to use
   * @param int $width the width of the bubble
   * @param int $height the height of the bubble
   * @param int $point_height the height of the pointer
   */
  public function __construct($id, $width = 40, $height = 40, $point_height = 10) {
    parent::__construct(array(), array('id'=>$id));
    $shoulder = max(0, ($width - 2 * $point_height) / 2);
    $point_width = ($width - 2 * $shoulder) / 2;

    $this->addPath(new SVGMoveto(-1 * $width / 2, -1 * $point_height, false));
    $this->addPath(new SVGLineto(0, -1 * $height, false));
    $this->addPath(new SVGLineto($width, 0, false));
    $this->addPath(new SVGLineto(0, $height, false));
    
    $this->addPath(new SVGLineto(-1 * $shoulder, 0, false));
    $this->addPath(new SVGLineto(-1 * $point_width, $point_height, false));
    $this->addPath(new SVGLineto(-1 * $point_width, -1 * $point_height, false));
    $this->addPath(new SVGClosepath());
  }
}
?>