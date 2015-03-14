<?php

require_once('AbstractTester.php');

/**
 * Tests the BillingReport.
 *
 * @author Dayan Paez
 * @version 2015-03-13
 */
class BillingReportTest extends AbstractTester {

  private $seasons;
  private $conferences;
  private $costs;
  private $types;
  private static $url = '/billing';

  protected function setUp() {
    $this->seasons = array();
    foreach (Season::getActive() as $i => $season) {
      if ($i > 3)
        break;
      $this->seasons[] = $season->id;
    }

    $this->conferences = array();
    foreach (DB::getConferences() as $conf) {
      $this->conferences[] = $conf->id;
    }

    $this->costs = array();
    $this->types = array();
    foreach (DB::getAll(DB::T(DB::ACTIVE_TYPE)) as $type) {
      $this->types[] = $type->id;
      $this->costs[] = 10;
    }
  }

  public function testCsv() {
    $args = array(
      'seasons' => $this->seasons,
      'confs' => $this->conferences,
      'types' => $this->types,
      'costs' => $this->costs,
      'create' => "Create",
    );

    $response = $this->getUrl(self::$url, $args);
    $this->assertResponseStatus($response, 200);

    $head = $response->getHead();
    $this->assertEquals('application/octet-stream', $head->getHeader('Content-Type'));
    $this->assertRegExp('/^attachment/', $head->getHeader('Content-Disposition'));
  }
}