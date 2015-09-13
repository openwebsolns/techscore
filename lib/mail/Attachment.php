<?php
namespace mail;

/**
 * An e-mail message attachment
 *
 * @author Dayan Paez
 * @created 2014-10-19
 */
class Attachment {
  /**
   * @var String MIME type for attached file
   */
  protected $mime_type;
  /**
   * @var String full filepath to file (optional)
   */
  protected $filepath;
  /**
   * @var String the name for the attachment
   */
  protected $name;
  /**
   * @var resource stream pointing to file (optional)
   */
  protected $stream;

  /**
   * Creates a new attachment
   *
   * @param String $filename the full path to the file
   * @param String $mime_type the optional mime_type
   * @see setFile
   */
  public function __construct($filename = null, $mime_type = null) {
    if ($filename !== null)
      $this->setFile($filename, $mime_type);
  }

  /**
   * Sets the file associated with this attachment
   *
   * @param String $filename the path to the file
   * @param String $mime_type optional MIME type, otherwise guess
   * @param String $name the new name (uses filename by default)
   * @throws InvalidArgumentException if unable to determine MIME type
   */
  public function setFile($filename, $mime_type = null, $name = null) {
    $this->filepath = (string)$filename;
    $this->stream = null;
    if ($mime_type !== null) {
      $this->mime_type = (string)$mime_type;
    }
    else {
      $finfo = new FInfo(FILEINFO_MIME_TYPE);
      $this->mime_type = $finfo->file($filename);
    }

    if (empty($this->mime_type) || $this->mime_type === false)
      throw new InvalidArgumentException("Empty MIME type for passed file \"$filename\"");

    $this->name = ($name) ? $name : basename($filename);
  }

  /**
   * Use the given resource as the attachment source
   *
   * @param resource $resource
   * @param String $mime_type the optional MIME type, or guess
   * @param String $name the new name
   * @throws InvalidArgumentException when things go awry
   */
  public function setResource($resource, $mime_type = null, $name = null) {
    if (!is_resource($resource))
      throw new InvalidArgumentException("Provided argument is not a resource");

    $this->filepath = null;
    $this->stream = $resource;
    $data = stream_get_meta_data($resource);
    if ($mime_type !== null) {
      $this->mime_type = (string)$mime_type;
    }
    else {
      $finfo = new FInfo(FILEINFO_MIME_TYPE);
      $this->mime_type = $finfo->file($data['uri']);
      $finfo->close();
    }

    if (empty($this->mime_type) || $this->mime_type === false)
      throw new InvalidArgumentException("Empty MIME type for passed file \"$filename\"");

    $this->name = ($name) ? $name : basename($data['uri']);
  }

  public function geFilePath() {
    return $this->filepath;
  }

  public function getMIME() {
    return $this->mime_type;
  }

  public function getName() {
    return $this->name;
  }

  public function getBase64EncodedData() {
    $res = null;
    $opened = false;
    if ($this->filepath !== null) {
      $res = fopen($this->filepath, 'r');
      $opened = true;
    }
    elseif ($this->stream !== null)
      $res = $this->stream;

    if (!$res)
      throw new InvalidArgumentException("Unable to read from give file.");

    $txt = '';
    while (!feof($res)) {
      $txt .= base64_encode(fread($res, 1024));
    }

    if ($opened)
      fclose($res);
    return $txt;
  }
}
?>