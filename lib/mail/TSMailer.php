<?php
/*
 * This file is part of TechScore
 */

/**
 * Simpler PHP mailer functionality
 *
 * @author Dayan Paez
 * @created 2014-10-19
 */
class TSMailer {

  /**
   * Sends a multipart (MIME) mail message to the given user with the
   * given subject, appending the correct headers (i.e., the "from"
   * field). This method uses the standard PHP mail function
   *
   * @param String|Array $to the e-mail address(es) to send to
   * @param String $subject the subject
   * @param Array $parts the different MIME parts, indexed by MIME type.
   * @param Array $extra_headers optional map of extra headers to send
   * @param Array:Attachment $attachments optional list of attachments
   * @return boolean the result, as returned by mail
   */
  public static function sendMultipart($to, $subject, Array $parts, Array $extra_headers = array(), Array $attachments = array()) {
    
    if (DB::g(STN::DIVERT_MAIL) !== null) {
      $to = DB::g(STN::DIVERT_MAIL);
      $subject = 'DIVERTED: ' . $subject;
    }

    require_once('EmailCreator.php');
    $creator = new EmailCreator();

    $extra_headers['From'] = DB::g(STN::TS_FROM_MAIL);
    $creator->setHeaders($extra_headers);

    foreach ($parts as $mime => $part) {
      $creator->addAlternative($part, $mime);
    }

    foreach ($attachments as $file) {
      $creator->addAttachment($file);
    }

    $email = $creator->createEmail();

    $body = $email->getBody();
    $headers = '';
    foreach ($email->getHeaders() as $key => $val) {
      $headers .= $key . ': ' . $val . "\n";
    }

    if (!is_array($to))
      $to = array($to);
    $res = true;
    foreach ($to as $recipient)
      $res = $res && @mail($recipient, $subject, $body, $headers);
    return $res;

    /*
    $segments = array();
    foreach ($parts as $mime => $part) {
      $segment = sprintf("Content-Type: %s\n", $mime);
      if (substr($mime, 0, strlen('text/plain')) != 'text/plain') {
        $segment .= "Content-Transfer-Encoding: base64\n";
        $part = base64_encode($part);
      }
      $segment .= "\n";
      $segment .= $part;
      $segments[] = $segment;
    }

    $content_type = 'multipart/alternative';

    $found = true;
    while ($found) {
      $bdry = uniqid(rand(100, 999), true);
      $found = false;
      foreach ($segments as $segment) {
        if (strstr($segment, $bdry) !== false) {
          $found = true;
          break;
        }
      }
    }

    $headers = sprintf("From: %s\nMIME-Version: 1.0\nContent-Type: %s; boundary=%s\n",
                       DB::g(STN::TS_FROM_MAIL),
                       $content_type,
                       $bdry);

    foreach ($extra_headers as $key => $val)
      $headers .= sprintf("%s: %s\n", $key, $val);
    $body = "This is a message with multiple parts in MIME format.\n";
    foreach ($segments as $segment)
      $body .= sprintf("--%s\n%s\n", $bdry, $segment);
    $body .= sprintf("--%s--", $bdry);

    if (!is_array($to))
      $to = array($to);
    $res = true;
    foreach ($to as $recipient)
      $res = $res && @mail($recipient, $subject, $body, $headers);
    return $res;
    */
  }

}
?>