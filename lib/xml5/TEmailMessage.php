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
  private $css = false;

  /**
   * @var Xmlable the container for appended content
   */
  private $container;

  public function __construct($title) {
    parent::__construct($title);

    $this->head->add(new XMetaHTTP('Content-Type', 'text/html; charset=UTF-8'));
    $this->body->set('style', $this->getCSS(self::BODY));

    // Header
    $this->body->add(
      new XDiv(
        array('style' => $this->getCSS(self::HEADDIV)),
        array(
          new XDiv(
            array('style' => $this->getCSS(self::HEADBAR)),
            array(
              new XH1(
                new XA(
                  $this->link('/'),
                  new XImg($this->link('/inc/img/techscore.png'), DB::g(STN::APP_NAME), array('style' => $this->getCSS(self::HEADIMG))),
                  array('style' => $this->getCSS(self::HEADLINK))
                ),
                array('style' => $this->getCSS(self::LOGO))
              ),
            )
          )
        )
      )
    );

    // Content
    $this->container = new XDiv(array('style' => $this->getCSS(self::BODYDIV)));
    $this->body->add(
      new XDiv(
        array('style' => $this->getCSS(self::BODYWRAP)),
        array(
          $this->container
        )
      )
    );

    // Footer
    $this->body->add(
      new XDiv(
        array('style' => $this->getCSS(self::FOOTDIV)),
        array(
          new XAddress(
            array('style' => $this->getCSS(self::FOOTADDRESS)),
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
  public function getCSS($key) {
    if ($this->css === false) {
      $this->css = json_decode(file_get_contents(__DIR__ . '/TEmailMessageCSS.json'), true);
    }

    if (is_array($this->css) && isset($this->css[$key])) {
      $resp = "";
      foreach ($this->css[$key] as $selector => $value) {
        $resp .= $selector . ':' . $value . ';';
      }
      return $resp;
    }
    return null;
  }

  public function link($path) {
    return sprintf('https://%s%s', Conf::$HOME, $path);
  }
}
?>