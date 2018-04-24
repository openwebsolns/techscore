<?php
namespace model;

use \DateTime;
use \DBObject;
use \InvalidArgumentException;
use \Season;

/**
 * A JSON-encodable payload.
 */
class PublicData {

  const V1 = "1.0";

  private $payload;

  public function __construct($version) {
    $this->payload = array('version' => $version);
  }

  public function with($key, $value) {
    if ($key === 'version') {
      throw new InvalidArgumentException('version can only be set at construction');
    }
    $this->payload[$key] = $this->translateValue($value);
    return $this;
  }

  public function toJson() {
    return json_encode($this->payload);
  }

  private function translateValue($value) {
    if ($value === null) {
      return $value;
    }

    if ($value instanceof Publishable) {
      return sprintf('url:%s', $value->getURL());
    }

    if ($value instanceof DBObject) {
      return $value->id;
    }

    if ($value instanceof DateTime) {
      return $value->format('c');
    }

    if (is_array($value)) {
      return array_map(array($this, 'translateValue'), $value);
    }

    return $value;
  }
}
