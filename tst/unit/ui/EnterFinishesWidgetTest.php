<?php
namespace ui;

use \AbstractUnitTester;
use \Breakdown;
use \Conf;
use \FItem;
use \LinkCSS;
use \Penalty;
use \TScorePage;

require_once('xml5/TScorePage.php');

/**
 * Test the beautiful widget.
 *
 * @author Dayan Paez
 * @version 2015-11-02
 */
class EnterFinishesWidgetTest extends AbstractUnitTester {

  public function testUsingSails() {
    $options = array(1, 2, 3, 4);
    $label = "Sail #";

    $testObject = new EnterFinishesWidget($label, $options);
    $testObject->addPlace(4, Breakdown::BYE);
    $testObject->addPlace(null, null);
    $testObject->addPlace(2, Penalty::DNF);
    $testObject->addPlace(1, null);

    $page = new TScorePage("Table");
    $page->head->add(new LinkCSS('https://' . Conf::$HOME . '/inc/css/default.css', 'screen'));
    $page->addContent(
      new FItem("Enter sails:", $testObject)
    );
    $xml = $page->toXML();
  }

  public function testSizing() {
    $expectedSizing = array(
      7  => array(4, 2),
      8  => array(4, 2),
      12 => array(6, 2),
      13 => array(5, 3),
      15 => array(5, 3),
      19 => array(7, 3),
    );
    foreach ($expectedSizing as $i => $expectedSize) {
      $options = array_fill(0, $i, 'X');
      $testObject = new EnterFinishesWidget("Test", $options);
      $cols = $testObject->getColumnsCount();
      $rows = $testObject->getRowsCount();

      $this->assertEquals($expectedSize[0], $rows);
      $this->assertEquals($expectedSize[1], $cols);
    }
  }
}