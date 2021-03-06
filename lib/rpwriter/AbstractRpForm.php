<?php
/*
 * This file is part of TechScore
 *
 * @package rpwriter
 */

/**
 * A generic RP form writer, to be extended by specialized forms.
 *
 * The forms need implement two methods:
 *
 *   - getPdfName: returns the full pathname of the PDF background file
 *   - makePdf:    generates PDF and returns full pathname to file
 *
 * @author Dayan Paez
 * @version 2010-02-08
 */
abstract class AbstractRpForm {

  /**
   * Gets the full path of the file to use as background
   *
   * @return String the filename or null if not supported
   */
  abstract public function getPdfName();

  /**
   * Generates a PDF file with a random name, and returns the filename
   *
   * @param string $tmpbase the basename of the random file
   * @return mixed the binar data
   * @throws RuntimeException should something go wrong
   */
  abstract public function makePdf(FullRegatta $reg, $tmpbase = 'ts2');

  /**
   * Generates PDF by attempting to connect to socket in $USE_SOCKET.
   *
   * @return mixed the binary PDF data
   * @throws InvalidArgumentException if unable to generate PDF
   */
  abstract public function socketPdf(FullRegatta $reg, $socket);
}
?>
