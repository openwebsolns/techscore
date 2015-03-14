<?php

require_once('AbstractTester.php');

/**
 * Test the CompareHeadToHead report.
 *
 * @author Dayan Paez
 * @version 2015-03-12
 */
class CompareHeadToHeadTest extends AbstractTester {

  /**
   * Season arguments to head to head
   */
  private $seasons;
  private static $url = '/compare-sailors';

  protected function setUp() {
    $this->seasons = array();
    foreach (Season::getActive() as $season) {
      $this->seasons[] = $season->id;
    }
  }

  public function testSingleSailor() {
    // Grab a sailor
    $sailors = DB::getAll(
      DB::T(DB::SAILOR),
      new DBBool(
        array(
          new DBCond('icsa_id', null, DBCond::NE),
          new DBCondIn('id', DB::prepGetAll(DB::T(DB::DT_RP), null, array('sailor')))
        )
      )
    );

    if (count($sailors) == 0) {
      $this->markTestSkipped("There are no sailors available with participation.");
      return;
    }

    $sailor = $sailors[rand(0, count($sailors) - 1)];
    $args = array(
      'seasons' => $this->seasons,
      'boat_role' => null,
      'sailor' => array($sailor->id),
    );

    $response = $this->getUrl(self::$url, $args);
    $this->assertResponseStatus($response, 200);

    $body = $response->getBody();
    $tables = $body->xpath('//html:div[@class="port"]/html:table');
    $this->assertNotEmpty($tables, "No table within port found!");
  }
}