<?php
/**
 * A DIV to be used with pagination. This page REQUIRES use of WS
 *
 * @author Dayan Paez
 * @version 2010-06-17
 * @package xml
 */
class LinksDiv extends XDiv {

  /**
   * Creates a new DIV with links to as many pages as specified,
   * highlighting the current page, and using the specified fields to
   * customize the links.
   *
   * The 'base' text is the base part of the URL to use in the links,
   * such as 'search.php'. Defaults to '.' or current directory. This
   * field will be used by WS to then build the link.
   *
   * The 'args' array will be used to create the links and the hidden
   * inputs in the form to skip to any page. This array is similar to
   * $_GET and will be fed to http_build_query in order to form the
   * ordinate part of the link.
   *
   * The 'key' text is the name to give the variable which determines
   * what page to move to, defaults to 'r' for backwards
   * compatibility.
   *
   * Note that the resulting div (with class 'page-nav'), writes links
   * that are 1-based.
   *
   * As a concrete example, calling:
   *
   * <code>
   * $a = new LinksDiv(10, 4, 'search', array('query'=>'foo'));
   * echo $a->toXML();
   * </code>
   *
   * will generate code like this (indented for clarity):
   *
   * <pre>
   * &lt;div class='page-nav'&gt;
   *   &lt;a href='search?query=foo&amp;r=0'&gt;1&lt;/a&gt;
   *   &lt;a href='search?query=foo&amp;r=2'&gt;3&lt;/a&gt;
   *   &lt;a href='search?query=foo&amp;r=3' class='current'&gt;4&lt;/a&gt;
   *   &lt;a href='search?query=foo&amp;r=4'&gt;5&lt;/a&gt;
   *   &lt;a href='search?query=foo&amp;r=9'&gt;10&lt;/a&gt;
   *
   *   &lt;form action='search' method='get'&gt;
   *     &lt;input type='hidden' name='query' value='foo'/&gt;
   *     &lt;input type='text'   name='r' value='4'/&gt;
   *   &lt;/form&gt;
   * &lt;/div>
   * </pre>
   *
   * @param int $number the total number of pages
   * @param int $current the current page number (optional). Outside
   * of range [0, $number) is equivalent to not specifying anything at all
   * @param String $base the base of the page
   * @param Array $args the $_GET arguments to attach
   * @param String $key the key to use for the page number in the $_GET
   * @param String $anchor the text to attach literally at the end of the URL
   */
  public function __construct($number,
                              $cur = -1,
                              $base = '.',
                              Array $args = array(),
                              $key = 'r',
                              $anchor = '') {
    parent::__construct(array('class'=>'page-nav'));
    $num = (int)$number;
    $cur = (int)$cur;

    if ($num <= 1) return; // print nothing

    $fmt = '%s?%s%s';
    // ALWAYS print the first page
    $args[$key] = 1;
    $this->add($href = new XA(WS::link($base, $args, $anchor), 1));
    if ($cur == 1) $href->set('class', 'current');

    if ($cur > 3) $this->add(new XText(' '));
    for ($i = max(2, $cur - 1); $i < min($num, $cur + 2); $i++) {
      $args[$key] = $i;
      $this->add($href = new XA(WS::link($base, $args, $anchor), $i));
      if ($i == $cur)
        $href->set('class', 'current');
    }
    if ($i < $num) $this->add(new XText(' '));
    // ALWAYS print the last page
    $args[$key] = $num;
    $this->add($href = new XA(WS::link($base, $args, $anchor), $num));
    if ($cur == $num) $href->set('class', 'current');

    // Form
    unset($args[$key]);
    $this->add(new XForm(WS::link($base, array(), $anchor), XForm::GET, array(), array($f = new XP())));
    $f->add('Jump:');
    foreach ($args as $k => $value)
      $f->add(new XHiddenInput($k, $value));
    $f->add(new XNumberInput($key, $cur, 1, $num, 1, array('size'=>'2')));
    $f->add(new XSubmitInput('go', "Go"));
  }
}
?>