<?php
/**
 * Update the given regatta, given as an argument. This update entails
 * checking the regatta against the database and updating either its
 * rotation, its score or both. In addition, if the regatta is not
 * meant to be published, this will also attempt to delete that
 * regatta's folder and contents. In short, a full update.
 *
 * e.g.: php UpdateScore 491 score
 * e.g.: php UpdateScore 490 # which does both
 *
 * @author Dayan Paez
 * @version 2010-08-27
 */
class UpdateRegatta {

  /**
   * Deletes the given regatta's information from the public site.
   * Note that this method is automatically called from
   * <pre>runScore</pre> and <pre>runRotation</pre> if the rotation
   * type is "personal". This method does not touch the season page.
   *
   * @param Regatta $reg the regatta whose information to delete.
   */
  public static function runDelete(Regatta $reg) {
    $R = realpath(dirname(__FILE__).'/../../html');
    $season = $reg->get(Regatta::SEASON);
    $dirname = "$R/$season/" . $reg->get(Regatta::NICK_NAME);
    if (is_dir($dirname) && $dir = @opendir($dirname)) {
      // Delete contents of dir
      while (false !== ($file = readdir($dir)))
	@unlink(sprintf('%s/%s', $dirname, $file));
      // Delete directory
      closedir($dir);
      rmdir($dirname);
    }
  }

  /**
   * Updates the score page(s) for the given regatta.
   *
   */
  public static function runScore(Regatta $reg) {
    if ($reg->get(Regatta::TYPE) == "personal") {
      self::runDelete($reg);
      return;
    }

    $R = realpath(dirname(__FILE__).'/../../html');
    $M = new ReportMaker($reg);
    $season = $reg->get(Regatta::SEASON);
    if (!file_exists("$R/$season") && mkdir("$R/$season") === false)
      throw new RuntimeException(sprintf("Unable to make the season folder: %s\n", $season), 2);

    $dirname = "$R/$season/".$reg->get(Regatta::NICK_NAME);
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException("Unable to make regatta directory: $dirname\n", 4);

    $filename = "$dirname/index.html";
    if (@file_put_contents($filename, $M->getScoresPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta report: %s\n", $filename), 8);
  }

  /**
   * Updates the rotation page for this regatta. (This might include
   * deleting an existing one if rotations not available).
   *
   */
  public static function runRotation(Regatta $reg) {
    if ($reg->get(Regatta::TYPE) == "personal") {
      self::runDelete($reg);
      return;
    }

    $M = new ReportMaker($reg);
    if (!$M->hasRotation()) {
      throw new RuntimeException(sprintf("Regatta %s (%d) does not have a rotation!",
					 $reg->get(Regatta::NAME), $reg->id()), 8);
    }

    $R = realpath(dirname(__FILE__).'/../../html');
    $season = $reg->get(Regatta::SEASON);
    if (!file_exists("$R/$season") && mkdir("$R/$season") === false)
      throw new RuntimeException(sprintf("Unable to make the season folder: %s\n", $season), 2);

    $dirname = "$R/$season/".$reg->get(Regatta::NICK_NAME);
    if (!file_exists($dirname) && mkdir($dirname) === false)
      throw new RuntimeException("Unable to make regatta directory: $dirname\n", 4);
    
    $filename = "$dirname/rotations.html";
    if (@file_put_contents($filename, $M->getRotationPage()) === false)
      throw new RuntimeException(sprintf("Unable to make the regatta report: %s\n", $filename), 8);

    // If there's already in index.html, update that one too.
    if (file_exists("$dirname/index.html"))
      self::runScore($reg);
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  // Arguments
  if (count($argv) < 2 || count($argv) > 3) {
    printf("usage: %s <regatta-id> [score|rotation]\n", $_SERVER['PHP_SELF']);
    exit(1);
  }
  // SETUP PATHS and other CONSTANTS
  $_SERVER['HTTP_HOST'] = $argv[0];
  ini_set('include_path', ".:".realpath(dirname(__FILE__).'/../'));
  require_once('conf.php');

  $action = UpdateRequest::getTypes();
  if (isset($argv[2])) {
    if (!in_array($argv[2], $action)) {
      printf("Invalid update action requested: %s\n\n", $argv[2]);
      printf("usage: %s <regatta-id> [score|rotation]\n", $_SERVER['PHP_SELF']);
      exit(1);
    }
    $action = array($argv[2]);
  }

  // GET REGATTA
  try {
    $REGATTA = new Regatta($argv[1]);
  }
  catch (InvalidArgumentException $e) {
    printf("Invalid regatta ID provided: %s\n", $argv[1]);
    exit(2);
  }
  foreach ($action as $act) {
    if ($act == UpdateRequest::ACTIVITY_SCORE) {
      try {
	UpdateRegatta::runScore($REGATTA);
	error_log(sprintf("I/0/%s\t(%d): Successful!\n", date('r'), $REGATTA->id()), 3, LOG_SCORE);
      }
      catch (RuntimeException $e) {
	error_log(sprintf("E/%d/%s\t(%d): %s\n", $e->getCode(), date('r'), $argv[1], $e->getMessage()),
		  3, LOG_SCORE);
      }
    }
    elseif ($act == UpdateRequest::ACTIVITY_ROTATION) {
      try {
	UpdateRegatta::runRotation($REGATTA);
	error_log(sprintf("I/0/%s\t(%d): Successful!\n", date('r'), $REGATTA->id()), 3, LOG_ROTATION);
      }
      catch (RuntimeException $e) {
	error_log(sprintf("E/%d/%s\t(%d): %s\n", $e->getCode(), date('r'), $argv[1], $e->getMessage()),
		  3, LOG_ROTATION);
      }
    }
  }
}
?>
