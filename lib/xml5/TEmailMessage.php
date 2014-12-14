<?php
/*
 * This file is part of TechScore
 *
 * @package xml5
 */

require_once('xml5/TS.php');

/**
 * HTML page for e-mails that link back to Techscore
 *
 * Note: TEmailPage, which came before, is used for generic,
 * public-facing email messages.
 *
 * @author Dayan Paez
 * @created 2014-10-17
 */
class TEmailMessage extends XPage {

  /**
   * Constants for CSS rules
   */

  const HTML = 'html';
  const BODY = 'body';
  const SUBMIT = 'submit';
  const HEADDIV = 'headdiv';
  const HEADBAR = 'headbar';
  const HEADLINK = 'headlink';
  const HEADIMG = 'headimg';
  const LOGO = 'logo';
  const BODYWRAP = 'bodywrap';
  const BODYDIV = 'bodydiv';
  const FOOTDIV = 'footdiv';
  const FOOTADDRESS = 'footaddress';

  private $editor;

  /**
   * @var Array loading of file TMailMessageCSS.json with CSS instructions
   * @see getCSS
   *
   * Start with value of 'false' due to 'null' return value of
   * json_decode
   */
  private static $CSS_JSON = false;

  /**
   * @var Xmlable the container for appended content
   */
  private $container;

  public function __construct($title) {
    parent::__construct($title);

    $this->set('style', self::getCSS(self::HTML));
    $this->head->add(new XMetaHTTP('Content-Type', 'text/html; charset=UTF-8'));
    $this->body->set('style', self::getCSS(self::BODY));

    // Header
    $this->body->add(
      new XDiv(
        array('style' => self::getCSS(self::HEADDIV)),
        array(
          new XDiv(
            array('style' => self::getCSS(self::HEADBAR)),
            array(
              new XH1(
                new XA(
                  $this->link('/'),
                  new XImg($this->link('/inc/img/techscore.png'), DB::g(STN::APP_NAME), array('style' => self::getCSS(self::HEADIMG))),
                  array('style' => self::getCSS(self::HEADLINK))
                ),
                array('style' => self::getCSS(self::LOGO))
              ),
            )
          )
        )
      )
    );

    // Content
    $this->container = new XDiv(array('style' => self::getCSS(self::BODYDIV)));
    $this->body->add(
      new XDiv(
        array('style' => self::getCSS(self::BODYWRAP)),
        array(
          $this->container
        )
      )
    );

    // Footer
    $this->body->add(
      new XDiv(
        array('style' => self::getCSS(self::FOOTDIV)),
        array(
          new XAddress(
            array('style' => self::getCSS(self::FOOTADDRESS)),
            array(
              sprintf("%s v%s %s", DB::g(STN::APP_NAME), DB::g(STN::APP_VERSION), DB::g(STN::APP_COPYRIGHT))
            )
          )
        )
      )
    );
  }

  /**
   * Add arg. to the correct place in <body>
   *
   * @param Xmlable $elem
   */
  public function append($elem) {
    $this->container->add($elem);
    if (($css = self::getCSS($elem->name)) !== null)
      $elem->set('style', $css);
  }

  /**
   * Convert plain text to HTML
   *
   * @param String $plain_text the input
   * @return Array:Xmlable list of HTML elements
   */
  public function convert($plain_text) {
    if ($this->editor === null) {
      require_once('xml5/TSEditor.php');
      $this->editor = new TSEditor();
    }
    return $this->editor->parse($plain_text);
  }

  /**
   * Convenience method to add plain text
   *
   * @param String $plain_text
   */
  public function convertAndAppend($plain_text) {
    foreach ($this->convert($plain_text) as $sub)
      $this->append($sub);
  }

  /**
   * Return the CSS rule for given key, suitable for 'style' attr.
   *
   * @param String $key the index to find in TEmailMessageCSS
   * @return String the rule
   */
  public static function getCSS($key) {
    if (self::$CSS_JSON === false) {
      self::$CSS_JSON = json_decode(file_get_contents(__DIR__ . '/TEmailMessageCSS.json'), true);
    }

    if (is_array(self::$CSS_JSON) && isset(self::$CSS_JSON[$key])) {
      $resp = "";
      foreach (self::$CSS_JSON[$key] as $selector => $value) {
        $resp .= $selector . ':' . $value . ';';
      }
      return $resp;
    }
    return null;
  }

  /**
   * Returns all the rules in one string
   *
   * @return String
   */
  public static function getCSSStylesheet() {
    if (self::$CSS_JSON === false) {
      self::$CSS_JSON = json_decode(file_get_contents(__DIR__ . '/TEmailMessageCSS.json'), true);
    }

    $sheet = '';
    if (is_array(self::$CSS_JSON)) {
      foreach (self::$CSS_JSON as $target => $rules) {
        $sheet .= sprintf('%s {', $target);
        foreach ($rules as $selector => $value) {
          $sheet .= $selector . ':' . $value . ';';
        }
        $sheet .= '}';
      }
    }
    return $sheet;
  }

  public function link($path) {
    return sprintf('https://%s%s', Conf::$HOME, $path);
  }
}
?>