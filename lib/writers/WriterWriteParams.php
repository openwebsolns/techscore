<?php
namespace writers;

use \Writeable;

/**
 * Parameters when calling AbstractWriter->write().
 *
 * @author Dayan Paez
 * @created 2026-11-09
 */
class WriterWriteParams {

  private string $filename;
  private Writeable $contents;

  /**
   * Creates a new set of parameters.
   *
   * @param string $filename the full path of the file to write
   * @param Writeable $contents to write
   */
  public function __construct(
    string $filename,
    Writeable $contents
  ) {
    $this->filename = $filename;
    $this->contents = $contents;
  }

  public function __get($name) {
    switch ($name) {
    case 'filename':
    case 'contents':
      return $this->$name;
    default:
      throw new \InvalidArgumentException("Invalid property '{$name}'");
    }
  }
}
