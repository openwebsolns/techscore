<?php
namespace pub;

use \AbstractUnitTester;
use \DB;
use \DBExpression;
use \DBM;
use \DBObject;
use \InvalidArgumentException;
use \Pub_File;
use \STN;

/**
 * Sanity test for InitJs.
 *
 * @author Dayan Paez
 * @version 2015-12-04
 */
class InitJsTest extends AbstractUnitTester {

  private $testObject;

  protected function setUp() {
    DB::setDbm(new InitJsTestDBM());
    InitJsTestDBM::resetForTest();
    $this->testObject = new InitJs();
  }

  public function testEverything() {
    // Setup
    $file1ToIgnore = new Pub_File();
    $file1ToIgnore->options = null;
    $file1ToIgnore->id = "FILE_1_TO_IGNORE.js";

    $file2ToIgnore = new Pub_File();
    $file2ToIgnore->options = array();
    $file2ToIgnore->id = "FILE_2_TO_IGNORE.js";

    $fileToInclude = new Pub_File();
    $fileToInclude->options = array(Pub_File::AUTOLOAD_SYNC);
    $fileToInclude->id = "FILE_TO_INCLUDE.js";

    InitJsTestDBM::setJsFiles(
      array(
        $file1ToIgnore,
        $file2ToIgnore,
        $fileToInclude,
      )
    );

    $GCSE = "GCSE_ID";
    $GA = "GOOGLE_ANALYTICS";
    $UVID = "USERVOICE_ID";
    $UVFORUM = "USERVOICE_FORUM";
    $FB_APP = "FACEBOOK_APP_ID";
    $TWITTER = "TWITTER_ID";
    $GPLUS = "GOOGLE_PLUS";
    InitJsTestDBM::setSettings(
      array(
        STN::GCSE_ID => $GCSE,
        STN::GOOGLE_ANALYTICS => $GA,
        STN::USERVOICE_ID => $UVID,
        STN::USERVOICE_FORUM => $UVFORUM,
        STN::FACEBOOK_APP_ID => $FB_APP,
        STN::TWITTER => $TWITTER,
        STN::GOOGLE_PLUS => $GPLUS,
      )
    );

    // Test!
    $file = fopen('php://memory', 'r+');
    $this->testObject->write($file);
    fseek($file, 0);
    $filedata = stream_get_contents($file);
    $this->assertContains($GCSE, $filedata);
    $this->assertContains($GA, $filedata);
    $this->assertContains($UVID, $filedata);
    $this->assertContains($UVFORUM, $filedata);
    $this->assertContains($FB_APP, $filedata);
    $this->assertContains('//platform.twitter.com', $filedata);
    $this->assertContains('apis.google.com/js/plusone.js', $filedata);

    $this->assertContains($fileToInclude->id, $filedata);
    $this->assertNotContains($file1ToIgnore->id, $filedata);
    $this->assertNotContains($file2ToIgnore->id, $filedata);

    // Test caching
    InitJsTestDBM::resetForTest();
    $file = fopen('php://memory', 'r+');
    $this->testObject->write($file);
    fseek($file, 0);
    $filedata2 = stream_get_contents($file);
    $this->assertEquals($filedata, $filedata2);
  }

  public function testAsyncFile() {
    // Setup
    $fileToInclude = new Pub_File();
    $fileToInclude->options = array(Pub_File::AUTOLOAD_ASYNC);
    $fileToInclude->id = "FILE_TO_INCLUDE.js";

    InitJsTestDBM::setJsFiles(array($fileToInclude));

    // Test!
    $file = fopen('php://memory', 'r+');
    $this->testObject->write($file);
    fseek($file, 0);
    $filedata = stream_get_contents($file);
    $this->assertContains($fileToInclude->id, $filedata);
  }

}

/**
 * Mock DBM.
 */
class InitJsTestDBM extends DBM {

  private static $jsFiles;
  private static $settings;

  public static function resetForTest() {
    self::setJsFiles(array());
    self::setSettings(array());
  }

  public static function setJsFiles(Array $files) {
    self::$jsFiles = $files;
  }

  public static function setSettings(Array $settingsById) {
    self::$settings = $settingsById;
  }

  public static function getAll(DBObject $obj, DBExpression $cond = null, $limit = null) {
    if ($obj instanceof Pub_File) {
      return self::$jsFiles;
    }
    throw new InvalidArgumentException(
      sprintf("Did not expect ::getAll(%s).", get_class($obj))
    );
  }

  public static function get(DBObject $obj, $id) {
    if ($obj instanceof STN) {
      if (array_key_exists($id, self::$settings)) {
        $stn = new STN();
        $stn->id = $id;
        $stn->value = self::$settings[$id];
        return $stn;
      }
      return null;
    }
    throw new InvalidArgumentException(
      sprintf("Did not expect ::get(%s,%s).", get_class($obj), $id)
    );
  }

}