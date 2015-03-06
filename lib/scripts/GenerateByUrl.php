<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2015-02-23
 * @package scripts
 */

require_once('AbstractScript.php');
require_once('GeneratorArguments.php');

/**
 * Parses one or more given URLs and regens that resource.
 *
 * @author Dayan Paez
 * @created 2015-02-23
 */
class GenerateByUrl extends AbstractScript {

  /**
   * Attempt to generate the pages for the given slugs.
   *
   * @param Array $slugs the slugs to generate.
   */
  public function run(Array $slugs) {
    foreach ($slugs as $slug) {
      $res = $this->parse($slug);
      $res->execute();
    }
  }

  /**
   * Find the resources associated with given slug.
   *
   * @param String $slug a partial URL: /school/<ID>
   * @return GeneratorArguments
   * @throws TSScriptException
   */
  public function parse($slug) {
    $slug = trim($slug);

    // remove index.html
    $suf = 'index.html';
    $len = mb_strlen($suf);
    if (mb_strlen($slug) > $len && substr($slug, -1 * $len) == $suf) {
      $slug = substr($slug, 0, mb_strlen($slug) - $len);
    }

    if ($slug == '' || mb_substr($slug, 0, 1) != '/') {
      throw new TSScriptException("Invalid URL slug provided: \"$slug\".");
    }

    // Root level
    if ($slug == '/') {
      require_once('UpdateFront.php');
      return new GeneratorArguments(new UpdateFront());
    }
    if ($slug == '/404.html') {
      require_once('Update404.php');
      return new GeneratorArguments(new Update404(), array(true));
    }
    if ($slug == '/init.js') {
      require_once('UpdateFile.php');
      return new GeneratorArguments(new UpdateFile(), array(), 'runInitJs');
    }
    if ($slug == '/seasons/') {
      require_once('UpdateSeasonsSummary.php');
      return new GeneratorArguments(new UpdateSeasonsSummary());
    }
    if ($slug == '/schools/') {
      require_once('UpdateSchoolsSummary.php');
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runSchools');
    }
    if ($slug == '/sailors/') {
      require_once('UpdateSchoolsSummary.php');
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runSailors');
    }
    if ($slug == sprintf('/%s/', DB::g(STN::CONFERENCE_URL))) {
      require_once('UpdateSchoolsSummary.php');
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runConferences');
    }

    // Files
    $matches = array();
    if (preg_match(':^/inc/(img|css|png)/([^/]+)$:', $slug, $matches) == 1) {
      require_once('UpdateFile.php');
      return new GeneratorArguments(new UpdateFile(), $matches[2]);
    }
    if (preg_match(':^/inc/([^/]+)$:', $slug, $matches) == 1) {
      require_once('UpdateFile.php');
      return new GeneratorArguments(new UpdateFile(), $matches[1]);
    }

    // Individual season summaries
    if (preg_match(':^/([fsmw][0-9][0-9])/$:', $slug, $matches) == 1) {
      $season = DB::getSeason($matches[1]);
      if ($season === null) {
        throw new TSScriptException("Invalid season provided: $slug.");
      }
      require_once('UpdateSeason.php');
      return new GeneratorArguments(new UpdateSeason(), array($season));
    }

    // Not handled?
    throw new TSScriptException("Do not know how to generate slug: $slug.");
  }
}

// ------------------------------------------------------------
// When run as a script
if (isset($argv) && is_array($argv) && basename($argv[0]) == basename(__FILE__)) {
  require_once(dirname(dirname(__FILE__)).'/conf.php');

  $P = new GenerateByUrl();
  $opts = $P->getOpts($argv);
  $slugs = array();
  foreach ($opts as $opt) {
    $slugs[] = $opt;
  }

  if (count($slugs) == 0) {
    throw new TSScriptException("No slugs provided.");
  }
  $P->run($slugs);
}
?>