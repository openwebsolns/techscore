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
      return new GeneratorArguments(new UpdateFile(), array($matches[2]));
    }
    if (preg_match(':^/([^/.]+\.[a-z]+)$:', $slug, $matches) == 1) {
      require_once('UpdateFile.php');
      return new GeneratorArguments(new UpdateFile(), array($matches[1]));
    }

    // Subtree rooted at seasons
    if (preg_match(':^/([fsmw][0-9][0-9])/:', $slug, $matches) == 1) {
      return $this->parseSeasonTree($slug);
    }

    // Not handled?
    throw new TSScriptException("Do not know how to generate slug: $slug.");
  }

  /**
   * Helper method to parse slugs under, e.g. /f10/
   *
   * @param String $slug the full slug.
   * @return GeneratorArguments the arguments.
   * @throws TSScriptException if no season or invalid one provided.
   * @throws InvalidArgumentException if internal contract violated.
   */
  private function parseSeasonTree($slug) {
    $tokens = explode('/', $slug);
    array_shift($tokens);
    if ($tokens[count($tokens) - 1] == '') {
      array_pop($tokens);
    }
    if (count($tokens) < 1) {
      throw new InvalidArgumentException("Expected slug of the form /XNN/...");
    }

    $season = DB::getSeason(array_shift($tokens));
    if ($season === null) {
      throw new TSScriptException("Invalid season provided: $slug.");
    }

    // Season page itself
    if (count($tokens) == 0) {
      require_once('UpdateSeason.php');
      return new GeneratorArguments(new UpdateSeason(), array($season));
    }

    $regatta_url = array_shift($tokens);
    $regatta = $season->getRegattaWithUrl($regatta_url);
    if ($regatta === null) {
      throw new TSScriptException("No regatta with slug: $regatta_url.");
    }

    // TODO: differentiate between subresources?
    require_once('UpdateRegatta.php');
    return new GeneratorArguments(
      new UpdateRegatta(),
      array($regatta, array(UpdateRequest::ACTIVITY_DETAILS))
    );
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