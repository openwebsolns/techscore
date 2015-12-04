<?php
namespace writers;

use \Writeable;

/**
 * Parent class for all public site serializers.
 *
 * This class represents a black box around the medium and process by
 * which data is saved; allowing subclasses to serialize to a file, a
 * database, an external device, etc.
 *
 * @author Dayan Paez
 * @created 2012-10-09
 */
abstract class AbstractWriter {

  /**
   * Handle Writeable object directly.
   *
   * This is the basic serialization method. Note that the filename
   * will start with a leading slash, like a relative URL. Also note
   * that the serializer may need to create the appropriate
   * directories specified by fname. Note that the contents are
   * passed by reference.
   *
   * @param String $fname the relative path to the file
   * @param Writeable $elem the element to write
   * @throws TSWriterException
   */
  abstract public function write($fname, Writeable $elem);

  /**
   * Removes the tree rooted at given filename.
   *
   * The filename could point to a file or directory, and it may or
   * many not exist. The method should not throw an Exception if the
   * specified files does not exist.
   *
   * @param String $fname the filename to remove
   * @throws TSScriptException if unable to remove
   */
  abstract public function remove($fname);
}
