<?php
namespace ui;

/**
 * Represents an HTTP response with status, headers, and a body.
 */
class HttpResponse {

  /** E.g. 200, 403, etc */
  private $statusCode;

  /** E.g. OK, Bad Request */
  private $statusDescription;

  /** The optional contents of the response */
  private $body;

  /** Headers to include in the response as an associative array. */
  private $headers;

  private function __construct($statusCode, $statusDescription, $body, Array $headers) {
    $this->statusCode = $statusCode;
    $this->statusDescription = $statusDescription;
    $this->body = $body;
    $this->headers = $headers;
  }

  public function __get($field) {
    return $this->$field;
  }

  public static function ok($body, Array $headers = array()) {
    return new HttpResponse(200, "OK", $body, $headers);
  }

  public static function seeOther($redirectUrl, Array $additionalHeaders = array()) {
    return new HttpResponse(303, "See Other", "", [
      ...$additionalHeaders,
      'Location' => $redirectUrl,
    ]);
  }

  public static function badRequest($body, Array $headers = array()) {
    return new HttpResponse(400, "Bad Request", $body, $headers);
  }

  public static function forbidden($body, Array $headers = array()) {
    return new HttpResponse(403, "Forbidden", $body, $headers);
  }
}
