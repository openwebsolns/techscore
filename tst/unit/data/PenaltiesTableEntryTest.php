<?php
namespace data;

use \AbstractUnitTester;
use \BadFunctionCallException;

use \model\ReducedWinsPenalty;
use \Division;
use \DivisionPenalty;
use \Finish;
use \FinishModifier;
use \Penalty;
use \Race;
use \Regatta;
use \School;
use \Score;
use \Team;

/**
 * Tests the creation and sorting of these entries.
 *
 * @author Dayan Paez
 * @version 2017-05-02
 */
class PenaltiesTableEntryTest extends AbstractUnitTester {

  /**
   * @expectedException BadFunctionCallException
   */
  public function testMagicGetter() {
    $testObject = new PenaltiesTableEntry();
    $testObject->foo;
    $this->fail('Expected BadFunctionCallException when asking for "foo".');
  }

  public function testFromFinishModifier() {
    $score = new Score(17, "Bad handling");
    $finish = new Finish();
    $finish->race = new Race();
    $finish->team = new Team();
    $finish->earned = 12;
    $finish->score = $score;

    $modifier = new FinishModifier();
    $modifier->finish = $finish;
    $modifier->amount = 8;
    $modifier->type = Penalty::DSQ;
    $modifier->comments = "TestComments";

    $testObject = PenaltiesTableEntry::fromFinishModifier($modifier);
    $this->assertSame($finish->race, $testObject->race);
    $this->assertSame($finish->team, $testObject->team);
    $this->assertNull($testObject->division);
    $this->assertEquals($modifier->type, $testObject->type);
    $this->assertContains((string) $finish->earned, $testObject->amount);
    $this->assertContains((string) $score->score, $testObject->amount);
    $this->assertEquals($modifier->comments, $testObject->comments);
  }

  public function testFromDivisionPenaltyTeamRacing() {
    $regatta = new Regatta();
    $regatta->scoring = Regatta::SCORING_TEAM;

    $penalty = new DivisionPenalty();
    $penalty->team = new Team();
    $penalty->division = Division::C();
    $penalty->type = DivisionPenalty::LOP;
    $penalty->comments = "TestComments";

    $testObject = PenaltiesTableEntry::fromDivisionPenalty($penalty, $regatta);
    $this->assertNull($testObject->race);
    $this->assertSame($penalty->team, $testObject->team);
    $this->assertSame($penalty->division, $testObject->division);
    $this->assertEquals($penalty->type, $testObject->type);
    $this->assertContains('-2', $testObject->amount);
    $this->assertContains('+2', $testObject->amount);
    $this->assertEquals($penalty->comments, $testObject->comments);
  }

  public function testFromDivisionPenaltyStandardRacing() {
    $regatta = new Regatta();
    $regatta->scoring = Regatta::SCORING_STANDARD;
    $penalty = new DivisionPenalty();
    $penalty->division = Division::B();

    $testObject = PenaltiesTableEntry::fromDivisionPenalty($penalty, $regatta);
    $this->assertContains('20', $testObject->amount);
  }

  public function testFromReducedWinsPenalty() {
    $penalty = new ReducedWinsPenalty();
    $penalty->team = new Team();
    $penalty->race = new Race();
    $penalty->amount = 0.75;
    $penalty->comments = "TestComments";

    $testObject = PenaltiesTableEntry::fromReducedWinsPenalty($penalty);
    $this->assertNull($testObject->division);
    $this->assertSame($penalty->team, $testObject->team);
    $this->assertSame($penalty->race, $testObject->race);
    $this->assertEquals('Discretionary', $testObject->type);
    $this->assertContains((string) $penalty->amount, $testObject->amount);
    $this->assertEquals($penalty->comments, $testObject->comments);
  }

  public function testSortByRace() {
    $modifier = new FinishModifier();
    $modifier->finish = new Finish();
    $modifier->finish->race = new Race();
    $modifier->finish->race->number = 3;
    $modifier->finish->race->division = Division::B();
    $pen1 = PenaltiesTableEntry::fromFinishModifier($modifier);

    $rwPenalty1 = new ReducedWinsPenalty();
    $rwPenalty1->race = new Race();
    $rwPenalty1->race->number = 5;
    $rwPenalty1->race->division = Division::A();
    $pen2 = PenaltiesTableEntry::fromReducedWinsPenalty($rwPenalty1);

    $divPenalty = new DivisionPenalty();
    $divPenalty->division = Division::A();
    $pen3 = PenaltiesTableEntry::fromDivisionPenalty($divPenalty, new Regatta());

    $rwPenalty2 = new ReducedWinsPenalty();
    $rwPenalty2->race = null;
    $pen4 = PenaltiesTableEntry::fromReducedWinsPenalty($rwPenalty2);

    $input = [
      $pen4,
      $pen2,
      $pen1,
      $pen3,
    ];

    usort($input, PenaltiesTableEntry::compareCallback());
    $this->assertSame($pen1, $input[0]);
    $this->assertSame($pen2, $input[1]);
    $this->assertSame($pen3, $input[2]);
    $this->assertSame($pen4, $input[3]);
  }

  public function testSortByTeam() {
    $src = new ReducedWinsPenalty();
    $src->team = new Team();
    $src->team->name = "A";
    $src->team->school = new School();
    $src->team->school->name = "School";
    $pen1 = PenaltiesTableEntry::fromReducedWinsPenalty($src);

    $src = new ReducedWinsPenalty();
    $src->team = new Team();
    $src->team->name = "B";
    $src->team->school = new School();
    $src->team->school->name = "School";
    $pen2 = PenaltiesTableEntry::fromReducedWinsPenalty($src);

    $src = new ReducedWinsPenalty();
    $src->team = new Team();
    $src->team->name = "C";
    $src->team->school = new School();
    $src->team->school->name = "School";
    $pen3 = PenaltiesTableEntry::fromReducedWinsPenalty($src);

    $input = [
      $pen3,
      $pen2,
      $pen1,
    ];

    usort($input, PenaltiesTableEntry::compareCallback());
    $this->assertSame($pen1, $input[0]);
    $this->assertSame($pen2, $input[1]);
    $this->assertSame($pen3, $input[2]);
  }
}