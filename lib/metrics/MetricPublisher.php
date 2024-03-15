<?php
namespace metrics;

/**
 * Publishes metric values.
 */
interface MetricPublisher {

  const UNIT_COUNT = 'count';
  const UNIT_MILLIS = 'millis';

  /**
   * Publishes given metric.
   *
   * @param String $metricName name of the metric to emit
   * @param double $amount the count/amount for the metric
   * @param MetricUnit $unit the unit hint for the metric
   */
  public function publish($metricName, $amount, $unit = self::UNIT_COUNT);
}
