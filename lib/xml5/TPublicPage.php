<?php
/*
 * This file is part of TechScore
 *
 * @package xml5
 */

require_once('xml5/TS.php');

/**
 * Public page for scores, written in the new and improved HtmlLib
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class TPublicPage extends XPage {

  private $filled;
  /**
   * @var Array:Xmlable elements to add to the menu, in order
   */
  private $menu;
  /**
   * @var Array:Xmlable elements to add to the announce div, in order
   */
  private $announce;
  /**
   * @var Array:Xmlable elements to add to the content div
   */
  private $content;

  /**
   * @var the header title, if any, for the given page
   * @see setHeader
   */
  private $header_title;
  /**
   * @var Array the map of attributes to use for the header_title
   */
  private $header_attrs;
  /**
   * @var Array the associative array of name-value pairs. These will
   * be added as a table under the $header_title
   * @see setHeader
   */
  private $header_table;

  /**
   * @var String the title of the page
   */
  private $title;

  /**
   * @var String the meta-description for the page
   */
  private $description;

  /**
   * @var Array the list of meta-keywords for the page
   */
  private $keywords;

  /**
   * @var XDiv the body div where the specific content is populated
   */
  private $page_content;

  /**
   * Creates a new public page with the given title
   *
   * @param String $title the title of the page
   */
  public function __construct($title) {
    parent::__construct($title . " | ICSA Real-Time Regatta Results");

    $this->setDoctype(self::HTML_5);
    $this->filled = false;
    $this->menu = array();
    $this->content = array();
    $this->announce = array();

    $this->header_title = null;
    $this->header_attrs = array();
    $this->header_table = array();

    $this->keywords = array("regatta", "results", "scores", "icsa", "sailing");
    $this->title = $title;
  }

  /**
   * Fills the content of this page only once, according to the status
   * of the variable 'filled'
   *
   */
  private function fill() {
    if ($this->filled) return;

    // Section separator
    $sep = new XHR(array('class'=>'nav'));

    // Stylesheets
    $this->head->add(new XMetaHTTP('content-type', 'text/html;charset=UTF-8'));
    $this->head->add(new XMeta('generator', "OpenWeb Solutions, LLC"));
    if ($this->description !== null)
      $this->head->add(new XMeta('description', $this->description));
    if (count($this->keywords) > 0)
      $this->head->add(new XMeta('keywords', implode(',', $this->keywords)));
    foreach ($this->getCSS() as $css)
      $this->head->add(new LinkCSS($css, 'screen,print'));
    $this->head->add(new LinkCSS('/inc/css/print.css', 'print'));

    // Add Google Analytics code
    $this->head->add(new XScript('text/javascript', null,
                                 "var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-5316542-1']);
  _gaq.push(['_setDomainName', 'collegesailing.org']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();"));

    // Twitter summary cards
    $this->head->add(new XMeta('twitter:card', 'summary'));
    $this->head->add(new XMeta('twitter:title', $this->title));
    $this->head->add(new XMeta('twitter:description', $this->description));
    $this->head->add(new XMeta('twitter:image', sprintf('http://%s/inc/img/icsa.png', Conf::$PUB_HOME)));

    if (Conf::$GCSE_ID !== null)
      $this->head->add(new XScript('text/javascript', sprintf('//www.google.com/cse/cse.js?cx=%s', Conf::$GCSE_ID)));

    // Navigation
    $this->body->add(new XDiv(array('class'=>'nav'),
                              array(new XH3("Navigate"),
                                    new XUl(array(),
                                            array(new XLi(new XA('#menu', "Menu")),
                                                  new XLi(new XA('#page-content', "Content")),
                                                  new XLi(new XA('#sponsors', "Our sponsors")))))));
    $this->body->add($sep);

    // Header
    $this->body->add($div = new XDiv(array('id'=>'page-header')));
    $div->add(new XDiv(array('id'=>'top-wrapper'),
                       array(new XDiv(array('id'=>'last_updated'), array(date('M j, Y @ H:i:s'))),
                             $sc = new XDiv(array('id'=>'social')),
                             $sw = new XDiv(array('id'=>'search-wrapper')))));
    if (Conf::$FACEBOOK !== null)
      $sc->add(new XA(sprintf('http://www.facebook.com/%s', Conf::$FACEBOOK), new XImg('/inc/img/fb.png', "FB")));
    if (Conf::$TWITTER !== null) {
      $sc->add(new XA(sprintf('http://www.twitter.com/%s', Conf::$TWITTER), new XImg('/inc/img/tw.png', "Twitter")));
      $this->head->add(new XMeta('twitter:site', '@' . Conf::$TWITTER));
    }
    if (Conf::$GCSE_ID !== null)
      $sw->add(new XDiv(array('class'=>'gcse-search')));

    $div->add(new XDiv(array('id'=>'menu-wrapper'),
                       array(new XH3("Menu", array('class'=>'nav')),
                             $menu = new XUl(array('id'=>'menu')))));

    // Fill menu, splitting in half, and wedging a link to the home
    // page in between
    $count = count($this->menu);
    $stop = ceil($count / 2);
    for ($i = 0; $i < $stop; $i++)
      $menu->add(new XLi($this->menu[$i]));
    $menu->add(new XLi(new XImg('/inc/img/logo.png', "ICSA Burgee"), array('id'=>'logo')));
    for ($i = $stop; $i < $count; $i++)
      $menu->add(new XLi($this->menu[$i]));

    $this->body->add($sep);

    // Page content
    $this->page_content = new XDiv(array('id'=>'page-content'));
    $this->body->add($this->page_content);
    if ($this->header_title !== null) {
      $this->page_content->add($sub = new XDiv(array('id'=>'content-header'), array($h1 = new XH1(""))));
      $h1->add(new XSpan("", array('id'=>'left-fill')));
      $h1->add(new XSpan($this->header_title, $this->header_attrs));
      $h1->add(new XSpan("", array('id'=>'right-fill')));

      if (count($this->header_table) > 0) {
        $sub->add($tab = new XTable(array('id'=>'page-info')));
        foreach ($this->header_table as $key => $val)
          $tab->add(new XTR(array(), array(new XTH(array(), $key), new XTD(array(), $val))));
      }
    }

    // Sections
    foreach ($this->content as $sub)
      $this->page_content->add($sub);

    $this->body->add($sep);

    // Footer
    $this->body->add(new XDiv(array('id'=>'page-footer'),
                              array(new XDiv(array('id'=>'sponsors'),
                                             array(new XH3("Our sponsors"),
                                                   new XUl(array('id'=>'sponsors-list'),
                                                           array(new XLi(new XA('http://gillna.com', new XImg('/inc/img/sponsors/gill.png', "Gill"))),
                                                                 new XLi(new XA('http://www.apsltd.com', new XImg('/inc/img/sponsors/aps.png', "APS"))),
                                                                 new XLi(new XA('http://www.sperrytopsider.com/', new XImg('/inc/img/sponsors/sperry-gray.png', "Sperry Top-Sider"))),
                                                                 new XLi(new XA('http://www.laserperformance.com/', new XImg('/inc/img/sponsors/laserperformance.png', "LaserPerformance"))),
                                                                 new XLi(new XA('http://www.ussailing.org/', new XImg('/inc/img/sponsors/ussailing.png', "US Sailing"))),
                                                                 new XLi(new XA('http://www.quantumsails.com/', new XImg('/inc/img/sponsors/qtag.png', "Quantum Sails"))))))),

                                    new XAddress(array(), array(new XA('http://www.openweb-solutions.net', Conf::$COPYRIGHT))))));

    $this->filled = true;
  }

  /**
   * Appends the given element to the content of the page
   *
   * @param Xmlable the element to add
   */
  public function addSection(Xmlable $elem) {
    $this->content[] = $elem;
  }

  /**
   * Appends the given element to the menu
   *
   * @param Xmlable $elem the element to add
   */
  public function addMenu($elem) {
    $this->menu[] = $elem;
  }

  /**
   * Append element to announce (ul) list
   *
   * @param Xmlable $elem any argument fit for XLi
   */
  public function addAnnounce($elem) {
    $this->announce[] = $elem;
  }

  /**
   * Sets the title for the content of the page
   *
   * @param String $title the title to use
   * @param Array $table assoc. array for the #page-info
   */
  public function setHeader($title, Array $table = array(), Array $attrs = array()) {
    $this->header_title = (string)$title;
    $this->header_table = $table;
    $this->header_attrs = $attrs;
  }

  /**
   * Set description for page.
   *
   * @param String $desc the description (null to remove)
   */
  public function setDescription($desc = null) {
    $this->description = $desc;
  }

  /**
   * Adds the meta keyword to the given page
   *
   * @param String $word the keyword to add to the list
   */
  public function addMetaKeyword($word) {
    $this->keywords[] = $word;
  }

  /**
   * Delays the creation of the page and returns it as a string
   *
   * @return String the page
   */
  public function toXML() {
    $this->fill();
    return parent::toXML();
  }

  /**
   * Delays the creation of the page and echoes it to standard outpout
   *
   */
  public function printXML() {
    $this->fill();
    parent::printXML();
  }

  /**
   * Get the "body" of the page
   *
   */
  public function getPageContent() {
    $this->fill();
    return $this->page_content;
  }

  /**
   * Fetch list of CSS links for the page
   *
   * Subclasses should use this method to customize the CSS list.
   *
   * @return Array:URLs the links to the CSS files
   */
  protected function getCSS() {
    return array('/inc/css/icsa.css');
  }
}
?>