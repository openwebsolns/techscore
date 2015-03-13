<?php
namespace http;

use \SimpleXMLElement;
use \InvalidArgumentException;

/**
 * The body portion of an HTTP response.
 *
 * @author Dayan Paez
 * @version 2015-03-10
 */
class ResponseBody {

  /**
   * @var String the raw body.
   */
  private $raw;

  private $xmlVersion;

  public function __construct($raw = null) {
    if ($raw !== null) {
      $this->setRaw($raw);
    }
  }

  /**
   * Set the raw (text/binary) input.
   *
   * @param mixed $raw
   */
  public function setRaw($raw) {
    $this->raw = (string)$raw;
    $this->xmlVersion = null;
  }

  /**
   * Get the raw content of the body.
   *
   * @return String the raw input.
   */
  public function getRaw() {
    return $this->raw;
  }

  /**
   * Attempts to parse body using SimpleXML, returns root node.
   *
   * @return SimpleXMLElement
   * @throws InvalidArgumentException if unable to parse.
   */
  public function asXml() {
    if ($this->xmlVersion === null) {
      libxml_use_internal_errors(true);
      $sxe = new SimpleXMLElement($this->getRaw());
      if ($sxe === false) {
        $res = array();
        foreach (libxml_get_errors() as $error) {
          $res[] = $error->message;
        }
        throw new InvalidArgumentException(
          sprintf("Failed loading XML: %s.", implode(". ",  $res))
        );
      }
      $this->xmlVersion = $sxe;
    }
    return $this->xmlVersion;
  }

  /**
   * Treats the body as XML, and applies the given xpath query.
   *
   * @param String $xpath the xpath query
   * @return Array the result of calling xpath() on SimpleXMLElement.
   * @throws InvalidArgumentException
   */
  public function xpath($xpath) {
    $root = $this->xmlVersion;
    if ($root === null) {
      $root = $this->asXml();
      
      // Register default namespace
      $namespaces = $root->getNamespaces();
      if (array_key_exists('', $namespaces)) {
        $root->registerXPathNamespace('html', $namespaces['']);
      }
    }

    return $root->xpath($xpath);
  }
}