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
   * @var String the URL of the image to use in the Twitter card. If
   * not provided, then the logo will be used
   *
   * @see setTwitterImage
   */
  private $twitter_image;

  /**
   * @var String the URL to use for a Facebook "Like" button
   */
  private $facebook_like;

  /**
   * @var Array map of the OpenGraph properties, if any.
   *
   * Keys 'url' and 'type' are required in order for open graph
   * information to be included
   */
  private $opengraph_props = array();

  /**
   * @var boolean true to include row for social plugins
   */
  private $add_social_plugins = false;

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
    $this->head->add(new XMetaHTTP('X-UA-Compatible', 'IE=Edge'));
    $this->head->add(new XMeta('generator', "OpenWeb Solutions, LLC"));
    if ($this->description !== null)
      $this->head->add(new XMeta('description', $this->description));
    if (count($this->keywords) > 0)
      $this->head->add(new XMeta('keywords', implode(',', $this->keywords)));
    foreach ($this->getCSS() as $css)
      $this->head->add(new LinkCSS($css, 'screen,print'));

    $this->head->add(new XScript('text/javascript', '/inc/js/mobile-switch.js'));

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
    $url = $this->twitter_image;
    if ($url === null)
      $url = sprintf('http://%s/inc/img/icsa.png', Conf::$PUB_HOME);
    $this->head->add(new XMeta('twitter:image', $url));

    // Open graph
    if (isset($this->opengraph_props['url']) && isset($this->opengraph_props['type'])) {
      $this->head->set('prefix', 'og: http://ogp.me/ns#');
      $this->head->add(new XElem('meta', array('property'=>'og:title', 'content'=>$this->title)));
      $this->head->add(new XElem('meta', array('property'=>'og:description', 'content'=>$this->description)));
      $this->head->add(new XElem('meta', array('property'=>'og:site_name', 'content'=>"ICSA Real-Time Regatta Results")));
      $this->head->add(new XElem('meta', array('property'=>'og:url', 'content'=>$this->opengraph_props['url'])));
      $this->head->add(new XElem('meta', array('property'=>'og:type', 'content'=>$this->opengraph_props['type'])));
      if (isset($this->opengraph_props['image']))
        $urrl = $this->opengraph_props['image'];
      $this->head->add(new XElem('meta', array('property'=>'og:image', 'content'=>$url)));

      foreach ($this->opengraph_props as $key => $val) {
        if ($key != 'url' && $key != 'type' && $key != 'image')
          $this->head->add(new XElem('meta', array('property'=>$key, 'content'=>$val)));
      }
    }

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
    if (DB::g(STN::FACEBOOK) !== null) {
      $lnk = "Facebook";
      $img = DB::getFile('fb.png');
      if ($img !== null)
        $lnk = $img->asImg('/inc/img/fb.png', $lnk);
      $sc->add(new XA(sprintf('http://www.facebook.com/%s', DB::g(STN::FACEBOOK)), $lnk));
    }

    if (DB::g(STN::TWITTER) !== null) {
      $lnk = "Twitter";
      $img = DB::getFile('tw.png');
      if ($img !== null)
        $lnk = $img->asImg('/inc/img/tw.png', $lnk);
      $sc->add(new XA(sprintf('http://www.twitter.com/%s', DB::g(STN::TWITTER)), $lnk));
      $this->head->add(new XMeta('twitter:site', '@' . DB::g(STN::TWITTER)));
    }

    if (DB::g(STN::FLICKR_NAME) !== null) {
      $lnk = "Flickr";
      $img = DB::getFile('flickr.png');
      if ($img !== null)
        $lnk = $img->asImg('/inc/img/flickr.png', $lnk);
      $sc->add(new XA(sprintf('//www.flickr.com/photos/%s', DB::g(STN::FLICKR_NAME)), $lnk));
    }

    if (DB::g(STN::GCSE_ID) !== null) {
      $this->head->add(new XScript('text/javascript', sprintf('//www.google.com/cse/cse.js?cx=%s', DB::g(STN::GCSE_ID)), null, array('async'=>'async', 'defer'=>'defer')));
      $sw->add(new XDiv(array('class'=>'gcse-search')));
    }

    $div->add(new XDiv(array('id'=>'menu-wrapper'),
                       array(new XH3("Menu", array('class'=>'nav')),
                             $menu = new XUl(array('id'=>'menu')))));

    // Fill menu, splitting in half, and wedging a link to the home
    // page in between
    $count = count($this->menu);
    $stop = ceil($count / 2);
    for ($i = 0; $i < $stop; $i++)
      $menu->add(new XLi($this->menu[$i]));

    $lnk = "ICSA";
    $img = DB::getFile('logo.png');
    if ($img !== null)
      $lnk = $img->asImg('/inc/img/logo.png', $lnk);
    $menu->add(new XLi($lnk, array('id'=>'logo')));
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

      // Social plugins?
      if ($this->add_social_plugins) {
        $td = new XTD(array('id'=>'social-wrapper', 'colspan'=>2));
        $has_social = false;
        if (DB::g(STN::FACEBOOK_APP_ID) !== null && $this->facebook_like !== null) {
          $has_social = true;
          $td->add(new XDiv(array('id'=>'fb-wrapper'),
                            array(new XDiv(array('id'=>'fb-root')),
                                  new XDiv(array('class'=>'fb-like',
                                                 'data-href'=>$this->facebook_like,
                                                 'data-width'=>450,
                                                 'data-layout'=>'button_count',
                                                 'data-show-faces'=>'false',
                                                 'data-send'=>'false')))));
        
          $this->head->add($scr = new XScript('text/javascript', sprintf('//connect.facebook.net/en_US/all.js#xfbml=1&appId=%s', DB::g(STN::FACEBOOK_APP_ID)), null, array('async'=>'async', 'defer'=>'defer')));
          $scr->set('id', 'facebook-jssdk');
        }
        if (DB::g(STN::TWITTER) !== null) {
          $has_social = true;

          $lnk = "Tweet";
          $img = DB::getFile('tw.png');
          if ($img !== null)
            $lnk = $img->asImg('/inc/img/tw.png', $lnk);

          $td->add(new XDiv(array('id'=>'twitter-wrapper'), array(new XA('https://twitter.com/share', $lnk, array('class'=>'twitter-share-button', 'data-via'=>'ICSAscores')))));
          // data-hashtags
          $this->head->add($scr = new XScript('text/javascript', '//platform.twitter.com/widgets.js'));
          $scr->set('id', 'twitter-wjs');
        }

        if ($has_social)
          $tab->add(new XTR(array(), array($td)));
      }
    }

    // Sections
    foreach ($this->content as $sub)
      $this->page_content->add($sub);

    $this->body->add($sep);

    // UserVoice
    if (DB::g(STN::USERVOICE_ID) !== null && DB::g(STN::USERVOICE_FORUM) !== null) {
      $this->head->add(new XScript('text/javascript', sprintf('//widget.uservoice.com/%s.js', DB::g(STN::USERVOICE_ID)), null, array('async'=>'async', 'defer'=>'defer')));
      $this->head->add(new XScript('text/javascript', null,
                                   sprintf('
UserVoice = window.UserVoice || [];
UserVoice.push(["showTab", "classic_widget", {
  mode: "feedback",
  primary_color: "#6C6D6F",
  link_color: "#3465a4",
  forum_id: %d,
  tab_label: "Feedback",
  tab_color: "#6c6d6f",
  tab_position: "bottom-left",
  tab_inverted: true
}]);
', DB::g(STN::USERVOICE_FORUM))));
    }

    // Footer
    $this->body->add($foot = new XDiv(array('id'=>'page-footer')));

    // Sponsors
    $sponsors = array(array('http://gillna.com', 'gill.png', "Gill"),
                      array('http://www.apsltd.com', 'aps.png', "APS"),
                      array('http://www.sperrytopsider.com/', 'sperry-gray.png', "Sperry Top-Sider"),
                      array('http://www.laserperformance.com/', 'laserperformance.png', "LaserPerformance"),
                      array('http://www.ussailing.org/', 'ussailing.png', "US Sailing"),
                      array('http://www.quantumsails.com/', 'qtag.png', "Quantum Sails"));
    if (count($sponsors) > 0) {
      $foot->add(new XDiv(array('id'=>'sponsors'),
                          array(new XH3("Our sponsors"),
                                $slist = new XUl(array('id'=>'sponsors-list')))));

      foreach ($sponsors as $sponsor) {
        $img = new XSpan($sponsor[2]);
        $file = DB::getFile($sponsor[1]);
        if ($file !== null)
          $img = $file->asImg('/inc/img/' . $file->id, $sponsor[2]);
        $slist->add(new XLi(new XA($sponsor[0], $img)));
      }
    }

    // Copyright
    $foot->add(new XAddress(array(), array(new XA('http://www.openweb-solutions.net', Conf::$COPYRIGHT))));

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
   * Set the URL to use for the Twitter Summary Card.
   *
   * The URL should be a fully defined.
   *
   * @param String $url the URL, or null to reset
   */
  public function setTwitterImage($url = null) {
    $this->twitter_image = $url;
  }

  /**
   * Include a Facebook Like button?
   *
   * @param String $url the URL to use, or null for none
   */
  public function setFacebookLike($url = null) {
    $this->facebook_like = $url;
  }

  /**
   * Map of open-graph protocol data.
   *
   * Keys 'url' and 'type' must be provided in order for the map to be
   * used.
   *
   * @param Array $props thet property map
   */
  public function setOpenGraphProperties(Array $props = array()) {
    $this->opengraph_props = $props;
  }

  public function addSocialPlugins($flag = true) {
    $this->add_social_plugins = ($flag !== false);
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