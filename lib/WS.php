<?php
/**
 * WebServer redirection and linking utility. This static class
 * provides a common API through which to create links and move to
 * pages. At the very least, it facilitates the process of navigation
 * by folding the two lines usually required into one:
 *
 * <code>
 * // old method
 * header('Location: http://www.fiu.edu');
 * exit;
 *
 * // now becomes
 * WS::go('http://www.fiu.edu');
 * </code>
 *
 * WS also respects short-hand redirection techniques, although it is
 * still the role of the site administrator to provide for the
 * resolution of those URLs.
 *
 * @author Dayan Paez
 * @created 2011-06-02
 * @package php
 */
class WS {

  /**
   * @var boolean determine whether to keep the '.php' at the end of
   * links and redirects. This will also attempt to fold certain
   * GET variables into the URL.
   *
   * For backward compatibility, these variables are 'd' (becomes "=")
   * and 'r' (becomes "|"). This can be changed, see
   * <code>fold()</code>
   */
  public static $use_rewrite = false;

  /**
   * @var String the root of the site to prepend to all links. By
   * default, this is blank. This is a useful setting if the site is
   * moved to a different subfolder of a domain. Thus, links that used
   * to be to '/inc/css/default.css', will now point to the correct
   * URL '/program/inc/css/default.css'
   */
  public static $root = '';

  private static $folds = array('d'=>'=', 'r'=>'|');
  private static $default = 'index';

  /**
   * Replace the given GET variables by the provided equivalent when
   * writing the links. Provide the variables as an ordered
   * associative array. For instance, to get the default behavior of
   * translating:
   *
   * <pre>
   * school.php?r=bar&d=foo&other=great  =>  school=foo|bar?other=great
   * </pre>
   *
   * use the following call
   *
   * <code>
   * WS::fold( array( 'd'=>'=', 'r'=>'|' ) );
   * </code>
   *
   * Note that this is only applicable if using rewrite.
   *
   * @param Array $keys the ordered associative array of replacements.
   * Provide an empty array to unset
   */
  public static function fold(Array $keys = array()) {
    self::$folds = $keys;
  }

  /**
   * When going to a resource whose basename (without the extension)
   * matches the given index, the value will be stripped. This way,
   * the URL becomes, e.g. /path/to/ instead of /path/to/index.php
   *
   * The default value is 'index' which covers index.*
   *
   * @param String $index the base to ignore. Set to '' for no
   * replacement.
   */
  public static function setIndex($index = '') {
    self::$default = $index;
  }

  /**
   * Attempts to return to the referer, otherwise goes to the
   * default page specified.
   *
   * 2011-06-16: do not redirect back to 'myself'
   *
   * @param String $url the default url to go to if no referrer found
   * @param boolean $same_host if true, respect the REFERER field only
   * if it matches the same HOST as the current one.
   */
  public static function goBack($url, $same_host = false) {
    if (isset($_SERVER['HTTP_REFERER']) && isset($_SERVER['HTTP_HOST']) &&
        $_SERVER['HTTP_REFERER'] != WS::alink($_SERVER['REQUEST_URI'])) {
      if ($same_host === false)
        WS::go($_SERVER['HTTP_REFERER']);

      $sub = sprintf('%s://%s%s',
                     ($_SERVER['HTTPS'] == 'on') ? 'https' : 'http',
                     $_SERVER['HTTP_HOST'],
                     self::$root);
      if (substr($_SERVER['HTTP_REFERER'], 0, strlen($sub)) == $sub)
        WS::go($_SERVER['HTTP_REFERER']);
    }
    WS::go($url);
  }

  /**
   * Go to different location, by calling link on the parameters given.
   *
   * @param String $url the url to format, WITHOUT any GET fields
   * @param Array $args the optional query string to build
   * @param String $anchor the optional page anchor '#anchor'
   * @see link
   */
  public static function go($url, Array $args = array(), $anchor = '') {
    header('Location: ' . self::link($url, $args, $anchor));
    exit;
  }

  /**
   * Generate a text link according to the global rules. If the URL
   * starts with a protocol signature, then no rewriting is done
   *
   * Otherwise, the URL is treated as 'relative', and the following
   * rules are applied: the $root variable is prepended to the URL to
   * resolve the absolute URL relative to the current domain. Then, if
   * rewriting is requested, the extension is dropped, and the
   * appropriate folding takes place, if applicable.
   *
   * @param String $url the url to format, WITHOUT any GET fields
   * @param Array $args the optional query string to build
   * @param String $anchor the optional page anchor '#anchor'
   * @return String the formatted url
   */
  public static function link($url, Array $args = array(), $anchor = '') {
    if (preg_match('_^[a-z]+://_', $url)) {
      if (count($args) > 0)
        $url .= '?'.http_build_query($args);
      $url .= $anchor;
      return $url;
    }

    if (self::$use_rewrite === true) {
      $url = preg_replace('/\.php$/', '', $url);
      if ($url == self::$default)
        $url = '';

      foreach (self::$folds as $key => $rep) {
        if (isset($args[$key])) $url .= $rep . $args[$key];
        unset($args[$key]);
      }
    }
    if (count($args) > 0)
      $url .= '?' . http_build_query($args);
    $url .= $anchor;

    return self::$root . $url;
  }

  /**
   * Create an (a)bsolute link, by default pointing to this host (as
   * returned by $_SERVER['HTTP_HOST']. Set the optional argument to
   * use a different host instead.
   *
   * It is a good idea to feed the output of WS::link to this function
   * in order to create a complete URL.
   *
   * @param String $url the URL to link to (not altered)
   *
   * @param String $other_host if non-null, use that instead of the
   * current host, as returned by $_SERVER['HTTP_HOST'] to build the
   * full link.
   *
   * @param String $protocol if non-null, use that protocol (http,
   * https, etc) instead of the default one.
   *
   * @return String the full url
   */
  public static function alink($url, $other_host = null, $protocol = null) {
    $host = ($other_host !== null) ? $other_host : $_SERVER['HTTP_HOST'];
    if ($protocol === null)
      $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http';
    return sprintf('%s://%s%s', $protocol, $host, $url);
  }

  /**
   * Attempts to reverse the given URL, undoing the process from
   * link. For instance, this method will strip away the root from the
   * given URL, and it will also attempt to undo any rewriting that
   * may have taken place (if $use_rewrite is true).
   *
   * Note that this method will also remove anchors from the URL.
   *
   * @param String $url the rewritten url
   * @param boolean $strip_query set to true to undo full rewrite,
   * including the query string, anchor, and possible extension
   *
   * @return String the full URL
   */
  public static function unlink($url, $strip_query = false) {
    if (preg_match('_^[a-z]+://_', $url) == 1)
      return $url;

    $url = substr($url, strlen(self::$root));
    if ($strip_query === false)
      return $url;

    // also remove any anchor
    if (($anc = strpos($url, '#')) !== false)
      $url = substr($url, 0, $anc);

    // also remove any query string
    if (($anc = strpos($url, '?')) !== false)
      $url = substr($url, 0, $anc);

    // undo rewriting
    if (self::$use_rewrite) {
      $url = strrev($url);
      foreach (array_reverse(self::$folds) as $key => $rep) {
        if (($anc = strpos($url, $rep)) !== false)
          $url = substr($url, $anc + 1);
      }
      $url = strrev($url);
      if ($url[strlen($url) - 1] == '/') $url = self::$default;

      // add extension if none found
      if (preg_match('/\.[a-z0-9]+$/', $url) == 0)
        $url .= '.php';
    }
    return $url;
  }
}
?>