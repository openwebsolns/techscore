<?php
/*
 * This file is part of TechScore
 */

require_once('Attachment.php');

/**
 * Use a string as an attachment file
 *
 * @author Dayan Paez
 * @created 2014-10-20
 */
class StringAttachment extends Attachment {

  protected $filedata;

  public function __construct($filename, $mime_type, $filedata) {
    $this->name = (string)$filename;
    $this->mime_type = (string)$mime_type;
    $this->filedata = (string)$filedata;
  }

  public function getBase64EncodedData() {
    return base64_encode($this->filedata);
  }
}
?>