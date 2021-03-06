<?php
use \model\PublicData;

require_once('xml5/TS.php');

/**
 * Public page for scores, written in the new and improved HtmlLib
 *
 * @author Dayan Paez
 * @version 2011-03-06
 */
class TPublicPage extends XPage {

  const METAKEY_TS_DATA = 'ts:data';

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
   * @var PublicData the associated metadata, if any
   */
  private $public_data;

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
    parent::__construct(sprintf("%s | %s Real-Time Regatta Results", $title, DB::g(STN::ORG_NAME)));

    $this->setDoctype(self::HTML_5);
    $this->filled = false;
    $this->menu = array();
    $this->content = array();
    $this->announce = array();

    $this->header_title = null;
    $this->header_attrs = array();
    $this->header_table = array();

    $this->keywords = array("regatta", "results", "scores", "sailing");
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
    if ($this->public_data !== null) {
      $this->head->add(new XMeta(self::METAKEY_TS_DATA, $this->public_data->toJson()));
    }
    foreach ($this->getCSS() as $css)
      $this->head->add(new LinkCSS($css, 'screen,print'));

    $this->head->add(new XScript('text/javascript', '/init.js'));

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
      $this->head->add(new XElem('meta', array('property'=>'og:site_name', 'content'=>sprintf("%s Real-Time Regatta Results", DB::g(STN::ORG_NAME)))));
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
                                    $nav = new XUl(array(),
                                                   array(new XLi(new XA('#menu', "Menu")),
                                                         new XLi(new XA('#page-content', "Content")))))));
    $this->body->add($sep);

    // Header
    $this->body->add($div = new XDiv(array('id'=>'page-header')));
    $div->add(new XDiv(array('id'=>'top-wrapper'),
                       array(new XDiv(array('id'=>'last_updated'), array(date('M j, Y @ H:i:s T'))),
                             $sc = new XDiv(array('id'=>'social')),
                             new XDiv(array('id'=>'search-wrapper'),
                                      array(new XDiv(array('class'=>'gcse-search')))))));

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

    if (DB::g(STN::PAYPAL_HOSTED_BUTTON_ID) !== null) {
      $sc->add($this->createPayPalForm());
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

    $lnk = DB::g(STN::ORG_NAME);
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
        $sub->add($ul = new XUl(array('id'=>'page-info')));
        foreach ($this->header_table as $key => $val)
          $ul->add(new XLi(array(new XSpan($key, array('class'=>'page-info-key')),
                                 new XSpan($val, array('class'=>'page-info-value')))));
      }

      // Social plugins?
      if ($this->add_social_plugins) {
        $td = new XDiv(array('id'=>'social-wrapper'));
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
        }
        if (DB::g(STN::TWITTER) !== null) {
          $has_social = true;

          $lnk = "Tweet";
          $img = DB::getFile('tw.png');
          if ($img !== null)
            $lnk = $img->asImg('/inc/img/tw.png', $lnk);

          $td->add(new XDiv(array('id'=>'twitter-wrapper'), array(new XA('https://twitter.com/share', $lnk, array('class'=>'twitter-share-button', 'data-via'=>DB::g(STN::TWITTER))))));
          // data-hashtags
        }
        if (DB::g(STN::GOOGLE_PLUS) !== null) {
          $has_social = true;

          $td->add(new XDiv(array('id'=>'gplus-wrapper', 'class'=>'g-plusone', 'data-size'=>'medium')));
        }
        if (DB::g(STN::PAYPAL_HOSTED_BUTTON_ID) !== null) {
          $has_social = true;

          $td->add($this->createPayPalForm());
        }

        if ($has_social)
          $sub->add($td);
      }
    }

    // Sections
    foreach ($this->content as $sub)
      $this->page_content->add($sub);

    $this->body->add($sep);

    // Footer
    $this->body->add($foot = new XDiv(array('id'=>'page-footer')));

    // Sponsors
    $sponsors = Pub_Sponsor::getSponsorsForSite();
    if (count($sponsors) > 0) {
      $nav->add(new XLi(new XA('#sponsors', "Our sponsors")));
      $foot->add(new XDiv(array('id'=>'sponsors'),
                          array(new XH3("Our sponsors"),
                                $slist = new XUl(array('id'=>'sponsors-list')))));

      foreach ($sponsors as $sponsor) {
        $cnt = new XSpan($sponsor->name);
        if ($sponsor->logo !== null)
          $cnt = $sponsor->logo->asImg('/inc/img/' . $sponsor->logo->id, $sponsor->name);
        if ($sponsor->url !== null)
          $cnt = new XA($sponsor->url, $cnt);
        $slist->add(new XLi($cnt));
      }
    }

    // Copyright
    $foot->add(new XAddress(array(), array(new XA('http://www.openweb-solutions.net', DB::g(STN::APP_COPYRIGHT)))));

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

  public function setPublicData(PublicData $data) {
    $this->public_data = $data;
  }

  /**
   * Delays the creation of the page and writes to given resource
   *
   */
  public function write($resource) {
    $this->fill();
    parent::write($resource);
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
    return array('/inc/css/style.css');
  }

  /**
   * Get the link to the organization, if any
   *
   * @return XA link to the home page, or null
   */
  public function getOrgLink() {
    if (($n = DB::g(STN::ORG_NAME)) !== null && ($u = DB::g(STN::ORG_URL)) !== null)
      return new XA($u, sprintf("%s Home", $n));
    return null;
  }

  /**
   * Get the link to the organization's teams page, if any
   *
   * @return XA link to org teams page, if any
   */
  public function getOrgTeamsLink() {
    if (($n = DB::g(STN::ORG_NAME)) !== null && ($u = DB::g(STN::ORG_TEAMS_URL)) !== null)
      return new XA($u, sprintf("%s Teams", $n));
    return null;
  }

  private function createPayPalForm() {
    $f = new XForm('https://www.paypal.com/cgi-bin/webscr', XForm::POST);
    $f->set('class', 'paypal-donate-form');
    $f->set('target', '_blank');
    $f->add(new XHiddenInput('cmd', '_s-xclick'));
    $f->add(new XHiddenInput('hosted_button_id', DB::g(STN::PAYPAL_HOSTED_BUTTON_ID)));
    $f->add(new XElem('input', array('type'=>'image', 'name'=>'submit', 'src'=>'https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif', 'alt'=>"Donate with PayPal")));
    $f->add(new XImg('https://www.paypalobjects.com/en_US/i/scr/pixel.gif', "", array('width'=>1, 'height'=>1)));

    return $f;
  }

}
?>
