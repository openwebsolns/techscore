<?php
/**
 * This file is part of TechScore.
 *
 * This script, which can be run from the command line or
 * programmatically, will serialize the data about the schools in the
 * database to the file '/cache/schools.db', in the hopes that it can
 * be used by the public site's 404 page for better navigation.
 *
 * @author Dayan Paez
 * @created 2011-05-30
 * @package cache
 */
class GenerateSchools {

  const VERSION = "1.0";
  const XMLNS = 'http://www.collegesailing.info/schemas/schools.db';

  /**
   * Generate the local cache document
   *
   * @return XDoc the XML document
   */
  public static function getCache() {
    require_once('xml5/XmlLib.php');
    require_once('mysqli/DB.php');
    DBME::setConnection(Preferences::getConnection());

    $doc = new XDoc('SchoolDatabase', array('version' => self::VERSION, 'xmlns' => self::XMLNS));
    $doc->add($conf_elem = new XElem('Conferences'));
    $doc->add($schl_elem = new XElem('Schools'));
    $confs = array();
    foreach (DBME::getAll(DBME::$SCHOOL) as $school) {
      if (!isset($confs[$school->conference->id])) {
	$confs[$school->conference->id] = $school->conference;
	$conf_elem->add(new XElem('Conference', array('id'=>$school->conference->id),
				  array(new XText($school->conference->name))));
      }
      $schl_elem->add(new XElem('School', array('id'=>$school->id, 'conference' => $school->conference->id),
				array(new XElem('Name', array(), array(new XText($school->name))),
				      new XElem('NickName', array(), array(new XText($school->nick_name))),
				      new XElem('City', array(), array(new XText($school->city))),
				      new XElem('State', array(), array(new XText($school->state))))));
    }
    return $doc;
  }

  /**
   * Generate the local cache document and save it to file,
   * /cache/schools.db.
   *
   * @throws RuntimeException if something goes wrong
   */
  public static function run() {
    $R = realpath(dirname(__FILE__).'/../../cache');
    if ($R === false)
      throw new RuntimeException("Cache folder does not exist.");

    $doc = GenerateSchools::getCache();
    if (@file_put_contents("$R/schools.db", $doc->toXML()) === false)
      throw new RuntimeException(sprintf("Unable to make the schools database cache: %s\n", $filename), 8);
  }
}

if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  try {
    GenerateSchools::run();
  }
  catch (Exception $e) {
    printf("(EE|%d) %s\n", $e->getCode(), $e->getMessage());
    exit(1);
  }
}
?>