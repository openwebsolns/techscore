<?php
/*
 * This file is part of TechScore
 *
 * @package tscore/scripts
 */

/**
 * CLI-usage exceptions
 *
 * @author Dayan Paez
 * @version 2012-05-21
 */
class TSScriptException extends Exception {}


/**
 * Parent class for all CLI-enabled scripts.
 *
 * The class provides facilities for parsing CLI arguments, while
 * providing a centralized error handling scheme and a set of global
 * options.
 *
 * @author Dayan Paez
 * @created 2012-10-05
 */
abstract class AbstractScript {
  /**
   * @var int the level of verbosity (default 0 = no output)
   */
  protected static $verbosity = 0;
  /**
   * @var boolean print the filepath being written on standard output
   */
  private static $print_filenames = false;

  /**
   * Outputs the given message to standard error.
   *
   * Message is written only if current verbosity setting is at least
   * the one provided.
   *
   * @param String $mes the message to print (sans newlines)
   * @param int $threshold the minimum verbosity level needed
   */
  protected static function err($mes, $threshold = 1) {
    if (self::$verbosity >= $threshold) {
      for ($i = 0; $i < $threshold - 1; $i++)
        fwrite(STDERR, " ");
      fwrite(STDERR, $mes);
    }
  }

  /**
   * As with err, but append a new line automatically
   *
   */
  protected static function errln($mes, $threshold = 1) {
    self::err($mes . "\n", $threshold);
  }

  /**
   * Prints the given message if print filenames is set to on
   *
   * @param String $mes the message to print (sans newline)
   */
  protected static function out($mes) {
    if (self::$print_filenames) {
      fwrite(STDOUT, $mes);
      fwrite(STDOUT, "\n");
    }
  }

  /**
   * Sets how talkactive the script should be. (Default: quiet)
   *
   * @param int $level the verbosity level (higher means more output)
   */
  public static function setVerbosity($level = 0) {
    self::$verbosity = (int)$level;
  }

  /**
   * Set whether to print the name of the files affected
   *
   * This will print relative filenames of files written or removed.
   *
   * @param boolean $flag true to print (default)
   */
  public static function printFilenames($flag = true) {
    self::$print_filenames = ($flag !== false);
  }

  /**
   * Serializes the given document to the given filename, which
   * assumes that all the necessary parent directories have been
   * created.
   *
   * @param String $fname the full pathname of the file
   * @param Xmlable $p the document to serialize.
   * @see AbstractWriter::write
   */
  protected static function writeXml($fname, Xmlable $p) {
    $x = $p->toXML();
    self::writeFile($fname, $x);
  }

  /**
   * Writes the given contents to the given file.
   *
   * @param String $fname the name of the file
   * @param String $contents the contents
   * @see AbstractWriter::write
   */
  protected static function writeFile(&$fname, &$contents) {
    foreach (self::getWriters() as $writer)
      $writer->write($fname, $contents);
    self::out($fname);
  }

  /**
   * Removes the file tree rooted at the given filename
   *
   * @param String $fname the name of the file to remove
   * @see AbstractWriter::remove
   */
  protected static function remove($fname) {
    foreach (self::getWriters() as $writer)
      $writer->remove($fname);
    self::out($fname);
  }

  /**
   * Fetches list of writers to use
   *
   * @return Array:AbstractWriter the writers
   */
  protected static function &getWriters() {
    if (self::$writers === null) {
      self::$writers = array();
      foreach (Conf::$WRITERS as $writer) {
        require_once(sprintf('writers/%s.php', $writer));
        self::$writers[] = new $writer();
      }
    }
    return self::$writers;
  }

  /**
   * @var Array:AbstractWriter Cached lits of writer objects
   * @see getWriters
   */
  protected static $writers = null;

  // ------------------------------------------------------------
  // CLI features: provide a uniform usage method
  // ------------------------------------------------------------

  /**
   * Create a new generator. For CLI error handlers, see getOpts
   *
   * @see getOpts
   */
  public function __construct() {
  }

  // ------------------------------------------------------------
  // CLI exception handler catches TSScriptExceptions
  // ------------------------------------------------------------

  public function handle_exception(Exception $e) {
    if ($e instanceof TSScriptException)
      $this->usage($e->getMessage(), max($e->getCode(), 1));
    if ($this->cli_exception_handler !== null) {
      $h = $this->cli_exception_handler;
      $h($e);
    }
    exit($e->getCode());
  }

  /**
   * Process command line options (a la GNU getopts)
   *
   * Parses the "command line arguments" in $args, interpreting those
   * that apply "globally", such as -v (verbose) and -f (print
   * filenames). Returns a new list with all the uninternalized
   * arguments, in the same order as they appear originally.
   *
   * Note that the function will separate combined short-form
   * arguments (as in 'cp -rv' => -r -v).
   *
   * The script assumes that the first entry in $argv is the calling
   * script name; i.e. the first item in the list is ignored.
   *
   * This function will also set the CLI error/exception handlers
   *
   * @param Array $args the list of arguments
   * @return Array the parsed options
   */
  public function getOpts($argv) {
    $this->cli_exception_handler = set_exception_handler(array($this, 'handle_exception'));

    if (count($argv) == 0)
      return array();

    $this->cli_base = array_shift($argv);
    // Separate single options into tokens (a la getopt)
    $args = array();
    foreach ($argv as $i) {
      if (strlen($i) > 1 && $i[0] == '-' && $i[1] != '-') {
        for ($j = 1; $j < strlen($i); $j++)
          $args[] = '-'.$i[$j];
      }
      else
        $args[] = $i;
    }

    // Gobble up verbosity and others
    $verb = 0;
    $list = array();
    foreach ($args as $opt) {
      switch ($opt) {
      case '-h':
      case '--help':
        $this->usage(null, 0);
        break;

      case '-v':
      case '--verbose':
        $verb++;
        break;

      case '-f':
      case '--print-filename':
        self::printFilenames();
        break;

      default:
        $list[] = $opt;
      }
    }
    self::setVerbosity($verb);
    return $list;
  }

  /**
   * Display usage summary and exit the program.
   *
   * Subclasses should override the protected $usage variable to be
   * the String that gets displayed when this method is called.
   *
   * By default, this method will be called when triggered explictly
   * by client code (in response to an invalid flag, for example), or
   * automatically by AbstractGenerator::getOpts when the -h, --help
   * flags are encountered.
   *
   * Note that this method should only be called after ::getOpts, as
   * that method will extract the script name, which is then used in
   * the auto-generated usage message.
   *
   * @param String|null $mes the optional message
   * @param int $exit the exit code
   * @see $cli_base
   * @see $cli_usage
   * @see $cli_opts
   */
  public function usage($mes = null, $exit = 1) {
    if ($mes !== null)
      echo $mes, "\n\n";
    $base = $this->cli_base;
    if ($base === null)
      $base = 'Update*.php';
    printf("usage: %s [options] %s\n\n", $base, $this->cli_opts);
    if ($this->cli_usage !== null)
      echo $this->cli_usage, "\n\n";

    if ($mes === null) {
      echo "Global options:

 -v --verbose [+]     Increase communication on standard error
 -f --print-filename  Print names of files written to standard output
 -h --help            Print this message and exit
";
    }
    exit($exit);
  }

  /**
   * @var String the base of the CLI script. Automatically generated
   * by ::getOpts from $argv
   */
  protected $cli_base;
  /**
   * @var callable the previous exception  handler
   */
  protected $cli_exception_handler;
  /**
   * @var String the usage summary (e.g.: <department_id>. Note that
   * AbstractGenerator::usage automatically prepends the $cli_base.
   */
  protected $cli_opts;
  /**
   * @var String the help message to be displayed
   */
  protected $cli_usage;
}
?>