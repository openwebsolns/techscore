<?php
namespace metrics;

use \DB;
use \Metric;

/**
 * Stores metrics in the database.
 *
 * @created 2024-03-14
 */
class DBMetricPublisher implements MetricPublisher {

  /**
   * Publishes given metric.
   *
   * @param String $metricName name of the metric to emit
   * @param double $amount the count/amount for the metric
   * @param MetricUnit $unit the unit hint for the metric
   */
  public function publish($metricName, $amount, $unit = self::UNIT_COUNT) {
    $obj = new Metric();
    $obj->amount = (double) $amount;
    $obj->metric = $metricName;
    $obj->published_on = DB::T(DB::NOW);
    DB::set($obj, false);
  }
}
