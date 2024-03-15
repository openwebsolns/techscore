<?php
namespace metrics;

use \Conf;

/**
 * Emits TS metrics using configured MetricPublisher.
 *
 * created 2024-04-14
 */
class TSMetric {

  /**
   * @var MetricPublisher cached publisher.
   */
  private static $publisher;

  /**
   * Set the publisher to use. Exposed mostly for testing.
   *
   * @param MetricPublisher $publisher the publisher
   */
  public static function init(MetricPublisher $publisher) {
    self::$publisher = $publisher;
  }

  /**
   * Publishes given metric using configured publisher.
   *
   * @param String $metricName name of the metric to emit
   * @param double $amount the count/amount for the metric
   * @param MetricUnit $unit the unit hint for the metric
   */
  public static function publish($metricName, $amount = 1.0, $unit = MetricPublisher::UNIT_COUNT) {
    if (self::$publisher === null) {
      $classname = Conf::$METRIC_PUBLISHER;
      self::init(new $classname(Conf::$METRIC_PUBLISHER_PARAMS));
    }

    self::$publisher->publish($metricName, $amount, $unit);
  }
}
