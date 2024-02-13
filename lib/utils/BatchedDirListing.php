<?php
namespace utils;

use \InvalidArgumentException;

/*
 * This file is part of TechScore
 *
 * @version 2.0
 * @package scripts
 */

/**
 * Batched listing of directories, with wide support (hopefully)
 *
 * @author Dayan Paez
 * @created 2014-11-13
 */
class BatchedDirListing {

  private $dir_handle;
  private $dir_path;
  private $recursive = false;
  private $regexp = null;

  const TYPE_DIR = 1;
  const TYPE_FILE = 2;
  const TYPE_ALL = 3;

  /**
   * @var int bitmask of filter types
   */
  private $types = self::TYPE_ALL;

  /**
   * Opens a new listing with optional directory
   *
   * @param String $dir the path to the directory to open
   * @param boolean $recursive true to list all subdirs
   */
  public function __construct($dir = null, $recursive = false) {
    if ($dir !== null)
      $this->openDir($dir, $recursive);
  }

  public function filterByRegexp($regexp = null) {
    if (preg_match($regexp, '') === false)
      throw new InvalidArgumentException("Invalid regexp provided [$regexp]");
    $this->regexp = $regexp;
  }

  public function filterByType($bitmask = self::TYPE_ALL) {
    $this->type = (int)$bitmask;
  }

  /**
   * Set the new directory to batch list from
   *
   * @param String $dir the path to the directory to open
   * @param boolean $recursive true to list all subdirs
   * @throws InvalidArgumentException if directory is invalid
   */
  public function openDir($dir, $recursive = false) {
    if (!is_dir($dir))
      throw new InvalidArgumentException("Invalid directory provided: $dir");
    if (is_resource($this->dir_handle))
      closedir($this->dir_handle);
    $this->dir_path = (string)$dir;
    $this->dir_handle = opendir($this->dir_path);
    if (!$this->dir_handle)
      throw new InvalidArgumentException("Unable to open $dir");
    $this->recursive = ($recursive !== false);
  }

  /**
   * Retrieves the next batch of files from opened directory
   *
   * @param int $size the size of the batch
   * @return Array (localized) filenames
   */
  public function nextBatch($batch_size = 100) {
    if (!is_resource($this->dir_handle))
      throw new InvalidArgumentException("Directory must be opened with openDir first");
    if ($batch_size <= 0)
      throw new InvalidArgumentException("Batch size must be positive ($batch_size given).");

    $batch = array();
    while (count($batch) < $batch_size && ($entry = readdir($this->dir_handle)) !== false) {
      // Apply filters
      if ($this->regexp !== null && preg_match($this->regexp, $entry) == 0)
        continue;
      if ($this->types > 0) {
        // TODO
      }

      $batch[] = $entry;
    }

    if (count($batch) == 0) {
      $this->closeDir();
      return false;
    }

    return $batch;
  }

  public function closeDir() {
    if (!is_resource($this->dir_handle))
      throw new InvalidArgumentException("Cannot close what hasn't been opened");
    return closedir($this->dir_handle);
  }
}
?>
