<?php
namespace utils;

use \InvalidArgumentException;
use \utils\callbacks\IsAvailableCallback;

/**
 * A utility class to fetch route tables.
 *
 * Any class that wants to consume the manager (such as AbstractPane or its
 * children) need create just one instance of the object, and load onto it
 * the structure to manage. Then, the client class can fetch the necessary
 * information via the accessor methods provided.
 *
 * @author Dayan Paez
 * @version 2015-03-29
 */
class RouteManager {

  /**
   * The permissible keys for each classname map.
   */
  const URLS = 'urls';
  const NAME = 'name';
  const PERMISSIONS = 'permissions';
  /**
   * The "namespace" of the given pane as a directory structure under lib.
   */
  const PATH = 'path';
  /**
   * Callback to determine whether a pane is generally available.
   * Unlike restrictions based on permissions, this possibly null
   * property allows toggling general visibility of a pane, for
   * instance when the underlying feature serviced by that pane is not
   * enabled for a given installation.
   */
  const IS_AVAILABLE_CALLBACK = 'is-available-callback';

  /**
   * Registered "routes" available; to use in loadFile method.
   */
  const ROUTE_USER = 'user';

  /**
   * List of properties that may be null, and therefore optional.
   */
  private static $OPTIONAL_PROPERTIES = array(
    self::PATH,
    self::IS_AVAILABLE_CALLBACK,
  );

  /**
   * @var Array list, indexed by classname, loaded via 'load' methods.
   */
  private $structure;

  /**
   * Load a table from a given JSON-encoded string.
   *
   * The structure should be a map where the classname is the key and
   * the value is another map with the following keys:
   *
   *   - urls (array of possible URLs)
   *   - name (string: title to use)
   *   - permissions (array of necessary permissions)
   *   - path (possibly null to autoload by namespace)
   *   - is-available-callback (possibly null)
   *
   * @param Array $map the structure to load.
   */
  public function load(Array $map) {
    $this->structure = $map;
  }

  /**
   * Load a table from the given filename.
   *
   * The set of possible filenames are those found in the routes
   * subdirectory. No directory or .php suffix should be provided.
   *
   * @param String $filename the basename of the file to load.
   * @throws InvalidArgumentException if invalid file provided.
   */
  public function loadFile($filename) {
    $name = sprintf('%s/routes/%s.php', __DIR__, $filename);
    if (!file_exists($name)) {
      throw new InvalidArgumentException("File $name does not exist.");
    }
    $map = require($name);
    if (!is_array($map)) {
      throw new InvalidArgumentException("File $name does not contain a valid route structure.");
    }
    $this->load($map);
  }

  /**
   * Ascertains that value provided matches expected type for key.
   *
   * @param String $key one of the class constants.
   * @param mixed $value the value to validate
   * @return mixed the value provided.
   * @throws InvalidArgumentException if invalid.
   */
  private function validateValue($key, $value) {
    if (in_array($key, self::$OPTIONAL_PROPERTIES) && $value === null) {
      return $value;
    }
    if (($key == self::NAME || $key == self::PATH) && is_string($value)) {
      return $value;
    }
    if (($key == self::PERMISSIONS || $key == self::URLS) && is_array($value)) {
      return $value;
    }
    if ($key == self::IS_AVAILABLE_CALLBACK && $value instanceof IsAvailableCallback) {
      return $value;
    }
    throw new InvalidArgumentException("Invalid type in structure for " . $key);
  }

  /**
   * Gets the requested property for the given classname.
   *
   * Clients are encouraged to use one of the direct accessor methods.
   *
   * @param String $classname one of the keys of loaded map.
   * @param Const $key one of the class constants.
   * @return mixed the given property (string or array)
   * @throws InvalidArgumentException if invalid classname or key.
   */
  public function getProperty($classname, $key) {
    if ($this->structure === null) {
      throw new InvalidArgumentException("RouteManager not properly initialized.");
    }
    if ($key != self::NAME
        && $key != self::URLS
        && $key != self::PERMISSIONS
        && $key != self::IS_AVAILABLE_CALLBACK
        && $key != self::PATH) {
      throw new InvalidArgumentException("Invalid key requested: " . $key);
    }
    if (!array_key_exists($classname, $this->structure)) {
      throw new InvalidArgumentException("No routes exist for class " . $classname);
    }
    $table = $this->structure[$classname];
    if (!array_key_exists($key, $table)) {
      if (in_array($key, self::$OPTIONAL_PROPERTIES)) {
        return null;
      }
      throw new InvalidArgumentException(
        sprintf(
          "Malformed structure loaded for %s: key \"%s\" not found.",
          $classname,
          $key));
    }
    return $this->validateValue($key, $table[$key]);
  }

  /**
   * Return the name property for given classname.
   *
   * @param String $classname one of the keys of loaded map.
   * @return String the name.
   * @throws InvalidArgumentException if invalid classname.
   */
  public function getName($classname) {
    return $this->getProperty($classname, self::NAME);
  }

  /**
   * Return the path property for given classname.
   *
   * @param String $classname one of the keys of loaded map.
   * @return String the path.
   * @throws InvalidArgumentException if invalid classname.
   */
  public function getPath($classname) {
    return $this->getProperty($classname, self::PATH);
  }

  /**
   * Return the permissions property for given classname.
   *
   * @param String $classname one of the keys of loaded map.
   * @return Array the permissions.
   * @throws InvalidArgumentException if invalid classname.
   */
  public function getPermissions($classname) {
    return $this->getProperty($classname, self::PERMISSIONS);
  }

  public function getIsAvailableCallback($classname) {
    return $this->getProperty($classname, self::IS_AVAILABLE_CALLBACK);
  }

  /**
   * Return the urls property for given classname.
   *
   * @param String $classname one of the keys of loaded map.
   * @return Array the urls.
   * @throws InvalidArgumentException if invalid classname.
   */
  public function getUrls($classname) {
    return $this->getProperty($classname, self::URLS);
  }

  /**
   * Creates a full URL using the first valid for given classname.
   *
   * @param String $classname the key to find in the map.
   * @param Context $context replaces known keys with those from context.
   * @return String the formed URL.
   * @throws InvalidArgumentException if none valid found.
   */
  public function makeUrl($classname, Context $context) {
    $urls = $this->getUrls($classname);
    if (count($urls) == 0) {
      throw new InvalidArgumentException("No URLs found for " . $classname);
    }
    $url = $urls[0];
    $school = $context->getSchool();
    if ($school !== null) {
      $url = str_replace(':school', $school->id, $url);
    }
    return $url;
  }

  /**
   * Return the first classname with given URL.
   *
   * @param String $url the URL to fetch.
   * @return String the classname or null.
   */
  public function getClassnameFromUrl($url) {
    foreach ($this->structure as $classname => $map) {
      if (in_array($url, $this->getUrls($classname))) {
        return $classname;
      }
    }
    return null;
  }

}