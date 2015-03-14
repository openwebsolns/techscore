<?php

require_once('AbstractTester.php');

/**
 * Tests the MembershipReport.
 *
 * @author Dayan Paez
 * @version 2015-03-13
 */
class MembershipReportTest extends AbstractTester {

  private $seasons;
  private $conferences;
  private $types;
  private static $url = '/membership';

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

    $this->types = array();
    foreach (DB::getAll(DB::T(DB::ACTIVE_TYPE)) as $type) {
      $this->types[] = $type->id;
    }
  }

  public function testCsv() {
    $args = array(
      'seasons' => $this->seasons,
      'confs' => $this->conferences,
      'types' => $this->types,
      'create' => "Create",
    );

    $response = $this->getUrl(self::$url, $args);
    $this->assertResponseStatus($response, 200);

    $head = $response->getHead();
    $this->assertEquals('application/octet-stream', $head->getHeader('Content-Type'));
    $this->assertRegExp('/^attachment/', $head->getHeader('Content-Disposition'));
  }
}