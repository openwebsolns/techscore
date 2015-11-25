<?php
namespace scripts;

use \xml5\TPublic404Page;

use \DB;
use \DBObject;
use \InvalidArgumentException;
use \STN;
use \Season;
use \TSScriptException;
use \UpdateRequest;

/**
 * Parses one or more given URLs and regens that resource.
 *
 * @author Dayan Paez
 * @created 2015-02-23
 */
class GenerateByUrl extends AbstractScript {

  const SEASON_REGEXP = '[fsmw][0-9][0-9]';

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
   * Merely parses, and reports on errored ones, without quitting.
   *
   * @param Array $slug the slugs to parse.
   */
  public function runParse(Array $slugs) {
    foreach ($slugs as $slug) {
      try {
        $res = $this->parse($slug);
        $args = array();
        foreach ($res->getParameters() as $arg) {
          if ($arg instanceof DBObject) {
            $args[] = sprintf('%s:%s', get_class($arg), $arg->id);
          }
        }
        printf(
          "%-15s\t%s\t%s\t%s\n",
          get_class($res->getGenerator()),
          $res->getMethod(),
          implode(', ', $args),
          $slug
        );
      }
      catch (TSScriptException $e) {
        printf("ERROR\t%s\t%s\n", $slug, $e->getMessage());
      }
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
      throw new TSScriptException(sprintf("Invalid URL slug provided: \"%s\".", $slug));
    }

    // Root level
    if ($slug == '/') {
      return new GeneratorArguments(new UpdateFront());
    }
    if ($slug == '/404.html') {
      return new GeneratorArguments(new Update404(), array(TPublic404Page::MODE_GENERAL));
    }
    if ($slug == '/init.js') {
      return new GeneratorArguments(new UpdateFile(), array(), 'runInitJs');
    }
    if ($slug == '/seasons/') {
      return new GeneratorArguments(new UpdateSeasonsSummary());
    }
    if ($slug == '/sailors/') {
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runSailors');
    }
    if ($slug == sprintf('/%s/', DB::g(STN::CONFERENCE_URL))) {
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runConferences');
    }

    // Files
    $matches = array();
    if (preg_match(':^/inc/(img|css|png)/([^/]+)$:', $slug, $matches) == 1) {
      return new GeneratorArguments(new UpdateFile(), array($matches[2]));
    }
    if (preg_match(':^/([^/.]+\.[a-z]+)$:', $slug, $matches) == 1) {
      return new GeneratorArguments(new UpdateFile(), array($matches[1]));
    }

    // Subtree rooted at seasons
    if (preg_match(sprintf(':^/(%s)/:', self::SEASON_REGEXP), $slug, $matches) == 1) {
      return $this->parseSeasonTree($slug);
    }

    // Subtree rooted at schools
    if (preg_match(':^/schools/:', $slug, $matches) == 1) {
      return $this->parseSchoolTree($slug);
    }

    // Subtree rooted at sailors
    if (preg_match(':^/sailors/:', $slug, $matches) == 1) {
      return $this->parseSailorTree($slug);
    }

    // Not handled?
    throw new TSScriptException("Do not know how to generate slug: $slug.");
  }

  /**
   * Helper method to parse slugs under, e.g. /schools/
   *
   * @param String $slug the full slug.
   * @return GeneratorArguments the arguments.
   * @throws TSScriptException if no season or invalid one provided.
   * @throws InvalidArgumentException if internal contract violated.
   */
  private function parseSchoolTree($slug) {
    if ($slug == '/schools/404.html') {
      return new GeneratorArguments(new Update404(), array(TPublic404Page::MODE_SCHOOL));
    }

    $tokens = explode('/', $slug);
    array_shift($tokens);
    if ($tokens[count($tokens) - 1] == '') {
      array_pop($tokens);
    }
    if (count($tokens) < 1 || $tokens[0] != 'schools') {
      throw new InvalidArgumentException("Expected slug of the form /schools/...");
    }
    array_shift($tokens);

    if (count($tokens) == 0) {
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runSchools');
    }

    $school_url = array_shift($tokens);
    $school = DB::getSchoolByUrl($school_url);
    if ($school === null) {
      throw new TSScriptException(sprintf("Invalid school URL provided: %s.", $school_url));
    }

    // With no season, use current
    $season = Season::forDate(DB::T(DB::NOW));
    if (count($tokens) > 0) {
      if (preg_match(sprintf('/^%s$/', self::SEASON_REGEXP), $tokens[0]) == 1) {
        $season = DB::getSeason($tokens[0]);
        array_shift($tokens);
      }
    }

    if ($season === null) {
      throw new TSScriptException(sprintf("Unable to parse %s: No season (or no current one).", $slug));
    }

    // Roster?
    $method = 'run';
    if (count($tokens) > 0 && $tokens[0] == 'roster') {
      array_shift($tokens);
      $method = 'runRoster';
    }

    if (count($tokens) > 0) {
      throw new TSScriptException(
        sprintf(
          "Don't know what to do with tail end of slug: %s.",
          implode('/', $tokens)
        )
      );
    }

    return new GeneratorArguments(new UpdateSchool(), array($school, $season), $method);
  }

  /**
   * Helper method to parse slugs under, e.g. /sailors/
   *
   * @param String $slug the full slug.
   * @return GeneratorArguments the arguments.
   * @throws TSScriptException if no season or invalid one provided.
   * @throws InvalidArgumentException if internal contract violated.
   */
  private function parseSailorTree($slug) {
    if (DB::g(STN::SAILOR_PROFILES) === null) {
      throw new TSScriptException("Sailor profile feature is diabled.");
    }

    $tokens = explode('/', $slug);
    array_shift($tokens);
    if ($tokens[count($tokens) - 1] == '') {
      array_pop($tokens);
    }
    if (count($tokens) < 1 || $tokens[0] != 'sailors') {
      throw new InvalidArgumentException("Expected slug of the form /sailors/...");
    }
    array_shift($tokens);

    if (count($tokens) == 0) {
      return new GeneratorArguments(new UpdateSchoolsSummary(), array(), 'runSailors');
    }

    $sailor_url = array_shift($tokens);
    $sailor = DB::getSailorByUrl($sailor_url);
    if ($sailor === null) {
      throw new TSScriptException(sprintf("Invalid sailor URL provided: %s.", $sailor_url));
    }

    // With no season, use current
    $season = Season::forDate(DB::T(DB::NOW));
    if (count($tokens) > 0) {
      if (preg_match(sprintf('/^%s$/', self::SEASON_REGEXP), $tokens[0]) == 1) {
        $season = DB::getSeason($tokens[0]);
        array_shift($tokens);
      }
    }

    if ($season === null) {
      throw new TSScriptException(sprintf("Unable to parse %s: No season (or no current one).", $slug));
    }

    if (count($tokens) > 0) {
      throw new TSScriptException(
        sprintf(
          "Don't know what to do with tail end of slug: %s.",
          implode('/', $tokens)
        )
      );
    }

    return new GeneratorArguments(new UpdateSailor(), array($sailor, $season));
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
      throw new TSScriptException(sprintf("Invalid season provided: %s.", $slug));
    }

    // Season page itself
    if (count($tokens) == 0) {
      return new GeneratorArguments(new UpdateSeason(), array($season));
    }

    $regatta_url = array_shift($tokens);
    $regatta = $season->getRegattaWithUrl($regatta_url);
    if ($regatta === null) {
      throw new TSScriptException(sprintf("No regatta with slug: %s.", $regatta_url));
    }

    // TODO: differentiate between subresources?
    return new GeneratorArguments(
      new UpdateRegatta(),
      array($regatta, array(UpdateRequest::ACTIVITY_DETAILS))
    );
  }

  public function runCli(Array $argv) {
    $opts = $this->getOpts($argv);
    $parseOnly = false;
    $slugs = array();
    foreach ($opts as $opt) {
      if ($opt == '--parse') {
        $parseOnly = true;
        continue;
      }
      $slugs[] = $opt;
    }

    if (count($slugs) == 0) {
      throw new TSScriptException("No slugs provided.");
    }
    if ($parseOnly) {
      $this->runParse($slugs);
    }
    else {
      $this->run($slugs);
    }
  }

  protected $cli_opts = '[--parse] url1 [url2...]';
  protected $cli_usage = 'Write the resources identified by the given URLs slugs.

The URLs provided should be of the form "/slug/path/" with an optional index.html
at the end.

  --parse   Do not generate, just parse and print the result.';
}
