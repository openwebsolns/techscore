<?php
/*
 * This file is part of TechScore
 *
 * @author Dayan Paez
 * @version 2.0
 * @package xml
 */

require_once('xml5/TS.php');

/**
 * The basic HTML page for TechScore files. This page extends the
 * XPage class and sets up the necessary structure common to all the
 * pages: headers, footers, contents.
 *
 * @author Dayan Paez
 * @version 2.0
 * @version 2009-10-19
 */
class TScorePage extends XPage {

  // Private variables
  private $user;
  private $reg;

  private $header;
  private $menu;
  private $content;
  private $contentAttrs;

  private $mobile;
  private $filled;

  private $title;

  /**
   * Creates a new page with the given title
   *
   * @param String $title the title of the page
   * @param Account $user the possible logged-in user
   * @param Regatta $reg the possible regatta in use. This affects the
   * menu that is displayed.
   */
  public function __construct($title, Account $user = null, Regatta $reg = null) {
    parent::__construct($title . " | " . DB::g(STN::APP_NAME));
    $this->title = $title;
    $this->user = $user;
    $this->reg = $reg;

    $this->mobile = $this->isMobile();

    $this->content = array();
    $this->contentAttrs = array();
    $this->filled = false;
    $this->menu = new XDiv(array('id'=>'menubar'));
    $this->header = new XDiv(array('id'=>'headbar'));

    // Favicon the W3C way
    $this->head->add(new XLink(array('rel'=>'icon', 'type'=>'image/x-icon', 'href'=>WS::link('/favicon.ico'))));
    $this->head->set('profile', 'http://www.w3.org/2005/10/profile');

    $this->head->add(new XMeta('viewport', "width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no"));
    $this->head->add(new XMetaHTTP('X-UA-Compatible', 'IE=Edge'));

    // Deal with IE7 and below
    $this->head->add(new XRawText('<!--[if lt IE 9]> '));
    $this->head->add(new XLinkCSS('text/css', '/inc/css/ie.css', 'screen,print', 'stylesheet'));
    $this->head->add(new XRawText(' <![endif]-->'));
    $this->body->add(new XRawText('<!--[if lt IE 9]> '));
    $this->body->add(new XDiv(array('id'=>'ie-warn'),
                              array(new XDiv(array('id'=>'ie-close'),
                                             array(new XA('#', new XImg('/inc/img/ie-close.jpg', "Close"), array('onclick'=>'javascript:this.parentNode.parentNode.style.display="none"; return false;')))),
                                    new XDiv(array('id'=>'ie-cont'),
                                             array(new XDiv(array('id'=>'ie-img'),
                                                            array(new XImg('/inc/img/ie6-warn.jpg', "Warning!"))),
                                                   new XDiv(array('id'=>'ie-wrap'),
                                                            array(new XDiv(array('id'=>'ie-mes1'),
                                                                           array("You are using an outdated browser")),
                                                                  new XDiv(array('id'=>'ie-mes2'),
                                                                           array(sprintf("For a better experience with %s, please upgrade to a modern web browser.", DB::g(STN::APP_NAME)))))),
                                                   new XDiv(array('class'=>'ie-b'),
                                                            array(new XA('http://www.firefox.com', new XImg('/inc/img/ff.jpg', "Firefox")))),
                                                   new XDiv(array('class'=>'ie-b'),
                                                            array(new XA('http://www.google.com/chrome', new XImg('/inc/img/chrome.jpg', "Chrome")))),
                                                   new XDiv(array('class'=>'ie-b'),
                                                            array(new XA('http://www.opera.com', new XImg('/inc/img/opera.jpg', "Opera")))))))));
    $this->body->add(new XRawText(' <![endif]-->'));

    $this->fillHead();
  }

  private function fill() {
    if ($this->filled) return;
    $this->filled = true;

    // Header
    $this->body->add(new XDiv(array('id'=>'headdiv'), array($this->header)));
    $this->fillPageHeader($this->user, $this->reg);

    // Menu
    $this->body->add(new XDiv(array('id'=>'menudiv', 'class'=>'m-menu-hidden'), array($this->menu)));
    $this->body->add(new XHr(array('class'=>'hidden')));

    // Content
    $this->body->add(new XDiv(array('id'=>'bodywrap'), array($c = new XDiv($this->contentAttrs))));
    $c->set('id', 'bodydiv');

    // Announcement
    if (class_exists('Session', false))
      $c->add(Session::getAnnouncements('/inc/img'));
    foreach ($this->content as $cont)
      $c->add($cont);

    // Footer
    $this->body->add($div = new XDiv(array('id'=>'footdiv')));
    $div->add(new XAddress(array(), array(sprintf("%s v%s %s", DB::g(STN::APP_NAME), DB::g(STN::APP_VERSION), DB::g(STN::APP_COPYRIGHT)))));
    if ($this->user !== null) {
      $manual = "";
      if (DB::g(STN::HELP_HOME) !== null)
        $manual = new XP(array(),
                         array("Maybe the ", new XA(DB::g(STN::HELP_HOME), "user manual", array('accesskey'=>'h')), " can help!"));
      $div->add(
        new XDiv(array('id'=>'help-me'), array(
                   new XDiv(array('id'=>'help-form-screen')),
                   new XA('#help-me', array(new XSpan("Questions", array('id'=>'help-desk')), "?"), array('id'=>'help-form-trigger')),
                   new XForm('/help', XForm::POST, array('id'=>'help-form'),
                             array(
                               new XHiddenInput('csrf_token', Session::getCsrfToken()),
                               new XA('#_', "Close", array('id'=>'help-form-close')),
                               new XDiv(array('id'=>'help-wrap'),
                                        array(
                                          new XH3("Have a question?"),
                                          $manual,
                                          new XP(array('class'=>'help-item'), new XTextInput('subject', "", array('min'=>3, 'max'=>150, 'placeholder'=>"Subject", 'required'=>'required'))),
                                          new XP(array('class'=>'help-item'), new XTextArea('message', "", array('min'=>10, 'max'=>3000, 'placeholder'=>"What seems to be the problem?", 'required'=>'required'))),
                                          new XSubmitP('ask', "Ask the Admins!", array('id'=>'help-submit')))))))));
    }
  }

  /**
   * Determines whether the page is being accessed through a mobile
   * device
   *
   */
  public function isMobile() {
    return (isset($_SERVER['HTTP_USER_AGENT']) &&
            (strpos($_SERVER['HTTP_USER_AGENT'], "Mobi") !== false));
  }

  /**
   * Fills up the head element of this page
   *
   */
  private function fillHead() {
    $this->head->add(new XMeta('robots', 'noindex, nofollow'));
    $this->head->add(new XMetaHTTP('Content-Type', 'text/html; charset=UTF-8'));

    // CSS Stylesheets
    $this->head->add($css = new LinkCSS('/inc/css/default.css?v=6', 'screen'));
    $css->set('id', 'main-style');
    $this->head->add(new LinkCSS('/inc/css/print.css','print'));

    // Javascript
    // Session JS: only if applicable
    if (class_exists("TSSessionHandler") && ($exp = TSSessionHandler::getExpiration()) !== null) {
      $this->head->add(new XScript('text/javascript', null, sprintf('window.SESSION_EXPIRATION=%d;', $exp)));
      $this->head->add(new XScript('text/javascript', '/inc/js/check-session-load.js'));
    }
    if (!$this->mobile) {
      $this->head->add(new XScript('text/javascript', '/inc/js/cselect.js', null, array('id'=>'cselect-js', 'async'=>'async', 'defer'=>'defer')));
      $this->head->add(new XScript('text/javascript', '/inc/js/mselect.js', null, array('id'=>'mselect-js', 'async'=>'async', 'defer'=>'defer')));
    }
    $this->head->add(new XScript('text/javascript', '/inc/js/form.js?v=2'));
  }

  /**
   * Creates the header of this page
   *
   */
  private function fillPageHeader(Account $user = null, Regatta $reg = null) {
    $img = 'techscore.png';
    $this->header->add(new XH1(new XA('/', new XImg('/inc/img/' . $img, DB::g(STN::APP_NAME), array('id'=>'headimg'))), array('id'=>'logo', 'class'=>'m-menu-hidden')));

    $m_user_menu = new XUl();

    if ($reg !== null) {
      $this->header->add($h4 = new XH4(new XA(sprintf('/score/%s/', $reg->id), $reg->name), array('id'=>'regatta')));
      if ($reg->private)
        $h4->add(new XImg(WS::link('/inc/img/priv.png'), "Private", array('title'=>'Regatta is not public')));

      $this->header->add(new XDiv(array('id'=>'close'), array(new XA('/', "Close", array('accesskey'=>'w')))));
      $m_user_menu->add(new XLi(new XA('/', "Close"), array('id'=>'m-close')));

      if (!$reg->private) {
        $link = new XA(sprintf('http://%s%s', Conf::$PUB_HOME, $reg->getURL()), "Public Site", array('accesskey'=>'p', 'onclick'=>'this.target="public"'));
        $this->header->add(new XDiv(array('id'=>'public-link'), array($link)));
        $m_user_menu->add(new XLi($link, array('id'=>'m-public-link')));
      }
    }
    else {
      $this->header->add(new XH4($this->title, array('id'=>'m-title')));
    }

    if ($user !== null) {
      $unread = count(DB::getUnreadMessages($user));
      $inbox_title = "My Inbox";
      if ($unread > 0) {
        $inbox_title .= sprintf(" (%s)", ($unread >= 100) ? "99+" : $unread);
      }

      $m_user_menu->add(new XLi(new XA('/account', "My Account")));
      $m_user_menu->add(new XLi(new XA('/inbox', $inbox_title)));
      $m_user_menu->add(new XLi(new XA('/logout', "Logout", array('id'=>'m-logout'))));

      $this->header->add($md = new XDiv(array('id'=>'user-menudiv'),
                                  array($ul = new XUl(array('id'=>'user-menu'),
                                                      array(new XLi(new XSpan($user)),
                                                            new XLi(new XA('/', "Home")),
                                                            new XLi(new XA('/account', "My Account")),
                                                            new XLi(new XA('/inbox', $inbox_title)),
                                                            new XLi(new XA('/logout', "Logout", array('accesskey'=>'l'))))))));
      if ($unread > 0) {
        $md->add(new XSpan(sprintf("%s Message%s",
                                   ($unread > 100) ? "100+" : $unread,
                                   ($unread > 1) ? "s" : ""
                           ),
                           array('id'=>'unread-messages')));
      }

      if (Conf::$USURPER !== null) {
        $m_user_menu->add(new XLi(new XA('/account?reset', "Reset Session")));
        $ul->add(new XLi(new XA('/account?reset', "Reset Session")));
      }
    }

    if (count($m_user_menu->children()) > 0) {
      $this->addMenu(new XDiv(array('class'=>'menu mobile'), array(new XH4("Site navigation"), $m_user_menu)));
    }
  }

  /**
   * Adds the Xmlable to the content of this page
   *
   * @param Xmlable $elem an element to append to the body of this
   * page
   */
  public function addContent($elem) {
    $this->content[] = $elem;
  }

  /**
   * Add the given attribute to the content wrapper.
   *
   * NOTE: id will be overridden.
   *
   * @param String $name the name of the attribute
   * @param String $value the value.
   */
  public function setContentAttribute($name, $value) {
    $this->contentAttrs[$name] = $value;
  }

  /**
   * Adds the given element to the menu division of this page
   *
   * @param Xmlable $elem to add to the menu of this page
   */
  public function addMenu($elem) {
    $this->menu->add($elem);
  }

  /**
   * Adds the given element to the page header
   *
   * @param Xmlable $elem to add to the page header
   */
  public function addHeader(Xmlable $elem) {
    $this->header->add($elem);
  }

  /**
   * Adds the given element to the navigation part
   *
   * @param Xmlable $elem to add to navigation
   */
  public function addNavigation(Xmlable $elem) {
    $this->header->add($elem);
  }

  public function toXML() {
    $this->fill();
    return parent::toXML();
  }
  public function printXML() {
    $this->fill();
    return parent::printXML();
  }
}

?>
