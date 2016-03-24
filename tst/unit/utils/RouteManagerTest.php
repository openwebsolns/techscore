<?php
namespace utils;

use \AbstractUnitTester;
use \DB;

/**
 * Test the route manager thingy.
 *
 * @author Dayan Paez
 * @version 2016-03-24
 */
class RouteManagerTest extends AbstractUnitTester {

  public function testCreateIsAvailableCallbackForSettings() {
    // desperate for unit testing, we scour the current list of
    // settings, hoping to find one that is ON and one that is OFF. I
    // know, this is deplorable!
    $offSetting = null;
    $onSetting = null;
    foreach (DB::getSettingNames() as $setting) {
      $val = DB::g($setting);
      if ($val === null) {
        $offSetting = $setting;
      }
      else {
        $onSetting = $setting;
      }
      if ($offSetting && $onSetting) {
        break;
      }
    }

    if ($offSetting === null || $onSetting === null) {
      $this->markTestSkipped("No OFF=$offSetting or ON=$onSetting to test.");
      return;
    }

    $callback = RouteManager::createIsAvailableCallbackForSettings(array($offSetting, $onSetting));
    $this->assertTrue(is_callable($callback));
    $this->assertTrue(call_user_func($callback));

    $callback = RouteManager::createIsAvailableCallbackForSettings(array($offSetting, $offSetting));
    $this->assertFalse(call_user_func($callback));
  }

}