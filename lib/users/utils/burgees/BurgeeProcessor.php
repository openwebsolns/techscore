<?php
namespace users\utils\burgees;

/**
 * Resizes a file intended to be used as a school burgee.
 *
 * @author Dayan Paez
 * @version 2015-11-11
 */
interface BurgeeProcessor {

  /**
   * Sets the image to use to generate burgees.
   *
   * @param String $filename full path to the local file.
   * @throws SoterException when unable to work.
   */
  public function init($filename);

  /**
   * Creates a burgee using the file set in setBaseImage.
   *
   * @param int $width the width to fit into.
   * @param int $height the height to fit into.
   * @return Burgee
   * @throws SoterException when unable to perform.
   */
  public function createBurgee($width, $height);

  /**
   * Performs any cleanup duties.
   *
   */
  public function cleanup();
}