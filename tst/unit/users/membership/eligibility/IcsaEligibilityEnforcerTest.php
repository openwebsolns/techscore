<?php
namespace users\membership\eligibility;

use \model\StudentProfile;
use \Season;

use \AbstractUnitTester;

/**
 * Test logic of the ICSA eligibility enforcer.
 */
class IcsaEligibilityEnforcerTest extends AbstractUnitTester {

  private $testObject;

  protected function setUp() {
    $this->testObject = new IcsaEligibilityEnforcer();
    DB::setDbm(new IcsaEligibilityEnforcerDBM());
    IcsaEligibilityEnforcerDBM::resetForTest();
  }

  public function testNullEligibilityStart() {
    $profile = new StudentProfile();
    $seasons = array(new Season(), new Season());

    $results = $this->testObject->calculateEligibleSeasons($profile, $seasons);
    $this->assertEquals(2, count($results));

    foreach ($results as $i => $result) {
      $this->assertSame($seasons[$i], $result->getSeason());
      $this->assertEquals(EligibilityResult::STATUS_OK, $result->getStatus());
      $this->assertNull($result->getReason());
    }
  }

  public function testSinceEligibilityStart() {
    $seasons = array(
      
    );
    $profile = new StudentProfile();
  }
}

class IcsaEligibilityEnforcerDBM extends DBM {

  
}